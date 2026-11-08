#!/usr/bin/env node
import assert from 'node:assert';
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { WORKFLOW_PATH } from '../lib.mjs';
import {
  assessMissingCiRun,
  assessRecoveryNeed,
  decideRecovery,
} from '../should-recover.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../../..');
const recoveryWorkflow = readFileSync(
  path.join(repoRoot, '.github/workflows/recover-pr.yml'),
  'utf8',
);

function testRecoveryWorkflowIsTriggeredForNoChecks() {
  // Regression for the TESTEB-63 no_checks incident. A commit without any ci
  // run never produces a workflow_run event, so a recovery workflow that only
  // listens to "ci completed" can never repair it. Recovery must also run on
  // pull_request events and verify that no ci run exists before taking over.
  assert.match(
    recoveryWorkflow,
    /^on:\s*$/m,
    'the recovery workflow must declare its triggers',
  );
  assert.match(
    recoveryWorkflow,
    /^  workflow_run:\s*$/m,
    'recovery must keep handling stale checks after a completed ci run',
  );
  assert.match(
    recoveryWorkflow,
    /^  pull_request:\s*$/m,
    'recovery must run on pull requests so a commit without a ci run gets a result',
  );
  assert.match(
    recoveryWorkflow,
    /types:\s*\[[^\]]*opened[^\]]*synchronize[^\]]*\]/,
    'the pull_request trigger must cover opened and synchronize events',
  );
  assert.match(
    recoveryWorkflow,
    /--require-missing-ci-run/,
    'recovery must only take over when the ci workflow has no run for the commit',
  );
  assert.match(
    recoveryWorkflow,
    /--workflow \.github\/workflows\/ci\.yml/,
    'recovery must check the ci workflow specifically',
  );
  assert.match(
    recoveryWorkflow,
    /PR_NUMBER:\s*\$\{\{\s*github\.event\.pull_request\.number\s*\}\}/,
    'the pull_request event must resolve the pull request number',
  );
  assert.match(
    recoveryWorkflow,
    /PR_HEAD_SHA:\s*\$\{\{\s*github\.event\.pull_request\.head\.sha\s*\}\}/,
    'the pull_request event must resolve the real head commit',
  );
  assert.match(
    recoveryWorkflow,
    /args\+=\(--pr "\$PR_NUMBER"\)/,
    'recovery must target the pull request from the event',
  );
  assert.match(
    recoveryWorkflow,
    /args\+=\(--sha "\$PR_HEAD_SHA"\)/,
    'recovery must fall back to the event head commit',
  );
  console.log(
    'PASS: recovery runs on pull_request and only when the commit has no ci run',
  );
}

function analyzeDecisionInvocation(text) {
  const start = text.indexOf('- name: Decide whether recovery is needed');
  assert.ok(start >= 0, 'recovery must have a decision step');
  const rest = text.slice(start);
  const nextStep = rest.search(/\n      - name:/);
  const block = nextStep >= 0 ? rest.slice(0, nextStep) : rest;
  const cwd = block.match(/^\s+working-directory:\s*(\S+)\s*$/m);
  const script = block.match(/node\s+(\S*should-recover\.mjs)/);
  return {
    workingDirectory: cwd ? cwd[1] : null,
    script: script ? script[1] : null,
  };
}

// The decision script must be executed from inside the checked-out tooling
// repository using the same relative path the tests import
// (scripts/ci/should-recover.mjs). Invoking a prefixed copy such as
// tooling/scripts/ci/should-recover.mjs from the workspace root makes it
// ambiguous whether the changed source is actually the code the job runs, which
// is exactly what a previous review rejected.
function invocationRunsChangedSource(invocation, checkoutPath = 'tooling') {
  if (invocation.workingDirectory !== checkoutPath) {
    return false;
  }
  if (!invocation.script || invocation.script.startsWith(`${checkoutPath}/`)) {
    return false;
  }
  return invocation.script === 'scripts/ci/should-recover.mjs';
}

