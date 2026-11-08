#!/usr/bin/env node
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../../..');

const REQUIRED_CHECKS = [
  'php-lint',
  'php-tests',
  'plugin-tests',
  'php-plugin-tests',
];

function readDoc(name) {
  return readFileSync(path.join(repoRoot, 'docs', name), 'utf8');
}

function testCommentRecordsCauseAndFullEvidence() {
  const comment = readDoc('testeb-67-comment.md');
  assert.ok(
    comment.includes('no_checks'),
    'the task comment must record the no_checks cause',
  );
  assert.ok(
    comment.includes('.github/workflows'),
    'the task comment must explain the missing workflow trigger',
  );
  for (const check of REQUIRED_CHECKS) {
    assert.ok(
      comment.includes(check),
      `the task comment evidence must include ${check}`,
    );
  }
  assert.ok(
    comment.includes(
      'php wp/plugins/velotok-core/tests/accounts-contract-test.php',
    ),
    'the task comment must name the mandatory accounts acceptance command',
  );
  assert.ok(
    comment.includes('525b0d4803e03858cdb0bfce9cb90837bdf0473e'),
    'the task comment must reference the incident commit under test',
  );
  assert.ok(
    comment.includes('<!-- testeb-67-evidence:start -->') &&
      comment.includes('<!-- testeb-67-evidence:end -->'),
    'the task comment must contain the evidence block the finalizer replaces',
  );
  assert.ok(
    comment.includes('GITHUB_RUN_ID'),
    'the task comment must explain the finalizer run-id bug that was fixed',
  );
  assert.ok(
    comment.includes('42492889252'),
    'the task comment must link the real completed recovery run',
  );
  assert.ok(
    comment.includes('ci/recover=success'),
    'the task comment must show the commit status linking the recovery run',
  );
  assert.ok(
    comment.includes('6a14f53349e6f7da967e1bd1aded48c12deb82be'),
    'the task comment must record the real merge commit',
  );
  assert.ok(
    !comment.includes('это черновик') &&
      !comment.includes('будет опубликован автоматически после push'),
    'the task comment must contain final evidence, not a draft placeholder',
  );
  console.log('PASS: the task comment records the cause and the full post-fix evidence');
}

function testTaskCommentRecordExists() {
  const record = readDoc('testeb-67-task-comment.md');
  assert.ok(
    record.includes('TESTEB-67'),
    'the recorded task comment must name the task',
  );
  assert.ok(
    record.includes('525b0d4803e03858cdb0bfce9cb90837bdf0473e'),
    'the recorded task comment must reference the incident commit under test',
  );
  assert.ok(
    record.includes('финализатор') && record.includes('recovery-прогон'),
    'the recorded task comment must explain how the real recovery evidence is published',
  );
  for (const check of REQUIRED_CHECKS) {
    assert.ok(
      record.includes(check),
      `the recorded task comment must include ${check}`,
    );
  }
  assert.ok(
    record.includes(
      'php wp/plugins/velotok-core/tests/accounts-contract-test.php',
    ),
    'the recorded task comment must include the mandatory acceptance command',
  );
  assert.ok(
    record.includes('42492889252'),
    'the recorded task comment must link the real completed recovery run',
  );
  assert.ok(
    record.includes('6a14f53349e6f7da967e1bd1aded48c12deb82be'),
    'the recorded task comment must record the real merge commit',
  );
  console.log('PASS: the recorded task comment contains the verified evidence');
}

function testTesteb68CommentRecordsCauseWithoutStub() {
  const comment = readDoc('testeb-68-comment.md');
  assert.ok(
    comment.includes('841470c19d9e62c1a6c4e4c402dff322c81fd747'),
    'the TESTEB-68 comment must reference the pull request head under test',
  );
  assert.ok(
    comment.includes('42500219010'),
    'the TESTEB-68 comment must link the original CI run',
  );
  assert.ok(
    comment.includes('128791152777'),
    'the TESTEB-68 comment must identify the stale php-tests check run',
  );
  assert.ok(
    comment.includes('42504665607'),
    'the TESTEB-68 comment must link the failed recovery attempt that exposed the bug',
  );
  assert.ok(
    comment.includes('Resource not accessible by integration'),
    'the TESTEB-68 comment must record why updating the foreign check run failed',
  );
  for (const check of REQUIRED_CHECKS) {
    assert.ok(
      comment.includes(check),
      `the TESTEB-68 comment must include ${check}`,
    );
  }
  assert.ok(
    comment.includes(
      'php wp/plugins/velotok-core/tests/accounts-contract-test.php',
    ),
    'the TESTEB-68 comment must name the mandatory accounts acceptance command',
  );
  assert.ok(
    comment.includes('<!-- testeb-68-evidence:start -->') &&
      comment.includes('<!-- testeb-68-evidence:end -->'),
    'the TESTEB-68 comment must contain the evidence block the finalizer replaces',
  );
  assert.ok(
    comment.includes('42505520113'),
    'the TESTEB-68 comment must link the successful post-fix recovery run',
  );
  assert.ok(
    comment.includes('330a631e8655c0ae1f2c8d76a011ae319e90a0ac'),
    'the TESTEB-68 comment must record the real merge commit of PR #78',
  );
  assert.ok(
    comment.includes('ci/recover=success'),
    'the TESTEB-68 comment must show the status that unblocked the deferred merge',
  );
  for (const placeholder of [
    'будут добавлены',
    'будет добавлен',
    'заглушк',
    'это черновик',
  ]) {
    assert.ok(
      !comment.includes(placeholder),
      `the TESTEB-68 comment must not contain the placeholder "${placeholder}"`,
    );
  }
  console.log('PASS: the TESTEB-68 comment records the concrete cause, not a stub');
}

function testTesteb68TaskCommentRecordExists() {
  const record = readDoc('testeb-68-task-comment.md');
  assert.ok(record.includes('TESTEB-68'));
  assert.ok(record.includes('841470c19d9e62c1a6c4e4c402dff322c81fd747'));
  assert.ok(record.includes('42500219010'));
  assert.ok(record.includes('42504665607'));
  assert.ok(record.includes('128791152777'));
  assert.ok(record.includes('Resource not accessible by integration'));
  assert.ok(
    record.includes('42505520113'),
    'the TESTEB-68 task record must link the successful recovery run',
  );
  assert.ok(
    record.includes('330a631e8655c0ae1f2c8d76a011ae319e90a0ac'),
    'the TESTEB-68 task record must record the real merge commit',
  );
  for (const check of REQUIRED_CHECKS) {
    assert.ok(
      record.includes(check),
      `the TESTEB-68 task record must include ${check}`,
    );
  }
  console.log('PASS: the TESTEB-68 task record contains the verified evidence');
}

function main() {
  testCommentRecordsCauseAndFullEvidence();
  testTaskCommentRecordExists();
  testTesteb68CommentRecordsCauseWithoutStub();
  testTesteb68TaskCommentRecordExists();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
