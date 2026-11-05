#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'inc', 'theme-design-variants.php');
const phpTestPath = path.join(__dirname, 'theme-design-variants-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Theme design variants stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Theme design variants PHP contract test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-c1924ac5', 'Contract must reference spec clarify-c1924ac5');
includes('velotok_theme_design_variants_contract', 'Contract accessor is missing');
includes('velotok_theme_design_variants', 'Variants accessor is missing');
includes('velotok_theme_design_variant_keys', 'Variant keys accessor is missing');
includes('velotok_theme_design_variant_statuses', 'Status accessor is missing');
includes('velotok_theme_design_variant_key', 'Variant normalization helper is missing');
includes('velotok_theme_select_design_variant', 'Stable selection facade is missing');
includes('velotok_theme_design_select_variant', 'Design-prefixed selection alias is missing');
includes('velotok_theme_design_variant_states', 'Variant status builder is missing');
includes('velotok_theme_design_variants_open_questions', 'Open questions accessor is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'operation'", 'Contract must declare the operation');
includes("'select_design_variant'", 'Selection operation must be named select_design_variant');
includes("'required_variants'", 'Contract must declare the available variants');
includes("'variants'", 'Contract must declare the variant definitions');
includes("'selected_status'", 'Contract must declare the selected status');
includes("'rejected_status'", 'Contract must declare the rejected status');
includes("'network'", 'Contract must declare the network behaviour');
includes("'persistence'", 'Contract must declare the persistence behaviour');
includes("'open_questions'", 'Open questions section is missing');
includes('DESIGN_VARIANT_REQUIRED', 'Missing variant error code is missing');
includes('DESIGN_VARIANT_UNKNOWN', 'Unknown variant error code is missing');
includes('DESIGN_VARIANT_INVALID', 'Invalid variant error code is missing');

assert.ok(
  /'mocked'\s*=>\s*true/.test(source),
  'Contract root must be explicitly marked mocked: true'
);

assert.ok(
  /'selected_status'\s*=>\s*'selected'/.test(source),
  'Selected status must be selected'
);

assert.ok(
  /'rejected_status'\s*=>\s*'rejected'/.test(source),
  'Rejected status must be rejected'
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
  'fsockopen(',
  'stream_socket_' + 'client(',
  'socket_' + 'create(',
];

for (const needle of forbidden) {
  assert.ok(!source.includes(needle), `Stub must not read external data or make network calls: ${needle}`);
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
  console.log('PASS: theme design variants stub contract verified with PHP');
} else {
  console.log('PASS: theme design variants stub structure and mocked markers verified (PHP not available)');
}
