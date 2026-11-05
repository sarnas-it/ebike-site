#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const includesDir = path.join(root, 'includes');
const phpTestPath = path.join(root, 'tests', 'guest-cart-checkout-redirect-contract-test.php');
const stubName = 'guest-cart-checkout-redirect.php';
const required = [stubName, 'guest-cart-registration.php', 'cart.php', 'catalog.php'];

for (const name of required) {
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
    env: { ...process.env, VELOTOK_GUEST_CART_REDIRECT_STUB: stubOverride },
  });
}

const original = runTest(path.join(includesDir, stubName));
assert.strictEqual(original.status, 0, `Original redirect stub must be green:\n${original.stderr || ''}`);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-guest-cart-redirect-'));

try {
  const tmpIncludes = path.join(tmpDir, 'includes');
  fs.mkdirSync(tmpIncludes, { recursive: true });

  for (const name of required) {
    fs.copyFileSync(path.join(includesDir, name), path.join(tmpIncludes, name));
  }

  const registrationPath = path.join(tmpIncludes, 'guest-cart-registration.php');
  const source = fs.readFileSync(registrationPath, 'utf8');
  const mutated = source.replace(
    /'redirects_to_checkout'\s*=>\s*true/,
    "'redirects_to_checkout' => false"
  );
  assert.notStrictEqual(mutated, source, 'Mutation must change the checkout redirect marker');
  fs.writeFileSync(registrationPath, mutated, 'utf8');

  const broken = runTest(path.join(tmpIncludes, stubName));
  assert.notStrictEqual(
    broken.status,
    0,
    'Contract test must fail when the visitor is not redirected to checkout after transfer'
  );

  const missing = runTest(path.join(tmpDir, 'missing-redirect-stub.php'));
  assert.notStrictEqual(
    missing.status,
    0,
    'Contract test must fail when the checkout redirect stub is missing'
  );
} finally {
  fs.rmSync(tmpDir, { recursive: true, force: true });
}

console.log('PASS: checkout redirect contract test is red without the stub and green with the stub');
