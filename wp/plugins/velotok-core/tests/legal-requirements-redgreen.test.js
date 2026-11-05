#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'legal-requirements.php');
const phpTestPath = path.join(root, 'tests', 'legal-requirements-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Legal requirements stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Legal requirements contract test is missing: ${phpTestPath}`);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(stubOverride) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_LEGAL_REQUIREMENTS_STUB: stubOverride },
  });
}

const original = runTest(stubPath);
assert.strictEqual(original.status, 0, `Original legal requirements stub must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-legal-requirements-'));

try {
  const mutatedPath = path.join(tmpDir, 'legal-requirements.php');
  const source = fs.readFileSync(stubPath, 'utf8');

  const sourceMutation = source.replace(
    "$has_source      = '' !== $source;",
    '$has_source      = true;'
  );
  assert.notStrictEqual(sourceMutation, source, 'Mutation must disable the source check');
  fs.writeFileSync(mutatedPath, sourceMutation, 'utf8');

  const sourceBroken = runTest(mutatedPath);
  assert.notStrictEqual(
    sourceBroken.status,
    0,
    'Contract test must fail when a material without a source link is accepted'
  );

  const requirementMutation = source.replace(
    '$has_requirement = ! empty( $requirement_matches ) && ! empty( $context_matches );',
    '$has_requirement = true;'
  );
  assert.notStrictEqual(requirementMutation, source, 'Mutation must disable the requirement check');
  fs.writeFileSync(mutatedPath, requirementMutation, 'utf8');

  const requirementBroken = runTest(mutatedPath);
  assert.notStrictEqual(
    requirementBroken.status,
    0,
    'Contract test must fail when a material without a requirement description is accepted'
  );

  const mockedMutation = source.replace(
    /'mocked'\s*=>\s*true,/,
    "'mocked'             => false,"
  );
  assert.notStrictEqual(mockedMutation, source, 'Mutation must remove the mocked marker');
  fs.writeFileSync(mutatedPath, mockedMutation, 'utf8');

  const mockedBroken = runTest(mutatedPath);
  assert.notStrictEqual(
    mockedBroken.status,
    0,
    'Contract test must fail when the stub is no longer marked mocked: true'
  );

  const missing = runTest(path.join(tmpDir, 'missing-stub.php'));
  assert.notStrictEqual(
    missing.status,
    0,
    'Contract test must fail when the legal requirements stub is missing'
  );
} finally {
  fs.rmSync(tmpDir, { recursive: true, force: true });
}

console.log('PASS: legal requirements contract test is red without the stub and green with the stub');
