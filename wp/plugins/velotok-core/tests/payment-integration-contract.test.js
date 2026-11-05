#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'payment-integration.php');
const phpTestPath = path.join(root, 'tests', 'payment-integration-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Payment integration stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('ecommerce-store', 'Contract must reference spec ecommerce-store');
includes('TESTEB-153', 'Contract must reference requirement TESTEB-153');
includes('clarify-096bb945', 'Contract must reference the payment open question');
includes('velotok_core_payment_integration_contract', 'Contract accessor is missing');
includes('velotok_core_payment_integration_stub_contract', 'Stub contract alias is missing');
includes('velotok_core_payment_system_contract', 'Payment system contract alias is missing');
includes('velotok_core_payment_integration_create', 'Payment creation helper is missing');
includes('velotok_core_payment_integration_process', 'Payment processing helper is missing');
includes('velotok_core_payment_integration_status', 'Payment status helper is missing');
includes('velotok_core_payment_integration_confirm', 'Payment confirmation helper is missing');
includes('velotok_core_payment_integration_is_paid', 'Payment paid checker is missing');
includes('velotok_core_payment_integration_validate', 'Payment validator is missing');
includes('velotok_core_payment_integration_open_questions', 'Open questions accessor is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'parent_spec'", 'Contract must declare the parent spec');
includes("'provider'", 'Contract must declare the payment provider');
includes("'yookassa'", 'Payment provider must be ЮKassa');
includes("'statuses'", 'Contract must declare payment statuses');
includes("'currency'", 'Contract must declare the payment currency');
includes("'future_source'", 'Contract must declare the future integration source');
includes("'env'", 'Contract must declare future env names');
includes("'open_questions'", 'Open questions section is missing');

for (const status of ['pending', 'waiting_for_capture', 'succeeded', 'canceled']) {
  includes(`'${status}'`, `Payment status is missing: ${status}`);
}

for (const code of [
  'PAYMENT_PROVIDER_UNSUPPORTED',
  'PAYMENT_AMOUNT_INVALID',
  'PAYMENT_CURRENCY_UNSUPPORTED',
  'PAYMENT_STATUS_INVALID',
]) {
  includes(`'${code}'`, `Payment error code is missing: ${code}`);
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
  'wp_remote_' + 'post(',
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

  assert.strictEqual(result.status, 0, 'PHP payment integration contract test failed');
  console.log('PASS: payment integration stub contract verified with PHP');
} else {
  console.log('PASS: payment integration stub structure and mocked markers verified (PHP not available)');
}
