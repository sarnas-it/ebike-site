#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const modulePath = path.join(root, 'includes', 'delivery-woocommerce.php');
const zonesPath = path.join(root, 'includes', 'delivery-zones.php');
const methodsPath = path.join(root, 'includes', 'delivery-shipping-methods.php');
const phpTestPath = path.join(root, 'tests', 'delivery-woocommerce-integration-test.php');
const setupScriptPath = path.join(root, '..', '..', 'cli', 'setup.sh');
const zoneSetupPath = path.join(root, '..', '..', 'cli', 'delivery-zones.php');

assert.ok(fs.existsSync(modulePath), `WooCommerce delivery integration is missing: ${modulePath}`);
assert.ok(fs.existsSync(zonesPath), `Delivery zones module is missing: ${zonesPath}`);
assert.ok(fs.existsSync(methodsPath), `Shipping method classes are missing: ${methodsPath}`);
assert.ok(fs.existsSync(phpTestPath), `Delivery WooCommerce PHP test is missing: ${phpTestPath}`);
assert.ok(fs.existsSync(setupScriptPath), `Site setup script is missing: ${setupScriptPath}`);
assert.ok(fs.existsSync(zoneSetupPath), `Delivery zone setup script is missing: ${zoneSetupPath}`);

const source = fs.readFileSync(modulePath, 'utf8');
const methods = fs.readFileSync(methodsPath, 'utf8');
const setupScript = fs.readFileSync(setupScriptPath, 'utf8');
const zoneSetup = fs.readFileSync(zoneSetupPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

function methodIncludes(fragment, message) {
  assert.ok(methods.includes(fragment), message);
}

includes('TESTEB-28', 'Integration must reference requirement TESTEB-28');
includes("add_filter( 'woocommerce_shipping_methods'", 'Shipping methods must be registered with WooCommerce');
methodIncludes('Velotok_Shipping_Method_CDEK', 'CDEK shipping method class is missing');
methodIncludes('Velotok_Shipping_Method_Russian_Post', 'Russian Post shipping method class is missing');
methodIncludes('WC_Shipping_Method', 'Shipping methods must extend WC_Shipping_Method');
methodIncludes('calculate_shipping', 'Shipping methods must calculate their rate');
includes('woocommerce_after_shipping_rate', 'Delivery ETA must be displayed in checkout');
includes('woocommerce_checkout_update_order_meta', 'Selected delivery must be persisted on the order');
assert.ok(setupScript.includes('delivery-zones.php'), 'Site setup must configure WooCommerce delivery zones');
assert.ok(zoneSetup.includes('WC_Shipping_Zone'), 'Zone setup must create a WooCommerce shipping zone');
assert.ok(zoneSetup.includes('add_shipping_method'), 'Zone setup must attach delivery methods to the zone');

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: WooCommerce delivery integration checks skipped (PHP not available)');
  process.exit(0);
}

function runTest(integrationOverride) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_DELIVERY_WOOCOMMERCE_INTEGRATION: integrationOverride },
  });
}

const original = runTest(modulePath);
assert.strictEqual(original.status, 0, `Original WooCommerce delivery integration must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-delivery-woocommerce-'));

fs.copyFileSync(zonesPath, path.join(tmpDir, 'delivery-zones.php'));
fs.copyFileSync(methodsPath, path.join(tmpDir, 'delivery-shipping-methods.php'));

const mutated = source.replace(
  /\$methods\['cdek'\]\s*=\s*'Velotok_Shipping_Method_CDEK';/,
  ''
);

assert.notStrictEqual(mutated, source, 'Mutation must remove the CDEK shipping method registration');

const mutationPath = path.join(tmpDir, 'delivery-woocommerce.php');
fs.writeFileSync(mutationPath, mutated, 'utf8');

const broken = runTest(mutationPath);
assert.notStrictEqual(broken.status, 0, 'Integration test must fail when CDEK is not registered with WooCommerce');

const missing = runTest(path.join(tmpDir, 'missing-delivery-woocommerce.php'));
assert.notStrictEqual(missing.status, 0, 'Integration test must fail when the delivery integration module is missing');

console.log('PASS: WooCommerce delivery integration is red without shipping method registration and green with it');
