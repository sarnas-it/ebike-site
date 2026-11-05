#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'order-delivery.php');
const phpTestPath = path.join(root, 'tests', 'order-delivery-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Checkout delivery stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Checkout delivery PHP contract test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-b0e1a6d7', 'Contract must reference spec clarify-b0e1a6d7');
includes('velotok_core_order_delivery_contract', 'Order delivery contract accessor is missing');
includes('velotok_core_order_delivery_options', 'Selectable delivery options helper is missing');
includes('velotok_core_validate_order_delivery_method', 'Checkout delivery validation helper is missing');
includes('velotok_core_order_delivery_cost', 'Delivery cost helper is missing');
includes('velotok_core_order_delivery_totals', 'Order total helper is missing');
includes('velotok_core_save_checkout_delivery', 'Checkout delivery persistence helper is missing');
includes('velotok_core_apply_checkout_delivery', 'Checkout delivery apply helper is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'provider_contract'", 'Contract must declare the stub provider contract');
includes("'selection'", 'Contract must declare checkout selection fields');
includes("'order_fields'", 'Contract must declare order persistence fields');
includes("'totals'", 'Contract must declare order total rules');
includes('include_delivery', 'Delivery cost must be included in the order total');
includes("'display'", 'Contract must declare display rules');
includes('show_delivery_cost', 'Delivery cost display rule is missing');
includes('russian_post', 'Delivery option Почта РФ is missing');
includes('Почта РФ', 'Delivery label Почта РФ is missing');
includes('cdek', 'Delivery option СДЭК is missing');
includes('СДЭК', 'Delivery label СДЭК is missing');
includes('other', 'Delivery option Иной логист is missing');
includes('Иной логист', 'Delivery label Иной логист is missing');
includes('DELIVERY_METHOD_REQUIRED', 'Missing delivery error code is missing');
includes('DELIVERY_METHOD_INVALID', 'Invalid delivery error code is missing');
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
  console.log('PASS: checkout delivery stub contract verified with PHP');

  const redGreenRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-order-delivery-'));
  try {
    const redGreenTest = path.join(redGreenRoot, 'tests', 'order-delivery-contract-test.php');
    fs.mkdirSync(path.dirname(redGreenTest), { recursive: true });
    fs.copyFileSync(phpTestPath, redGreenTest);

    const redGreen = spawnSync('php', [redGreenTest], { encoding: 'utf8' });
    assert.notStrictEqual(redGreen.status, 0, 'Contract test must fail when the delivery stub is missing');
    console.log('PASS: contract test fails without the delivery stub (red-green)');
  } finally {
    fs.rmSync(redGreenRoot, { recursive: true, force: true });
  }
} else {
  console.log('PASS: checkout delivery stub structure and mocked markers verified (PHP not available)');
}
