#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'content-articles.php');
const phpTestPath = path.join(root, 'tests', 'content-articles-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Content articles stub is missing: ${stubPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

function includes(fragment, message) {
  assert.ok(source.includes(fragment), message);
}

includes('ecommerce-store', 'Contract must reference spec ecommerce-store');
includes('TESTEB-149', 'Contract must reference requirement TESTEB-149');
includes('velotok_core_content_articles_contract', 'Contract accessor is missing');
includes('velotok_core_content_articles_coverage_contract', 'Explicit coverage contract alias is missing');
includes('velotok_core_content_article_topics', 'Topics accessor is missing');
includes('velotok_core_content_article_topic_key', 'Topic resolver is missing');
includes('velotok_core_validate_content_article', 'Article validator is missing');
includes('velotok_core_content_article_matches_topic', 'Article matching helper is missing');
includes('velotok_core_content_articles', 'Articles accessor is missing');
includes('velotok_core_get_content_articles', 'Article list accessor is missing');
includes('velotok_core_content_article', 'Single article accessor is missing');
includes('velotok_core_check_content_articles_coverage', 'Coverage checker is missing');
includes('velotok_core_validate_content_articles_coverage', 'Coverage validation alias is missing');
includes('velotok_core_content_articles_open_questions', 'Open questions accessor is missing');

includes("'mocked'", 'Contract must declare the mocked marker');
includes("'source'", 'Contract must declare its source');
includes("'stub'", 'Contract source must be the stub');
includes("'parent_spec'", 'Contract must declare the parent spec');
includes("'required_topics'", 'Contract must declare required topics');
includes("'membership'", 'Contract must declare the membership rule');
includes('hybrid', 'Membership must be hybrid');
includes("'open_questions'", 'Open questions section is missing');
includes("'articles'", 'Contract must expose mocked articles');

for (const topic of ['product_articles', 'store_articles']) {
  includes(`'${topic}'`, `Required content topic is missing: ${topic}`);
}

for (const label of ['Статьи о товарах', 'Статьи о магазине']) {
  includes(label, `Content topic label is missing: ${label}`);
}

for (const code of [
  'TOPIC_UNKNOWN',
  'PRODUCT_ARTICLES_MISMATCH',
  'STORE_ARTICLES_MISMATCH',
  'CONTENT_ARTICLE_INVALID',
  'CONTENT_ARTICLE_INCOMPLETE',
  'CONTENT_ARTICLE_FIELD_REQUIRED',
  'CONTENT_ARTICLES_INCOMPLETE',
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
  console.log('PASS: content articles stub contract verified with PHP');
} else {
  console.log('PASS: content articles stub structure and mocked markers verified (PHP not available)');
}
