#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import { WORKFLOW_PATH } from '../lib.mjs';
import {
  collectRolloutEvidence,
  publishRolloutEvidence,
  renderRolloutComment,
  selectLatestCiRun,
  summarizeRolloutEvidence,
} from '../rollout.mjs';

const OWNER = 'sarnas-it';
const REPO = 'ebike-site';
const FIX_PR = 65;
const HEAD_SHA = 'a1b2c3d4e5f60718293a4b5c6d7e8f9012345678';
const STALE_SHA = '7052bf282923dfaa016b483789346b1e6f0193e8';
const MERGE_SHA = 'efe1bc4dac551dc870cec68aff16a973e095dd4f';
const RUN_ID = 42467339807;
const RUN_URL = `https://github.com/${OWNER}/${REPO}/actions/runs/${RUN_ID}`;
const BASE_BRANCH = 'main';
const MARKER = '<!-- testeb-63-rollout -->';

const GREEN_JOBS = [
  { id: 1, name: 'php-lint', status: 'completed', conclusion: 'success' },
  { id: 2, name: 'php-tests', status: 'completed', conclusion: 'success' },
  { id: 3, name: 'plugin-tests', status: 'completed', conclusion: 'success' },
  { id: 4, name: 'ci-repair-tests', status: 'completed', conclusion: 'success' },
];

// The tracker merges PR #65 only after acceptance, so the normal state before
// acceptance is an open, mergeable pull request with green CI on its real head.
const OPEN_PR = {
  number: FIX_PR,
  state: 'open',
  merged: false,
  mergeable: true,
  mergeable_state: 'clean',
  merged_at: null,
  merge_commit_sha: null,
  base: { ref: BASE_BRANCH },
  head: { sha: HEAD_SHA },
};

const MERGED_PR = {
  ...OPEN_PR,
  state: 'closed',
  merged: true,
  merged_at: '2026-11-08T09:00:00Z',
  merge_commit_sha: MERGE_SHA,
};

const REPOSITORY = { default_branch: BASE_BRANCH };

const WORKFLOW_CONTENT = {
  type: 'file',
  encoding: 'base64',
  content: Buffer.from('name: ci\non:\n  pull_request:\n').toString('base64'),
};

const SUCCESS_RUN = {
  id: RUN_ID,
  html_url: RUN_URL,
  path: WORKFLOW_PATH,
  name: 'ci',
  event: 'pull_request',
  status: 'completed',
  conclusion: 'success',
  head_sha: HEAD_SHA,
  head_branch: 'feat/ebike-site-testeb-63',
  created_at: '2026-11-08T08:30:00Z',
  updated_at: '2026-11-08T08:35:00Z',
};

function greenEvidence(overrides = {}) {
  return summarizeRolloutEvidence({
    pr: OPEN_PR,
    repository: REPOSITORY,
    workflow: WORKFLOW_CONTENT,
    run: SUCCESS_RUN,
    jobs: GREEN_JOBS,
    targetSha: HEAD_SHA,
    workflowRef: HEAD_SHA,
    ...overrides,
  });
}

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
  pr = OPEN_PR,
  run = SUCCESS_RUN,
  runs = [SUCCESS_RUN],
  jobs = GREEN_JOBS,
  workflow = WORKFLOW_CONTENT,
  comments = [],
} = {}) {
  return (call) => {
    const { url, method } = call;
    if (url.endsWith(`/pulls/${FIX_PR}`) && method === 'GET') {
      return response(200, pr);
    }
    if (url.endsWith(`/repos/${OWNER}/${REPO}`) && method === 'GET') {
      return response(200, REPOSITORY);
    }
    if (url.includes('/contents/')) {
      const hasRef = url.includes(`ref=${HEAD_SHA}`) || url.includes(`ref=${BASE_BRANCH}`);
      if (!hasRef) {
        throw new Error(`unexpected contents ref: ${url}`);
      }
      if (!workflow) {
        return response(404, { message: 'Not Found' });
      }
      return response(200, workflow);
    }
    if (url.includes(`/actions/runs/${RUN_ID}/jobs`) && method === 'GET') {
      return response(200, { total_count: jobs.length, jobs });
    }
    if (url.includes(`/actions/runs/${RUN_ID}`) && method === 'GET') {
      return response(200, run);
    }
    if (url.includes('/actions/runs?') && url.includes(`head_sha=${HEAD_SHA}`)) {
      return response(200, { total_count: runs.length, workflow_runs: runs });
    }
    if (url.includes(`/issues/${FIX_PR}/comments`) && method === 'GET') {
      return response(200, comments);
    }
    if (url.includes(`/issues/${FIX_PR}/comments`) && method === 'POST') {
      return response(201, { id: 801, ...call.body });
    }
    if (url.includes('/issues/comments/') && method === 'PATCH') {
      return response(200, { id: Number(url.split('/').pop()), ...call.body });
    }
    throw new Error(`unexpected request: ${method} ${url}`);
  };
}