function testRecoveryRunsTheChangedDecisionScript() {
  const invocation = analyzeDecisionInvocation(recoveryWorkflow);
  assert.strictEqual(invocation.workingDirectory, 'tooling');
  assert.strictEqual(invocation.script, 'scripts/ci/should-recover.mjs');
  assert.strictEqual(
    invocationRunsChangedSource(invocation),
    true,
    'the recovery job must execute scripts/ci/should-recover.mjs, not a prefixed copy',
  );

  const scriptPath = path.join(repoRoot, invocation.script);
  assert.ok(
    existsSync(scriptPath),
    `${invocation.script} must exist at the path the recovery job executes`,
  );

  // Prove that the file the workflow runs is the one that understands the new
  // flag, instead of relying on a local unit test of a different copy.
  const help = spawnSync(process.execPath, [scriptPath, '--help'], {
    encoding: 'utf8',
  });
  assert.strictEqual(help.status, 0, help.stderr);
  assert.match(
    help.stdout,
    /--require-missing-ci-run/,
    'the executed decision script must parse --require-missing-ci-run',
  );

  // Red fixture: the rejected style invoked a prefixed copy without a working
  // directory, so it does not satisfy the same-source requirement.
  const legacy = [
    '      - name: Decide whether recovery is needed',
    '        run: |',
    '          node tooling/scripts/ci/should-recover.mjs --pr 1',
    '      - name: Next',
  ].join('\n');
  assert.strictEqual(
    invocationRunsChangedSource(analyzeDecisionInvocation(legacy)),
    false,
    'a prefixed invocation must be detected as not proving the changed source is used',
  );
  console.log(
    'PASS: recovery executes the tested scripts/ci/should-recover.mjs with --require-missing-ci-run',
  );
}

function testMissingCiRunIsRecovered() {
  const decision = assessMissingCiRun([], WORKFLOW_PATH);
  assert.strictEqual(decision.needed, true);
  assert.match(decision.reason, /no_checks/);
  console.log('PASS: a commit with no ci run is a no_checks incident');
}

function testExistingCiRunIsLeftToTheRegularWorkflow() {
  for (const status of ['queued', 'in_progress', 'completed']) {
    const decision = assessMissingCiRun(
      [
        {
          id: 1,
          name: 'ci',
          path: WORKFLOW_PATH,
          status,
          conclusion: status === 'completed' ? 'failure' : null,
        },
      ],
      WORKFLOW_PATH,
    );
    assert.strictEqual(
      decision.needed,
      false,
      `a ${status} ci run already owns the result, recovery must not race it`,
    );
  }

  const otherWorkflow = assessMissingCiRun(
    [{ id: 2, name: 'Recover PR #73 checks', path: '.github/workflows/recover-pr73.yml' }],
    WORKFLOW_PATH,
  );
  assert.strictEqual(
    otherWorkflow.needed,
    true,
    'a run of another workflow is not a ci run for the commit',
  );
  console.log(
    'PASS: an existing ci run (even queued or failed) is left to the regular workflow',
  );
}

function testNoChecksDecisionRequiresRecovery() {
  const checks = { total: 0, pending: [], failed: [], runs: [] };
  const plain = assessRecoveryNeed(checks);
  assert.strictEqual(plain.needed, true);
  assert.match(plain.reason, /no check runs/);

  const { decision, ciRunDecision } = decideRecovery({
    checks,
    runs: [],
    requireMissingCiRun: true,
    workflowPath: WORKFLOW_PATH,
  });
  assert.strictEqual(ciRunDecision.needed, true);
  assert.strictEqual(decision.needed, true);
  console.log('PASS: zero checks with no ci run requests recovery');
}

function testExistingCiRunOverridesTheNoChecksDecision() {
  const checks = { total: 0, pending: [], failed: [], runs: [] };
  const { decision, ciRunDecision } = decideRecovery({
    checks,
    runs: [
      {
        id: 3,
        name: 'ci',
        path: WORKFLOW_PATH,
        status: 'queued',
        conclusion: null,
      },
    ],
    requireMissingCiRun: true,
    workflowPath: WORKFLOW_PATH,
  });
  assert.strictEqual(ciRunDecision.needed, false);
  assert.strictEqual(
    decision.needed,
    false,
    'a queued ci run means the commit is not a no_checks incident',
  );
  assert.match(decision.reason, /regular workflow owns the result/);
  console.log('PASS: a queued ci run prevents a duplicate recovery run');
}

function main() {
  testRecoveryWorkflowIsTriggeredForNoChecks();
  testRecoveryRunsTheChangedDecisionScript();
  testMissingCiRunIsRecovered();
  testExistingCiRunIsLeftToTheRegularWorkflow();
  testNoChecksDecisionRequiresRecovery();
  testExistingCiRunOverridesTheNoChecksDecision();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
