#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'checkout-contracts.php');
const phpTestPath = path.join(root, 'tests', 'checkout-contracts-test.php');

assert.ok(fs.existsSync(stubPath), `Checkout contract stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-096bb945', 'Contract must reference spec clarify-096bb945');
includes('velotok_core_checkout_contract', 'Contract accessor is missing');
includes('velotok_core_delivery_methods', 'Delivery methods accessor is missing');
includes('velotok_core_validate_delivery_method', 'Delivery validation accessor is missing');
includes('velotok_core_payment_methods', 'Payment methods accessor is missing');
includes('velotok_core_validate_payment_method', 'Payment validation accessor is missing');
includes('velotok_core_content_acceptance_contract', 'Content acceptance accessor is missing');
includes('yookassa', 'YooKassa payment method is missing');
includes('project_manager', 'Content acceptance must require the project manager role');

for (const label of ['Почта РФ', 'СДЭК', 'Иной логист']) {
  includes(label, `Delivery method label is missing: ${label}`);
}

for (const code of ['DELIVERY_METHOD_REQUIRED', 'DELIVERY_METHOD_UNSUPPORTED', 'PAYMENT_METHOD_REQUIRED', 'PAYMENT_METHOD_UNSUPPORTED']) {
  includes(code, `Validation error code is missing: ${code}`);
}

assert.ok(
  /'mocked'\s*=>\s*true/.test(source),
  'Contract root must be explicitly marked mocked: true'
);

const forbidden = [
  'get' + 'env(',
  'apache_get' + 'env(',
  '$' + '_ENV',
  '$' + '_SERVER',
  'file_get' + '_contents(',
  'fopen(',
  'curl_' + 'init(',
  'wp_remote_' + 'get(',
];

for (const needle of forbidden) {
  assert.ok(!source.includes(needle), `Stub must not read external data: ${needle}`);
}

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status === 0) {
  const result = spawnSync('php', [phpTestPath], { encoding: 'utf8' });

  if (result.stdout) {
    process.stdout.write(result.stdout);
  }
  if (result.stderr) {
    process.stderr.write(result.stderr);
  }

  assert.strictEqual(result.status, 0, 'PHP contract test failed');
  console.log('PASS: checkout stub contract verified with PHP');
} else {
  console.log('PASS: checkout stub structure and mocked markers verified (PHP not available)');
}
