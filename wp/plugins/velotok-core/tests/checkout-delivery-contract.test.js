#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'checkout-delivery.php');
const phpTestPath = path.join(root, 'tests', 'checkout-delivery-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Checkout delivery form stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Checkout delivery PHP contract test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('TESTEB-152', 'Contract must reference task TESTEB-152');
includes('clarify-b0e1a6d7', 'Contract must reference spec clarify-b0e1a6d7');
includes('velotok_core_checkout_delivery_form_contract', 'Checkout delivery form contract accessor is missing');
includes('velotok_core_checkout_delivery_form_field', 'Checkout delivery field helper is missing');
includes('velotok_core_checkout_delivery_form_options', 'Checkout delivery options helper is missing');
includes('velotok_core_checkout_delivery_form_submit', 'Checkout delivery submit helper is missing');
includes('velotok_core_submit_checkout_delivery', 'Checkout delivery submit alias is missing');
includes('velotok_core_checkout_delivery_form_open_questions', 'Checkout delivery open questions helper is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'form'", 'Contract must declare the checkout delivery form');
includes('velotok-checkout-delivery', 'Checkout delivery form id is missing');
includes('velotok_delivery_method', 'Checkout delivery field name is missing');
includes("'provider_contract'", 'Contract must declare the stub provider contract');
includes("'order_fields'", 'Contract must declare order persistence fields');
includes("'totals'", 'Contract must declare order total rules');
includes('include_delivery', 'Delivery cost must be included in the order total');
includes('DELIVERY_METHOD_REQUIRED', 'Missing delivery error code is missing');
includes('DELIVERY_METHOD_INVALID', 'Invalid delivery error code is missing');
includes('russian_post', 'Delivery option Почта РФ is missing');
includes('Почта РФ', 'Delivery label Почта РФ is missing');
includes('cdek', 'Delivery option СДЭК is missing');
includes('СДЭК', 'Delivery label СДЭК is missing');
includes('other', 'Delivery option Иной логист is missing');
includes('Иной логист', 'Delivery label Иной логист is missing');
includes('open_questions', 'Open questions section is missing');
includes('real_integration', 'Real integration scope marker is missing');

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
  'fsockopen(',
  'stream_socket_' + 'client(',
  'socket_' + 'create(',
];

for (const needle of forbidden) {
  assert.ok(!source.includes(needle), `Stub must not read external data or make network calls: ${needle}`);
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
  console.log('PASS: checkout delivery form stub contract verified with PHP');

  const redGreenRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-checkout-delivery-'));
  try {
    const redGreenTest = path.join(redGreenRoot, 'tests', 'checkout-delivery-contract-test.php');
    fs.mkdirSync(path.dirname(redGreenTest), { recursive: true });
    fs.copyFileSync(phpTestPath, redGreenTest);

    const redGreen = spawnSync('php', [redGreenTest], { encoding: 'utf8' });
    assert.notStrictEqual(redGreen.status, 0, 'Contract test must fail when the checkout delivery form stub is missing');
    console.log('PASS: contract test fails without the checkout delivery form stub (red-green)');
  } finally {
    fs.rmSync(redGreenRoot, { recursive: true, force: true });
  }
} else {
  console.log('PASS: checkout delivery form stub structure and mocked markers verified (PHP not available)');
}
