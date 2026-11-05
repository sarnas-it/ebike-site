#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'guest-cart-registration.php');
const phpTestPath = path.join(root, 'tests', 'guest-cart-registration-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Guest cart registration stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-30af92ca', 'Contract must reference spec clarify-30af92ca');
includes('ecommerce-store', 'Contract must reference parent spec ecommerce-store');
includes('TESTEB-78', 'Contract must reference requirement TESTEB-78');
includes('TESTEB-79', 'Contract must reference requirement TESTEB-79');
includes('TESTEB-80', 'Contract must reference requirement TESTEB-80');
includes('TESTEB-81', 'Contract must reference requirement TESTEB-81');
includes('transfer_requirement', 'Contract must declare the transfer requirement marker');
includes('redirect_requirement', 'Contract must declare the redirect requirement marker');
includes('checkout_redirect_requirement', 'Contract must declare the checkout redirect requirement marker');
includes('current_task', 'Contract must declare the current task marker');
includes("'task'                    => 'TESTEB-81'", 'Contract must be marked with task TESTEB-81');
includes("'current_task'            => 'TESTEB-81'", 'Current task must be TESTEB-81');
includes("'requirement_id'          => 'TESTEB-81'", 'Requirement id must be TESTEB-81');
includes("'redirect_requirement'    => 'TESTEB-81'", 'Redirect requirement must be TESTEB-81');
includes('velotok_core_guest_cart_checkout_redirect_contract', 'Dedicated checkout redirect contract accessor is missing');
includes('velotok_core_guest_cart_redirect_contract', 'Shorter checkout redirect contract accessor is missing');
includes('velotok_core_guest_cart_redirect_stub_contract', 'Redirect stub contract alias is missing');
includes('velotok_core_guest_cart_redirect_data_contract', 'Redirect data contract alias is missing');
includes('velotok_core_guest_cart_redirect_to_checkout', 'Redirect to checkout helper is missing');
includes('velotok_core_guest_cart_redirect_after_transfer', 'Redirect after transfer helper is missing');
includes('guestCartCheckoutRedirectContract', 'Global checkout redirect contract alias is missing');
includes('guestCartRedirectContract', 'Global redirect contract alias is missing');
includes('guestCartRedirectToCheckout', 'Global redirect helper alias is missing');
includes('guestCartCheckoutRedirect', 'Global checkout redirect helper alias is missing');
includes('velotok_core_guest_cart_transfer_contract', 'Dedicated transfer contract accessor is missing');
includes('velotok_core_guest_cart_transfer_stub_contract', 'Dedicated transfer stub contract alias is missing');
includes('velotok_core_guest_cart_transfer_data_contract', 'Dedicated transfer data contract alias is missing');
includes('velotok_core_guest_cart_transfer_after_registration', 'Transfer after registration helper is missing');
includes('guestCartTransferContract', 'Global transfer contract alias is missing');
includes('guest_cart_register_transfer_checkout', 'Contract must declare the guest cart operation');
includes('velotok_core_guest_cart_registration_contract', 'Guest cart contract accessor is missing');
includes('velotok_core_guest_cart_registration_stub_contract', 'Guest cart stub contract alias is missing');
includes('velotok_core_guest_cart_registration_data_contract', 'Guest cart data contract alias is missing');
includes('velotok_core_guest_cart_create', 'Guest cart creation helper is missing');
includes('velotok_core_guest_cart_add', 'Guest cart add helper is missing');
includes('velotok_core_guest_cart_register', 'Guest registration helper is missing');
includes('velotok_core_guest_cart_transfer', 'Guest cart transfer helper is missing');
includes('velotok_core_guest_cart_checkout_redirect', 'Checkout redirect helper is missing');
includes('velotok_core_guest_cart_buy', 'Buy flow helper is missing');
includes('velotok_core_guest_cart_open_questions', 'Open questions accessor is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'parent_spec'", 'Contract must declare the parent spec');
includes("'guest_cart_allowed'", 'Guest cart permission marker is missing');
includes("'registers_on_buy'", 'Registration on buy marker is missing');
includes("'transfers_after_registration'", 'Transfer after registration marker is missing');
includes("'attaches_to_user_account'", 'Transfer must attach the cart to the user account');
includes("'requires_registration'", 'Transfer must require registration');
includes("'redirects_to_checkout'", 'Checkout redirect marker is missing');
includes("'guest_cart'", 'Contract must declare guest cart rules');
includes("'allow_unauthenticated'", 'Guest cart must allow unauthenticated visitors');
includes("'accumulate_items'", 'Guest cart must accumulate items');
includes("'buy_action'", 'Contract must declare the buy action');
includes("'registers_user'", 'Buy action must register the user');
includes("'registration'", 'Contract must declare registration rules');
includes("'creates_account'", 'Registration must create an account');
includes("'transfer'", 'Contract must declare transfer rules');
includes("'merge_quantities'", 'Guest cart merge strategy is missing');
includes("'preserve_user_cart'", 'Guest cart transfer must preserve the user cart');
includes("'clear_guest_after_transfer'", 'Guest cart clearing rule is missing');
includes("'redirect'", 'Contract must declare redirect rules');
includes("'after_transfer'", 'Redirect must happen after transfer');
includes("'requires_transfer'", 'Redirect must require the guest cart transfer');
includes("'next_step'", 'Redirect must declare the next checkout step');
includes("'/checkout/'", 'Checkout redirect target is missing');
includes("'open_questions'", 'Open questions section is missing');
includes("'authentication'", 'Authentication open question is missing');
includes("'existing_user'", 'Existing user open question is missing');
includes("'merge_rules'", 'Merge rules open question is missing');
includes("'checkout_target'", 'Checkout target open question is missing');
includes("'local_run'", 'Local run open question is missing');

for (const operation of [
  'create_guest_cart',
  'add_item',
  'register',
  'transfer_cart',
  'buy',
  'redirect_to_checkout',
]) {
  includes(`'${operation}'`, `Guest cart operation is missing: ${operation}`);
}

for (const code of [
  'CART_EMPTY',
  'REGISTRATION_EMAIL_REQUIRED',
  'REGISTRATION_EMAIL_INVALID',
  'REGISTRATION_EMAIL_EXISTS',
  'GUEST_CART_TRANSFER_FAILED',
  'GUEST_CART_TRANSFER_REQUIRED',
]) {
  includes(`'${code}'`, `Guest cart error code is missing: ${code}`);
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

  assert.strictEqual(result.status, 0, 'PHP guest cart registration contract test failed');
  console.log('PASS: guest cart registration stub contract verified with PHP');
} else {
  console.log('PASS: guest cart registration structure and mocked markers verified (PHP not available)');
}
