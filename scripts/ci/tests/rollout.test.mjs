#!/usr/bin/env node
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { WORKFLOW_PATH } from '../lib.mjs';
import {
  collectRolloutEvidence,
  publishRolloutEvidence,
  renderRolloutComment,
  selectLatestCiRun,
  summarizeRolloutEvidence,
} from '../rollout.mjs';

// This fixture is a verbatim recording of the GitHub API responses for the real
// ci run on the pull request #65 head that was rejected for stale evidence
// (05c8f37). It is regression-test input: the authoritative evidence for a
// submission is always the ci run on that submission's actual head, generated
// by the verify-ci-rollout workflow. Using the recording keeps the rollout
// tests on actual repository data instead of invented SHAs and run ids.
const here = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(
  readFileSync(
    path.join(here, 'fixtures/pr65-ci-recording.json'),
    'utf8',
  ),
);

const OWNER = fixture.repository.owner;
const REPO = fixture.repository.name;
const FIX_PR = fixture.pullRequest.number;
const HEAD_SHA = fixture.pullRequest.head.sha;
const STALE_SHA = '7052bf282923dfaa016b483789346b1e6f0193e8';
const RUN_ID = fixture.run.id;
const RUN_URL = fixture.run.html_url;
const CHECK_SUITE_ID = fixture.run.check_suite_id;
const BASE_BRANCH = fixture.repository.default_branch;
const MARKER = '<!-- testeb-63-rollout -->';

const GREEN_JOBS = fixture.jobs;
const STANDARD_CHECK_RUNS = fixture.checkRuns;

// The tracker merges PR #65 only after acceptance, so the normal state before
// acceptance is an open, mergeable pull request with green CI on its real head.
// The fixture is the real API response: the head SHA, run id, suite id and all
// job/check ids come from GitHub, not from this test file.
const OPEN_PR = fixture.pullRequest;

// The post-merge branch cannot be observed for PR #65 before acceptance. Use
// the real merge commit of an already merged fix (PR #73) so even this
// projection is grounded in a commit that exists in the repository.
const MERGE_SHA = '6a14f53349e6f7da967e1bd1aded48c12deb82be';
const MERGED_PR = {
  ...OPEN_PR,
  state: 'closed',
  merged: true,
  merged_at: '2026-11-08T15:04:35Z',
  merge_commit_sha: MERGE_SHA,
};

const REPOSITORY = fixture.repository;

const WORKFLOW_CONTENT = fixture.workflow.present
  ? {
      type: 'file',
      encoding: 'base64',
      sha: fixture.workflow.blob_sha,
      content: Buffer.from('name: ci\non:\n  pull_request:\n').toString('base64'),
    }
  : null;

const SUCCESS_RUN = fixture.run;

const MERGED_RUN_ID = 42492889252;
const MERGED_RUN_URL = `https://github.com/${OWNER}/${REPO}/actions/runs/${MERGED_RUN_ID}`;
const MERGED_CHECK_SUITE_ID = CHECK_SUITE_ID + 10;

const MERGED_RUN = {
  ...SUCCESS_RUN,
  id: MERGED_RUN_ID,
  html_url: MERGED_RUN_URL,
  event: 'push',
  head_sha: MERGE_SHA,
  head_branch: BASE_BRANCH,
  check_suite_id: MERGED_CHECK_SUITE_ID,
};

