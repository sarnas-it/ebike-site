#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'cart.php');
const phpTestPath = path.join(root, 'tests', 'cart-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Cart stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('ecommerce-store', 'Contract must reference spec ecommerce-store');
includes('TESTEB-151', 'Contract must reference requirement TESTEB-151');
includes('clarify-30af92ca', 'Contract must reference the guest cart open question');
includes('velotok_core_cart_contract', 'Cart contract accessor is missing');
includes('velotok_core_cart_stub_contract', 'Cart stub contract alias is missing');
includes('velotok_core_cart_data_contract', 'Cart data contract alias is missing');
includes('velotok_core_cart_create', 'Cart creation helper is missing');
includes('velotok_core_cart_items', 'Cart items accessor is missing');
includes('velotok_core_cart_get_item', 'Single cart item accessor is missing');
includes('velotok_core_cart_add_item', 'Add item helper is missing');
includes('velotok_core_cart_update_item', 'Update item helper is missing');
includes('velotok_core_cart_remove_item', 'Remove item helper is missing');
includes('velotok_core_cart_count', 'Item count helper is missing');
includes('velotok_core_cart_subtotal', 'Subtotal helper is missing');
includes('velotok_core_cart_total', 'Cart total helper is missing');
includes('velotok_core_cart_totals', 'Cart totals helper is missing');
includes('velotok_core_cart_clear', 'Cart clear helper is missing');
includes('velotok_core_cart_guest', 'Guest cart helper is missing');
includes('velotok_core_cart_transfer_guest_cart', 'Guest cart transfer helper is missing');
includes('velotok_core_cart_open_questions', 'Open questions accessor is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'parent_spec'", 'Contract must declare the parent spec');
includes("'cart_type'", 'Contract must declare the cart type');
includes("'shopping_cart'", 'Cart type must be shopping_cart');
includes("'operation'", 'Contract must declare the cart operation');
includes("'provide_cart_for_checkout'", 'Cart operation must support checkout');
includes("'item_fields'", 'Contract must declare item fields');
includes("'required_fields'", 'Contract must declare required item fields');
includes("'operations'", 'Contract must declare cart operations');
includes("'storage'", 'Contract must declare storage rules');
includes("'guest_cart'", 'Contract must declare guest cart rules');
includes("'transfer_on_registration'", 'Guest cart transfer on registration is missing');
includes("'merge_quantities'", 'Guest cart merge strategy is missing');
includes("'totals'", 'Contract must declare totals rules');
includes("'sum_price_times_quantity'", 'Cart total strategy is missing');
includes("'checkout'", 'Contract must declare checkout support');
includes("'open_questions'", 'Open questions section is missing');

for (const field of ['key', 'product_id', 'name', 'price', 'quantity', 'line_total']) {
  includes(`'${field}'`, `Cart item field is missing: ${field}`);
}

for (const operation of [
  'add_item',
  'update_item',
  'remove_item',
  'get_items',
  'calculate_totals',
  'clear',
  'transfer_guest_cart',
]) {
  includes(`'${operation}'`, `Cart operation is missing: ${operation}`);
}

for (const code of [
  'CART_EMPTY',
  'CART_PRODUCT_NOT_FOUND',
  'CART_ITEM_NOT_FOUND',
  'CART_QUANTITY_INVALID',
]) {
  includes(`'${code}'`, `Cart error code is missing: ${code}`);
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

  assert.strictEqual(result.status, 0, 'PHP cart contract test failed');
  console.log('PASS: cart stub contract verified with PHP');
} else {
  console.log('PASS: cart stub structure and mocked markers verified (PHP not available)');
}
