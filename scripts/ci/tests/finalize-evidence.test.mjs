#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import { EVIDENCE_START } from '../evidence.mjs';
import {
  finalizeRecoveryEvidence,
  findRecoveryRunForTrigger,
  isSuccessfulRecoveryRun,
  parseArgs,
  waitForRunCompletion,
} from '../wait-and-publish-evidence.mjs';

const OWNER = 'sarnas-it';
const REPO = 'ebike-site';
const PR_NUMBER = 71;
const TARGET_SHA = '19c4f0dbfb0b14eb7b9ef5fc6ecd6207769e5008';
const RUN_ID = 52525252525;
const RUN_URL = `https://github.com/sarnas-it/ebike-site/actions/runs/${RUN_ID}`;
const MERGE_SHA = '3333333333333333333333333333333333333333';
const MARKER = '<!-- testeb-66-recovery -->';

const GREEN_CHECKS = [
  { name: 'php-lint', conclusion: 'success' },
  { name: 'php-tests', conclusion: 'success' },
  { name: 'plugin-tests', conclusion: 'success' },
  { name: 'php-plugin-tests', conclusion: 'success' },
];

const SUCCESS_RUN = {
  id: RUN_ID,
  html_url: RUN_URL,
  path: '.github/workflows/recover-pr71.yml',
  event: 'push',
  status: 'completed',
  conclusion: 'success',
  head_sha: '0123456789abcdef0123456789abcdef01234567',
  head_branch: 'feat/ebike-site-testeb-66',
};

