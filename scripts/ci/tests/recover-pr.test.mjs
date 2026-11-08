#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import {
  allChecksPassed,
  publishCheckResults,
  recoverPullRequest,
  summarizeRecovery,
} from '../lib.mjs';

const OWNER = 'sarnas-it';
const REPO = 'ebike-site';
const PR_NUMBER = 73;
const TARGET_SHA = '525b0d4803e03858cdb0bfce9cb90837bdf0473e';
const MOVED_SHA = '1111111111111111111111111111111111111111';
const MERGE_SHA = '2222222222222222222222222222222222222222';
const API = 'https://api.github.com/repos';
const MARKER = '<!-- testeb-67-recovery -->';

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
  pr = {},
  comments = [],
  existingChecks = [],
  mergeStatus = 200,
  patchStatus = 200,
} = {}) {
  return (call) => {
    const { url, method } = call;
    if (url.includes('/check-runs') && method === 'GET') {
      return response(200, { total_count: existingChecks.length, check_runs: existingChecks });
    }
    if (url.endsWith('/check-runs') && method === 'POST') {
      return response(201, { id: 501, ...call.body });
    }
    if (/\/check-runs\/\d+$/.test(url) && method === 'PATCH') {
      if (patchStatus !== 200) {
        return response(patchStatus, {
          message: 'Resource not accessible by integration',
        });
      }
      return response(200, { id: Number(url.split('/').pop()), ...call.body });
    }
    if (url.includes('/statuses/') && method === 'POST') {
      return response(201, { id: 1, ...call.body });
    }
    if (url.includes('/issues/') && url.includes('/comments') && method === 'GET') {
      return response(200, comments);
    }
    if (url.includes('/issues/') && url.includes('/comments') && method === 'POST') {
      return response(201, { id: 701, ...call.body });
    }
    if (url.endsWith(`/pulls/${PR_NUMBER}`) && method === 'GET') {
      return response(200, {
        number: PR_NUMBER,
        state: 'open',
        merged: false,
        merge_commit_sha: null,
        head: { sha: TARGET_SHA },
        ...pr,
      });
    }
    if (url.endsWith(`/pulls/${PR_NUMBER}/merge`) && method === 'PUT') {
      if (mergeStatus === 200) {
        return response(200, { merged: true, sha: MERGE_SHA });
      }
      return response(mergeStatus, { message: 'merge rejected' });
    }
    throw new Error(`unexpected request: ${method} ${url}`);
  };
}

function mergeCalls(calls) {
  return calls.filter((call) => call.url.endsWith(`/pulls/${PR_NUMBER}/merge`));
}