function testGreenOpenFixIsReadyForAutomerge() {
  const evidence = greenEvidence();
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.deepStrictEqual(evidence.problems, []);
  assert.strictEqual(evidence.pullRequest.merged, false);
  assert.strictEqual(evidence.pullRequest.state, 'open');
  assert.strictEqual(evidence.pullRequest.mergeable, true);
  assert.strictEqual(evidence.targetSha, HEAD_SHA);
  assert.strictEqual(evidence.run.id, RUN_ID);
  assert.strictEqual(evidence.run.headSha, HEAD_SHA);
  assert.deepStrictEqual(
    evidence.jobs.map((job) => `${job.name}=${job.conclusion}`),
    GREEN_JOBS.map((job) => `${job.name}=${job.conclusion}`),
  );
  console.log(
    'PASS: an open, mergeable fix with a successful CI run on its real head is ready for automerge',
  );
}

function testMergedFixIsAlsoReady() {
  const evidence = greenEvidence({
    pr: MERGED_PR,
    workflowRef: BASE_BRANCH,
  });
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.strictEqual(evidence.pullRequest.merged, true);
  assert.strictEqual(evidence.pullRequest.mergeCommitSha, MERGE_SHA);
  console.log('PASS: the same fix stays green once the tracker has merged it');
}

function testConflictingFixIsRed() {
  const evidence = greenEvidence({
    pr: { ...OPEN_PR, mergeable: false, mergeable_state: 'dirty' },
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) => problem.includes('is not mergeable')),
    'an unmergeable pull request must be red',
  );
  assert.ok(
    evidence.problems.some((problem) => problem.includes('merge conflicts')),
    'a conflicting pull request must be red',
  );
  console.log('PASS: an unmergeable fix with conflicts against main is red');
}

function testClosedUnmergedFixIsRed() {
  const evidence = greenEvidence({ pr: { ...OPEN_PR, state: 'closed' } });
  assert.strictEqual(evidence.ready, false);
  assert.ok(evidence.problems.some((problem) => problem.includes('expected open or merged')));
  console.log('PASS: a closed, unmerged fix is red');
}

function testWrongBaseBranchIsRed() {
  const evidence = greenEvidence({
    pr: { ...OPEN_PR, base: { ref: 'develop' } },
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(evidence.problems.some((problem) => problem.includes('expected main')));
  console.log('PASS: a fix merged into the wrong base branch is red');
}

function testMissingWorkflowOnHeadIsRed() {
  const evidence = greenEvidence({ workflow: null });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) =>
      problem.includes(`${WORKFLOW_PATH} is missing on ${HEAD_SHA}`),
    ),
    'the fix is not present while the workflow is missing from the pull request head',
  );
  console.log('PASS: a fix that is not present in the pull request head is red');
}

