#!/usr/bin/env node
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { EXPECTED_CI_JOBS, summarizeRolloutEvidence } from '../rollout.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../../..');
const fixture = JSON.parse(
  readFileSync(
    path.join(here, 'fixtures/pr65-ci-recording.json'),
    'utf8',
  ),
);

function testFixtureIsRecordedFromGithub() {
  const { pullRequest: pr, run, repository, workflow } = fixture;
  assert.match(
    fixture.captured_at,
    /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/,
    'the fixture must record when it was captured from the GitHub API',
  );
  assert.match(pr.head.sha, /^[0-9a-f]{40}$/);
  assert.strictEqual(pr.base.ref, 'main');
  assert.strictEqual(repository.default_branch, 'main');
  assert.strictEqual(pr.number, 65);
  assert.strictEqual(run.id, 42500669683);
  assert.strictEqual(run.head_sha, pr.head.sha);
  assert.strictEqual(run.path, '.github/workflows/ci.yml');
  assert.strictEqual(run.event, 'pull_request');
  assert.strictEqual(run.status, 'completed');
  assert.strictEqual(run.conclusion, 'success');
  assert.strictEqual(workflow.present, true);
  assert.match(workflow.blob_sha, /^[0-9a-f]{40}$/);
  assert.ok(
    fixture.source.run.includes(`/actions/runs/${run.id}`),
    'the fixture must point at the real ci run it was captured from',
  );
  console.log('PASS: rollout fixture is a recording of real GitHub API data');
}

function testRealRunHasEveryRequiredJobAndCheck() {
  const { run, jobs, checkRuns } = fixture;
  const jobNames = jobs.map((job) => job.name).sort();
  assert.deepStrictEqual(
    jobNames,
    [...EXPECTED_CI_JOBS].sort(),
    'the recorded run must contain the complete required ci job set',
  );
  for (const job of jobs) {
    assert.strictEqual(
      job.conclusion,
      'success',
      `job ${job.name} in the recorded run must be green`,
    );
  }

  const checkByName = new Map(checkRuns.map((check) => [check.name, check]));
  for (const name of EXPECTED_CI_JOBS) {
    const check = checkByName.get(name);
    assert.ok(check, `standard check run ${name} must be recorded`);
    assert.strictEqual(check.conclusion, 'success');
    assert.strictEqual(check.head_sha, run.head_sha);
    assert.strictEqual(String(check.check_suite.id), String(run.check_suite_id));
  }
  console.log('PASS: the real ci run exposes every required job and standard check');
}

function testRealEvidenceIsGreenAndPinnedToTheHead() {
  const evidence = summarizeRolloutEvidence({
    pr: fixture.pullRequest,
    repository: fixture.repository,
    workflow: fixture.workflow.present ? fixture.workflow : null,
    run: fixture.run,
    jobs: fixture.jobs,
    checkRuns: fixture.checkRuns,
    targetSha: fixture.pullRequest.head.sha,
    workflowRef: fixture.pullRequest.head.sha,
  });
  assert.strictEqual(evidence.ready, true, evidence.problems.join('; '));
  assert.strictEqual(evidence.targetSha, fixture.pullRequest.head.sha);
  assert.strictEqual(evidence.run.id, fixture.run.id);
  assert.strictEqual(evidence.pullRequest.mergeable, true);
  assert.strictEqual(evidence.pullRequest.merged, false);
  assert.strictEqual(
    evidence.pullRequest.mergeCommitSha,
    null,
    'GitHub exposes a temporary test-merge commit for an open PR; it is not a landed merge commit',
  );
  console.log('PASS: the recorded PR #65 head is green and ready for automerge');
}

function testRecordedEvidenceCannotProveAnotherSubmittedHead() {
  // Regression for the review finding: the recording was captured on the
  // rejected commit, so it must never satisfy a gate for a different head.
  const submittedSha = '0123456789abcdef0123456789abcdef01234567';
  const evidence = summarizeRolloutEvidence({
    pr: {
      ...fixture.pullRequest,
      head: { ...fixture.pullRequest.head, sha: submittedSha },
    },
    repository: fixture.repository,
    workflow: fixture.workflow.present ? fixture.workflow : null,
    run: fixture.run,
    jobs: fixture.jobs,
    checkRuns: fixture.checkRuns,
    targetSha: submittedSha,
    workflowRef: submittedSha,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some(
      (problem) =>
        problem.includes(`is for ${fixture.pullRequest.head.sha}`) &&
        problem.includes(`expected ${submittedSha}`),
    ),
    'recorded evidence from the rejected commit must not prove a new head',
  );
  console.log(
    'PASS: recorded evidence for the rejected commit is rejected for another head',
  );
}

function testRecordedChecksWithoutCommitProvenanceAreRejected() {
  // Every accepted check run must name the exact commit under test. A recording
  // with stripped head_sha has no way to prove the submitted SHA, so it is red.
  const anonymous = fixture.checkRuns.map((check) => {
    const { head_sha: _headSha, ...rest } = check;
    return rest;
  });
  const evidence = summarizeRolloutEvidence({
    pr: fixture.pullRequest,
    repository: fixture.repository,
    workflow: fixture.workflow.present ? fixture.workflow : null,
    run: fixture.run,
    jobs: fixture.jobs,
    checkRuns: anonymous,
    targetSha: fixture.run.head_sha,
    workflowRef: fixture.run.head_sha,
  });
  assert.strictEqual(evidence.ready, false);
  assert.ok(
    evidence.problems.some(
      (problem) =>
        problem.includes('is for unknown') &&
        problem.includes(`expected ${fixture.run.head_sha}`),
    ),
    'check runs without head_sha cannot prove the submitted commit',
  );
  console.log(
    'PASS: recorded check runs without commit provenance are rejected',
  );
}

function testDocumentationDescribesDynamicEvidence() {
  for (const relative of ['docs/testeb-63-comment.md', 'docs/ci.md']) {
    const text = readFileSync(path.join(repoRoot, relative), 'utf8');
    assert.ok(
      text.includes(fixture.pullRequest.head.sha),
      `${relative} must name the recorded (historical) commit`,
    );
    assert.ok(
      text.includes(String(fixture.run.id)),
      `${relative} must link the recorded historical ci run for PR #65`,
    );
    assert.ok(
      text.includes(fixture.run.html_url),
      `${relative} must link the recorded historical ci run url for PR #65`,
    );
    assert.ok(
      text.includes('pr65-ci-recording.json'),
      `${relative} must point at the recorded GitHub API fixture`,
    );
    assert.ok(
      text.includes('TARGET_SHA') && text.includes('verify-ci-rollout'),
      `${relative} must describe the dynamic per-head evidence workflow`,
    );
    assert.ok(
      text.includes('не источник истины') ||
        text.includes('не является доказательством'),
      `${relative} must label the recording as historical, not current evidence`,
    );
  }
  console.log(
    'PASS: CI documentation labels the recording as historical and describes dynamic evidence',
  );
}

function main() {
  testFixtureIsRecordedFromGithub();
  testRealRunHasEveryRequiredJobAndCheck();
  testRealEvidenceIsGreenAndPinnedToTheHead();
  testRecordedEvidenceCannotProveAnotherSubmittedHead();
  testRecordedChecksWithoutCommitProvenanceAreRejected();
  testDocumentationDescribesDynamicEvidence();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
