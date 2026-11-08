#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import {
  EVIDENCE_END,
  EVIDENCE_START,
  RECOVERY_WORKFLOW_PATH,
  collectRecoveryEvidence,
  recoveryWorkflowForPullRequest,
  findRecoveryRunLink,
  publishRecoveryEvidence,
  renderEvidenceComment,
  summarizeRecoveryEvidence,
} from '../evidence.mjs';

const OWNER = 'sarnas-it';
const REPO = 'ebike-site';
const PR_NUMBER = 73;
const TARGET_SHA = '525b0d4803e03858cdb0bfce9cb90837bdf0473e';
const RUN_ID = 51515151515;
const RUN_URL = `https://github.com/sarnas-it/ebike-site/actions/runs/${RUN_ID}`;
const MERGE_SHA = '89abcdef0123456789abcdef0123456789abcdef';
const MARKER = '<!-- testeb-67-recovery -->';

const GREEN_CHECKS = [
  { name: 'php-lint', conclusion: 'success', head_sha: TARGET_SHA },
  { name: 'php-tests', conclusion: 'success', head_sha: TARGET_SHA },
  { name: 'plugin-tests', conclusion: 'success', head_sha: TARGET_SHA },
  { name: 'php-plugin-tests', conclusion: 'success', head_sha: TARGET_SHA },
];

const SUCCESS_RUN = {
  id: RUN_ID,
  html_url: RUN_URL,
  path: RECOVERY_WORKFLOW_PATH,
  event: 'push',
  status: 'completed',
  conclusion: 'success',
  head_sha: '0123456789abcdef0123456789abcdef01234567',
  head_branch: 'feat/ebike-site-testeb-67',
};

const MERGED_PR = {
  number: PR_NUMBER,
  state: 'closed',
  merged: true,
  merge_commit_sha: MERGE_SHA,
  merged_at: '2026-11-08T04:03:25Z',
  head: { sha: TARGET_SHA },
};

const GREEN_STATUSES = [
  {
    context: 'ci/recover',
    state: 'success',
    target_url: RUN_URL,
    description: 'Recovered checks passed for the pull request',
  },
];

function response(status, body) {
  return {
    ok: status >= 200 && status < 300,
    status,
    async text() {
      return body === undefined ? '' : JSON.stringify(body);
    },
  };
}

function makeFetch(handler) {
  const calls = [];
  const fetchImpl = async (url, options = {}) => {
    const call = {
      url,
      method: options.method || 'GET',
      body: options.body ? JSON.parse(options.body) : null,
    };
    calls.push(call);
    return handler(call);
  };
  return { fetchImpl, calls };
}

function baseHandler({
  pr = MERGED_PR,
  run = SUCCESS_RUN,
  checkRuns = GREEN_CHECKS,
  comments = [],
} = {}) {
  return (call) => {
    const { url, method } = call;
    if (url.endsWith(`/pulls/${PR_NUMBER}`) && method === 'GET') {
      return response(200, pr);
    }
    if (url.includes(`/commits/${TARGET_SHA}/check-runs`) && method === 'GET') {
      return response(200, { total_count: checkRuns.length, check_runs: checkRuns });
    }
    if (url.includes(`/commits/${TARGET_SHA}/status`) && method === 'GET') {
      return response(200, {
        state: 'success',
        total_count: GREEN_STATUSES.length,
        statuses: GREEN_STATUSES,
      });
    }
    if (url.includes(`/actions/runs/${RUN_ID}`) && method === 'GET') {
      return response(200, run);
    }
    if (url.includes('/issues/') && url.includes('/comments') && method === 'GET') {
      return response(200, comments);
    }
    if (url.includes('/issues/comments/') && method === 'PATCH') {
      return response(200, { id: Number(url.split('/').pop()), ...call.body });
    }
    if (url.includes('/issues/') && url.includes('/comments') && method === 'POST') {
      return response(201, { id: 701, ...call.body });
    }
    throw new Error(`unexpected request: ${method} ${url}`);
  };
}

