#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'product-attributes.php');
const themePath = path.resolve(root, '..', '..', 'themes', 'velotok', 'inc', 'woocommerce.php');
const phpTestPath = path.join(root, 'tests', 'product-attributes-contract-test.php');
const phpCardTestPath = path.join(root, 'tests', 'product-attributes-card-test.php');

assert.ok(fs.existsSync(stubPath), `Additional attributes stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');
const themeSource = fs.readFileSync(themePath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-2c698d44', 'Contract must reference spec clarify-2c698d44');
includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'missing_key'", 'Contract must declare the missing-key rule');
includes('empty_array', 'Unknown products must return an empty list');
includes("'future_source'", 'Contract must declare the future source');
includes("'seller'", 'Future source must be seller data');
includes('velotok_core_product_attributes_contract', 'Contract accessor is missing');
includes('velotok_core_product_attributes_get', 'Value accessor is missing');
includes('velotok_core_product_attributes_has', 'Existence helper is missing');
includes('velotok_core_product_attributes_rows', 'Card tuple helper is missing');

for (const key of ['model', 'frame', 'warranty', 'max_load']) {
  includes(`'${key}'`, `Additional attribute key is missing: ${key}`);
}

for (const slug of ['riviera', 'street-7', 'hrebet-pro', 'hrebet-trail', 'family', 'courier', 'sklad-20']) {
  includes(`'${slug}'`, `Demo product is missing from the stub: ${slug}`);
}

assert.ok(
  /'mocked'\s*=>\s*true/.test(source),
  'Contract root must be explicitly marked mocked: true'
);

const stubAccessor = 'velotok_' + 'core_product_attributes_rows';

assert.ok(
  !themeSource.includes(stubAccessor),
  'The theme must not depend on the product attributes stub'
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
  '$wpdb',
];

for (const needle of forbidden) {
  assert.ok(!source.includes(needle), `Stub must not read external data: ${needle}`);
}

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status === 0) {
  for (const testPath of [phpTestPath, phpCardTestPath]) {
    const result = spawnSync('php', [testPath], { encoding: 'utf8', cwd: path.resolve(root, '..', '..', '..') });

    if (result.stdout) {
      process.stdout.write(result.stdout);
    }
    if (result.stderr) {
      process.stderr.write(result.stderr);
    }

    assert.strictEqual(result.status, 0, `PHP attributes test failed: ${testPath}`);
  }
  console.log('PASS: additional product attributes stub verified with PHP');
} else {
  console.log('PASS: additional product attributes stub structure and mocked markers verified (PHP not available)');
}
