#!/usr/bin/env node
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import {
  renderRolloutComment,
  selectLatestCiRun,
  summarizeRolloutEvidence,
} from '../rollout.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../../..');
const fixture = JSON.parse(
  readFileSync(
    path.join(here, 'fixtures/pr65-submitted-sha-recording.json'),
    'utf8',
  ),
);

const MARKER = '<!-- testeb-63-rollout -->';
const SUBMITTED_SHA = fixture.pullRequest.head.sha;
const SUBMITTED_RUN_ID = fixture.run.id;
const SUBMITTED_RUN_URL = fixture.run.html_url;
const LATER_SHA = '9f1c0d2e3a4b5c6d7e8f901234567890abcdef12';

const WORKFLOW = {
  type: 'file',
  encoding: 'base64',
  sha: 'a'.repeat(40),
  content: Buffer.from('name: ci\non:\n  pull_request:\n').toString('base64'),
};

function evidenceFor({ sha = SUBMITTED_SHA, pr = fixture.pullRequest, run = fixture.run, checkRuns = fixture.checkRuns } = {}) {
  return summarizeRolloutEvidence({
    pr,
    repository: fixture.repository,
    workflow: WORKFLOW,
    run,
    jobs: fixture.jobs,
    checkRuns,
    targetSha: sha,
    workflowRef: sha,
  });
}

function testRecordedSubmittedHeadIsGreen() {
  const evidence = evidenceFor();
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.strictEqual(evidence.targetSha, SUBMITTED_SHA);
  assert.strictEqual(evidence.run.id, SUBMITTED_RUN_ID);
  assert.strictEqual(evidence.run.headSha, SUBMITTED_SHA);
  assert.strictEqual(evidence.pullRequest.mergeable, true);
  assert.strictEqual(evidence.pullRequest.mergeableState, 'unstable');
  assert.deepStrictEqual(
    evidence.jobs.map((job) => `${job.name}=${job.conclusion}`).sort(),
    fixture.jobs.map((job) => `${job.name}=${job.conclusion}`).sort(),
  );
  console.log(
    'PASS: the submitted SHA has its own green ci run, standard check runs and a mergeable PR (criteria 2 and 3)',
  );
}

function testEvidenceFromSubmittedHeadCannotProveALaterHead() {
  const evidence = evidenceFor({ sha: LATER_SHA });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some(
      (problem) =>
        problem.includes(`CI run ${SUBMITTED_RUN_ID} is for ${SUBMITTED_SHA}`) &&
        problem.includes(`expected ${LATER_SHA}`),
    ),
    'the recorded run on the submitted SHA must not stand in for a later head',
  );
  assert.ok(
    evidence.problems.some(
      (problem) =>
        problem.includes(`is for ${SUBMITTED_SHA}`) &&
        problem.includes(`expected ${LATER_SHA}`),
    ),
    'standard check runs must be attached to the later head, not the recorded one',
  );
  assert.strictEqual(
    selectLatestCiRun([fixture.run], LATER_SHA, '.github/workflows/ci.yml'),
    null,
    'a run for another commit is not even a candidate for the later head',
  );
  console.log(
    'PASS: evidence recorded on the submitted SHA is rejected for any later submission',
  );
}

function testRunWithoutCommitProvenanceIsNotACandidate() {
  // Regression for the review finding: a run whose payload does not name the
  // commit must not be selected for the submitted SHA. Before the fix such a
  // run slipped through the candidate filter because the head_sha check was
  // skipped when the field was missing.
  const { head_sha: _headSha, ...anonymousRun } = fixture.run;
  assert.strictEqual(
    selectLatestCiRun([anonymousRun], SUBMITTED_SHA, '.github/workflows/ci.yml'),
    null,
    'a run without head_sha cannot be tied to the submitted SHA',
  );
  console.log(
    'PASS: a run without commit provenance is rejected as evidence for the submitted SHA',
  );
}

function testMergeableStateIsRequired() {
  const conflicting = evidenceFor({
    pr: { ...fixture.pullRequest, mergeable: false, mergeable_state: 'dirty' },
  });
  assert.strictEqual(conflicting.ready, false);
  assert.ok(
    conflicting.problems.some((problem) => problem.includes('is not mergeable')),
    'mergeable=false must make the roll-out red',
  );
  assert.ok(
    conflicting.problems.some((problem) => problem.includes('merge conflicts')),
    'a dirty mergeable_state must make the roll-out red',
  );

  const unknown = evidenceFor({
    pr: { ...fixture.pullRequest, mergeable: null, mergeable_state: 'unknown' },
  });
  assert.strictEqual(unknown.ready, false);
  assert.ok(
    unknown.problems.some((problem) => problem.includes('is not mergeable')),
    'an uncomputed mergeable value is not proof that the PR is mergeable',
  );
  console.log('PASS: criterion 3 requires mergeable=true without conflicts');
}

