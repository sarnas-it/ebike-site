#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'product-validation.php');
const phpTestPath = path.join(root, 'tests', 'product-photo-validation-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Product validation stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-9e1a1acd', 'Contract must reference spec clarify-9e1a1acd');
includes('velotok_core_photo_validation_contract', 'Photo contract accessor is missing');
includes('velotok_core_validate_product_photo', 'Photo validator is missing');
includes('PHOTO_REQUIRED', 'Photo required error code is missing');
includes('PHOTO_INVALID_FORMAT', 'Photo format error code is missing');
includes("'photo'", 'Photo attribute is missing');
includes("'supported_image_mime_types'", 'Supported image MIME type list is missing');
includes("'attachment_id'", 'Photo attribute type is missing');

for (const mime of ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif']) {
  includes(mime, `Supported image MIME type is missing: ${mime}`);
}

assert.ok(
  /'mocked'\s*=>\s*true/.test(source),
  'Contract root must be explicitly marked mocked: true'
);

const photoRuleStart = source.indexOf("'photo'       => array(");
const titleRuleStart = source.indexOf("'title'       => array(");

assert.ok(photoRuleStart !== -1, 'Photo rule definition is missing');
assert.ok(titleRuleStart > photoRuleStart, 'Photo rule must precede the title rule');

const ruleSource = source.slice(photoRuleStart, titleRuleStart);

assert.ok(/'mocked'\s*=>\s*true/.test(ruleSource), 'Photo rule must be explicitly marked mocked: true');
assert.ok(ruleSource.includes("'spec'"), 'Photo rule must reference the spec identifier');

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

  assert.strictEqual(result.status, 0, 'PHP photo contract test failed');
  console.log('PASS: product photo validation stub contract verified with PHP');
} else {
  console.log('PASS: product photo validation stub structure and mocked markers verified (PHP not available)');
}
