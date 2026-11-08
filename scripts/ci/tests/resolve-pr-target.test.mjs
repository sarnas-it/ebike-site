#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import { resolveTargetFromPullRequest } from '../resolve-pr-target.mjs';

const INCIDENT_SHA = '19c4f0dbfb0b14eb7b9ef5fc6ecd6207769e5008';
const MOVED_SHA = '1111111111111111111111111111111111111111';

function testFollowsCurrentHeadWhenIncidentShaMoved() {
  const target = resolveTargetFromPullRequest(
    {
      number: 71,
      state: 'open',
      merged: false,
      head: { ref: 'feat/ebike-site-testeb-2', sha: MOVED_SHA },
    },
    { expectedSha: INCIDENT_SHA },
  );

  assert.strictEqual(target.sha, MOVED_SHA);
  assert.strictEqual(target.headRef, 'feat/ebike-site-testeb-2');
  assert.strictEqual(target.drifted, true, 'the moved head must be reported as drift');
  assert.strictEqual(target.expectedSha, INCIDENT_SHA);
  console.log('PASS: a moved pull request head is the recovery target, not the stale incident SHA');
}

function testUsesHeadWhenNoIncidentSha() {
  const target = resolveTargetFromPullRequest({
    number: 71,
    state: 'closed',
    merged: true,
    head: { ref: 'feat/ebike-site-testeb-2', sha: INCIDENT_SHA },
  });

  assert.strictEqual(target.sha, INCIDENT_SHA);
  assert.strictEqual(target.drifted, false);
  assert.strictEqual(target.merged, true);
  console.log('PASS: the current head is used even after the pull request was merged');
}

function testRejectsPullRequestWithoutHead() {
  assert.throws(
    () => resolveTargetFromPullRequest({ number: 71, head: {} }),
    /has no head sha/,
  );
  console.log('PASS: a pull request without a head SHA is rejected');
}

function main() {
  testFollowsCurrentHeadWhenIncidentShaMoved();
  testUsesHeadWhenNoIncidentSha();
  testRejectsPullRequestWithoutHead();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