function testCommentPinsSubmittedShaAndItsOwnRun() {
  const body = renderRolloutComment(evidenceFor(), MARKER);
  assert.ok(body.includes(MARKER));
  assert.ok(body.includes(SUBMITTED_SHA), 'the comment must name the submitted SHA');
  assert.ok(body.includes(SUBMITTED_RUN_URL), 'the comment must link the submitted run');
  assert.ok(body.includes('mergeable=true'));
  assert.ok(body.includes('php-lint=success'));

  const laterRunId = 999000111;
  const laterRunUrl = `https://github.com/${fixture.repository.owner}/${fixture.repository.name}/actions/runs/${laterRunId}`;
  const laterRun = {
    ...fixture.run,
    id: laterRunId,
    html_url: laterRunUrl,
    head_sha: LATER_SHA,
    check_suite_id: fixture.run.check_suite_id + 1,
    updated_at: '2026-11-08T19:30:00Z',
  };
  const laterChecks = fixture.checkRuns.map((check) => ({
    ...check,
    id: `later-${check.id}`,
    head_sha: LATER_SHA,
    check_suite: { id: laterRun.check_suite_id },
    details_url: `${laterRunUrl}/job/${check.id}`,
  }));
  const laterBody = renderRolloutComment(
    evidenceFor({
      sha: LATER_SHA,
      pr: { ...fixture.pullRequest, head: { ...fixture.pullRequest.head, sha: LATER_SHA } },
      run: laterRun,
      checkRuns: laterChecks,
    }),
    MARKER,
  );
  assert.ok(laterBody.includes(LATER_SHA));
  assert.ok(laterBody.includes(laterRunUrl));
  assert.ok(
    !laterBody.includes(SUBMITTED_SHA) && !laterBody.includes(SUBMITTED_RUN_URL),
    'a new submission must replace the previous run evidence entirely',
  );
  console.log(
    'PASS: the evidence comment always pins the submitted SHA to its own run',
  );
}

function testDocumentationDoesNotPinAStaleSubmission() {
  // Regression for the review finding: the task comment used to cite a run on
  // the previously rejected head as if it were evidence for the current
  // submission. Committing any particular run/SHA is self-defeating because
  // the evidence commit changes the submitted SHA. The documentation must
  // describe the dynamic per-head workflow and leave the actual run link to
  // the submission result instead of pinning a stale one.
  const comment = readFileSync(
    path.join(repoRoot, 'docs/testeb-63-comment.md'),
    'utf8',
  );
  assert.ok(
    !comment.includes(SUBMITTED_SHA),
    'the task comment must not pin the previously submitted SHA as current evidence',
  );
  assert.ok(
    !comment.includes(SUBMITTED_RUN_URL),
    'the task comment must not pin a run from an older submission as evidence',
  );
  assert.ok(
    !comment.includes(String(SUBMITTED_RUN_ID)),
    'the task comment must not cite a stale run id',
  );
  assert.ok(
    comment.includes(`verify-ci-rollout`) && comment.includes('TARGET_SHA'),
    'the task comment must mention the workflow that regenerates evidence per head',
  );
  assert.ok(
    comment.includes(MARKER),
    'the task comment must point at the per-head evidence comment',
  );
  for (const relative of ['docs/ci.md', 'verify.log']) {
    const text = readFileSync(path.join(repoRoot, relative), 'utf8');
    assert.ok(
      !text.includes(SUBMITTED_SHA) && !text.includes(SUBMITTED_RUN_URL),
      `${relative} must not pin a run from a previous submission as evidence`,
    );
  }
  console.log(
    'PASS: documentation leaves the run link to the submission result instead of pinning a stale SHA',
  );
}

function main() {
  testRecordedSubmittedHeadIsGreen();
  testEvidenceFromSubmittedHeadCannotProveALaterHead();
  testRunWithoutCommitProvenanceIsNotACandidate();
  testMergeableStateIsRequired();
  testCommentPinsSubmittedShaAndItsOwnRun();
  testDocumentationDoesNotPinAStaleSubmission();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
