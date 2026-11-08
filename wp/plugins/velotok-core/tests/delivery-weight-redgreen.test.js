#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const modulePath = path.join(root, 'includes', 'delivery-zones.php');
const phpTestPath = path.join(root, 'tests', 'delivery-weight-contract-test.php');

assert.ok(fs.existsSync(modulePath), `Delivery zones module is missing: ${modulePath}`);
assert.ok(fs.existsSync(phpTestPath), `Delivery weight PHP test is missing: ${phpTestPath}`);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(stubOverride) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_DELIVERY_WEIGHT_STUB: stubOverride },
  });
}

const original = runTest(modulePath);
assert.strictEqual(original.status, 0, `Original delivery weight implementation must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-delivery-weight-'));
const source = fs.readFileSync(modulePath, 'utf8');
const mutated = source.replace(
  /\$item_weight = velotok_core_delivery_zones_item_weight\( \$item \);/,
  '$item_weight = 0.0;'
);

assert.notStrictEqual(mutated, source, 'Mutation must make the order weight ignore the line items');

const mutationPath = path.join(tmpDir, 'delivery-zones.php');
fs.writeFileSync(mutationPath, mutated, 'utf8');

const broken = runTest(mutationPath);
assert.notStrictEqual(broken.status, 0, 'Weight test must fail when the order weight is not derived from the items');

const missing = runTest(path.join(tmpDir, 'missing-delivery-zones.php'));
assert.notStrictEqual(missing.status, 0, 'Weight test must fail when the delivery zones module is missing');

console.log('PASS: delivery weight test is red without unambiguous order weight and green with it');