async function testPublishesChecksOnTargetShaAndMerges() {
  const { fetchImpl, calls } = makeFetch(baseHandler());

  const result = await recoverPullRequest({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    results: {
      'php-lint': 'success',
      'php-tests': 'success',
      'plugin-tests': 'success',
    },
    detailsUrl: 'https://github.com/sarnas-it/ebike-site/actions/runs/1',
    summary: 'recovered',
    commentBody: `${MARKER}\nbody`,
    commentMarker: MARKER,
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(result.merged, true);
  assert.strictEqual(result.mergeMethod, 'squash');
  assert.strictEqual(result.mergeCommitSha, MERGE_SHA);

  const created = calls.filter((call) => call.method === 'POST' && call.url.endsWith('/check-runs'));
  assert.deepStrictEqual(
    created.map((call) => call.body.name).sort(),
    ['php-lint', 'php-tests', 'plugin-tests'],
  );
  assert.ok(
    created.every((call) => call.body.head_sha === TARGET_SHA),
    'recursive checks must be attached to the exact commit under test',
  );
  assert.ok(
    created.every((call) => call.body.status === 'completed' && call.body.conclusion === 'success'),
    'recovered checks must be completed and successful',
  );

  const status = calls.find((call) => call.url.includes(`/statuses/${TARGET_SHA}`));
  assert.ok(status, 'a commit status must be published on the target commit');
  assert.strictEqual(status.body.state, 'success');

  assert.strictEqual(mergeCalls(calls).length, 1, 'the pull request must be merged once');
  assert.strictEqual(mergeCalls(calls)[0].body.merge_method, 'squash');

  const comment = calls.find(
    (call) => call.method === 'POST' && call.url.includes('/issues/') && call.url.includes('/comments'),
  );
  assert.ok(comment, 'the recovery needs a confirmed comment on the pull request');
  assert.ok(comment.body.body.includes(MARKER));
  console.log('PASS: checks are published on the exact target commit and the PR is merged');
}

async function testMergesResolvedCurrentHeadAfterIncidentShaMoved() {
  const { fetchImpl, calls } = makeFetch(
    baseHandler({ pr: { head: { sha: MOVED_SHA } } }),
  );

  const result = await recoverPullRequest({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: MOVED_SHA,
    results: { 'php-lint': 'success', 'php-tests': 'success' },
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(result.merged, true);
  assert.strictEqual(mergeCalls(calls).length, 1);
  const created = calls.filter((call) => call.method === 'POST' && call.url.endsWith('/check-runs'));
  assert.ok(
    created.every((call) => call.body.head_sha === MOVED_SHA),
    'a moved head is checked and merged as the current target, not skipped as stale',
  );
  assert.ok(
    created.every((call) => call.body.head_sha !== TARGET_SHA),
    'the stale incident SHA must not receive the new checks',
  );
  console.log('PASS: recovery checks and merges the resolved current head after head drift');
}

async function testRefusesMergeWhenHeadMoved() {
  const { fetchImpl, calls } = makeFetch(
    baseHandler({ pr: { head: { sha: MOVED_SHA } } }),
  );

  const result = await recoverPullRequest({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    results: { 'php-lint': 'success' },
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(result.merged, false);
  assert.strictEqual(result.skipped, 'head-moved');
  assert.strictEqual(mergeCalls(calls).length, 0, 'a moved head must never be merged blindly');
  console.log('PASS: recovery refuses to merge when the PR head moved');
}

async function testFailedCheckPreventsMerge() {
  const { fetchImpl, calls } = makeFetch(baseHandler());

  const result = await recoverPullRequest({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    results: { 'php-lint': 'success', 'php-tests': 'failure' },
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(result.merged, false);
  assert.strictEqual(result.skipped, 'checks-failed');

  const created = calls.filter((call) => call.method === 'POST' && call.url.endsWith('/check-runs'));
  const failing = created.find((call) => call.body.name === 'php-tests');
  assert.ok(failing, 'the failing check must be published');
  assert.strictEqual(failing.body.conclusion, 'failure');

  const status = calls.find((call) => call.url.includes(`/statuses/${TARGET_SHA}`));
  assert.strictEqual(status.body.state, 'failure');
  assert.strictEqual(mergeCalls(calls).length, 0, 'failing checks must block the merge');
  console.log('PASS: failed checks are published and block the merge');
}

async function testSkipsAlreadyMergedPr() {
  const { fetchImpl, calls } = makeFetch(
    baseHandler({ pr: { merged: true, merge_commit_sha: MERGE_SHA } }),
  );

  const result = await recoverPullRequest({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    results: { 'php-lint': 'success' },
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(result.merged, true);
  assert.strictEqual(result.skipped, 'already-merged');
  assert.strictEqual(mergeCalls(calls).length, 0, 'an already merged PR must not be merged again');
  console.log('PASS: an already merged PR is not merged twice');
}

async function testCommentIsIdempotent() {
  const { fetchImpl, calls } = makeFetch(
    baseHandler({
      comments: [{ id: 1, body: `${MARKER}\nold recovery comment` }],
    }),
  );

  await recoverPullRequest({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    results: { 'php-lint': 'success' },
    commentBody: `${MARKER}\nnew text`,
    commentMarker: MARKER,
    token: 'test-token',
    fetchImpl,
  });

  const posted = calls.filter(
    (call) => call.method === 'POST' && call.url.includes('/issues/') && call.url.includes('/comments'),
  );
  assert.strictEqual(posted.length, 0, 'the recovery comment must be posted only once');
  console.log('PASS: the recovery comment is idempotent');
}

async function testExistingCheckRunIsUpdated() {
  const { fetchImpl, calls } = makeFetch(
    baseHandler({ existingChecks: [{ id: 99, name: 'php-lint' }] }),
  );

  const published = await publishCheckResults({
    owner: OWNER,
    repo: REPO,
    headSha: TARGET_SHA,
    results: { 'php-lint': 'success' },
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(published.success, true);
  const patches = calls.filter((call) => call.method === 'PATCH');
  assert.strictEqual(patches.length, 1, 'an existing check run must be updated, not duplicated');
  assert.ok(patches[0].url.endsWith('/check-runs/99'));
  const posts = calls.filter((call) => call.method === 'POST' && call.url.endsWith('/check-runs'));
  assert.strictEqual(posts.length, 0);
  console.log('PASS: existing check runs are updated in place');
}

async function testUnpatchableExistingChecksStillMerge() {
  const existingChecks = [
    {
      id: 31,
      name: 'php-lint',
      status: 'completed',
      conclusion: 'success',
      completed_at: '2026-11-08T16:30:34Z',
    },
    {
      id: 32,
      name: 'php-tests',
      status: 'in_progress',
      conclusion: 'success',
      completed_at: '2026-11-08T16:30:32Z',
    },
  ];
  const { fetchImpl, calls } = makeFetch(
    baseHandler({ existingChecks, patchStatus: 403 }),
  );

  const result = await recoverPullRequest({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    results: {
      'php-lint': 'success',
      'php-tests': 'success',
      'plugin-tests': 'success',
    },
    detailsUrl: 'https://github.com/sarnas-it/ebike-site/actions/runs/2',
    summary: 'recovered',
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(
    result.merged,
    true,
    'recovery must still merge when the old check runs belong to another run',
  );
  assert.strictEqual(mergeCalls(calls).length, 1);

  const created = calls.filter(
    (call) => call.method === 'POST' && call.url.endsWith('/check-runs'),
  );
  assert.strictEqual(
    created.length,
    3,
    'a fresh completed check is published for every check when patching is forbidden',
  );
  assert.ok(
    created.every(
      (call) =>
        call.body.head_sha === TARGET_SHA &&
        call.body.status === 'completed' &&
        call.body.conclusion === 'success',
    ),
  );
  const status = calls.find((call) =>
    call.url.includes(`/statuses/${TARGET_SHA}`),
  );
  assert.strictEqual(status.body.state, 'success');
  console.log('PASS: recovery republishes unpatchable checks and completes the deferred merge');
}

function testAllChecksPassed() {
  assert.strictEqual(allChecksPassed({ 'php-lint': 'success' }), true);
  assert.strictEqual(allChecksPassed({ 'php-lint': 'success', 'php-tests': 'skipped' }), true);
  assert.strictEqual(allChecksPassed({ 'php-lint': 'success', 'php-tests': 'failure' }), false);
  assert.strictEqual(allChecksPassed({}), false);
  console.log('PASS: success/skipped is green and failure/empty is red');
}

function testSummarizeRecovery() {
  const recovered = summarizeRecovery({
    pr: {
      merged: true,
      merge_commit_sha: MERGE_SHA,
      head: { sha: TARGET_SHA },
    },
    checkRuns: [{ name: 'php-lint', conclusion: 'success' }],
    expectedSha: TARGET_SHA,
  });
  assert.strictEqual(recovered.merged, true);
  assert.strictEqual(recovered.headMatches, true);
  assert.strictEqual(recovered.allGreen, true);

  const notRecovered = summarizeRecovery({
    pr: {
      merged: false,
      merge_commit_sha: null,
      head: { sha: TARGET_SHA },
    },
    checkRuns: [],
    expectedSha: TARGET_SHA,
  });
  assert.strictEqual(notRecovered.merged, false);
  assert.strictEqual(notRecovered.allGreen, false);
  console.log('PASS: recovery verification is red until the PR is merged with green checks');
}

async function main() {
  await testPublishesChecksOnTargetShaAndMerges();
  await testMergesResolvedCurrentHeadAfterIncidentShaMoved();
  await testRefusesMergeWhenHeadMoved();
  await testFailedCheckPreventsMerge();
  await testSkipsAlreadyMergedPr();
  await testCommentIsIdempotent();
  await testExistingCheckRunIsUpdated();
  await testUnpatchableExistingChecksStillMerge();
  testAllChecksPassed();
  testSummarizeRecovery();
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
