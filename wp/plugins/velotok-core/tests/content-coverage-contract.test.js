#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'content-coverage.php');
const phpTestPath = path.join(root, 'tests', 'content-coverage-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Content coverage stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-a2fd2f66', 'Contract must reference spec clarify-a2fd2f66');
includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'classification'", 'Contract must declare the classification mode');
includes('hybrid', 'Classification mode must be hybrid');
includes('model_review', 'Model review category is missing');
includes('legislation', 'Legislation category is missing');
includes('green_energy_news', 'Green energy news category is missing');
includes('Обзор моделей в зелёной энергетике', 'Model review label is missing');
includes('Требования законодательства', 'Legislation label is missing');
includes('Новости о зелёной энергетике', 'Green energy news label is missing');
includes('velotok_core_content_coverage_contract', 'Contract accessor is missing');
includes('velotok_core_content_coverage_has_full_coverage', 'Coverage helper is missing');
includes('velotok_core_content_coverage_evaluate', 'Evaluation helper is missing');
includes('velotok_core_content_coverage_is_compliant', 'Category check helper is missing');

assert.ok(
  /'mocked'\s*=>\s*true/.test(source),
  'Contract root must be explicitly marked mocked: true'
);

assert.ok(
  /'spec'\s*=>\s*'clarify-a2fd2f66'/.test(source),
  'Contract root must reference clarify-a2fd2f66'
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
  console.log('PASS: content coverage stub contract verified with PHP');
} else {
  console.log('PASS: content coverage stub structure and mocked markers verified (PHP not available)');
}
