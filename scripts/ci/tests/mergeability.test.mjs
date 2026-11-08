#!/usr/bin/env node
import assert from 'node:assert';
import process from 'node:process';
import {
  DEFAULT_BASE_BRANCH,
  DEFAULT_FIX_PR,
  assessMergeability,
  renderMergeability,
} from '../mergeability.mjs';

const OPEN_MERGEABLE_PR = {
  number: DEFAULT_FIX_PR,
  state: 'open',
  merged: false,
  mergeable: true,
  mergeable_state: 'unstable',
  base: { ref: DEFAULT_BASE_BRANCH },
  merge_commit_sha: 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
};

const MERGED_PR = {
  ...OPEN_MERGEABLE_PR,
  state: 'closed',
  merged: true,
  merge_commit_sha: '6a14f53349e6f7da967e1bd1aded48c12deb82be',
};

function testOpenMergeableFixIsReady() {
  const evidence = assessMergeability(OPEN_MERGEABLE_PR);
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.strictEqual(evidence.prNumber, DEFAULT_FIX_PR);
  assert.strictEqual(evidence.mergeable, true);
  assert.match(renderMergeability(evidence), /mergeable:\s+green/);
  console.log('PASS: an open PR #65 with mergeable=true and no conflicts satisfies criterion 3');
}

function testMergedFixIsAlsoReady() {
  const evidence = assessMergeability(MERGED_PR);
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.strictEqual(evidence.merged, true);
  assert.strictEqual(
    evidence.mergeCommitSha,
    '6a14f53349e6f7da967e1bd1aded48c12deb82be',
  );
  console.log('PASS: a merged fix stays green once it has a merge commit');
}

function testConflictsAreRed() {
  const evidence = assessMergeability({
    ...OPEN_MERGEABLE_PR,
    mergeable: false,
    mergeable_state: 'dirty',
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) => problem.includes('is not mergeable')),
    'mergeable=false must make criterion 3 red',
  );
  assert.ok(
    evidence.problems.some((problem) => problem.includes('merge conflicts')),
    'a dirty mergeable_state must name the conflicts',
  );
  console.log('PASS: conflicts with the base branch make criterion 3 red');
}

function testUnknownMergeabilityIsRed() {
  const evidence = assessMergeability({
    ...OPEN_MERGEABLE_PR,
    mergeable: null,
    mergeable_state: 'unknown',
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some((problem) => problem.includes('is not mergeable')),
    'an uncomputed mergeable value is not proof of mergeability',
  );
  console.log('PASS: unknown mergeability is red instead of being accepted');
}

function testWrongBaseAndClosedUnmergedAreRed() {
  const wrongBase = assessMergeability({
    ...OPEN_MERGEABLE_PR,
    base: { ref: 'develop' },
  });
  assert.strictEqual(wrongBase.ready, false);
  assert.ok(wrongBase.problems.some((problem) => problem.includes('expected main')));

  const closed = assessMergeability({
    ...OPEN_MERGEABLE_PR,
    state: 'closed',
    merged: false,
  });
  assert.strictEqual(closed.ready, false);
  assert.ok(
    closed.problems.some((problem) => problem.includes('expected open or merged')),
  );
  console.log('PASS: a wrong base branch or a closed, unmerged PR is red');
}

function testMissingPrAndMissingMergeCommitAreRed() {
  const missing = assessMergeability(null);
  assert.strictEqual(missing.ready, false);
  assert.ok(
    missing.problems.some((problem) =>
      problem.includes(`pull request #${DEFAULT_FIX_PR} was not found`),
    ),
  );

  const mergedWithoutCommit = assessMergeability({
    ...MERGED_PR,
    merge_commit_sha: null,
  });
  assert.strictEqual(mergedWithoutCommit.ready, false);
  assert.ok(
    mergedWithoutCommit.problems.some((problem) =>
      problem.includes('has no merge commit sha'),
    ),
  );
  console.log('PASS: a missing PR or merged PR without a merge commit is red');
}

function main() {
  testOpenMergeableFixIsReady();
  testMergedFixIsAlsoReady();
  testConflictsAreRed();
  testUnknownMergeabilityIsRed();
  testWrongBaseAndClosedUnmergedAreRed();
  testMissingPrAndMissingMergeCommitAreRed();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