function testGreenEvidenceIsReady() {
  const evidence = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: GREEN_CHECKS,
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(evidence.ready, true);
  assert.deepStrictEqual(evidence.problems, []);
  assert.strictEqual(evidence.run.id, RUN_ID);
  assert.strictEqual(evidence.pullRequest.mergeCommitSha, MERGE_SHA);
  assert.deepStrictEqual(
    evidence.checks.conclusions.map((check) => `${check.name}=${check.conclusion}`),
    [
      'php-lint=success',
      'php-tests=success',
      'plugin-tests=success',
      'php-plugin-tests=success',
    ],
  );
  console.log('PASS: green checks backed by a successful recovery run are ready');
}

function testSyntheticChecksWithoutRunAreRed() {
  const evidence = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: null,
    checkRuns: GREEN_CHECKS,
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) => problem.includes('no recovery workflow run')),
    'green check runs without a real workflow run must not be trusted',
  );
  console.log('PASS: synthetic green checks without a CI run are red');
}

function testFailedOrInProgressRunIsRed() {
  const failed = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: { ...SUCCESS_RUN, conclusion: 'failure' },
    checkRuns: GREEN_CHECKS,
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(failed.ready, false);
  assert.ok(failed.problems.some((problem) => problem.includes('completed with failure')));

  const running = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: { ...SUCCESS_RUN, status: 'in_progress', conclusion: null },
    checkRuns: GREEN_CHECKS,
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(running.ready, false);
  assert.ok(running.problems.some((problem) => problem.includes('not a completed successful run')));

  const provisional = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: { ...SUCCESS_RUN, status: 'in_progress', conclusion: null },
    checkRuns: GREEN_CHECKS,
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
    allowRunning: true,
  });
  assert.strictEqual(provisional.ready, true);
  console.log('PASS: failed/in-progress runs are red unless the current run is allowed');
}

function testWrongWorkflowRunIsRed() {
  const evidence = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: { ...SUCCESS_RUN, path: '.github/workflows/ci.yml' },
    checkRuns: GREEN_CHECKS,
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(evidence.problems.some((problem) => problem.includes('belongs to')));
  console.log('PASS: a successful run from another workflow is not accepted as evidence');
}

function testUnmergedOrFailedChecksAreRed() {
  const unmerged = summarizeRecoveryEvidence({
    pr: { ...MERGED_PR, merged: false, merge_commit_sha: null },
    run: SUCCESS_RUN,
    checkRuns: GREEN_CHECKS,
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(unmerged.ready, false);
  assert.ok(unmerged.problems.some((problem) => problem.includes('not merged')));

  const failedChecks = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: [{ name: 'php-lint', conclusion: 'failure' }],
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(failedChecks.ready, false);
  assert.ok(failedChecks.problems.some((problem) => problem.includes('failed check')));

  const noChecks = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: [],
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(noChecks.ready, false);
  assert.ok(noChecks.problems.some((problem) => problem.includes('no check runs')));

  const statusOnly = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: [],
    statuses: [{ context: 'ci/recover', state: 'success' }],
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(statusOnly.ready, false);
  assert.ok(
    statusOnly.problems.some((problem) => problem.includes('no check runs')),
    'a bare commit status is not a substitute for named check runs',
  );
  console.log('PASS: an unmerged PR or failed/missing checks are red');
}

function testGreenEvidenceWithoutLinkedStatusIsRed() {
  const evidence = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: GREEN_CHECKS,
    statuses: [],
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) =>
      problem.includes('no successful ci/recover status'),
    ),
    'green checks must be linked to a recovery run through the ci/recover status',
  );
  console.log('PASS: green checks without a ci/recover link are red');
}

function testEvidenceForUnlinkedRunIsRed() {
  const otherRunUrl =
    'https://github.com/sarnas-it/ebike-site/actions/runs/999999';
  const evidence = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: GREEN_CHECKS,
    statuses: [
      { context: 'ci/recover', state: 'success', target_url: otherRunUrl },
    ],
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) => problem.includes('not linked')),
    'a run that did not publish the ci/recover status cannot prove the checks',
  );
  console.log('PASS: a recovery run not linked from the commit status is red');
}

