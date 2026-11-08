#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const modulePath = path.join(root, 'includes', 'delivery-zones.php');
const phpTestPath = path.join(root, 'tests', 'delivery-test-mode-contract-test.php');

assert.ok(fs.existsSync(modulePath), `Delivery zones module is missing: ${modulePath}`);
assert.ok(fs.existsSync(phpTestPath), `Delivery test-mode PHP test is missing: ${phpTestPath}`);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: delivery test-mode red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(stubOverride) {
  const env = { ...process.env };

  if (stubOverride) {
    env.VELOTOK_DELIVERY_TEST_MODE_STUB = stubOverride;
  } else {
    delete env.VELOTOK_DELIVERY_TEST_MODE_STUB;
  }

  return spawnSync('php', [phpTestPath], { encoding: 'utf8', env });
}

const original = runTest();

assert.strictEqual(original.status, 0, `Original test-mode implementation must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-delivery-test-mode-'));
const source = fs.readFileSync(modulePath, 'utf8');
const mutated = source.replace(
  /\$provider_test\s*=\s*isset\(\s*\$provider\['test_mode_info'\]\s*\)\s*&&\s*is_array\(\s*\$provider\['test_mode_info'\]\s*\)\s*\?\s*\$provider\['test_mode_info'\]\s*:\s*\$test_info;/,
  '$provider_test = $test_info;'
);

assert.notStrictEqual(mutated, source, 'Mutation must drop the provider test-mode flag');

const mutationPath = path.join(tmpDir, 'delivery-zones.php');
fs.writeFileSync(mutationPath, mutated, 'utf8');

const broken = runTest(mutationPath);
assert.notStrictEqual(broken.status, 0, 'Test-mode test must fail when the provider test-mode flag is dropped');

const missing = runTest(path.join(tmpDir, 'missing-delivery-zones.php'));
assert.notStrictEqual(missing.status, 0, 'Test-mode test must fail when the delivery zones module is missing');

console.log('PASS: delivery test mode test is red without the provider flag and green with it');
