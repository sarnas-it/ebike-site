#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const modulePath = path.join(root, 'includes', 'delivery-zones.php');
const phpTestPath = path.join(root, 'tests', 'delivery-provider-parity-contract-test.php');

assert.ok(fs.existsSync(modulePath), `Delivery zones module is missing: ${modulePath}`);
assert.ok(fs.existsSync(phpTestPath), `Delivery provider parity PHP test is missing: ${phpTestPath}`);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: delivery provider parity red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(stubOverride) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_DELIVERY_PROVIDER_PARITY_STUB: stubOverride },
  });
}

const original = runTest(modulePath);
assert.strictEqual(original.status, 0, `Original delivery provider parity implementation must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-delivery-provider-parity-'));
const source = fs.readFileSync(modulePath, 'utf8');
const mutated = source.replace(
  /'equal'\s*=>\s*true/,
  "'equal' => false"
);

assert.notStrictEqual(mutated, source, 'Mutation must stop the providers from being declared equal');

const mutationPath = path.join(tmpDir, 'delivery-zones.php');
fs.writeFileSync(mutationPath, mutated, 'utf8');

const broken = runTest(mutationPath);
assert.notStrictEqual(broken.status, 0, 'Provider parity test must fail when the providers are not equal');

const missing = runTest(path.join(tmpDir, 'missing-delivery-zones.php'));
assert.notStrictEqual(missing.status, 0, 'Provider parity test must fail when the delivery zones module is missing');

console.log('PASS: delivery provider parity test is red without equal providers and green with them');
