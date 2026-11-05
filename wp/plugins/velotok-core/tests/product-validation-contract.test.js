#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'product-validation.php');
const phpTestPath = path.join(root, 'tests', 'product-validation-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Product validation stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-9e1a1acd', 'Contract must reference spec clarify-9e1a1acd');
includes('velotok_core_product_validation_contract', 'Contract accessor is missing');
includes('velotok_core_get_required_attributes', 'Required attributes accessor is missing');
includes('velotok_core_validate_product_title', 'Dedicated title validator is missing');
includes('velotok_core_validate_product_description', 'Description validator is missing');
includes('velotok_core_validate_product_price', 'Price validator is missing');
includes('velotok_core_validate_product_photo', 'Photo validator is missing');
includes('velotok_core_validate_product_publication', 'Publication validator is missing');

for (const key of ['photo', 'title', 'description', 'price']) {
  includes(`'${key}'`, `Required attribute is missing: ${key}`);
}

for (const code of [
  'TITLE_REQUIRED',
  'TITLE_TOO_LONG',
  'DESCRIPTION_REQUIRED',
  'PRICE_INVALID_FORMAT',
  'PRICE_NEGATIVE',
  'PHOTO_REQUIRED',
]) {
  includes(`'${code}'`, `Validation error code is missing: ${code}`);
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
  console.log('PASS: product validation stub contract verified with PHP');
} else {
  console.log('PASS: product validation stub structure and mocked markers verified (PHP not available)');
}
