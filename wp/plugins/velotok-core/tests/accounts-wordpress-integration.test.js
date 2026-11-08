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

const broken = source
  .replace(confirmedLine, "update_user_meta( $user_id, VELOTOK_ACCOUNT_CONFIRMED_META, '1' );")
  .replace(pendingLine, "update_user_meta( $user_id, VELOTOK_ACCOUNT_STATUS_META, 'active' );");

assert.notStrictEqual(broken, source, 'Failed to build the broken WordPress accounts integration');

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-accounts-wp-'));
const brokenPath = path.join(tmpDir, 'accounts-wordpress.php');
fs.writeFileSync(brokenPath, broken);

const env = Object.assign({}, process.env, { VELOTOK_ACCOUNTS_WP_INTEGRATION: brokenPath });
const brokenRun = spawnSync('php', [phpTestPath], { cwd: root, env, encoding: 'utf8' });

fs.rmSync(tmpDir, { recursive: true, force: true });

assert.ok(
  brokenRun.status !== 0,
  'Red-green proof failed: the test passed when registration marked the account confirmed immediately'
);

console.log('PASS: WordPress accounts integration is persistent and the test fails without email confirmation');
