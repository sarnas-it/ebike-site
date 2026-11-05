#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'spec-data.php');
const phpTestPaths = [
  path.join(root, 'tests', 'spec-data-contract-test.php'),
  path.join(root, 'tests', 'spec-data-runtime-contract-test.php'),
];

assert.ok(fs.existsSync(stubPath), `Specification data stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-a1b91b62', 'Contract must reference spec clarify-a1b91b62');
includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'missing_key'", 'Contract must declare the missing-key rule');
includes('empty_string', 'Missing keys must be fixed to empty strings');
includes("'future_source'", 'Contract must declare the future source');
includes("'env'", 'Future source must be env');
includes('velotok_core_spec_data', 'Contract accessor is missing');
includes('velotok_core_spec_get', 'Value accessor is missing');
includes('velotok_core_spec_has', 'Key existence helper is missing');
includes('velotok_core_spec_env_name', 'Environment name mapper is missing');
includes('velotok_core_spec_is_mocked', 'Mocked-provider helper is missing');
includes("'accessor'", 'Contract must declare its single access point');

for (const key of [
  'shop_phone',
  'shop_email',
  'shop_address',
  'shop_hours',
  'catalog_model_types',
  'catalog_range',
  'warranty_years',
  'delivery_cities',
  'installment_months',
]) {
  includes(`'${key}'`, `Specification key is missing: ${key}`);
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
  for (const phpTestPath of phpTestPaths) {
    const result = spawnSync('php', [phpTestPath], { encoding: 'utf8' });

    if (result.stdout) {
      process.stdout.write(result.stdout);
    }
    if (result.stderr) {
      process.stderr.write(result.stderr);
    }

    assert.strictEqual(
      result.status,
      0,
      `PHP contract test failed: ${path.basename(phpTestPath)}`
    );
  }

  console.log('PASS: specification data stub contract verified with PHP');
} else {
  console.log('PASS: specification data stub structure and mocked markers verified (PHP not available)');
}
