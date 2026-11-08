#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import {
  findRecoveryRunLink,
  renderEvidenceBlock,
  summarizeRecoveryEvidence,
} from '../evidence.mjs';

// Snapshot of the real GitHub state for PR sarnas-it/ebike-site#78 after the
// TESTEB-68 recovery fix had been pushed. Keeping the incident and the
// recovered state in the test makes the acceptance criteria executable:
// the incident snapshot must stay red, the real post-fix snapshot must be
// green, and the evidence block must name the actual run and merge commit.
const PR_NUMBER = 78;
const HEAD_SHA = '841470c19d9e62c1a6c4e4c402dff322c81fd747';
const FIX_SHA = '65899b710cb71e2dea0c5b3ec46dc3171fac5a4f';
const FAILED_RUN_ID = 42504665607;
const RECOVERED_RUN_ID = 42505520113;
const RECOVERED_RUN_URL = `https://github.com/sarnas-it/ebike-site/actions/runs/${RECOVERED_RUN_ID}`;
const MERGE_SHA = '330a631e8655c0ae1f2c8d76a011ae319e90a0ac';
const RECOVERY_WORKFLOW = '.github/workflows/recover-pr78.yml';

const RECOVERED_RUN = {
  id: RECOVERED_RUN_ID,
  html_url: RECOVERED_RUN_URL,
  path: RECOVERY_WORKFLOW,
  event: 'push',
  status: 'completed',
  conclusion: 'success',
  head_sha: FIX_SHA,
  head_branch: 'feat/ebike-site-testeb-68',
  created_at: '2026-11-08T17:33:04Z',
  updated_at: '2026-11-08T17:33:41Z',
};

const MERGED_PR = {
  number: PR_NUMBER,
  state: 'closed',
  merged: true,
  merge_commit_sha: MERGE_SHA,
  merged_at: '2026-11-08T17:33:38Z',
  head: { sha: HEAD_SHA },
};

const INCIDENT_PR = {
  ...MERGED_PR,
  merged: false,
  merge_commit_sha: null,
  merged_at: null,
};

const RECOVERED_CHECKS = [
  {
    id: 128807314799,
    name: 'ci-repair-tests',
    status: 'completed',
    conclusion: 'success',
    completed_at: '2026-11-08T17:33:35Z',
  },
  {
    id: 128807310353,
    name: 'php-plugin-tests',
    status: 'completed',
    conclusion: 'success',
    completed_at: '2026-11-08T17:33:34Z',
  },
  {
    id: 128807306499,
    name: 'plugin-tests',
    status: 'completed',
    conclusion: 'success',
    completed_at: '2026-11-08T17:33:34Z',
  },
  {
    id: 128807302983,
    name: 'php-tests',
    status: 'completed',
    conclusion: 'success',
    completed_at: '2026-11-08T17:33:33Z',
  },
  {
    id: 128807297687,
    name: 'php-lint',
    status: 'completed',
    conclusion: 'success',
    completed_at: '2026-11-08T17:33:32Z',
  },
  {
    // The original incident duplicate is still stored on the commit. It must
    // not make the recovered evidence look pending because a newer completed
    // php-tests run exists (id 128807302983 above).
    id: 128791152777,
    name: 'php-tests',
    status: 'in_progress',
    conclusion: 'success',
    completed_at: '2026-11-08T16:30:32Z',
  },
];

const INCIDENT_CHECKS = [
  {
    id: 128791152777,
    name: 'php-tests',
    status: 'in_progress',
    conclusion: 'success',
    completed_at: '2026-11-08T16:30:32Z',
  },
];

const RECOVERED_STATUSES = [
  {
    context: 'ci/recover',
    state: 'success',
    target_url: RECOVERED_RUN_URL,
    description: 'Recovered checks passed for the pull request',
    updated_at: '2026-11-08T17:33:36Z',
  },
];

function evidenceFor({ pr, run, checkRuns, statuses }) {
  return summarizeRecoveryEvidence({
    pr,
    run,
    checkRuns,
    statuses,
    targetSha: HEAD_SHA,
    expectedWorkflowPath: RECOVERY_WORKFLOW,
  });
}

