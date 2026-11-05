#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'catalog.php');
const phpTestPath = path.join(root, 'tests', 'catalog-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Catalog stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('ecommerce-store', 'Contract must reference spec ecommerce-store');
includes('TESTEB-150', 'Contract must reference requirement TESTEB-150');
includes('velotok_core_catalog_contract', 'Contract accessor is missing');
includes('velotok_core_product_catalog_contract', 'Product catalog contract alias is missing');
includes('velotok_core_catalog_data_contract', 'Catalog data contract alias is missing');
includes('velotok_core_catalog_categories', 'Categories accessor is missing');
includes('velotok_core_catalog_category', 'Single category accessor is missing');
includes('velotok_core_catalog_products', 'Products accessor is missing');
includes('velotok_core_get_catalog_products', 'Get-prefixed products accessor is missing');
includes('velotok_core_catalog_product', 'Single product accessor is missing');
includes('velotok_core_catalog_has_product', 'Product existence helper is missing');
includes('velotok_core_catalog_count', 'Product count helper is missing');
includes('velotok_core_catalog_open_questions', 'Open questions accessor is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'parent_spec'", 'Contract must declare the parent spec');
includes("'required_fields'", 'Contract must declare required product fields');
includes("'categories'", 'Contract must declare catalog categories');
includes("'products'", 'Contract must expose mocked products');
includes("'product_type'", 'Contract must declare the product type');
includes('electric_bicycle', 'Catalog products must be electric bicycles');
includes("'open_questions'", 'Open questions section is missing');

for (const category of ['city', 'mountain', 'cargo', 'folding']) {
  includes(`'${category}'`, `Required catalog category is missing: ${category}`);
}

for (const slug of ['riviera', 'street-7', 'hrebet-pro', 'hrebet-trail', 'family', 'courier', 'sklad-20']) {
  includes(`'${slug}'`, `Mocked product is missing: ${slug}`);
}

for (const label of [
  'Городские',
  'Горные',
  'Грузовые',
  'Складные',
]) {
  includes(label, `Catalog category label is missing: ${label}`);
}

for (const code of [
  'CATALOG_PRODUCT_NOT_FOUND',
  'CATALOG_CATEGORY_UNKNOWN',
  'CATALOG_EMPTY',
]) {
  includes(`'${code}'`, `Contract error code is missing: ${code}`);
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
  console.log('PASS: catalog stub contract verified with PHP');
} else {
  console.log('PASS: catalog stub structure and mocked markers verified (PHP not available)');
}
