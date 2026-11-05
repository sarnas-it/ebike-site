#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const pluginRoot = path.resolve(__dirname, '..');
const contractPath = path.join(pluginRoot, 'includes', 'checkout-contracts.php');
const integrationPath = path.join(pluginRoot, 'includes', 'checkout-integration.php');
const paymentPath = path.join(pluginRoot, 'includes', 'payment-gateway.php');
const shippingPath = path.join(pluginRoot, 'includes', 'shipping-methods.php');
const phpTestPath = path.join(__dirname, 'checkout-contract-test.php');

for (const file of [contractPath, integrationPath, paymentPath, shippingPath]) {
  assert.ok(fs.existsSync(file), `Checkout stub file is missing: ${file}`);
}

const contract = fs.readFileSync(contractPath, 'utf8');
const integration = fs.readFileSync(integrationPath, 'utf8');
const payment = fs.readFileSync(paymentPath, 'utf8');
const shipping = fs.readFileSync(shippingPath, 'utf8');

function includes(source, fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes(contract, 'clarify-096bb945', 'Contract must reference spec clarify-096bb945');
includes(contract, "'source'", 'Contract must declare its source');
includes(contract, "'stub'", 'Contract source must be the stub');
includes(contract, 'velotok_core_checkout_contract', 'Contract accessor is missing');
includes(contract, 'velotok_core_delivery_methods', 'Delivery methods accessor is missing');
includes(contract, 'velotok_core_delivery_method', 'Delivery method accessor is missing');
includes(contract, 'velotok_core_payment_contract', 'Payment contract accessor is missing');
includes(contract, 'velotok_core_human_acceptance_contract', 'Human acceptance accessor is missing');

assert.ok(
  /'mocked'\s*=>\s*true/.test(contract),
  'Contract root must be explicitly marked mocked: true'
);

for (const fragment of [
  'yookassa',
  'ЮKassa',
  "gateway_id'     => 'velotok_yookassa_stub'",
  "postal_rf",
  'Почта РФ',
  "cdek",
  'СДЭК',
  "other_logist",
  'Иной логист',
  "project_lead",
  'Руководитель проекта',
  'velotok_accept_order',
]) {
  includes(contract, fragment, `Contract is missing: ${fragment}`);
}

for (const hook of [
  'woocommerce_review_order_before_payment',
  'woocommerce_checkout_process',
  'woocommerce_checkout_create_order',
  'woocommerce_admin_order_data_after_billing_address',
  '_velotok_delivery_method',
  'velotok_core_register_project_lead_role',
  'velotok_core_accept_order',
  'admin_post_velotok_accept_order',
  'velotok_core_load_payment_gateway',
  'velotok_core_load_shipping_methods',
]) {
  includes(integration, hook, `Checkout integration is missing: ${hook}`);
}

for (const fragment of [
  'class Velotok_YooKassa_Stub_Gateway extends WC_Payment_Gateway',
  "'velotok_yookassa_stub'",
  'public $mocked = true;',
  'woocommerce_payment_gateways',
  'mocked: true',
]) {
  includes(payment, fragment, `YooKassa stub is missing: ${fragment}`);
}

for (const fragment of [
  'class Velotok_Stub_Shipping_Method extends WC_Shipping_Method',
  'Velotok_Postal_RF_Shipping_Method',
  'Velotok_CDEK_Shipping_Method',
  'Velotok_Other_Logist_Shipping_Method',
  "'velotok_postal_rf'",
  "'velotok_cdek'",
  "'velotok_other_logist'",
  'public $mocked = true;',
  'woocommerce_shipping_methods',
]) {
  includes(shipping, fragment, `Delivery stub is missing: ${fragment}`);
}

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
  assert.ok(!contract.includes(needle), `Contract stub must not read external data: ${needle}`);
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
  console.log('PASS: checkout, delivery and payment stub contract verified with PHP');
} else {
  console.log('PASS: checkout, delivery and payment stub structure and mocked markers verified (PHP not available)');
}
