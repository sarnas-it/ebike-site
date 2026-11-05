#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'product-extra-attributes.php');
const phpTestPath = path.join(root, 'tests', 'product-extra-attributes-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Product extra attributes stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-2c698d44', 'Contract must reference spec clarify-2c698d44');
includes('ecommerce-store', 'Contract must reference the parent spec ecommerce-store');
includes('velotok_core_product_extra_attributes_contract', 'Contract accessor is missing');
includes('velotok_core_extra_attributes_contract', 'Short contract alias is missing');
includes('velotok_core_additional_attributes_contract', 'Additional contract alias is missing');
includes('velotok_core_get_product_extra_attributes', 'Extra attributes accessor is missing');
includes('velotok_core_product_extra_attributes', 'Product-prefixed accessor alias is missing');
includes('velotok_core_extra_attributes_from_values', 'Arbitrary values normalizer is missing');
includes('velotok_core_extra_attributes_open_questions', 'Open questions accessor is missing');
includes('velotok_core_extra_attribute_stub_values', 'Mocked values provider is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'required_attributes'", 'Contract must declare the required card attributes');
includes("'additional_attributes'", 'Contract must declare the additional attributes');
includes("'arbitrary'", 'Contract must allow arbitrary attributes');
includes('rendered_humanized', 'Unknown attributes must be rendered with a human label');
includes('hidden', 'Empty attribute values must be hidden');
includes("'stub_values'", 'Contract must declare the mocked value set');
includes("'future_source'", 'Contract must declare the future source');
includes("'open_questions'", 'Open questions section is missing');

for (const required of ['photo', 'title', 'description', 'price']) {
  includes(`'${required}'`, `Required card attribute is missing: ${required}`);
}

for (const key of ['model', 'battery', 'brakes', 'frame', 'max_load_kg']) {
  includes(`'${key}'`, `Known extra attribute is missing: ${key}`);
}

for (const label of [
  'Модель',
  'Аккумулятор',
  'Тормоза',
  'Рама',
  'Макс. нагрузка, кг',
]) {
  includes(label, `Extra attribute label is missing: ${label}`);
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
  console.log('PASS: product extra attributes stub contract verified with PHP');
} else {
  console.log('PASS: product extra attributes stub structure and mocked markers verified (PHP not available)');
}
