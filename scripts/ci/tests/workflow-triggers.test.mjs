#!/usr/bin/env node
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { WORKFLOW_PATH } from '../lib.mjs';

const EXPECTED_CI_JOBS = [
  'php-lint',
  'php-tests',
  'php-plugin-tests',
  'plugin-tests',
  'ci-repair-tests',
];

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
  testFinalizerWorkflowIsGreen();
  testSharedCheckRunnerCoversAllGroups();
  testCiWorkflowRunsPluginPhpContracts();
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
    resolvesCurrentHead: /scripts\/ci\/resolve-pr-target\.mjs/.test(text),
    checksOutResolvedHead:
      /ref:\s*\$\{\{\s*steps\.target\.outputs\.sha\s*\}\}/.test(text),
    hardcodesTargetSha:
      /TARGET_SHA:\s*['\"]?525b0d48/.test(text) ||
      /--sha\s+['\"]?525b0d48/.test(text),
    nameQuoted: /^name:\s*["'].*#73.*["']\s*$/m.test(text),
    runNameQuoted: /^run-name:\s*["'].*#73.*["']\s*$/m.test(text),
    runsSharedChecks: /scripts\/ci\/run-checks\.sh/.test(text),
    publishesAndMerges: /scripts\/ci\/recover-pr\.mjs/.test(text),
    dispatchesEvidenceFinalizer:
      /scripts\/ci\/request-evidence-finalize\.mjs/.test(text),
    publishesInProgressEvidence: /--allow-running/.test(text),
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
    '  TARGET_PR: "73"',
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
  assert.strictEqual(checks.resolvesCurrentHead, false);
  assert.strictEqual(checks.checksOutResolvedHead, false);
  assert.strictEqual(checks.runsSharedChecks, false);
  assert.strictEqual(checks.publishesAndMerges, false);
  assert.strictEqual(checks.dispatchesEvidenceFinalizer, false);
  assert.strictEqual(checks.nameQuoted, false);
  assert.strictEqual(checks.runNameQuoted, false);
  console.log('PASS: a recovery workflow without write permissions and dynamic head is red');
}

function testRecoveryWorkflowIsGreen() {
  const text = readFileSync(path.join(repoRoot, '.github/workflows/recover-pr73.yml'), 'utf8');
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
    '73',
    'recovery must target the pull request from the incident',
  );
  assert.strictEqual(
    checks.env.INCIDENT_SHA,
    '525b0d4803e03858cdb0bfce9cb90837bdf0473e',
    'the incident SHA must stay recorded for auditing',
  );
  assert.strictEqual(
    checks.resolvesCurrentHead,
    true,
    'recovery must resolve the current pull request head at run time',
  );
  assert.strictEqual(
    checks.checksOutResolvedHead,
    true,
    'recovery must check out the resolved head, not a hard-coded SHA',
  );
  assert.strictEqual(
    checks.hardcodesTargetSha,
    false,
    'recovery must not pin the target SHA from the incident, otherwise a moved head is skipped',
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
    checks.dispatchesEvidenceFinalizer,
    false,
    'recovery must not dispatch the finalizer: workflow_dispatch fails until the finalizer is on the default branch',
  );
  assert.strictEqual(
    checks.publishesInProgressEvidence,
    false,
    'recovery must not publish an in-progress run as the final evidence',
  );
  assert.strictEqual(
    checks.nameQuoted,
    true,
    'the workflow name must be quoted so "#73" is not parsed as a YAML comment',
  );
  assert.strictEqual(
    checks.runNameQuoted,
    true,
    'the run name must be quoted and identify PR #73 so the run is auditable',
  );
  console.log('PASS: recovery follows the current PR #73 head without a fragile finalizer dispatch');
}

function testFinalizerWorkflowIsGreen() {
  const text = readFileSync(
    path.join(repoRoot, '.github/workflows/finalize-pr73-evidence.yml'),
    'utf8',
  );
  const analysis = analyzeWorkflow(text);
  const permissions = analyzePermissions(text);

  assert.ok(
    analysis.triggers.includes('push'),
    'the finalizer must run from the same push as recovery instead of relying on workflow_dispatch',
  );
  assert.ok(
    analysis.triggers.includes('workflow_dispatch'),
    'the finalizer must still be manually dispatchable',
  );
  assert.strictEqual(analysis.hasPathsFilter, false);
  assert.strictEqual(permissions['issues'], 'write');
  assert.strictEqual(
    permissions['pull-requests'],
    'write',
    'the finalizer must be allowed to create/update the pull request comment',
  );
  assert.strictEqual(permissions['actions'], 'read');
  assert.ok(
    /scripts\/ci\/wait-and-publish-evidence\.mjs/.test(text),
    'the finalizer must wait for the run to complete before publishing',
  );
  assert.ok(
    text.includes('--trigger-sha'),
    'the finalizer must discover the recovery run from the triggering commit',
  );
  assert.ok(
    text.includes('--comment-file docs/testeb-67-comment.md'),
    'the finalizer must update the same pr comment as recovery',
  );
  assert.ok(
    !text.includes('--allow-running'),
    'the finalizer must only accept a completed successful run',
  );
  console.log('PASS: the push-triggered finalizer waits for completion and publishes final evidence');
}

function testSharedCheckRunnerCoversAllGroups() {
  const runner = readFileSync(path.join(repoRoot, 'scripts/ci/run-checks.sh'), 'utf8');
  for (const group of [
    'php-lint',
    'php-tests',
    'plugin-tests',
    'php-plugin-tests',
    'ci-repair-tests',
  ]) {
    assert.ok(runner.includes(group), `run-checks.sh must keep the ${group} check`);
  }
  console.log('PASS: the shared runner keeps the full check set');
}

function testCiWorkflowRunsPluginPhpContracts() {
  const text = readFileSync(workflowPath, 'utf8');
  assert.ok(
    /for test in wp\/plugins\/velotok-core\/tests\/\*-test\.php/.test(text),
    'ci must execute every plugin PHP contract test, not only lint it',
  );
  assert.ok(
    /php "\$\{test\}"/.test(text),
    'ci must run plugin PHP contract tests with php',
  );
  assert.ok(
    text.includes(
      'php wp/plugins/velotok-core/tests/accounts-contract-test.php',
    ),
    'ci must run the exact accounts acceptance command',
  );
  console.log('PASS: ci executes the plugin PHP acceptance tests');
}

function testCiWorkflowKeepsTheVerifiedJobSet() {
  const text = readFileSync(workflowPath, 'utf8');
  for (const job of EXPECTED_CI_JOBS) {
    assert.ok(
      new RegExp(`^  ${job}:\\s*$`, 'm').test(text),
      `ci.yml must keep the ${job} job that the shared runner verifies`,
    );
  }
  console.log('PASS: ci keeps every job that the shared runner verifies');
}
