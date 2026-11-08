#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import {
  WORKFLOW_PATH,
  planRepair,
  repairPrChecks,
  summarizeChecks,
  waitForChecks,
} from '../lib.mjs';

const OWNER = 'sarnas-it';
const REPO = 'ebike-site';
const PR_NUMBER = 73;
const OLD_SHA = '525b0d4803e03858cdb0bfce9cb90837bdf0473e';
const NEW_SHA = '1111111111111111111111111111111111111111';
const HEAD_REF = 'feat/ebike-site-testeb-3';
const FIX_BRANCH = 'feat/ebike-site-testeb-67';

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

function prPayload() {
  return { head: { ref: HEAD_REF, sha: OLD_SHA } };
}

function baseHandler(overrides = {}) {
  return (call) => {
    const key = `${call.method} ${call.url}`;
    if (overrides[key]) {
      return overrides[key](call);
    }
    if (call.url.endsWith(`/pulls/${PR_NUMBER}`)) {
      return response(200, prPayload());
    }
    if (call.url.endsWith(`/repos/${OWNER}/${REPO}`)) {
      return response(200, { default_branch: 'main' });
    }
    if (call.url.includes('/contents/') && call.url.endsWith('?ref=main')) {
      return response(200, {
        content: Buffer.from('name: ci\non:\n  pull_request:\n').toString('base64'),
      });
    }
    if (call.url.includes('/contents/') && call.method === 'PUT') {
      return response(201, { commit: { sha: NEW_SHA } });
    }
    if (call.url.includes('/actions/workflows/ci.yml/dispatches')) {
      return response(204);
    }
    throw new Error(`unexpected request in test: ${key}`);
  };
}

function assertTargetsPrHead(calls) {
  const forbidden = calls.filter(
    (call) =>
      call.body?.branch === FIX_BRANCH ||
      call.body?.ref === FIX_BRANCH ||
      call.url.includes(`ref=${encodeURIComponent(FIX_BRANCH)}`),
  );
  assert.deepStrictEqual(
    forbidden,
    [],
    'the repair must never push to a separate task branch; it must target the PR head branch',
  );
}

async function testCommitWorkflowOnPrHead() {
  const { fetchImpl, calls } = makeFetch(baseHandler({
    [`GET https://api.github.com/repos/${OWNER}/${REPO}/contents/.github/workflows/ci.yml?ref=${encodeURIComponent(HEAD_REF)}`]:
      () => response(404, { message: 'Not Found' }),
  }));

  const result = await repairPrChecks({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    token: 'test-token',
    fetchImpl,
    workflowContent: 'name: ci\non:\n  pull_request:\n  workflow_dispatch:\n',
  });

  assert.strictEqual(result.action, 'commit-workflow');
  assert.strictEqual(result.status, 'committed');
  assert.strictEqual(result.newHeadSha, NEW_SHA);
  assert.strictEqual(result.branch, HEAD_REF);

  const put = calls.find((call) => call.method === 'PUT');
  assert.ok(put, 'the missing workflow must be committed');
  assert.ok(put.url.includes(`/contents/${WORKFLOW_PATH}`));
  assert.strictEqual(put.body.branch, HEAD_REF);
  assertTargetsPrHead(calls);
  console.log('PASS: missing workflow is committed onto the PR head branch');
}

async function testDispatchWhenWorkflowAlreadyOnHead() {
  const { fetchImpl, calls } = makeFetch(baseHandler({
    [`GET https://api.github.com/repos/${OWNER}/${REPO}/contents/.github/workflows/ci.yml?ref=${encodeURIComponent(HEAD_REF)}`]:
      () => response(200, { content: 'name: ci\n' }),
  }));

  const result = await repairPrChecks({
    owner: OWNER,
    repo: REPO,
    pullNumber: PR_NUMBER,
    token: 'test-token',
    fetchImpl,
  });

  assert.strictEqual(result.action, 'dispatch-workflow');
  assert.strictEqual(result.status, 'dispatched');
  assert.strictEqual(
    calls.filter((call) => call.method === 'PUT').length,
    0,
    'an existing workflow must not be rewritten',
  );
  const dispatch = calls.find((call) =>
    call.url.includes('/actions/workflows/ci.yml/dispatches'),
  );
  assert.ok(dispatch, 'workflow dispatch must be requested');
  assert.strictEqual(dispatch.body.ref, HEAD_REF);
  assertTargetsPrHead(calls);
  console.log('PASS: an existing workflow is dispatched on the PR head branch');
}

function testPlanRejectsNonHeadTargets() {
  const plan = planRepair({
    headRef: HEAD_REF,
    headSha: OLD_SHA,
    workflowExists: false,
  });
  assert.strictEqual(plan.branch, HEAD_REF);
  assert.strictEqual(plan.action, 'commit-workflow');
  assert.throws(
    () => planRepair({ headRef: '', headSha: OLD_SHA, workflowExists: false }),
    /head ref and sha are required/,
  );
  console.log('PASS: repair plan always targets the PR head branch');
}

function testNoChecksIsRedAndSuccessIsGreen() {
  const red = summarizeChecks([], []);
  assert.strictEqual(red.total, 0);
  assert.strictEqual(red.success, false);

  const green = summarizeChecks(
    [{ conclusion: 'success' }, { conclusion: 'success' }],
    [],
  );
  assert.strictEqual(green.success, true);

  const failed = summarizeChecks([{ conclusion: 'failure' }], []);
  assert.strictEqual(failed.success, false);
  assert.deepStrictEqual(failed.failed, ['failure']);
  console.log('PASS: no checks is red, successful checks are green');
}

async function testWaitForChecksPollsUntilGreen() {
  let checkRunCalls = 0;
  const { fetchImpl } = makeFetch((call) => {
    if (call.url.includes('/check-runs')) {
      checkRunCalls += 1;
      if (checkRunCalls === 1) {
        return response(200, { total_count: 0, check_runs: [] });
      }
      return response(200, {
        total_count: 1,
        check_runs: [{ conclusion: 'success' }],
      });
    }
    if (call.url.includes('/status')) {
      return response(200, { total_count: 0, statuses: [] });
    }
    throw new Error(`unexpected request: ${call.url}`);
  });

  let clock = 0;
  const result = await waitForChecks({
    owner: OWNER,
    repo: REPO,
    ref: NEW_SHA,
    token: 'test-token',
    fetchImpl,
    timeoutMs: 1000,
    intervalMs: 10,
    now: () => clock,
    sleep: async (ms) => {
      clock += ms;
    },
  });

  assert.strictEqual(result.outcome, 'success');
  assert.strictEqual(result.total, 1);
  console.log('PASS: checks are polled until they turn green');
}

async function main() {
  await testCommitWorkflowOnPrHead();
  await testDispatchWhenWorkflowAlreadyOnHead();
  testPlanRejectsNonHeadTargets();
  testNoChecksIsRedAndSuccessIsGreen();
  await testWaitForChecksPollsUntilGreen();
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
