#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import {
  RECOVERY_WORKFLOW_PATH,
  publishRecoveryEvidence,
  renderEvidenceComment,
  summarizeRecoveryEvidence,
} from '../evidence.mjs';

const OWNER = 'sarnas-it';
const REPO = 'ebike-site';
const PR_NUMBER = 62;
const TARGET_SHA = '578e87b5a8587d6078aade92d26bd449448dd1d3';
const RUN_ID = 42445204104;
const RUN_URL = `https://github.com/sarnas-it/ebike-site/actions/runs/${RUN_ID}`;
const MERGE_SHA = 'efe1bc4dac551dc870cec68aff16a973e095dd4f';
const MARKER = '<!-- testeb-63-recovery -->';

const GREEN_CHECKS = [
  { name: 'php-lint', conclusion: 'success', head_sha: TARGET_SHA },
  { name: 'php-tests', conclusion: 'success', head_sha: TARGET_SHA },
  { name: 'plugin-tests', conclusion: 'success', head_sha: TARGET_SHA },
];

const SUCCESS_RUN = {
  id: RUN_ID,
  html_url: RUN_URL,
  path: RECOVERY_WORKFLOW_PATH,
  event: 'push',
  status: 'completed',
  conclusion: 'success',
  head_sha: 'e4b76df9c60653547c695419b040b6f5208657d1',
  head_branch: 'feat/ebike-site-testeb-63',
};

const MERGED_PR = {
  number: PR_NUMBER,
  state: 'closed',
  merged: true,
  merge_commit_sha: MERGE_SHA,
  merged_at: '2026-11-08T04:03:25Z',
  head: { sha: TARGET_SHA },
};

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
      return response(200, { state: 'success', total_count: 1, statuses: [] });
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
    statuses: [],
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(evidence.ready, true);
  assert.deepStrictEqual(evidence.problems, []);
  assert.strictEqual(evidence.run.id, RUN_ID);
  assert.strictEqual(evidence.pullRequest.mergeCommitSha, MERGE_SHA);
  assert.deepStrictEqual(
    evidence.checks.conclusions.map((check) => `${check.name}=${check.conclusion}`),
    ['php-lint=success', 'php-tests=success', 'plugin-tests=success'],
  );
  console.log('PASS: green checks backed by a successful recovery run are ready');
}

function testSyntheticChecksWithoutRunAreRed() {
  const evidence = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: null,
    checkRuns: GREEN_CHECKS,
    statuses: [],
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
    statuses: [],
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(failed.ready, false);
  assert.ok(failed.problems.some((problem) => problem.includes('completed with failure')));

  const running = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: { ...SUCCESS_RUN, status: 'in_progress', conclusion: null },
    checkRuns: GREEN_CHECKS,
    statuses: [],
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(running.ready, false);
  assert.ok(running.problems.some((problem) => problem.includes('not a completed successful run')));

  const provisional = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: { ...SUCCESS_RUN, status: 'in_progress', conclusion: null },
    checkRuns: GREEN_CHECKS,
    statuses: [],
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
    statuses: [],
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
    statuses: [],
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(unmerged.ready, false);
  assert.ok(unmerged.problems.some((problem) => problem.includes('not merged')));

  const failedChecks = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: [{ name: 'php-lint', conclusion: 'failure' }],
    statuses: [],
    targetSha: TARGET_SHA,
  });
  assert.strictEqual(failedChecks.ready, false);
  assert.ok(failedChecks.problems.some((problem) => problem.includes('failed check')));

  const noChecks = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: [],
    statuses: [],
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

function testRenderedCommentContainsRealEvidence() {
  const evidence = summarizeRecoveryEvidence({
    pr: MERGED_PR,
    run: SUCCESS_RUN,
    checkRuns: GREEN_CHECKS,
    statuses: [],
    targetSha: TARGET_SHA,
  });
  const body = renderEvidenceComment(`${MARKER}\nПричина`, evidence);
  assert.ok(body.includes(MARKER));
  assert.ok(body.includes(TARGET_SHA), 'comment must name the checked commit');
  assert.ok(body.includes(RUN_URL), 'comment must link the real workflow run');
  assert.ok(body.includes(MERGE_SHA), 'comment must record the merge commit');
  assert.ok(body.includes('php-lint=success'));
  console.log('PASS: the comment contains the run URL, checks and merge commit');
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

async function main() {
  testGreenEvidenceIsReady();
  testSyntheticChecksWithoutRunAreRed();
  testFailedOrInProgressRunIsRed();
  testWrongWorkflowRunIsRed();
  testUnmergedOrFailedChecksAreRed();
  testRenderedCommentContainsRealEvidence();
  await testPublishUpdatesExistingComment();
  await testPublishCreatesCommentWithEvidence();
  await testPublishRefusesWhenEvidenceIsRed();
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
