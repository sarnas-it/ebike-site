#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const includesDir = path.join(root, 'includes');
const stubName = 'content-approval.php';
const stubPath = path.join(includesDir, stubName);
const phpTestPath = path.join(root, 'tests', 'content-approval-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Content approval module is missing: ${stubPath}`);

const originalSource = fs.readFileSync(stubPath, 'utf8');

for (const fragment of [
  '_content_approved_by',
  'CONTENT_APPROVAL_REQUIRED',
  'CONTENT_APPROVAL_ROLE_FORBIDDEN',
  'project_manager',
  'Руководитель проекта',
  'velotok_core_content_approval_guard_transition',
  'velotok_core_content_approval_apply',
  'wp_insert_post_data',
]) {
  assert.ok(originalSource.includes(fragment), `Approval module is missing: ${fragment}`);
}

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(override) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_CONTENT_APPROVAL_STUB: override },
  });
}

const original = runTest(stubPath);
assert.strictEqual(original.status, 0, `Original approval module must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-content-approval-'));

try {
  const tmpIncludes = path.join(tmpDir, 'includes');
  fs.mkdirSync(tmpIncludes, { recursive: true });

  const mutatedPath = path.join(tmpIncludes, stubName);
  const mutated = originalSource.replace(/'enforce'\s*=>\s*true/, "'enforce' => false");

  assert.notStrictEqual(mutated, originalSource, 'Mutation must disable the approval guard');
  fs.writeFileSync(mutatedPath, mutated, 'utf8');

  const broken = runTest(mutatedPath);
  assert.notStrictEqual(
    broken.status,
    0,
    'Contract test must fail when publishing without human approval is no longer blocked'
  );

  const missing = runTest(path.join(tmpDir, 'missing-content-approval.php'));
  assert.notStrictEqual(
    missing.status,
    0,
    'Contract test must fail when the approval module is missing'
  );
} finally {
  fs.rmSync(tmpDir, { recursive: true, force: true });
}

console.log('PASS: content approval contract test is red without enforcement and green with it');
