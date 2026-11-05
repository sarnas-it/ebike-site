#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'checkout-payment-delivery.php');
const phpTestPath = path.join(root, 'tests', 'checkout-payment-delivery-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Checkout contract stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-096bb945', 'Contract must reference spec clarify-096bb945');
includes('velotok_core_checkout_contract', 'Contract accessor is missing');
includes('velotok_core_payment_contract', 'Payment contract accessor is missing');
includes('velotok_core_delivery_contract', 'Delivery contract accessor is missing');
includes('velotok_core_content_acceptance_contract', 'Content acceptance accessor is missing');
includes('velotok_core_create_payment_intent', 'Mocked payment intent helper is missing');
includes('velotok_core_delivery_methods', 'Delivery methods helper is missing');
includes('velotok_core_validate_delivery_method', 'Delivery validation helper is missing');
includes('velotok_core_validate_content_acceptance', 'Content acceptance validation helper is missing');
includes('velotok_core_validate_checkout', 'Checkout validation helper is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'provider'", 'Payment contract must declare its provider');
includes('yookassa', 'Payment provider must be YooKassa');
includes('ЮKassa', 'Payment provider label must be ЮKassa');
includes("'env'", 'Payment contract must declare future env names');
includes('VELOTOK_YOOKASSA_SHOP_ID', 'Future shop id env name is missing');
includes('VELOTOK_YOOKASSA_SECRET_KEY', 'Future secret key env name is missing');
includes('VELOTOK_YOOKASSA_MODE', 'Future mode env name is missing');

includes('russian_post', 'Delivery method Почта РФ is missing');
includes('Почта РФ', 'Delivery label Почта РФ is missing');
includes('cdek', 'Delivery method СДЭК is missing');
includes('СДЭК', 'Delivery label СДЭК is missing');
includes('other', 'Delivery method Иной логист is missing');
includes('Иной логист', 'Delivery label Иной логист is missing');

includes('content_acceptance', 'Content acceptance contract is missing');
includes('project_manager', 'Project manager role is missing');
includes('руководитель проекта', 'Project manager role label is missing');
includes("'human'", 'Content acceptance must be enforced by a human');
includes('CONTENT_ACCEPTANCE_REQUIRED', 'Content acceptance error code is missing');
includes('DELIVERY_METHOD_REQUIRED', 'Missing delivery error code is missing');
includes('DELIVERY_METHOD_INVALID', 'Invalid delivery error code is missing');
includes('open_questions', 'Open questions section is missing');

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

  assert.strictEqual(result.status, 0, 'PHP contract test failed');
  console.log('PASS: checkout, delivery and payment stub contract verified with PHP');
} else {
  console.log('PASS: checkout, delivery and payment stub structure and mocked markers verified (PHP not available)');
}