function testRunOnStaleCommitIsRed() {
  const evidence = greenEvidence({
    run: { ...SUCCESS_RUN, head_sha: STALE_SHA },
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some(
      (problem) => problem.includes(`is for ${STALE_SHA}`) && problem.includes(`expected ${HEAD_SHA}`),
    ),
    'a successful run on an older commit must not stand in for the current head',
  );

  const selected = selectLatestCiRun(
    [{ ...SUCCESS_RUN, head_sha: STALE_SHA, id: 1 }],
    HEAD_SHA,
    WORKFLOW_PATH,
  );
  assert.strictEqual(
    selected,
    null,
    'a run for an older commit is not even a candidate for the current head',
  );
  console.log('PASS: a green run from a stale commit is rejected as evidence');
}

function testFailedOrIncompleteRunIsRed() {
  const failed = greenEvidence({ run: { ...SUCCESS_RUN, conclusion: 'failure' } });
  assert.strictEqual(failed.ready, false);
  assert.ok(failed.problems.some((problem) => problem.includes('completed with failure')));

  const running = greenEvidence({
    run: { ...SUCCESS_RUN, status: 'in_progress', conclusion: null },
  });
  assert.strictEqual(running.ready, false);
  assert.ok(running.problems.some((problem) => problem.includes('not a completed run')));
  console.log('PASS: failed or incomplete CI runs are red');
}

function testMissingOrFailedJobIsRed() {
  const missing = greenEvidence({
    jobs: GREEN_JOBS.filter((job) => job.name !== 'ci-repair-tests'),
  });
  assert.strictEqual(missing.ready, false);
  assert.ok(
    missing.problems.some((problem) => problem.includes('missing the ci-repair-tests job')),
    'the full check set must be present',
  );

  const failed = greenEvidence({
    jobs: GREEN_JOBS.map((job) =>
      job.name === 'php-tests' ? { ...job, conclusion: 'failure' } : job,
    ),
  });
  assert.strictEqual(failed.ready, false);
  assert.ok(
    failed.problems.some((problem) => problem.includes('CI job php-tests is failure')),
  );
  console.log('PASS: a missing or failed CI job makes the rollout red');
}

function testWrongWorkflowPathIsRed() {
  const evidence = greenEvidence({
    run: { ...SUCCESS_RUN, path: '.github/workflows/recover-pr62.yml' },
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(evidence.problems.some((problem) => problem.includes('belongs to')));
  console.log('PASS: a run from another workflow is not accepted as CI evidence');
}

function testLatestRunWins() {
  const olderSuccess = { ...SUCCESS_RUN, id: 1, updated_at: '2026-11-08T08:00:00Z' };
  const newerFailure = {
    ...SUCCESS_RUN,
    id: 2,
    conclusion: 'failure',
    updated_at: '2026-11-08T09:00:00Z',
  };
  const selected = selectLatestCiRun([olderSuccess, newerFailure], HEAD_SHA);
  assert.strictEqual(selected.id, 2, 'a newer failed run must not be masked by an old success');
  console.log('PASS: the latest CI run is the one that is verified');
}

async function testCollectsFromApiByRunId() {
  const { fetchImpl, calls } = makeFetch(baseHandler());
  const evidence = await collectRolloutEvidence({
    owner: OWNER,
    repo: REPO,
    fixPr: FIX_PR,
    runId: String(RUN_ID),
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.ok(calls.some((call) => call.url.includes(`/pulls/${FIX_PR}`)));
  assert.ok(
    calls.some((call) => call.url.includes('/contents/') && call.url.includes(`ref=${HEAD_SHA}`)),
    'the workflow must be read from the real pull request head before the merge',
  );
  assert.ok(calls.some((call) => call.url.includes(`/actions/runs/${RUN_ID}/jobs`)));
  console.log('PASS: rollout evidence is collected from the repository, PR, workflow and run');
}

async function testCollectsLatestRunForHead() {
  const { fetchImpl } = makeFetch(baseHandler());
  const evidence = await collectRolloutEvidence({
    owner: OWNER,
    repo: REPO,
    fixPr: FIX_PR,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(evidence.targetSha, HEAD_SHA);
  assert.strictEqual(evidence.run.id, RUN_ID);
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  console.log('PASS: without an explicit run id the latest CI run for the real head is verified');
}

async function testCollectMergedFixReadsWorkflowFromBase() {
  const { fetchImpl, calls } = makeFetch(baseHandler({ pr: MERGED_PR }));
  const evidence = await collectRolloutEvidence({
    owner: OWNER,
    repo: REPO,
    fixPr: FIX_PR,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.ok(
    calls.some((call) => call.url.includes('/contents/') && call.url.includes(`ref=${BASE_BRANCH}`)),
    'after the merge the workflow is read from the base branch',
  );
  console.log('PASS: a merged fix is verified against the base branch');
}

async function testCollectFailsWhenWorkflowMissingOnHead() {
  const { fetchImpl } = makeFetch(baseHandler({ workflow: null }));
  const evidence = await collectRolloutEvidence({
    owner: OWNER,
    repo: REPO,
    fixPr: FIX_PR,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(evidence.problems.some((problem) => problem.includes('is missing on')));
  console.log('PASS: collecting evidence detects a workflow that is not in the fix');
}


function testRenderedRolloutCommentPinsRealHead() {
  const body = renderRolloutComment(greenEvidence(), MARKER);
  assert.ok(body.includes(MARKER));
  assert.ok(body.includes(HEAD_SHA), 'the comment must name the real head commit');
  assert.ok(body.includes(RUN_URL), 'the comment must link the real ci run');
  assert.ok(body.includes('php-lint=success'));
  assert.ok(body.includes('mergeable=true'));
  assert.ok(body.includes('green'));
  assert.ok(
    !body.includes(STALE_SHA),
    'a stale commit must never appear as current evidence',
  );
  console.log('PASS: the rollout evidence comment pins the real head and run');
}

async function testPublishRolloutEvidenceCreatesComment() {
  const { fetchImpl, calls } = makeFetch(baseHandler());
  const result = await publishRolloutEvidence({
    owner: OWNER,
    repo: REPO,
    fixPr: FIX_PR,
    marker: MARKER,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(result.evidence.ready, true, result.evidence.problems.join('; '));
  assert.strictEqual(result.comment.created, true);
  const posted = calls.find(
    (call) => call.method === 'POST' && call.url.includes(`/issues/${FIX_PR}/comments`),
  );
  assert.ok(posted, 'the evidence must be published on the fix pull request');
  assert.ok(posted.body.body.includes(HEAD_SHA));
  assert.ok(posted.body.body.includes(RUN_URL));
  console.log('PASS: verified rollout evidence is posted against the real head');
}

async function testPublishRolloutEvidenceUpdatesExistingComment() {
  const { fetchImpl, calls } = makeFetch(
    baseHandler({ comments: [{ id: 1, body: `${MARKER}\nold` }] }),
  );
  const result = await publishRolloutEvidence({
    owner: OWNER,
    repo: REPO,
    fixPr: FIX_PR,
    marker: MARKER,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(result.evidence.ready, true, result.evidence.problems.join('; '));
  assert.strictEqual(result.comment.updated, true);
  const patches = calls.filter((call) => call.method === 'PATCH');
  assert.strictEqual(patches.length, 1);
  assert.ok(patches[0].body.body.includes(HEAD_SHA));
  assert.strictEqual(
    calls.filter(
      (call) => call.method === 'POST' && call.url.includes(`/issues/${FIX_PR}/comments`),
    ).length,
    0,
    'the evidence comment must be updated in place, not duplicated',
  );
  console.log('PASS: rollout evidence updates one idempotent comment');
}

async function testPublishRolloutEvidenceRefusesStaleRun() {
  const staleRun = { ...SUCCESS_RUN, head_sha: STALE_SHA };
  const { fetchImpl, calls } = makeFetch(
    baseHandler({ run: staleRun, runs: [staleRun] }),
  );
  const result = await publishRolloutEvidence({
    owner: OWNER,
    repo: REPO,
    fixPr: FIX_PR,
    marker: MARKER,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(result.evidence.ready, false);
  assert.strictEqual(result.comment, null);
  assert.strictEqual(
    calls.filter((call) => call.method === 'POST' || call.method === 'PATCH').length,
    0,
    'red evidence must never be published as proof',
  );
  console.log('PASS: a stale green run is not published as evidence for the head');
}

async function main() {
  testGreenOpenFixIsReadyForAutomerge();
  testMergedFixIsAlsoReady();
  testConflictingFixIsRed();
  testClosedUnmergedFixIsRed();
  testWrongBaseBranchIsRed();
  testMissingWorkflowOnHeadIsRed();
  testRunOnStaleCommitIsRed();
  testFailedOrIncompleteRunIsRed();
  testMissingOrFailedJobIsRed();
  testWrongWorkflowPathIsRed();
  testLatestRunWins();
  await testCollectsFromApiByRunId();
  await testCollectsLatestRunForHead();
  await testCollectMergedFixReadsWorkflowFromBase();
  await testCollectFailsWhenWorkflowMissingOnHead();
  testRenderedRolloutCommentPinsRealHead();
  await testPublishRolloutEvidenceCreatesComment();
  await testPublishRolloutEvidenceUpdatesExistingComment();
  await testPublishRolloutEvidenceRefusesStaleRun();
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
