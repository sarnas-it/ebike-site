#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const modulePath = path.join(root, 'includes', 'delivery-zones.php');
const phpTestPath = path.join(root, 'tests', 'delivery-zones-contract-test.php');

assert.ok(fs.existsSync(modulePath), `Delivery zones module is missing: ${modulePath}`);
assert.ok(fs.existsSync(phpTestPath), `Delivery zones PHP contract test is missing: ${phpTestPath}`);

const source = fs.readFileSync(modulePath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('TESTEB-28', 'Contract must reference requirement TESTEB-28');
includes('woocommerce_zones', 'Contract must declare the WooCommerce zones source');
includes('reads_woocommerce_zones', 'Contract must declare that WooCommerce zones are read');
includes('WC_Shipping_Zones', 'WooCommerce shipping zones accessor is missing');
includes('velotok_core_delivery_zone_methods', 'Zone delivery methods helper is missing');
includes('velotok_core_delivery_zones_match', 'Zone matcher is missing');
includes('velotok_core_delivery_zones_eta_range', 'ETA range helper is missing');
includes('velotok_core_delivery_zones_method_cost', 'Delivery cost helper is missing');
includes('velotok_core_delivery_zones_pickup_option', 'Pickup option helper is missing');
includes('independent_of_zone', 'Pickup must be independent of the zone');
includes('only_russia', 'Pickup must be limited to Russia');
includes('DELIVERY_ZONE_NOT_FOUND', 'Out-of-zone error code is missing');
includes('DELIVERY_ADDRESS_AMBIGUOUS', 'Ambiguous address error code is missing');
includes('DELIVERY_PROVIDER_ERROR', 'Provider error code is missing');
includes('fallback_tariff', 'No fake tariff fallback must be declared');
includes("=> false", 'Provider fallback must be disabled');
includes('local_pickup', 'Pickup method identifier is missing');

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status === 0) {
  const result = spawnSync('php', [phpTestPath], { encoding: 'utf8' });

  if (result.stdout) {
    process.stdout.write(result.stdout);
  }
  if (result.stderr) {
    process.stderr.write(result.stderr);
  }

  assert.strictEqual(result.status, 0, 'PHP delivery zones contract test failed');
  console.log('PASS: delivery zones contract verified with PHP');
} else {
  console.log('PASS: delivery zones contract structure verified (PHP not available)');
}
