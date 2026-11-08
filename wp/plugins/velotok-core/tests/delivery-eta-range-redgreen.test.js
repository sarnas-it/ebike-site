#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const zonesPath = path.join(root, 'includes', 'delivery-zones.php');
const woocommercePath = path.join(root, 'includes', 'delivery-woocommerce.php');
const phpTestPath = path.join(root, 'tests', 'delivery-eta-range-contract-test.php');

assert.ok(fs.existsSync(zonesPath), `Delivery zones module is missing: ${zonesPath}`);
assert.ok(fs.existsSync(woocommercePath), `WooCommerce delivery module is missing: ${woocommercePath}`);
assert.ok(fs.existsSync(phpTestPath), `Delivery ETA PHP test is missing: ${phpTestPath}`);

const source = fs.readFileSync(zonesPath, 'utf8');
const woocommerce = fs.readFileSync(woocommercePath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('TESTEB-31', 'Delivery contract must reference requirement TESTEB-31');
includes('velotok_core_delivery_zones_eta_range', 'ETA range helper is missing');
includes("'format'    => 'range'", 'Delivery ETA must be declared as a range');
includes('_velotok_delivery_eta', 'Persisted ETA range meta key is missing');
assert.ok(
  woocommerce.includes('woocommerce_order_details_after_order_table'),
  'Delivery ETA must be displayed on the order details screen'
);
assert.ok(
  woocommerce.includes("'_velotok_delivery_eta'"),
  'Delivery ETA range must be persisted on the order'
);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: delivery ETA red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(zonesOverride, woocommerceOverride) {
  const env = { ...process.env, VELOTOK_DELIVERY_ETA_STUB: zonesOverride };

  if (woocommerceOverride) {
    env.VELOTOK_DELIVERY_ETA_WOOCOMMERCE = woocommerceOverride;
  }

  return spawnSync('php', [phpTestPath], { encoding: 'utf8', env });
}

const original = runTest(zonesPath, woocommercePath);
assert.strictEqual(original.status, 0, `Original delivery ETA implementation must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-delivery-eta-'));
const mutation = source.replace(
  '$max = $normalize( $candidate );',
  '$max = $min;'
);

assert.notStrictEqual(mutation, source, 'Mutation must collapse the ETA range to a single boundary');

const mutationZonesPath = path.join(tmpDir, 'delivery-zones.php');
const mutationWoocommercePath = path.join(tmpDir, 'delivery-woocommerce.php');

fs.writeFileSync(mutationZonesPath, mutation, 'utf8');
fs.writeFileSync(mutationWoocommercePath, woocommerce, 'utf8');

const broken = runTest(mutationZonesPath, mutationWoocommercePath);
assert.notStrictEqual(broken.status, 0, 'ETA test must fail when the delivery time is not kept as a range');

const missing = runTest(path.join(tmpDir, 'missing-delivery-zones.php'), woocommercePath);
assert.notStrictEqual(missing.status, 0, 'ETA test must fail when the delivery zones module is missing');

console.log('PASS: delivery ETA test is red without a stored range and green with it');
