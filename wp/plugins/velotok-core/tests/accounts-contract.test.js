#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'accounts.php');
const phpTestPath = path.join(root, 'tests', 'accounts-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Accounts stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Accounts PHP test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('accounts', 'Contract must reference spec accounts');
includes('TESTEB-1', 'Contract must reference requirement TESTEB-1');
includes('velotok_core_accounts_contract', 'Accounts contract accessor is missing');
includes('velotok_core_accounts_stub_contract', 'Accounts stub contract alias is missing');
includes('velotok_core_accounts_data_contract', 'Accounts data contract alias is missing');
includes('velotok_core_account_register', 'Account registration helper is missing');
includes('velotok_core_account_confirm_email', 'Email confirmation helper is missing');
includes('velotok_core_account_login', 'Account login helper is missing');
includes('velotok_core_account_logout', 'Account logout helper is missing');
includes('velotok_core_account_current_user', 'Current user helper is missing');
includes('velotok_core_account_can_access', 'Personal area access helper is missing');
includes('velotok_core_account_transfer_guest_cart', 'Guest cart transfer helper is missing');
includes('velotok_core_account_checkout_redirect', 'Checkout redirect helper is missing');
includes('email_confirmation_required', 'Registration must require email confirmation');
includes('existing_email_strategy', 'Existing email strategy must be declared');
includes('ACCOUNT_EMAIL_NOT_CONFIRMED', 'Unconfirmed login error is missing');
includes('ACCOUNT_ACCESS_DENIED', 'Personal area denial error is missing');
includes('mocked', 'Contract must be marked as mocked');
includes("'/checkout/'", 'Checkout redirect target is missing');

const php = spawnSync('php', [phpTestPath], { cwd: root, encoding: 'utf8' });

if (php.error) {
  if (php.error.code === 'ENOENT') {
    console.log('PASS: PHP contract test skipped (php is not available)');
    process.exit(0);
  }

  throw php.error;
}

assert.strictEqual(
  php.status,
  0,
  `Accounts PHP contract test failed:\n${php.stdout}\n${php.stderr}`
);

console.log('PASS: accounts contract and PHP behavior test passed');
