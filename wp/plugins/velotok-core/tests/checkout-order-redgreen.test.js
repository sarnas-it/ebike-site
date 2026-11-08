#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const modulePath = path.join(root, 'includes', 'checkout-order.php');
const phpTestPath = path.join(root, 'tests', 'checkout-order-contract-test.php');

assert.ok(fs.existsSync(modulePath), `Checkout order module is missing: ${modulePath}`);
assert.ok(fs.existsSync(phpTestPath), `Checkout order PHP contract test is missing: ${phpTestPath}`);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(stubOverride) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_CHECKOUT_ORDER_STUB: stubOverride },
  });
}

const original = runTest(modulePath);
assert.strictEqual(original.status, 0, `Original checkout order module must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-checkout-order-'));
const source = fs.readFileSync(modulePath, 'utf8');

function assertMutationFails(mutatedSource, label) {
  assert.notStrictEqual(mutatedSource, source, `Mutation must change the source: ${label}`);

  const mutationPath = path.join(tmpDir, `${label}.php`);
  fs.writeFileSync(mutationPath, mutatedSource, 'utf8');

  const broken = runTest(mutationPath);
  assert.notStrictEqual(broken.status, 0, `Contract test must fail without ${label}`);
}

assertMutationFails(
  source.replace(/'uses_wc_checkout'=> true/, "'uses_wc_checkout'=> false"),
  'woocommerce-checkout'
);

assertMutationFails(
  source.replace(
    "if ( array_key_exists( 'is_user_logged_in', $context ) ) {",
    "if ( false && array_key_exists( 'is_user_logged_in', $context ) ) {"
  ),
  'authorization'
);

assertMutationFails(
  source.replace(
    'return empty( velotok_core_checkout_cart_items( $context ) );',
    'return false;'
  ),
  'empty-cart'
);

assertMutationFails(
  source.replace(
    'if ( null !== $existing ) {',
    'if ( false && null !== $existing ) {'
  ),
  'duplicate-prevention'
);

const missing = runTest(path.join(tmpDir, 'missing-checkout-order.php'));
assert.notStrictEqual(missing.status, 0, 'Contract test must fail when the checkout order module is missing');

console.log('PASS: checkout order contract test is red without checkout, authorization, empty-cart and duplicate guards, and green with them');
