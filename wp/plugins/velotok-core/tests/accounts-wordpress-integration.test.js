#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const integrationPath = path.join(root, 'includes', 'accounts-wordpress.php');
const phpTestPath = path.join(root, 'tests', 'accounts-wordpress-integration-test.php');

assert.ok(fs.existsSync(integrationPath), `WordPress accounts integration is missing: ${integrationPath}`);
assert.ok(fs.existsSync(phpTestPath), `WordPress accounts PHP test is missing: ${phpTestPath}`);

const source = fs.readFileSync(integrationPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('wc_create_new_customer', 'Registration must persist users through wc_create_new_customer()');
includes('wp_create_user', 'Registration must fall back to wp_create_user()');
includes('wp_insert_user', 'Registration must fall back to wp_insert_user()');
includes('wp_signon', 'Login must use wp_signon()');
includes('wp_logout', 'Logout must use wp_logout()');
includes('wp_set_auth_cookie', 'Confirmation must create a WordPress session');
includes('update_user_meta', 'Registration must store confirmation state');
includes('admin_post_nopriv_velotok_register', 'Registration must have an admin-post handler');
includes('woocommerce_created_customer', 'WooCommerce registration must be marked pending');
includes('woocommerce_after_customer_login_form', 'The registration form must be reachable from My Account');
includes('add_shortcode', 'A registration form shortcode must be registered');
includes('[velotok_register]', 'The registration form must be documented as a shortcode');
includes('velotok_core_wp_suppress_customer_created_hook', 'Registration must suppress the duplicate WooCommerce hook');

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.error && php.error.code === 'ENOENT') {
  console.log('PASS: WordPress accounts integration test skipped (php is not available)');
  process.exit(0);
}

const realRun = spawnSync('php', [phpTestPath], { cwd: root, encoding: 'utf8' });

assert.strictEqual(
  realRun.status,
  0,
  `WordPress accounts integration test failed:\n${realRun.stdout}\n${realRun.stderr}`
);

const confirmedLine = "update_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, '0' );";
const pendingLine = "update_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, 'pending' );";
const persistLine = "return wc_create_new_customer( $email, $username, $password, $customer_data );";

const unconfirmed = source
  .replace(confirmedLine, "update_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, '1' );")
  .replace(pendingLine, "update_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, 'active' );");

assert.notStrictEqual(unconfirmed, source, 'Failed to build the broken WordPress accounts integration');

const unpersisted = source.replace(persistLine, 'return 0;');

assert.notStrictEqual(unpersisted, source, 'Failed to remove customer persistence from the integration');

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-accounts-wp-'));

const env = Object.assign({}, process.env);

function runBroken(variant, filename) {
  const brokenPath = path.join(tmpDir, filename);
  fs.writeFileSync(brokenPath, variant);
  const runEnv = Object.assign({}, env, { VELOTOK_ACCOUNTS_WP_INTEGRATION: brokenPath });
  return spawnSync('php', [phpTestPath], { cwd: root, env: runEnv, encoding: 'utf8' });
}

const unconfirmedRun = runBroken(unconfirmed, 'accounts-wordpress-unconfirmed.php');
const unpresistedRun = runBroken(unpersisted, 'accounts-wordpress-unpersisted.php');

fs.rmSync(tmpDir, { recursive: true, force: true });

assert.ok(
  unconfirmedRun.status !== 0,
  'Red-green proof failed: the test passed when registration marked the account confirmed immediately'
);

assert.ok(
  unpresistedRun.status !== 0,
  'Red-green proof failed: the registration handler test passed without persisting the customer'
);

console.log('PASS: WordPress accounts integration is persistent and the test fails without email confirmation or customer persistence');