const MERGED_STANDARD_CHECK_RUNS = GREEN_JOBS.map((job) => ({
  id: `merged-${job.id}`,
  name: job.name,
  status: 'completed',
  conclusion: 'success',
  head_sha: MERGE_SHA,
  details_url: `${MERGED_RUN_URL}/job/${job.id}`,
  check_suite: { id: MERGED_CHECK_SUITE_ID },
  app: { slug: 'github-actions' },
}));

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
  checkRuns = STANDARD_CHECK_RUNS,
  mergedRun = MERGED_RUN,
  mergedRuns = [MERGED_RUN],
  mergedJobs = GREEN_JOBS,
  mergedCheckRuns = MERGED_STANDARD_CHECK_RUNS,
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
    if (url.includes(`/commits/${HEAD_SHA}/check-runs`) && method === 'GET') {
      return response(200, { total_count: checkRuns.length, check_runs: checkRuns });
    }
    if (url.includes(`/commits/${MERGE_SHA}/check-runs`) && method === 'GET') {
      return response(200, {
        total_count: mergedCheckRuns.length,
        check_runs: mergedCheckRuns,
      });
    }
    if (url.includes(`/actions/runs/${RUN_ID}/jobs`) && method === 'GET') {
      return response(200, { total_count: jobs.length, jobs });
    }
    if (url.includes(`/actions/runs/${MERGED_RUN_ID}/jobs`) && method === 'GET') {
      return response(200, { total_count: mergedJobs.length, jobs: mergedJobs });
    }
    if (url.includes(`/actions/runs/${RUN_ID}`) && method === 'GET') {
      return response(200, run);
    }
    if (url.includes(`/actions/runs/${MERGED_RUN_ID}`) && method === 'GET') {
      return response(200, mergedRun);
    }
    if (url.includes('/actions/runs?') && url.includes(`head_sha=${HEAD_SHA}`)) {
      return response(200, { total_count: runs.length, workflow_runs: runs });
    }
    if (url.includes('/actions/runs?') && url.includes(`head_sha=${MERGE_SHA}`)) {
      return response(200, {
        total_count: mergedRuns.length,
        workflow_runs: mergedRuns,
      });
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
    run: MERGED_RUN,
    checkRuns: MERGED_STANDARD_CHECK_RUNS,
    targetSha: MERGE_SHA,
    workflowRef: BASE_BRANCH,
  });
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.strictEqual(evidence.pullRequest.merged, true);
  assert.strictEqual(evidence.pullRequest.mergeCommitSha, MERGE_SHA);
  assert.strictEqual(evidence.targetSha, MERGE_SHA);
  const body = renderRolloutComment(evidence, MARKER);
  assert.ok(
    body.includes(`Merge commit: \`${MERGE_SHA}\``),
    'the evidence comment must record the merge commit once it exists',
  );
  console.log('PASS: the same fix stays green once the tracker has merged it');
}

function testMergedFixCannotBeProvenByOldHead() {
  const evidence = greenEvidence({
    pr: MERGED_PR,
    targetSha: HEAD_SHA,
    workflowRef: BASE_BRANCH,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) =>
      problem.includes('is not the merge commit'),
    ),
    'after a merge only the commit that landed on the base branch counts',
  );
  console.log('PASS: a merged fix cannot be proven by the old pull request head');
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

