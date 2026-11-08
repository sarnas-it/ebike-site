#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import { resolveTargetFromPullRequest } from '../resolve-pr-target.mjs';

const INCIDENT_SHA = '525b0d4803e03858cdb0bfce9cb90837bdf0473e';
const MOVED_SHA = '1111111111111111111111111111111111111111';

function testFollowsCurrentHeadWhenIncidentShaMoved() {
  const target = resolveTargetFromPullRequest(
    {
      number: 73,
      state: 'open',
      merged: false,
      head: { ref: 'feat/ebike-site-testeb-3', sha: MOVED_SHA },
    },
    { expectedSha: INCIDENT_SHA },
  );

  assert.strictEqual(target.sha, MOVED_SHA);
  assert.strictEqual(target.headRef, 'feat/ebike-site-testeb-3');
  assert.strictEqual(target.drifted, true, 'the moved head must be reported as drift');
  assert.strictEqual(target.expectedSha, INCIDENT_SHA);
  console.log('PASS: a moved pull request head is the recovery target, not the stale incident SHA');
}

function testUsesHeadWhenNoIncidentSha() {
  const target = resolveTargetFromPullRequest({
    number: 73,
    state: 'closed',
    merged: true,
    head: { ref: 'feat/ebike-site-testeb-3', sha: INCIDENT_SHA },
  });

  assert.strictEqual(target.sha, INCIDENT_SHA);
  assert.strictEqual(target.drifted, false);
  assert.strictEqual(target.merged, true);
  console.log('PASS: the current head is used even after the pull request was merged');
}

function testRejectsPullRequestWithoutHead() {
  assert.throws(
    () => resolveTargetFromPullRequest({ number: 73, head: {} }),
    /has no head sha/,
  );
  console.log('PASS: a pull request without a head SHA is rejected');
}

function testPr68FollowsCurrentHeadWhenIncidentShaMoved() {
  const incidentSha = '766eade4e49a86d4c14791da8d33e735948cfadb';
  const target = resolveTargetFromPullRequest(
    {
      number: 68,
      state: 'open',
      merged: false,
      head: { ref: 'feat/ebike-site-testeb-29', sha: MOVED_SHA },
    },
    { expectedSha: incidentSha },
  );

  assert.strictEqual(target.sha, MOVED_SHA);
  assert.strictEqual(target.headRef, 'feat/ebike-site-testeb-29');
  assert.strictEqual(target.drifted, true, 'the moved PR #68 head must be reported as drift');
  assert.strictEqual(target.expectedSha, incidentSha);
  console.log('PASS: TESTEB-65 recovery follows the current PR #68 head, not the stale incident SHA');
}

function main() {
  testFollowsCurrentHeadWhenIncidentShaMoved();
  testUsesHeadWhenNoIncidentSha();
  testRejectsPullRequestWithoutHead();
  testPr68FollowsCurrentHeadWhenIncidentShaMoved();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
