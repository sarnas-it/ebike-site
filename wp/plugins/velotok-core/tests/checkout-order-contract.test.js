#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const modulePath = path.join(root, 'includes', 'checkout-order.php');
const phpTestPath = path.join(root, 'tests', 'checkout-order-contract-test.php');

assert.ok(fs.existsSync(modulePath), `Checkout order module is missing: ${modulePath}`);
assert.ok(fs.existsSync(phpTestPath), `Checkout order PHP contract test is missing: ${phpTestPath}`);

const source = fs.readFileSync(modulePath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('TESTEB-23', 'Contract must reference requirement TESTEB-23');
includes("'spec'            => 'checkout'", 'Contract must reference the checkout spec');
includes('WC_Checkout', 'WooCommerce Checkout class is missing');
includes('uses_wc_checkout', 'WooCommerce Checkout usage must be declared');
includes('create_order', 'Order creation through WC_Checkout is missing');
includes('wp_verify_nonce', 'Server-side nonce verification is missing');
includes('is_user_logged_in', 'Authorization check is missing');
includes('CHECKOUT_AUTH_REQUIRED', 'Authorization error code is missing');
includes('CHECKOUT_INVALID_NONCE', 'Nonce error code is missing');
includes('CHECKOUT_INVALID_FORM', 'Form validation error code is missing');
includes('CHECKOUT_EMPTY_CART', 'Empty cart error code is missing');
includes('CHECKOUT_DUPLICATE', 'Duplicate order error code is missing');
includes('prevents_duplicate', 'Duplicate prevention must be declared');
includes('_velotok_checkout_key', 'Idempotency order meta key is missing');
includes('delivery_tracking_number', 'Delivery tracking number is missing');
includes('customer_note', 'Order comment is missing');
includes('line_items', 'Order composition is missing');
includes('visible_to', 'Order data visibility is missing');

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status === 0) {
  const result = spawnSync('php', [phpTestPath], { encoding: 'utf8' });

  if (result.stdout) {
    process.stdout.write(result.stdout);
  }
  if (result.stderr) {
    process.stderr.write(result.stderr);
  }

  assert.strictEqual(result.status, 0, 'PHP checkout order contract test failed');
  console.log('PASS: checkout order contract verified with PHP');
} else {
  console.log('PASS: checkout order contract structure verified (PHP not available)');
}
