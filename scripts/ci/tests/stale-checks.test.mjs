#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import {
  summarizeChecks,
  upsertCheckRun,
} from '../lib.mjs';
import { assessRecoveryNeed } from '../should-recover.mjs';

const OWNER = 'sarnas-it';
const REPO = 'ebike-site';
const SHA = '841470c19d9e62c1a6c4e4c402dff322c81fd747';

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

function testStalePendingIsSupersededByCompletedRun() {
  const checks = summarizeChecks([
    {
      id: 1,
      name: 'php-tests',
      status: 'in_progress',
      conclusion: null,
      started_at: '2026-11-08T16:00:00Z',
    },
    {
      id: 2,
      name: 'php-tests',
      status: 'completed',
      conclusion: 'success',
      started_at: '2026-11-08T16:30:27Z',
      completed_at: '2026-11-08T16:30:31Z',
    },
  ]);
  assert.strictEqual(checks.success, true);
  assert.strictEqual(checks.total, 2);
  assert.deepStrictEqual(checks.pending, []);
  console.log('PASS: an old in_progress duplicate does not block a completed success');
}

function testNewerPendingRunStillBlocks() {
  const checks = summarizeChecks([
    {
      id: 1,
      name: 'php-tests',
      status: 'completed',
      conclusion: 'success',
      completed_at: '2026-11-08T16:00:00Z',
    },
    {
      id: 2,
      name: 'php-tests',
      status: 'in_progress',
      conclusion: null,
      started_at: '2026-11-08T16:30:00Z',
    },
  ]);
  assert.strictEqual(checks.success, false);
  assert.strictEqual(checks.pending.length, 1);
  console.log('PASS: a newer pending duplicate still blocks the check');
}

async function testPendingDuplicatesAreCompleted() {
  const { fetchImpl, calls } = makeFetch((call) => {
    if (call.url.includes('/check-runs') && call.method === 'GET') {
      return response(200, {
        total_count: 2,
        check_runs: [
          { id: 11, name: 'php-tests', status: 'in_progress', conclusion: null },
          { id: 12, name: 'php-tests', status: 'queued', conclusion: null },
        ],
      });
    }
    if (/\/check-runs\/\d+$/.test(call.url) && call.method === 'PATCH') {
      return response(200, { id: Number(call.url.split('/').pop()), ...call.body });
    }
    throw new Error(`unexpected request: ${call.method} ${call.url}`);
  });

  const result = await upsertCheckRun({
    owner: OWNER,
    repo: REPO,
    headSha: SHA,
    name: 'php-tests',
    conclusion: 'success',
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(result.conclusion, 'success');
  const patches = calls.filter((call) => call.method === 'PATCH');
  assert.deepStrictEqual(
    patches.map((call) => call.url.split('/').pop()).sort(),
    ['11', '12'],
    'every pending duplicate must be completed, not just the first one',
  );
  assert.strictEqual(
    calls.filter((call) => call.method === 'POST').length,
    0,
    'no extra check run is needed when the pending duplicates can be patched',
  );
  console.log('PASS: recovery completes every pending duplicate check run');
}

function testRecoveryDecisionHandlesStaleDuplicates() {
  const stale = assessRecoveryNeed({
    total: 1,
    pending: [],
    failed: [],
    runs: [
      { name: 'php-tests', status: 'in_progress', conclusion: null },
      { name: 'php-tests', status: 'completed', conclusion: 'success' },
    ],
  });
  assert.strictEqual(stale.needed, true);

  const green = assessRecoveryNeed({
    total: 1,
    pending: [],
    failed: [],
    runs: [{ name: 'php-tests', status: 'completed', conclusion: 'success' }],
  });
  assert.strictEqual(green.needed, false);

  const realFailure = assessRecoveryNeed({
    total: 1,
    pending: [],
    failed: ['failure'],
    runs: [{ name: 'php-tests', status: 'completed', conclusion: 'failure' }],
  });
  assert.strictEqual(realFailure.needed, false);
  assert.strictEqual(realFailure.failed, true);
  console.log('PASS: recovery is requested for stale duplicates but not for green or failed checks');
}

async function main() {
  testStalePendingIsSupersededByCompletedRun();
  testNewerPendingRunStillBlocks();
  await testPendingDuplicatesAreCompleted();
  testRecoveryDecisionHandlesStaleDuplicates();
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
