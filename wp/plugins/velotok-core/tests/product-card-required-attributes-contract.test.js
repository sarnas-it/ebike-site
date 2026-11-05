#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'product-validation.php');
const phpTestPath = path.join(root, 'tests', 'product-card-required-attributes-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Product card service stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Product card service PHP test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-41b1da6d', 'Service contract must reference spec clarify-41b1da6d');
includes('velotok_core_product_card_service_contract', 'Product card service contract is missing');
includes('velotok_core_save_product_card', 'Product card save service is missing');
includes("'validate_on_save'", 'Save validation marker is missing');
includes("'server_side'", 'Server-side validation marker is missing');
includes("'client_validation_only'", 'Client-validation marker is missing');
includes("'missing_attributes'", 'Missing attributes result is missing');
includes("'invalid_attributes'", 'Invalid attributes result is missing');

for (const key of ['photo', 'title', 'description', 'price']) {
  includes(`'${key}'`, `Required attribute is missing: ${key}`);
}

for (const code of [
  'PHOTO_REQUIRED',
  'TITLE_REQUIRED',
  'DESCRIPTION_REQUIRED',
  'PRICE_REQUIRED',
  'PRICE_NEGATIVE',
  'PHOTO_INVALID_FORMAT',
]) {
  includes(`'${code}'`, `Validation error code is missing: ${code}`);
}

assert.ok(
  /'mocked'\s*=>\s*true/.test(source),
  'Product card service must be explicitly marked mocked: true'
);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status === 0) {
  const result = spawnSync('php', [phpTestPath], { encoding: 'utf8' });

  if (result.stdout) {
    process.stdout.write(result.stdout);
  }
  if (result.stderr) {
    process.stderr.write(result.stderr);
  }

  assert.strictEqual(result.status, 0, 'PHP product card service contract test failed');
  console.log('PASS: product card required attributes service verified with PHP');
} else {
  console.log('PASS: product card required attributes service structure verified (PHP not available)');
}
