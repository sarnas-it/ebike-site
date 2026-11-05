#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const themeRoot = path.resolve(root, '..', '..', 'themes', 'velotok');
const stubPath = path.join(root, 'includes', 'product-validation.php');
const phpTestPath = path.join(root, 'tests', 'product-card-required-attributes-contract-test.php');
const cardTemplatePath = path.join(themeRoot, 'template-parts', 'model-card.php');
const cardRenderTestPath = path.join(themeRoot, 'tests', 'product-card-required-attributes-render-test.php');

assert.ok(fs.existsSync(stubPath), `Product card service stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Product card service PHP test is missing: ${phpTestPath}`);
assert.ok(fs.existsSync(cardTemplatePath), `Product card template is missing: ${cardTemplatePath}`);
assert.ok(fs.existsSync(cardRenderTestPath), `Product card render test is missing: ${cardRenderTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');
const cardTemplate = fs.readFileSync(cardTemplatePath, 'utf8');

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
includes("'card_attributes'", 'Saved card attributes are missing');

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

for (const key of ['photo', 'title', 'description', 'price']) {
  assert.ok(
    cardTemplate.includes(`data-card-attribute="${key}"`),
    `Product card template does not render required attribute: ${key}`
  );
}

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

function runPhpTest(testPath) {
  const result = spawnSync('php', [testPath], { encoding: 'utf8' });

  if (result.stdout) {
    process.stdout.write(result.stdout);
  }
  if (result.stderr) {
    process.stderr.write(result.stderr);
  }

  assert.strictEqual(result.status, 0, `PHP product card test failed: ${testPath}`);
}

if (php.status === 0) {
  runPhpTest(phpTestPath);
  runPhpTest(cardRenderTestPath);
  console.log('PASS: product card required attributes service and rendered card verified with PHP');
} else {
  console.log('PASS: product card required attributes service structure verified (PHP not available)');
}
