#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'green-energy-news.php');
const phpTestPath = path.join(root, 'tests', 'green-energy-news-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Green energy news stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Green energy news contract test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

for (const fragment of [
  'clarify-a2fd2f66',
  'TESTEB-122',
  'velotok_core_green_energy_news_contract',
  'velotok_core_green_energy_news_check',
  'velotok_core_green_energy_news_date',
  'GREEN_ENERGY_NEWS_EVENT_REQUIRED',
  'GREEN_ENERGY_NEWS_DATE_REQUIRED',
  'GREEN_ENERGY_NEWS_DATE_INVALID',
]) {
  assert.ok(source.includes(fragment), `Green energy news stub is missing: ${fragment}`);
}

assert.ok(/'mocked'\s*=>\s*true/.test(source), 'Stub must be explicitly marked mocked: true');
assert.ok(/'source'\s*=>\s*'stub'/.test(source), 'Stub source must be stub');

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(stubOverride) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_GREEN_ENERGY_NEWS_STUB: stubOverride },
  });
}

const original = runTest(stubPath);
assert.strictEqual(original.status, 0, `Original green energy news stub must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-green-energy-news-'));

try {
  const mutatedPath = path.join(tmpDir, 'green-energy-news.php');

  const dateMutation = source.replace(
    "$has_date  = '' !== $date;",
    '$has_date  = true;'
  );
  assert.notStrictEqual(dateMutation, source, 'Mutation must disable the date check');
  fs.writeFileSync(mutatedPath, dateMutation, 'utf8');

  const dateBroken = runTest(mutatedPath);
  assert.notStrictEqual(
    dateBroken.status,
    0,
    'Contract test must fail when a news material without a date is accepted'
  );

  const eventMutation = source.replace(
    '$has_event = ! empty( $event_matches ) && ! empty( $context_matches );',
    '$has_event = true;'
  );
  assert.notStrictEqual(eventMutation, source, 'Mutation must disable the event check');
  fs.writeFileSync(mutatedPath, eventMutation, 'utf8');

  const eventBroken = runTest(mutatedPath);
  assert.notStrictEqual(
    eventBroken.status,
    0,
    'Contract test must fail when a material without an event description is accepted'
  );

  const mockedMutation = source.replace(
    /'mocked'             => true,/,
    "'mocked'             => false,"
  );
  assert.notStrictEqual(mockedMutation, source, 'Mutation must remove the mocked marker');
  fs.writeFileSync(mutatedPath, mockedMutation, 'utf8');

  const mockedBroken = runTest(mutatedPath);
  assert.notStrictEqual(
    mockedBroken.status,
    0,
    'Contract test must fail when the stub is no longer marked mocked: true'
  );

  const missing = runTest(path.join(tmpDir, 'missing-stub.php'));
  assert.notStrictEqual(
    missing.status,
    0,
    'Contract test must fail when the green energy news stub is missing'
  );
} finally {
  fs.rmSync(tmpDir, { recursive: true, force: true });
}

console.log('PASS: green energy news contract test is red without the stub and green with the stub');