async function testCollectUsesRunLinkedFromCommitStatus() {
  const { fetchImpl, calls } = makeFetch((call) => {
    const { url, method } = call;
    if (url.endsWith(`/pulls/${PR_NUMBER}`) && method === 'GET') {
      return response(200, MERGED_PR);
    }
    if (url.includes(`/commits/${TARGET_SHA}/check-runs`) && method === 'GET') {
      return response(200, {
        total_count: GREEN_CHECKS.length,
        check_runs: GREEN_CHECKS,
      });
    }
    if (url.includes(`/commits/${TARGET_SHA}/status`) && method === 'GET') {
      return response(200, {
        state: 'success',
        total_count: GREEN_STATUSES.length,
        statuses: GREEN_STATUSES,
      });
    }
    if (url.includes(`/actions/runs/${RUN_ID}`) && method === 'GET') {
      return response(200, SUCCESS_RUN);
    }
    if (url.includes('/actions/workflows/') && url.includes('/runs')) {
      throw new Error(
        'the exact linked run must be used instead of a latest-successful fallback',
      );
    }
    throw new Error(`unexpected request: ${method} ${url}`);
  });

  const evidence = await collectRecoveryEvidence({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(evidence.ready, true);
  assert.strictEqual(evidence.run.id, RUN_ID);
  assert.ok(
    calls.some((call) => call.url.includes(`/actions/runs/${RUN_ID}`)),
    'the run linked from the ci/recover status must be fetched by id',
  );
  assert.deepStrictEqual(findRecoveryRunLink(GREEN_STATUSES), {
    id: RUN_ID,
    url: RUN_URL,
    state: 'success',
  });
  console.log(
    'PASS: evidence resolves the exact recovery run linked from the checked commit',
  );
}

function testRenderedCommentContainsRealEvidence() {
  const evidence = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: GREEN_CHECKS,
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  const body = renderEvidenceComment(`${MARKER}\nПричина`, evidence);
  assert.ok(body.includes(MARKER));
  assert.ok(body.includes(EVIDENCE_START), 'evidence block must be delimited');
  assert.ok(body.includes(EVIDENCE_END), 'evidence block must be delimited');
  assert.ok(body.includes('Подтверждение из реального recovery-прогона'));
  assert.ok(body.includes(TARGET_SHA), 'comment must name the checked commit');
  assert.ok(body.includes(RUN_URL), 'comment must link the real workflow run');
  assert.ok(body.includes('#51515151515'), 'comment must name the real run id');
  assert.ok(body.includes(MERGE_SHA), 'comment must record the merge commit');
  assert.ok(body.includes('status=completed'));
  assert.ok(body.includes('conclusion=success'));
  assert.ok(body.includes('php-lint=success'));
  assert.ok(
    body.includes('php-plugin-tests=success'),
    'the evidence must include the plugin PHP acceptance checks',
  );
  console.log('PASS: the comment contains the run URL/id, checks and merge commit');
}

function testRenderedCommentReplacesStaleEvidence() {
  const evidence = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: GREEN_CHECKS,
    statuses: GREEN_STATUSES,
    targetSha: TARGET_SHA,
  });
  const stale = [
    MARKER,
    'Причина',
    '',
    EVIDENCE_START,
    '## Подтверждение из реального recovery-прогона',
    '- Recovery workflow run: [#1](https://example.invalid/old) — status=in_progress',
    EVIDENCE_END,
  ].join('\n');
  const body = renderEvidenceComment(stale, evidence);
  assert.strictEqual(body.split(EVIDENCE_START).length - 1, 1, 'only one evidence block may exist');
  assert.strictEqual(body.split(EVIDENCE_END).length - 1, 1, 'only one evidence block may exist');
  assert.ok(body.includes(RUN_URL), 'stale evidence must be replaced with the real run');
  assert.ok(!body.includes('example.invalid/old'), 'stale in-progress evidence must be removed');
  console.log('PASS: stale evidence is replaced instead of being duplicated');
}