function testRecordedEvidenceCannotProveALaterSubmittedHead() {
  // This is the exact review finding: the recorded evidence was captured on
  // HEAD_SHA (the rejected commit), but a later submission has a new head. The
  // recorded run and its check runs must not be accepted for that new head.
  const submittedSha = '0123456789abcdef0123456789abcdef01234567';
  const evidence = greenEvidence({
    pr: { ...OPEN_PR, head: { ...OPEN_PR.head, sha: submittedSha } },
    targetSha: submittedSha,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some(
      (problem) =>
        problem.includes(`CI run ${RUN_ID} is for ${HEAD_SHA}`) &&
        problem.includes(`expected ${submittedSha}`),
    ),
    'a recorded run on the rejected commit must not prove a new submitted head',
  );
  assert.ok(
    evidence.problems.some(
      (problem) =>
        problem.includes(`is for ${HEAD_SHA}`) &&
        problem.includes(`expected ${submittedSha}`),
    ),
    'recorded check runs on the rejected commit must not prove a new submitted head',
  );
  console.log(
    'PASS: evidence recorded on the rejected commit cannot prove a later submitted head',
  );
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

function testStandardCheckRunsFromTheCiRunAreRequired() {
  const green = greenEvidence({ checkRuns: STANDARD_CHECK_RUNS });
  assert.strictEqual(green.ready, true, green.problems.join('; '));
  assert.deepStrictEqual(
    green.checkRuns.map((check) => check.name).sort(),
    GREEN_JOBS.map((job) => job.name).sort(),
  );

  const missing = greenEvidence({
    checkRuns: STANDARD_CHECK_RUNS.filter((check) => check.name !== 'php-tests'),
  });
  assert.strictEqual(missing.ready, false);
  assert.ok(
    missing.problems.some((problem) =>
      problem.includes('standard check run php-tests is missing'),
    ),
    'every ci job must expose its standard check run on the real head',
  );
  console.log('PASS: the gate requires the standard check runs of the verified ci run');
}

function testSyntheticCheckRunsAreRejected() {
  const synthetic = STANDARD_CHECK_RUNS.map((check) => ({
    ...check,
    details_url: `https://github.com/${OWNER}/${REPO}/actions/runs/999999999`,
    check_suite: { id: CHECK_SUITE_ID + 1 },
  }));
  const evidence = greenEvidence({ checkRuns: synthetic });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) =>
      problem.includes('synthetic Checks API evidence is not accepted'),
    ),
    'green check runs created outside the verified ci run must not count',
  );
  console.log('PASS: synthetic green check runs are not accepted as CI evidence');
}

function testCheckRunsFromAnotherSuiteLinkToRunAreRejected() {
  // A check run can point its details_url at the verified ci run while still
  // belonging to a different check suite (for example one created manually via
  // the Checks API). The check suite, not the link, proves provenance.
  const foreign = STANDARD_CHECK_RUNS.map((check) => ({
    ...check,
    check_suite: { id: CHECK_SUITE_ID + 1 },
  }));
  const evidence = greenEvidence({ checkRuns: foreign });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) =>
      problem.includes('synthetic Checks API evidence is not accepted'),
    ),
    'check runs from another check suite must not satisfy the gate even if they link to the run',
  );
  assert.strictEqual(
    evidence.run.checkSuiteId,
    CHECK_SUITE_ID,
    'the verified run must expose its check suite id for provenance checks',
  );
  console.log(
    'PASS: check runs from another check suite are rejected even when they link to the ci run',
  );
}

function testCheckRunsForAnotherCommitAreRejected() {
  // A check run attached to an older commit must never count for the submitted
  // head, even if it belongs to the same ci run/suite.
  const fromStaleCommit = STANDARD_CHECK_RUNS.map((check) => ({
    ...check,
    head_sha: STALE_SHA,
  }));
  const evidence = greenEvidence({ checkRuns: fromStaleCommit });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some(
      (problem) =>
        problem.includes(`is for ${STALE_SHA}`) &&
        problem.includes(`expected ${HEAD_SHA}`),
    ),
    'check runs must be attached to the exact submitted commit, not an older one',
  );
  console.log('PASS: standard check runs from another commit are rejected');
}

function testCheckRunsWithoutCommitProvenanceAreRejected() {
  // The review finding was that recorded evidence from another commit was used
  // for the submitted head. A check run that does not carry head_sha has no
  // provenance at all and must not be accepted for any concrete submission.
  const anonymous = STANDARD_CHECK_RUNS.map((check) => {
    const { head_sha: _headSha, ...rest } = check;
    return rest;
  });
  const evidence = greenEvidence({ checkRuns: anonymous });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some(
      (problem) =>
        problem.includes('is for unknown') &&
        problem.includes(`expected ${HEAD_SHA}`),
    ),
    'check runs without head_sha must not prove the submitted commit',
  );
  console.log('PASS: check runs without commit provenance are rejected');
}

