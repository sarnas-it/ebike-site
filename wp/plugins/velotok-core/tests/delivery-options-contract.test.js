#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'delivery-options.php');
const phpTestPath = path.join(root, 'tests', 'delivery-options-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Delivery options stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-a8fe76a0', 'Contract must reference spec clarify-a8fe76a0');
includes('velotok_core_delivery_options_contract', 'Delivery options contract accessor is missing');
includes('velotok_core_delivery_options', 'Delivery options list helper is missing');
includes('velotok_core_delivery_options_tariffs', 'Tariff grid helper is missing');
includes('velotok_core_delivery_options_cost', 'Delivery cost helper is missing');
includes('velotok_core_delivery_options_apply_to_order', 'Order persistence helper is missing');
includes('velotok_core_delivery_options_open_questions', 'Open questions helper is missing');
includes('velotok_core_delivery_option', 'Delivery option resolver is missing');
includes('velotok_core_delivery_tariffs', 'Tariff grid helper is missing');
includes('velotok_core_calculate_delivery_cost', 'Delivery cost calculation helper is missing');
includes('velotok_core_save_delivery_selection', 'Delivery selection persistence helper is missing');
includes('velotok_core_validate_delivery_option', 'Delivery option validation helper is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'provider'", 'Contract must declare its provider');
includes("'tariffs'", 'Contract must declare the tariff grid');
includes("'tariff_grid'", 'Contract must declare a tariff grid alias');
includes("'order'", 'Contract must declare the order persistence shape');
includes("'order_fields'", 'Contract must declare order persistence fields');

includes('russian_post', 'Delivery method Почта РФ is missing');
includes('Почта РФ', 'Delivery label Почта РФ is missing');
includes('cdek', 'Delivery method СДЭК is missing');
includes('СДЭК', 'Delivery label СДЭК is missing');
includes('other', 'Delivery method Иной логист is missing');
includes('Иной логист', 'Delivery label Иной логист is missing');
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
  '$' + 'wpdb',
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

  assert.strictEqual(result.status, 0, 'PHP delivery options contract test failed');
  console.log('PASS: delivery options stub contract verified with PHP');
} else {
  console.log('PASS: delivery options stub structure and mocked markers verified (PHP not available)');
}
