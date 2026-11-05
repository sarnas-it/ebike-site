#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'provisioning.php');
const phpTestPath = path.join(root, 'tests', 'provisioning-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Provisioning contract stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Provisioning PHP contract test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-497c0475', 'Contract must reference spec clarify-497c0475');
includes('clarify-096bb945', 'Contract must reference the parent spec clarify-096bb945');
includes('velotok_core_provisioning_contract', 'Contract accessor is missing');
includes('velotok_core_provisioning_operation_contract', 'Explicit operation contract alias is missing');
includes('velotok_core_provisioning_open_questions', 'Open questions accessor is missing');
includes('velotok_core_provisioning_env_name', 'Future env name accessor is missing');
includes('velotok_core_provision', 'Stable provisioning facade is missing');
includes('velotok_core_provisioning_mock', 'Mocked provisioning implementation is missing');
includes('velotok_core_provisioning_register_provider', 'Provider registration point is missing');
includes('velotok_core_provisioning_reset_provider', 'Provider reset helper is missing');
includes('velotok_core_provisioning_is_mocked', 'Mocked state helper is missing');
includes('velotok_core_provisioning_prepare_input', 'Input validation helper is missing');
includes('velotok_core_provisioning_hash', 'Deterministic hash helper is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'operation'", 'Contract must declare the operation');
includes("'provision'", 'Provisioning operation must be named provision');
includes("'network'", 'Contract must declare the network behaviour');
includes("'persistence'", 'Contract must declare the persistence behaviour');
includes("'idempotency'", 'Contract must declare the idempotency behaviour');
includes("'request'", 'Contract must declare the request shape');
includes("'response'", 'Contract must declare the response shape');
includes("'error_codes'", 'Contract must declare error codes');
includes('PROVISIONING_INVALID_INPUT', 'Invalid input error code is missing');
includes('PROVISIONING_MISSING_PARAMETER', 'Missing parameter error code is missing');
includes('VELOTOK_PROVISIONING_PROVIDER', 'Future provider env name is missing');
includes('VELOTOK_PROVISIONING_ENDPOINT', 'Future endpoint env name is missing');
includes('VELOTOK_PROVISIONING_TOKEN', 'Future token env name is missing');
includes('VELOTOK_PROVISIONING_TIMEOUT', 'Future timeout env name is missing');
includes('open_questions', 'Open questions section is missing');

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
  console.log('PASS: provisioning stub contract verified with PHP');
} else {
  console.log('PASS: provisioning stub structure and mocked markers verified (PHP not available)');
}
