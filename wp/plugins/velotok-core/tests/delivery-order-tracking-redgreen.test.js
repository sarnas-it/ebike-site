#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const includes = path.join(root, 'includes');
const modulePath = path.join(includes, 'order-delivery.php');
const phpTestPath = path.join(root, 'tests', 'delivery-order-tracking-contract-test.php');

assert.ok(fs.existsSync(modulePath), `Order delivery module is missing: ${modulePath}`);
assert.ok(fs.existsSync(phpTestPath), `Delivery tracking PHP test is missing: ${phpTestPath}`);

const source = fs.readFileSync(modulePath, 'utf8');

assert.ok(source.includes('TESTEB-38'), 'Delivery order contract must reference requirement TESTEB-38');
assert.ok(source.includes('velotok_core_delivery_order_tracking_number'), 'Order tracking number helper is missing');
assert.ok(source.includes('_velotok_delivery_tracking_number'), 'Persisted tracking meta key is missing');
assert.ok(
  source.includes('woocommerce_order_details_after_order_table'),
  'Tracking number must be displayed on the order details screen'
);
assert.ok(
  source.includes('woocommerce_admin_order_data_after_shipping_address'),
  'Tracking number must be visible to the shop owner in the order admin screen'
);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: delivery tracking red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(moduleOverride) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_DELIVERY_ORDER_TRACKING_STUB: moduleOverride },
  });
}

const original = runTest(modulePath);
assert.strictEqual(original.status, 0, `Original delivery tracking implementation must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-delivery-tracking-'));
const mutation = source.replace(
  'function velotok_core_delivery_order_tracking_number(',
  'function velotok_core_delivery_order_tracking_number_disabled('
);

assert.notStrictEqual(mutation, source, 'Mutation must disable the order tracking number reader');

const mutationPath = path.join(tmpDir, 'order-delivery.php');
fs.writeFileSync(mutationPath, mutation, 'utf8');
fs.copyFileSync(path.join(includes, 'delivery-zones.php'), path.join(tmpDir, 'delivery-zones.php'));
fs.copyFileSync(path.join(includes, 'delivery-woocommerce.php'), path.join(tmpDir, 'delivery-woocommerce.php'));

const broken = runTest(mutationPath);
assert.notStrictEqual(broken.status, 0, 'Tracking test must fail when the order tracking number is not readable');

const missing = runTest(path.join(tmpDir, 'missing-order-delivery.php'));
assert.notStrictEqual(missing.status, 0, 'Tracking test must fail when the order delivery module is missing');

console.log('PASS: delivery tracking test is red without a stored tracking number and green with it');
