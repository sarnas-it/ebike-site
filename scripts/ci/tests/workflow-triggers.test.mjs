#!/usr/bin/env node
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { WORKFLOW_PATH } from '../lib.mjs';
import { EXPECTED_CI_JOBS } from '../rollout.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../../..');
const workflowPath = path.join(repoRoot, WORKFLOW_PATH);

function analyzeWorkflow(text) {
  const lines = text.split(/\r?\n/);
  const onIndex = lines.findIndex((line) => /^on:\s*(#.*)?$/.test(line));
  assert.ok(onIndex >= 0, 'workflow must declare an "on:" trigger block');

  const triggerLines = [];
  for (let i = onIndex + 1; i < lines.length; i += 1) {
    const line = lines[i];
    if (/^\S/.test(line) && line.trim() !== '') {
      break;
    }
    triggerLines.push(line);
  }
  const block = triggerLines.join('\n');
  const triggers = triggerLines
    .map((line) => line.match(/^  ([A-Za-z_]+):/))
    .filter(Boolean)
    .map((match) => match[1]);

  return {
    triggers,
    hasPathsFilter: /^\s+paths(?:-ignore)?:/m.test(block),
    hasBranchesFilter: /^\s+branches(?:-ignore)?:/m.test(block),
  };
}

function testBrokenWorkflowIsRed() {
  const broken = [
    'name: ci',
    'on:',
    '  push:',
    '    branches: [main]',
    'jobs:',
    '  lint:',
    '    runs-on: ubuntu-latest',
    '    steps:',
    '      - run: echo ok',
  ].join('\n');
  const analysis = analyzeWorkflow(broken);
  assert.ok(
    !analysis.triggers.includes('pull_request'),
    'the fixture without pull_request must be detected as broken',
  );
  console.log('PASS: a workflow without pull_request is detected as broken');
}

function testRepositoryWorkflowIsGreen() {
  const text = readFileSync(workflowPath, 'utf8');
  const analysis = analyzeWorkflow(text);

  assert.ok(
    analysis.triggers.includes('pull_request'),
    'ci must run on every pull_request so PR commits get checks',
  );
  assert.ok(
    analysis.triggers.includes('workflow_dispatch'),
    'ci must be dispatchable to recover a head that has no run',
  );
  assert.ok(
    analysis.triggers.includes('push'),
    'ci must still run on push to main',
  );
  assert.strictEqual(
    analysis.hasPathsFilter,
    false,
    'ci must not filter pull_request by paths, otherwise commits get no checks',
  );

  const jobNames = [...text.matchAll(/^  ([A-Za-z0-9_-]+):\s*$/gm)].map(
    (match) => match[1],
  );
  assert.ok(jobNames.includes('php-lint'), 'php-lint job must remain enabled');
  console.log('PASS: repository workflow triggers on pull_request without path filters');
}

function main() {
  testBrokenWorkflowIsRed();
  testRepositoryWorkflowIsGreen();
  testBrokenRecoveryWorkflowIsRed();
  testRecoveryWorkflowIsGreen();
  testSharedCheckRunnerCoversAllGroups();
  testRolloutVerificationWorkflowIsWired();
  testCiWorkflowKeepsTheVerifiedJobSet();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}

function analyzePermissions(text) {
  const lines = text.split(/\r?\n/);
  const index = lines.findIndex((line) => /^permissions:\s*$/.test(line));
  const permissions = {};
  if (index < 0) {
    return permissions;
  }
  for (let i = index + 1; i < lines.length; i += 1) {
    const line = lines[i];
    if (/^\S/.test(line) && line.trim() !== '') {
      break;
    }
    const match = line.match(/^  ([A-Za-z_-]+):\s*(\S+)/);
    if (match) {
      permissions[match[1]] = match[2];
    }
  }
  return permissions;
}

function analyzeEnv(text) {
  const lines = text.split(/\r?\n/);
  const index = lines.findIndex((line) => /^env:\s*$/.test(line));
  const env = {};
  if (index < 0) {
    return env;
  }
  for (let i = index + 1; i < lines.length; i += 1) {
    const line = lines[i];
    if (/^\S/.test(line) && line.trim() !== '') {
      break;
    }
    const match = line.match(/^  ([A-Za-z_]+):\s*(.+)$/);
    if (match) {
      env[match[1]] = match[2].trim().replace(/^['"]|['"]$/g, '');
    }
  }
  return env;
}

function recoveryWorkflowChecks(text) {
  const analysis = analyzeWorkflow(text);
  const permissions = analyzePermissions(text);
  const env = analyzeEnv(text);
  return {
    triggers: analysis.triggers,
    hasPathsFilter: analysis.hasPathsFilter,
    permissions,
    env,
    checksOutExactSha: /ref:\s*(?:\$\{\{\s*env\.TARGET_SHA\s*\}\}|578e87b5a8587d6078aade92d26bd449448dd1d3)/.test(text),
    runsSharedChecks: /scripts\/ci\/run-checks\.sh/.test(text),
    publishesAndMerges: /scripts\/ci\/recover-pr\.mjs/.test(text),
    publishesRealRunEvidence: /scripts\/ci\/publish-evidence\.mjs/.test(text),
    allowsRunningEvidenceStep: /--allow-running/.test(text),
    runNameNamesTargetSha:
      /^run-name:.*578e87b5a8587d6078aade92d26bd449448dd1d3/m.test(text),
  };
}

function testBrokenRecoveryWorkflowIsRed() {
  const broken = [
    'name: Recover',
    'on:',
    '  workflow_dispatch:',
    'permissions:',
    '  contents: read',
    'env:',
    '  TARGET_PR: "62"',
    '  TARGET_SHA: deadbeef',
    'jobs:',
    '  recover:',
    '    runs-on: ubuntu-latest',
    '    steps:',
    '      - run: echo ok',
  ].join('\n');
  const checks = recoveryWorkflowChecks(broken);
  assert.ok(!checks.triggers.includes('push'), 'a recovery without push must be red');
  assert.strictEqual(checks.permissions['checks'], undefined);
  assert.strictEqual(checks.permissions['statuses'], undefined);
  assert.strictEqual(checks.permissions['pull-requests'], undefined);
  assert.strictEqual(checks.checksOutExactSha, false);
  assert.strictEqual(checks.runsSharedChecks, false);
  assert.strictEqual(checks.publishesAndMerges, false);
  assert.strictEqual(checks.publishesRealRunEvidence, false);
  assert.strictEqual(checks.allowsRunningEvidenceStep, false);
  assert.strictEqual(checks.runNameNamesTargetSha, false);
  console.log('PASS: a recovery workflow without write permissions and exact checkout is red');
}

function testRecoveryWorkflowIsGreen() {
  const text = readFileSync(path.join(repoRoot, '.github/workflows/recover-pr62.yml'), 'utf8');
  const checks = recoveryWorkflowChecks(text);

  assert.ok(checks.triggers.includes('push'), 'recovery must run on push to any branch');
  assert.ok(
    checks.triggers.includes('workflow_dispatch'),
    'recovery must be manually dispatchable',
  );
  assert.strictEqual(checks.hasPathsFilter, false, 'recovery must not filter by paths');
  assert.strictEqual(checks.permissions['checks'], 'write');
  assert.strictEqual(checks.permissions['statuses'], 'write');
  assert.strictEqual(checks.permissions['pull-requests'], 'write');
  assert.strictEqual(checks.permissions['contents'], 'write');
  assert.strictEqual(
    checks.env.TARGET_PR,
    '62',
    'recovery must target the pull request from the incident',
  );
  assert.strictEqual(
    checks.env.TARGET_SHA,
    '578e87b5a8587d6078aade92d26bd449448dd1d3',
    'recovery must check out the exact commit from the incident',
  );
  assert.strictEqual(
    checks.checksOutExactSha,
    true,
    'recovery must check out the exact target SHA, not a branch head',
  );
  assert.strictEqual(
    checks.runsSharedChecks,
    true,
    'recovery must run the same checks as the normal CI',
  );
  assert.strictEqual(
    checks.publishesAndMerges,
    true,
    'recovery must publish checks and complete the deferred merge',
  );
  assert.strictEqual(
    checks.publishesRealRunEvidence,
    true,
    'recovery must verify a real workflow run before confirming the result',
  );
  assert.strictEqual(
    checks.allowsRunningEvidenceStep,
    true,
    'the evidence step must be able to record its own in-progress run',
  );
  assert.strictEqual(
    checks.runNameNamesTargetSha,
    true,
    'the run name must identify the target SHA so the run is auditable',
  );
  assert.ok(
    text.includes('docs/testeb-63-comment.md'),
    'recovery must post a confirmed comment on the pull request',
  );
  console.log('PASS: recovery workflow targets PR #62 at the exact SHA and publishes verified evidence');
}

function testSharedCheckRunnerCoversAllGroups() {
  const runner = readFileSync(path.join(repoRoot, 'scripts/ci/run-checks.sh'), 'utf8');
  for (const group of ['php-lint', 'php-tests', 'plugin-tests', 'ci-repair-tests']) {
    assert.ok(runner.includes(group), `run-checks.sh must keep the ${group} check`);
  }
  console.log('PASS: the shared runner keeps the full check set');
}

function testRolloutVerificationWorkflowIsWired() {
  const text = readFileSync(
    path.join(repoRoot, '.github/workflows/verify-ci-rollout.yml'),
    'utf8',
  );
  const analysis = analyzeWorkflow(text);
  assert.ok(analysis.triggers.includes('push'), 'rollout verification must run on push');
  assert.ok(
    analysis.triggers.includes('workflow_dispatch'),
    'rollout verification must be manually dispatchable',
  );
  assert.strictEqual(
    analysis.hasPathsFilter,
    false,
    'the rollout gate must not skip pushes that do not touch the workflow itself',
  );
  assert.ok(
    /branches:\s*\[\s*main\s*\]/.test(text),
    'rollout verification must run on the base branch',
  );
  assert.ok(
    text.includes('scripts/ci/verify-rollout.mjs'),
    'rollout verification must call the red-green gate',
  );
  console.log('PASS: rollout verification runs on push to main and manually');
}

function testCiWorkflowKeepsTheVerifiedJobSet() {
  const text = readFileSync(workflowPath, 'utf8');
  for (const job of EXPECTED_CI_JOBS) {
    assert.ok(
      new RegExp(`^  ${job}:\\s*$`, 'm').test(text),
      `ci.yml must keep the ${job} job that verify-rollout checks`,
    );
  }
  console.log('PASS: ci keeps every job that the rollout gate verifies');
}