async function testPublishUpdatesExistingComment() {
  const { fetchImpl, calls } = makeFetch(
    baseHandler({ comments: [{ id: 1, body: `${MARKER}\nprepared draft` }] }),
  );
  const result = await publishRecoveryEvidence({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    runId: RUN_ID,
    template: `${MARKER}\nПричина`,
    marker: MARKER,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(result.evidence.ready, true);
  assert.strictEqual(result.comment.updated, true);
  assert.strictEqual(result.comment.created, false);
  const patches = calls.filter((call) => call.method === 'PATCH');
  assert.strictEqual(patches.length, 1, 'the existing comment must be updated in place');
  assert.ok(patches[0].url.endsWith('/issues/comments/1'));
  assert.ok(patches[0].body.body.includes(RUN_URL));
  assert.strictEqual(
    calls.filter((call) => call.method === 'POST' && call.url.includes('/comments')).length,
    0,
    'no duplicate comment may be created',
  );
  console.log('PASS: the prepared comment is updated with real evidence, not left as a draft');
}

async function testPublishCreatesCommentWithEvidence() {
  const { fetchImpl, calls } = makeFetch(baseHandler());
  const result = await publishRecoveryEvidence({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    runId: RUN_ID,
    template: `${MARKER}\nПричина`,
    marker: MARKER,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(result.comment.created, true);
  const posted = calls.find((call) => call.method === 'POST' && call.url.includes('/comments'));
  assert.ok(posted.body.body.includes(RUN_URL));
  assert.ok(posted.body.body.includes(MERGE_SHA));
  console.log('PASS: a missing comment is created with the verified evidence');
}

async function testPublishRefusesWhenEvidenceIsRed() {
  const { fetchImpl, calls } = makeFetch(baseHandler({ run: null }));
  const result = await publishRecoveryEvidence({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    runId: RUN_ID,
    template: `${MARKER}\nПричина`,
    marker: MARKER,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(result.evidence.ready, false);
  assert.strictEqual(result.comment, null);
  assert.strictEqual(
    calls.filter((call) => call.method === 'POST' || call.method === 'PATCH').length,
    0,
    'red evidence must never be published as confirmation',
  );
  console.log('PASS: red evidence is not published');
}

function testRecoveryWorkflowsResolvePerPullRequest() {
  assert.strictEqual(
    recoveryWorkflowForPullRequest(62),
    '.github/workflows/recover-pr62.yml',
    'PR #62 must use its own recovery workflow',
  );
  assert.strictEqual(
    recoveryWorkflowForPullRequest(73),
    '.github/workflows/recover-pr73.yml',
    'PR #73 must use its own recovery workflow',
  );
  assert.strictEqual(
    recoveryWorkflowForPullRequest(999),
    RECOVERY_WORKFLOW_PATH,
    'an unknown pull request falls back to the documented default workflow',
  );
  console.log('PASS: each pull request keeps its own recovery workflow');
}

async function main() {
  testGreenEvidenceIsReady();
  testSyntheticChecksWithoutRunAreRed();
  testFailedOrInProgressRunIsRed();
  testWrongWorkflowRunIsRed();
  testUnmergedOrFailedChecksAreRed();
  testGreenEvidenceWithoutLinkedStatusIsRed();
  testEvidenceForUnlinkedRunIsRed();
  await testCollectUsesRunLinkedFromCommitStatus();
  testRecoveryWorkflowsResolvePerPullRequest();
  testRenderedCommentContainsRealEvidence();
  testRenderedCommentReplacesStaleEvidence();
  await testPublishUpdatesExistingComment();
  await testPublishCreatesCommentWithEvidence();
  await testPublishRefusesWhenEvidenceIsRed();
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