function testFailedStandardCheckRunIsRed() {
  const failed = greenEvidence({
    checkRuns: STANDARD_CHECK_RUNS.map((check) =>
      check.name === 'php-lint' ? { ...check, conclusion: 'failure' } : check,
    ),
  });
  assert.strictEqual(failed.ready, false);
  assert.ok(
    failed.problems.some((problem) =>
      problem.includes('standard check run php-lint is failure'),
    ),
  );
  console.log('PASS: a failed standard check run makes the rollout red');
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

async function testCollectsStandardCheckRunsForHead() {
  const { fetchImpl, calls } = makeFetch(baseHandler());
  const evidence = await collectRolloutEvidence({
    owner: OWNER,
    repo: REPO,
    fixPr: FIX_PR,
    token: 'test-token',
    fetchImpl,
  });
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.ok(
    calls.some((call) => call.url.includes(`/commits/${HEAD_SHA}/check-runs`)),
    'the gate must read the standard check runs attached to the real head',
  );
  assert.strictEqual(evidence.checkRuns.length, GREEN_JOBS.length);
  console.log('PASS: standard check runs are read from the real head commit');
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
  assert.strictEqual(
    evidence.targetSha,
    MERGE_SHA,
    'after the merge the verified commit must be the merge commit, not the old PR head',
  );
  assert.strictEqual(evidence.run.id, MERGED_RUN_ID);
  assert.ok(
    calls.some((call) => call.url.includes('/contents/') && call.url.includes(`ref=${BASE_BRANCH}`)),
    'after the merge the workflow is read from the base branch',
  );
  assert.ok(
    calls.some((call) => call.url.includes(`/commits/${MERGE_SHA}/check-runs`)),
    'after the merge the standard check runs are read from the merge commit',
  );
  console.log('PASS: a merged fix is verified against the merge commit on the base branch');
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
  assert.ok(body.includes('Стандартные check runs ci на head'));
  assert.ok(body.includes('Merge commit: `none`'));
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

async function testPublishRolloutEvidenceRefusesSyntheticChecks() {
  const synthetic = STANDARD_CHECK_RUNS.map((check) => ({
    ...check,
    details_url: `https://github.com/${OWNER}/${REPO}/actions/runs/999999999`,
    check_suite: { id: CHECK_SUITE_ID + 1 },
  }));
  const { fetchImpl, calls } = makeFetch(baseHandler({ checkRuns: synthetic }));
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
    'synthetic green checks must never be published as proof',
  );
  console.log('PASS: synthetic check runs are not published as rollout evidence');
}

async function main() {
  testGreenOpenFixIsReadyForAutomerge();
  testMergedFixIsAlsoReady();
  testMergedFixCannotBeProvenByOldHead();
  testConflictingFixIsRed();
  testClosedUnmergedFixIsRed();
  testWrongBaseBranchIsRed();
  testMissingWorkflowOnHeadIsRed();
  testRunOnStaleCommitIsRed();
  testRecordedEvidenceCannotProveALaterSubmittedHead();
  testFailedOrIncompleteRunIsRed();
  testMissingOrFailedJobIsRed();
  testWrongWorkflowPathIsRed();
  testStandardCheckRunsFromTheCiRunAreRequired();
  testSyntheticCheckRunsAreRejected();
  testCheckRunsFromAnotherSuiteLinkToRunAreRejected();
  testCheckRunsForAnotherCommitAreRejected();
  testCheckRunsWithoutCommitProvenanceAreRejected();
  testFailedStandardCheckRunIsRed();
  testLatestRunWins();
  await testCollectsFromApiByRunId();
  await testCollectsStandardCheckRunsForHead();
  await testCollectsLatestRunForHead();
  await testCollectMergedFixReadsWorkflowFromBase();
  await testCollectFailsWhenWorkflowMissingOnHead();
  testRenderedRolloutCommentPinsRealHead();
  await testPublishRolloutEvidenceCreatesComment();
  await testPublishRolloutEvidenceUpdatesExistingComment();
  await testPublishRolloutEvidenceRefusesStaleRun();
  await testPublishRolloutEvidenceRefusesSyntheticChecks();
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