const MERGED_PR = {
  number: PR_NUMBER,
  state: 'closed',
  merged: true,
  merge_commit_sha: MERGE_SHA,
  merged_at: '2026-11-08T00:00:00Z',
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

async function testWaitPollsUntilCompleted() {
  const states = [
    { status: 'queued', conclusion: null },
    { status: 'in_progress', conclusion: null },
    { status: 'completed', conclusion: 'success' },
  ];
  let index = 0;
  let clock = 0;
  const run = await waitForRunCompletion({
    runId: RUN_ID,
    getRun: async () => states[Math.min(index++, states.length - 1)],
    timeoutMs: 1000,
    intervalMs: 10,
    now: () => clock,
    sleep: async (ms) => {
      clock += ms;
    },
  });
  assert.strictEqual(run.status, 'completed');
  assert.strictEqual(run.conclusion, 'success');
  console.log('PASS: the finalizer waits until the recovery run is completed');
}

async function testWaitTimesOutWithoutFinishedRun() {
  let clock = 0;
  const run = await waitForRunCompletion({
    runId: RUN_ID,
    getRun: async () => ({ status: 'in_progress', conclusion: null }),
    timeoutMs: 30,
    intervalMs: 10,
    now: () => clock,
    sleep: async (ms) => {
      clock += ms;
    },
  });
  assert.strictEqual(run.status, 'in_progress');
  assert.strictEqual(isSuccessfulRecoveryRun(run), false);
  console.log('PASS: an unfinished run is not treated as successful');
}

async function testFailedRunIsNotPublished() {
  const { fetchImpl, calls } = makeFetch((call) => {
    if (call.url.includes(`/actions/runs/${RUN_ID}`)) {
      return response(200, { ...SUCCESS_RUN, conclusion: 'failure' });
    }
    throw new Error(`unexpected request: ${call.method} ${call.url}`);
  });

  const result = await finalizeRecoveryEvidence({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    runId: RUN_ID,
    template: `${MARKER}\nпричина`,
    marker: MARKER,
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(result.published, false);
  assert.ok(result.reason.includes('failure'));
  assert.strictEqual(
    calls.filter((call) => call.method === 'POST' || call.method === 'PATCH').length,
    0,
    'a failed recovery run must never be published as final evidence',
  );
  console.log('PASS: a failed recovery run does not publish evidence');
}

async function testCompletedRunPublishesFinalEvidence() {
  const stale = [
    MARKER,
    'Причина',
    '',
    EVIDENCE_START,
    '- Recovery workflow run: [#1](https://example.invalid/old) — status=in_progress',
    '<!-- testeb-66-evidence:end -->',
  ].join('\n');

  const { fetchImpl, calls } = makeFetch((call) => {
    const { url, method } = call;
    if (url.includes(`/actions/runs/${RUN_ID}`) && method === 'GET') {
      return response(200, SUCCESS_RUN);
    }
    if (url.endsWith(`/pulls/${PR_NUMBER}`) && method === 'GET') {
      return response(200, MERGED_PR);
    }
    if (url.includes(`/commits/${TARGET_SHA}/check-runs`) && method === 'GET') {
      return response(200, { total_count: GREEN_CHECKS.length, check_runs: GREEN_CHECKS });
    }
    if (url.includes(`/commits/${TARGET_SHA}/status`) && method === 'GET') {
      return response(200, { state: 'success', total_count: 1, statuses: [] });
    }
    if (url.includes('/issues/') && url.includes('/comments') && method === 'GET') {
      return response(200, [{ id: 1, body: stale }]);
    }
    if (url.includes('/issues/comments/1') && method === 'PATCH') {
      return response(200, { id: 1, ...call.body });
    }
    if (url.includes('/issues/') && url.includes('/comments') && method === 'POST') {
      return response(201, { id: 701, ...call.body });
    }
    throw new Error(`unexpected request: ${method} ${url}`);
  });

  const result = await finalizeRecoveryEvidence({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    runId: RUN_ID,
    template: stale,
    marker: MARKER,
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(result.published, true);
  assert.strictEqual(result.evidence.ready, true);
  assert.strictEqual(result.comment.updated, true);
  const patch = calls.find((call) => call.method === 'PATCH');
  assert.ok(patch, 'the evidence comment must be updated in place');
  assert.ok(patch.body.body.includes(RUN_URL));
  assert.ok(patch.body.body.includes('status=completed'));
  assert.ok(patch.body.body.includes('conclusion=success'));
  assert.ok(patch.body.body.includes(MERGE_SHA));
  assert.ok(!patch.body.body.includes('example.invalid/old'));
  assert.strictEqual(
    calls.filter((call) => call.method === 'POST' && call.url.includes('/comments')).length,
    0,
    'the finalizer must not create a duplicate comment',
  );
  console.log('PASS: completed run evidence replaces the stale in-progress block');
}

async function testDiscoversRecoveryRunForTriggerSha() {
  const finalizerRun = {
    id: 777,
    path: '.github/workflows/finalize-pr71-evidence.yml',
    status: 'in_progress',
    conclusion: null,
    created_at: '2026-11-08T00:00:01Z',
  };
  const recoveryRun = {
    id: 778,
    path: '.github/workflows/recover-pr71.yml',
    status: 'in_progress',
    conclusion: null,
    created_at: '2026-11-08T00:00:00Z',
  };
  const { fetchImpl } = makeFetch((call) => {
    if (call.url.includes('/actions/runs?') && call.method === 'GET') {
      return response(200, { workflow_runs: [finalizerRun, recoveryRun] });
    }
    throw new Error(`unexpected request: ${call.method} ${call.url}`);
  });

  const run = await findRecoveryRunForTrigger({
    owner: OWNER,
    repo: REPO,
    triggerSha: TARGET_SHA,
    token: 'test-token',
    fetchImpl,
    timeoutMs: 0,
  });

  assert.strictEqual(run.id, recoveryRun.id);
  assert.strictEqual(
    run.path,
    '.github/workflows/recover-pr71.yml',
    'the finalizer must never mistake its own run for the recovery run',
  );
  console.log('PASS: the finalizer discovers the matching recovery run by trigger sha');
}

async function testMissingRecoveryRunIsNotInvented() {
  const { fetchImpl } = makeFetch((call) => {
    if (call.url.includes('/actions/runs?') && call.method === 'GET') {
      return response(200, {
        workflow_runs: [
          {
            id: 779,
            path: '.github/workflows/finalize-pr71-evidence.yml',
            status: 'completed',
            conclusion: 'success',
          },
        ],
      });
    }
    throw new Error(`unexpected request: ${call.method} ${call.url}`);
  });

  const run = await findRecoveryRunForTrigger({
    owner: OWNER,
    repo: REPO,
    triggerSha: TARGET_SHA,
    token: 'test-token',
    fetchImpl,
    timeoutMs: 0,
  });

  assert.strictEqual(run, null);
  console.log('PASS: a finalizer run without a recovery run is red, not invented');
}

function testTriggerShaIgnoresFinalizerRunIdEnv() {
  const previous = process.env.GITHUB_RUN_ID;
  process.env.GITHUB_RUN_ID = '777';
  try {
    const options = parseArgs([
      '--pr',
      String(PR_NUMBER),
      '--sha',
      TARGET_SHA,
      '--trigger-sha',
      TARGET_SHA,
    ]);
    assert.strictEqual(
      options.runId,
      null,
      'the finalizer must not default to its own GITHUB_RUN_ID when --trigger-sha is used',
    );
    assert.strictEqual(options.triggerSha, TARGET_SHA);

    const explicit = parseArgs([
      '--pr',
      String(PR_NUMBER),
      '--sha',
      TARGET_SHA,
      '--run-id',
      String(RUN_ID),
    ]);
    assert.strictEqual(explicit.runId, String(RUN_ID));
  } finally {
    if (previous === undefined) {
      delete process.env.GITHUB_RUN_ID;
    } else {
      process.env.GITHUB_RUN_ID = previous;
    }
  }
  console.log('PASS: --trigger-sha discovery ignores the finalizer GITHUB_RUN_ID');
}

async function testFinalizerFollowsTriggerShaInsteadOfItself() {
  const finalizerRun = {
    id: 9001,
    path: '.github/workflows/finalize-pr71-evidence.yml',
    event: 'push',
    status: 'in_progress',
    conclusion: null,
    head_sha: TARGET_SHA,
    created_at: '2026-11-08T00:00:01Z',
  };
  const recoveryRun = {
    ...SUCCESS_RUN,
    created_at: '2026-11-08T00:00:00Z',
  };

  const { fetchImpl, calls } = makeFetch((call) => {
    const { url, method } = call;
    if (url.includes('/actions/runs?') && method === 'GET') {
      return response(200, { workflow_runs: [finalizerRun, recoveryRun] });
    }
    if (url.includes(`/actions/runs/${RUN_ID}`) && method === 'GET') {
      return response(200, SUCCESS_RUN);
    }
    if (url.includes('/actions/runs/9001')) {
      throw new Error('the finalizer must not wait for its own run');
    }
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
      return response(200, { state: 'success', total_count: 1, statuses: [] });
    }
    if (url.includes('/issues/') && url.includes('/comments') && method === 'GET') {
      return response(200, []);
    }
    if (url.includes('/issues/') && url.includes('/comments') && method === 'POST') {
      return response(201, { id: 702, ...call.body });
    }
    throw new Error(`unexpected request: ${method} ${url}`);
  });

  const result = await finalizeRecoveryEvidence({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    targetSha: TARGET_SHA,
    runId: null,
    triggerSha: TARGET_SHA,
    template: `${MARKER}\nпричина`,
    marker: MARKER,
    token: 'test-token',
    fetchImpl,
    timeoutMs: 1000,
    intervalMs: 10,
  });

  assert.strictEqual(result.published, true);
  assert.strictEqual(result.run.id, RUN_ID);
  assert.strictEqual(result.comment.created, true);
  assert.ok(
    calls.some((call) => call.url.includes(`/actions/runs/${RUN_ID}`)),
    'the finalizer must wait for the discovered recovery run',
  );
  assert.ok(
    !calls.some((call) => call.url.includes('/actions/runs/9001')),
    'the finalizer must never wait for its own run',
  );
  const posted = calls.find(
    (call) => call.method === 'POST' && call.url.includes('/comments'),
  );
  assert.ok(posted.body.body.includes(RUN_URL));
  assert.ok(posted.body.body.includes('php-plugin-tests=success'));
  console.log('PASS: the finalizer follows the trigger SHA and publishes the recovery evidence');
}

async function main() {
  await testWaitPollsUntilCompleted();
  await testWaitTimesOutWithoutFinishedRun();
  await testFailedRunIsNotPublished();
  await testCompletedRunPublishesFinalEvidence();
  await testDiscoversRecoveryRunForTriggerSha();
  await testMissingRecoveryRunIsNotInvented();
  testTriggerShaIgnoresFinalizerRunIdEnv();
  await testFinalizerFollowsTriggerShaInsteadOfItself();
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
