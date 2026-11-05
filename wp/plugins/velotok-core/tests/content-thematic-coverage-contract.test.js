#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'content-thematic-coverage.php');
const phpTestPath = path.join(root, 'tests', 'content-thematic-coverage-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Content thematic coverage stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('clarify-a2fd2f66', 'Contract must reference spec clarify-a2fd2f66');
includes('ecommerce-store', 'Contract must reference the parent spec ecommerce-store');
includes('velotok_core_content_thematic_contract', 'Contract accessor is missing');
includes('velotok_core_content_thematic_coverage_contract', 'Explicit coverage contract alias is missing');
includes('velotok_core_content_topics', 'Topics accessor is missing');
includes('velotok_core_content_topic_key', 'Topic resolver is missing');
includes('velotok_core_validate_content_topic', 'Topic validator is missing');
includes('velotok_core_content_matches_topic', 'Topic matching helper is missing');
includes('velotok_core_classify_content', 'Content classifier is missing');
includes('velotok_core_check_content_coverage', 'Coverage checker is missing');
includes('velotok_core_validate_content_coverage', 'Coverage validation alias is missing');
includes('velotok_core_content_open_questions', 'Open questions accessor is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'parent_spec'", 'Contract must declare the parent spec');
includes("'required_topics'", 'Contract must declare required topics');
includes("'membership'", 'Contract must declare the membership rule');
includes('hybrid', 'Membership must be hybrid');
includes("'open_questions'", 'Open questions section is missing');

for (const topic of ['models_overview', 'legal_requirements', 'news']) {
  includes(`'${topic}'`, `Required content topic is missing: ${topic}`);
}

for (const alias of ['model_overview', 'green_energy_news']) {
  includes(`'${alias}'`, `Legacy content topic alias is missing: ${alias}`);
}

includes("'require_date'", 'News topic must require an event date');
includes('velotok_core_green_energy_news_date', 'News event date helper is missing');

for (const label of [
  'Обзор моделей в зелёной энергетике',
  'Требования законодательства',
  'Новости о зелёной энергетике',
]) {
  includes(label, `Content topic label is missing: ${label}`);
}

for (const code of [
  'TOPIC_UNKNOWN',
  'MODEL_OVERVIEW_MISMATCH',
  'LEGAL_REQUIREMENTS_MISMATCH',
  'GREEN_ENERGY_NEWS_MISMATCH',
  'MODEL_PROMOTION_DETECTED',
  'CONTENT_COVERAGE_INCOMPLETE',
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
  console.log('PASS: content thematic coverage stub contract verified with PHP');
} else {
  console.log('PASS: content thematic coverage stub structure and mocked markers verified (PHP not available)');
}
