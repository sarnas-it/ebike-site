#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import { WORKFLOW_PATH } from '../lib.mjs';
import {
  collectRolloutEvidence,
  selectLatestCiRun,
  summarizeRolloutEvidence,
} from '../rollout.mjs';

const OWNER = 'sarnas-it';
const REPO = 'ebike-site';
const FIX_PR = 65;
const FIX_SHA = '7052bf282923dfaa016b483789346b1e6f0193e8';
const MERGE_SHA = 'efe1bc4dac551dc870cec68aff16a973e095dd4f';
const RUN_ID = 42467339807;
const RUN_URL = `https://github.com/${OWNER}/${REPO}/actions/runs/${RUN_ID}`;
const BASE_BRANCH = 'main';

const GREEN_JOBS = [
  { id: 1, name: 'php-lint', status: 'completed', conclusion: 'success' },
  { id: 2, name: 'php-tests', status: 'completed', conclusion: 'success' },
  { id: 3, name: 'plugin-tests', status: 'completed', conclusion: 'success' },
  { id: 4, name: 'ci-repair-tests', status: 'completed', conclusion: 'success' },
];

const MERGED_PR = {
  number: FIX_PR,
  state: 'closed',
  merged: true,
  merged_at: '2026-11-08T09:00:00Z',
  merge_commit_sha: MERGE_SHA,
  base: { ref: BASE_BRANCH },
  head: { sha: FIX_SHA },
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
  head_sha: FIX_SHA,
  head_branch: 'feat/ebike-site-testeb-63',
  created_at: '2026-11-08T08:30:00Z',
  updated_at: '2026-11-08T08:35:00Z',
};

function greenEvidence(overrides = {}) {
  return summarizeRolloutEvidence({
    pr: MERGED_PR,
    repository: REPOSITORY,
    workflow: WORKFLOW_CONTENT,
    run: SUCCESS_RUN,
    jobs: GREEN_JOBS,
    targetSha: FIX_SHA,
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
  pr = MERGED_PR,
  run = SUCCESS_RUN,
  runs = [SUCCESS_RUN],
  jobs = GREEN_JOBS,
  workflow = WORKFLOW_CONTENT,
} = {}) {
  return (call) => {
    const { url, method } = call;
    if (url.endsWith(`/pulls/${FIX_PR}`) && method === 'GET') {
      return response(200, pr);
    }
    if (url.endsWith(`/repos/${OWNER}/${REPO}`) && method === 'GET') {
      return response(200, REPOSITORY);
    }
    if (url.includes('/contents/') && url.includes(`ref=${BASE_BRANCH}`)) {
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
    if (url.includes('/actions/runs?') && url.includes(`head_sha=${FIX_SHA}`)) {
      return response(200, { total_count: runs.length, workflow_runs: runs });
    }
    throw new Error(`unexpected request: ${method} ${url}`);
  };
}

function testGreenRolloutIsReady() {
  const evidence = greenEvidence();
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.deepStrictEqual(evidence.problems, []);
  assert.strictEqual(evidence.targetSha, FIX_SHA);
  assert.strictEqual(evidence.pullRequest.mergeCommitSha, MERGE_SHA);
  assert.strictEqual(evidence.run.id, RUN_ID);
  assert.deepStrictEqual(
    evidence.jobs.map((job) => `${job.name}=${job.conclusion}`),
    GREEN_JOBS.map((job) => `${job.name}=${job.conclusion}`),
  );
  console.log('PASS: a merged fix with a successful CI run on the fix commit is ready');
}

function testUnmergedFixIsRed() {
  const evidence = greenEvidence({
    pr: { ...MERGED_PR, merged: false, merge_commit_sha: null },
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) => problem.includes('is not merged into main')),
    'an unmerged fix pull request must be red',
  );
  console.log('PASS: an unmerged fix pull request is red');
}

function testWrongBaseBranchIsRed() {
  const evidence = greenEvidence({
    pr: { ...MERGED_PR, base: { ref: 'develop' } },
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(evidence.problems.some((problem) => problem.includes('expected main')));
  console.log('PASS: a fix merged into the wrong base branch is red');
}

function testMissingWorkflowOnBaseIsRed() {
  const evidence = greenEvidence({ workflow: null });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) =>
      problem.includes(`${WORKFLOW_PATH} is missing on main`),
    ),
    'the fix is not landed while the workflow is missing from main',
  );
  console.log('PASS: a fix that is not present on the base branch is red');
}

function testRunOnWrongCommitIsRed() {
  const evidence = greenEvidence({
    run: { ...SUCCESS_RUN, head_sha: '1111111111111111111111111111111111111111' },
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(evidence.problems.some((problem) => problem.includes('expected')));
  console.log('PASS: a successful run on another commit is not accepted as evidence');
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
  const selected = selectLatestCiRun([olderSuccess, newerFailure], FIX_SHA);
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
  assert.ok(calls.some((call) => call.url.includes('/contents/')));
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
  assert.strictEqual(evidence.targetSha, FIX_SHA);
  assert.strictEqual(evidence.run.id, RUN_ID);
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  console.log('PASS: without an explicit run id the latest CI run for the head is verified');
}

async function testCollectFailsWhenWorkflowMissingOnBase() {
  const { fetchImpl } = makeFetch(baseHandler({ workflow: null }));
  const evidence = await collectRolloutEvidence({
    owner: OWNER,
    repo: REPO,
    fixPr: FIX_PR,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(evidence.problems.some((problem) => problem.includes('is missing on main')));
  console.log('PASS: collecting evidence detects a workflow that is not on the base branch');
}

async function main() {
  testGreenRolloutIsReady();
  testUnmergedFixIsRed();
  testWrongBaseBranchIsRed();
  testMissingWorkflowOnBaseIsRed();
  testRunOnWrongCommitIsRed();
  testFailedOrIncompleteRunIsRed();
  testMissingOrFailedJobIsRed();
  testWrongWorkflowPathIsRed();
  testLatestRunWins();
  await testCollectsFromApiByRunId();
  await testCollectsLatestRunForHead();
  await testCollectFailsWhenWorkflowMissingOnBase();
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
