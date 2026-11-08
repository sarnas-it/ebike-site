#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const includesDir = path.join(root, 'includes');
const phpTestPath = path.join(root, 'tests', 'guest-cart-registration-contract-test.php');
const stubName = 'guest-cart-registration.php';
const stubPath = path.join(includesDir, stubName);

for (const name of [stubName, 'cart.php', 'catalog.php']) {
  assert.ok(fs.existsSync(path.join(includesDir, name)), `Required stub is missing: ${name}`);
}

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('PASS: red-green proof skipped (PHP not available)');
  process.exit(0);
}

function runTest(stubOverride) {
  return spawnSync('php', [phpTestPath], {
    encoding: 'utf8',
    env: { ...process.env, VELOTOK_GUEST_CART_STUB: stubOverride },
  });
}

const original = runTest(stubPath);
assert.strictEqual(original.status, 0, `Original guest cart stub must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-guest-cart-'));

try {
  const tmpIncludes = path.join(tmpDir, 'includes');
  fs.mkdirSync(tmpIncludes, { recursive: true });

  for (const name of [stubName, 'cart.php', 'catalog.php']) {
    fs.copyFileSync(path.join(includesDir, name), path.join(tmpIncludes, name));
  }

  const mutationTarget = path.join(tmpIncludes, stubName);
  const source = fs.readFileSync(mutationTarget, 'utf8');
  const mutated = source.replace(/'registers_on_buy'\s*=>\s*true/, "'registers_on_buy' => false");
  assert.notStrictEqual(mutated, source, 'Mutation must change the registration-on-buy marker');
  fs.writeFileSync(mutationTarget, mutated, 'utf8');

  const broken = runTest(mutationTarget);
  assert.notStrictEqual(
    broken.status,
    0,
    'Contract test must fail when «Купить» no longer registers the user'
  );

  const missing = runTest(path.join(tmpDir, 'missing-stub.php'));
  assert.notStrictEqual(
    missing.status,
    0,
    'Contract test must fail when the guest cart stub is missing'
  );
} finally {
  fs.rmSync(tmpDir, { recursive: true, force: true });
}

console.log('PASS: guest cart contract test is red without the stub and green with the stub');
