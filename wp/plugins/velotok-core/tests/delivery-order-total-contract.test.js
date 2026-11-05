#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'delivery-order-total.php');
const phpTestPath = path.join(root, 'tests', 'delivery-order-total-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Delivery order total stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-b0e1a6d7', 'Contract must reference spec clarify-b0e1a6d7');
includes('clarify-a8fe76a0', 'Contract must reference the delivery options parent spec');
includes('velotok_core_delivery_order_total_contract', 'Delivery order total contract accessor is missing');
includes('velotok_core_delivery_order_total', 'Delivery order total helper is missing');
includes('velotok_core_delivery_order_total_lines', 'Order total lines helper is missing');
includes('velotok_core_render_delivery_order_total', 'Order total render helper is missing');
includes('velotok_core_delivery_order_total_format_amount', 'Order total amount formatter is missing');
includes('velotok_core_delivery_order_total_open_questions', 'Open questions helper is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'includes_delivery'", 'Contract must declare that delivery is included in the total');
includes("'total_includes_delivery'", 'Contract must declare that the total includes delivery');
includes("'subtotal_plus_delivery'", 'Total calculation strategy is missing');
includes("'show_delivery_line'", 'Display contract must show the delivery line');
includes('Доставка', 'Delivery line label is missing');
includes('Итого', 'Order total label is missing');
includes('Бесплатно', 'Free delivery label is missing');
includes('DELIVERY_METHOD_REQUIRED', 'Missing delivery error code is missing');
includes('DELIVERY_METHOD_INVALID', 'Invalid delivery error code is missing');
includes('open_questions', 'Open questions section is missing');
includes('address_dependency', 'Address dependency open question is missing');

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
  console.log('PASS: delivery order total stub contract verified with PHP');
} else {
  console.log('PASS: delivery order total stub structure and mocked markers verified (PHP not available)');
}
