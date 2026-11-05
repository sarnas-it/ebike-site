#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const includes = path.join(root, 'includes');
const phpTestPath = path.join(root, 'tests', 'delivery-order-persistence-contract-test.php');
const orderDeliveryPath = path.join(includes, 'order-delivery.php');
const deliveryOptionsPath = path.join(includes, 'delivery-options.php');

assert.ok(fs.existsSync(orderDeliveryPath), `Order delivery stub is missing: ${orderDeliveryPath}`);
assert.ok(fs.existsSync(deliveryOptionsPath), `Delivery options stub is missing: ${deliveryOptionsPath}`);
assert.ok(fs.existsSync(phpTestPath), `Delivery persistence PHP contract test is missing: ${phpTestPath}`);

const orderDelivery = fs.readFileSync(orderDeliveryPath, 'utf8');
const deliveryOptions = fs.readFileSync(deliveryOptionsPath, 'utf8');

function includesIn(source, fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includesIn(orderDelivery, 'velotok_core_save_checkout_delivery', 'Checkout delivery persistence helper is missing');
includesIn(orderDelivery, 'velotok_core_apply_checkout_delivery', 'Checkout delivery apply helper is missing');
includesIn(deliveryOptions, 'velotok_core_save_delivery_selection', 'Delivery selection persistence helper is missing');
includesIn(deliveryOptions, 'velotok_core_delivery_options_apply_to_order', 'Delivery order persistence helper is missing');
includesIn(deliveryOptions, 'clarify-a8fe76a0', 'Delivery options contract must reference spec clarify-a8fe76a0');
includesIn(deliveryOptions, "'mocked'", 'Delivery options contract must declare the mocked marker');
includesIn(deliveryOptions, "'saved'", 'Delivery persistence result must declare the saved marker');
includesIn(orderDelivery, "'_velotok_delivery_method'", 'Order meta key for the delivery method is missing');
includesIn(orderDelivery, "'_velotok_delivery_cost'", 'Order meta key for the delivery cost is missing');

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: delivery persistence stub structure and mocked markers verified (PHP not available)');
  process.exit(0);
}

const green = spawnSync('php', [phpTestPath], { encoding: 'utf8' });

if (green.stdout) {
  process.stdout.write(green.stdout);
}
if (green.stderr) {
  process.stderr.write(green.stderr);
}

assert.strictEqual(green.status, 0, 'PHP delivery persistence contract test failed');
console.log('PASS: selected delivery method and cost are persisted in the order');

const redGreenRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-delivery-persistence-'));

try {
  const redIncludes = path.join(redGreenRoot, 'includes');
  const redTests = path.join(redGreenRoot, 'tests');
  fs.mkdirSync(redIncludes, { recursive: true });
  fs.mkdirSync(redTests, { recursive: true });

  fs.copyFileSync(deliveryOptionsPath, path.join(redIncludes, 'delivery-options.php'));
  fs.writeFileSync(
    path.join(redIncludes, 'order-delivery.php'),
    orderDelivery.replace(
      'function velotok_core_save_checkout_delivery(',
      'function velotok_core_save_checkout_delivery_removed_for_red_test('
    )
  );
  fs.copyFileSync(phpTestPath, path.join(redTests, 'delivery-order-persistence-contract-test.php'));

  const red = spawnSync('php', [path.join(redTests, 'delivery-order-persistence-contract-test.php')], {
    encoding: 'utf8',
  });

  assert.notStrictEqual(
    red.status,
    0,
    'Contract test must fail when the order delivery persistence requirement is removed'
  );
  console.log('PASS: contract test fails without the order delivery persistence stub (red-green)');
} finally {
  fs.rmSync(redGreenRoot, { recursive: true, force: true });
}
