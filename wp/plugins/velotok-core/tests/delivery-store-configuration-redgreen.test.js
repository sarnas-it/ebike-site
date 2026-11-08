#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const modulePath = path.join(root, 'includes', 'delivery-zones.php');
const phpTestPath = path.join(root, 'tests', 'delivery-store-configuration-contract-test.php');

assert.ok(fs.existsSync(modulePath), `Delivery zones module is missing: ${modulePath}`);
assert.ok(fs.existsSync(phpTestPath), `Delivery configuration PHP test is missing: ${phpTestPath}`);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: delivery store configuration red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(stubOverride) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_DELIVERY_CONFIG_STUB: stubOverride },
  });
}

const original = runTest(modulePath);
assert.strictEqual(original.status, 0, `Original delivery configuration implementation must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-delivery-config-'));
const source = fs.readFileSync(modulePath, 'utf8');
const mutated = source.replace(
  /'reads_store_configuration'\s*=>\s*true/,
  "'reads_store_configuration' => false"
);

assert.notStrictEqual(mutated, source, 'Mutation must stop delivery options from being read from the store configuration');

const mutationPath = path.join(tmpDir, 'delivery-zones.php');
fs.writeFileSync(mutationPath, mutated, 'utf8');

const broken = runTest(mutationPath);
assert.notStrictEqual(broken.status, 0, 'Configuration test must fail when store configuration is not read');

const missing = runTest(path.join(tmpDir, 'missing-delivery-zones.php'));
assert.notStrictEqual(missing.status, 0, 'Configuration test must fail when the delivery zones module is missing');

console.log('PASS: delivery store configuration test is red without reading the store configuration and green with it');
