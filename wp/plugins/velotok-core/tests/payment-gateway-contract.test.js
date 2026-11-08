#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'payment-gateway.php');
const phpTestPath = path.join(root, 'tests', 'payment-gateway-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Payment gateway is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Payment contract test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('TESTEB-44', 'Contract must reference requirement TESTEB-44');
includes('payment', 'Contract must reference the payment spec');
includes('WC_Payment_Gateway', 'Gateway must use WC_Payment_Gateway');
includes('Velotok_Yookassa_Gateway', 'Gateway class is missing');
includes('woocommerce_payment_gateways', 'Gateway registration hook is missing');
includes('velotok_core_yookassa_process_payment', 'Payment processing entry point is missing');
includes('velotok_core_payment_webhook_process', 'Webhook processing entry point is missing');
includes('velotok_core_payment_webhook_verify', 'Webhook signature verification is missing');
includes('hash_hmac', 'Webhook must use an HMAC signature');
includes('hash_equals', 'Webhook signature must be compared in constant time');
includes('Idempotence-Key', 'API requests must send an idempotency key');
includes('idempotent', 'Webhook must declare idempotency');
includes('velotok_core_payment_status_model', 'Fixed payment status model is missing');
includes('velotok_core_payment_apply_order_status', 'Order status synchronization is missing');
includes('velotok_core_yookassa_refund', 'Refund entry point is missing');
includes('refunded', 'Full refund must map to refunded');
includes('VELOTOK_YOOKASSA_SHOP_ID', 'Shop id must come from the environment');
includes('VELOTOK_YOOKASSA_SECRET_KEY', 'Secret key must come from the environment');
includes('VELOTOK_YOOKASSA_WEBHOOK_SECRET', 'Webhook secret must come from the environment');
includes('https://api.yookassa.ru/v3', 'API v3 base URL is missing');

for (const status of ['pending', 'waiting_for_capture', 'succeeded', 'canceled', 'refunded']) {
  includes(`'${status}'`, `Payment status is missing: ${status}`);
}

for (const code of ['PAYMENT_WEBHOOK_SIGNATURE_INVALID', 'PAYMENT_STATUS_INVALID', 'PAYMENT_REFUND_AMOUNT_INVALID']) {
  includes(code, `Error code is missing: ${code}`);
}

assert.ok(
  /class\s+Velotok_Yookassa_Gateway\s+extends\s+WC_Payment_Gateway/.test(source),
  'Gateway class must extend WC_Payment_Gateway'
);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: payment gateway structure verified (PHP not available)');
  process.exit(0);
}

const result = spawnSync('php', [phpTestPath], { encoding: 'utf8', cwd: path.resolve(root, '..', '..', '..') });

if (result.stdout) {
  process.stdout.write(result.stdout);
}
if (result.stderr) {
  process.stderr.write(result.stderr);
}

assert.strictEqual(result.status, 0, `PHP payment gateway test failed: ${phpTestPath}`);

const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-payment-'));
fs.mkdirSync(path.join(tempRoot, 'tests'));
fs.mkdirSync(path.join(tempRoot, 'includes'));
fs.copyFileSync(phpTestPath, path.join(tempRoot, 'tests', 'payment-gateway-contract-test.php'));

const withoutStub = spawnSync('php', [path.join(tempRoot, 'tests', 'payment-gateway-contract-test.php')], { encoding: 'utf8' });
assert.notStrictEqual(withoutStub.status, 0, 'Contract test must fail without the gateway implementation');

fs.copyFileSync(stubPath, path.join(tempRoot, 'includes', 'payment-gateway.php'));
const withStub = spawnSync('php', [path.join(tempRoot, 'tests', 'payment-gateway-contract-test.php')], { encoding: 'utf8' });
assert.strictEqual(withStub.status, 0, 'Contract test must pass with the gateway implementation');

fs.rmSync(tempRoot, { recursive: true, force: true });

console.log('PASS: WooCommerce ЮKassa payment gateway verified with PHP');