function testIncidentSnapshotIsRed() {
  const evidence = evidenceFor({
    pr: INCIDENT_PR,
    run: {
      id: FAILED_RUN_ID,
      html_url: `https://github.com/sarnas-it/ebike-site/actions/runs/${FAILED_RUN_ID}`,
      path: RECOVERY_WORKFLOW,
      event: 'push',
      status: 'completed',
      conclusion: 'failure',
      head_sha: '66b98dfcf4ecf41ef833fbf68dc7ae2f740913b0',
      head_branch: 'feat/ebike-site-testeb-68',
    },
    checkRuns: INCIDENT_CHECKS,
    statuses: [],
  });

  assert.strictEqual(
    evidence.ready,
    false,
    'the failed recovery attempt must never be accepted as proof',
  );
  assert.ok(
    evidence.problems.some((problem) => problem.includes('completed with failure')),
    'the failed first recovery run must be reported as red',
  );
  assert.ok(
    evidence.problems.some((problem) => problem.includes('not merged')),
    'the incident pull request was not merged yet',
  );
  console.log('PASS: the TESTEB-68 incident snapshot (failed run, no merge) is red');
}

function testRecoveredSnapshotIsGreen() {
  const evidence = evidenceFor({
    pr: MERGED_PR,
    run: RECOVERED_RUN,
    checkRuns: RECOVERED_CHECKS,
    statuses: RECOVERED_STATUSES,
  });

  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.deepStrictEqual(evidence.problems, []);
  assert.strictEqual(evidence.run.id, RECOVERED_RUN_ID);
  assert.strictEqual(evidence.run.conclusion, 'success');
  assert.strictEqual(evidence.pullRequest.mergeCommitSha, MERGE_SHA);
  assert.strictEqual(evidence.pullRequest.headMatches, true);

  const names = evidence.checks.conclusions.map((check) => check.name);
  assert.deepStrictEqual(
    names.sort(),
    ['ci-repair-tests', 'php-lint', 'php-plugin-tests', 'php-tests', 'plugin-tests'],
    'the stale in_progress php-tests duplicate must not be listed twice',
  );
  assert.ok(
    evidence.checks.conclusions.every((check) => check.conclusion === 'success'),
    'every authoritative check on the recovered head must be green',
  );
  console.log(
    'PASS: the real post-fix TESTEB-68 snapshot (run #42505520113, merge 330a631) is green',
  );
}

function testRecoveredStatusLinksExactRun() {
  assert.deepStrictEqual(findRecoveryRunLink(RECOVERED_STATUSES), {
    id: RECOVERED_RUN_ID,
    url: RECOVERED_RUN_URL,
    state: 'success',
  });
  console.log('PASS: ci/recover on the PR head links the exact successful recovery run');
}

function testEvidenceBlockNamesActualResult() {
  const evidence = evidenceFor({
    pr: MERGED_PR,
    run: RECOVERED_RUN,
    checkRuns: RECOVERED_CHECKS,
    statuses: RECOVERED_STATUSES,
  });
  const block = renderEvidenceBlock(evidence);

  assert.ok(block.includes(HEAD_SHA), 'evidence must name the checked PR head');
  assert.ok(
    block.includes(`#${RECOVERED_RUN_ID}`),
    'evidence must link the successful recovery run',
  );
  assert.ok(block.includes(MERGE_SHA), 'evidence must record the real merge commit');
  assert.ok(
    block.includes('доказательства полные (green)'),
    'the rendered evidence must state the green outcome',
  );
  assert.ok(
    !block.includes('php-tests=success, php-tests=success'),
    'a stale duplicate must not be repeated in the evidence list',
  );
  console.log('PASS: the evidence block contains the actual run, merge commit and outcome');
}

function main() {
  testIncidentSnapshotIsRed();
  testRecoveredSnapshotIsGreen();
  testRecoveredStatusLinksExactRun();
  testEvidenceBlockNamesActualResult();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
