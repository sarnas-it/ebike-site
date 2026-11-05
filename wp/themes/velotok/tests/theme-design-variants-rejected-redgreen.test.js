#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const stubPath = path.resolve(__dirname, '..', 'inc', 'theme-design-variants.php');
const phpTestPath = path.join(__dirname, 'theme-design-variants-rejected-contract-test.php');

assert.ok(fs.existsSync(stubPath), `Theme design variants stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `Rejected variants contract test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');

assert.ok(
  /'reject_unselected'\s*=>\s*true/.test(source),
  'Contract must explicitly require rejecting unselected variants'
);
assert.ok(
  /'unselected_status'\s*=>\s*'rejected'/.test(source),
  'Unselected variants must have the rejected status'
);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('SKIP: PHP is not available, rejected variants red-green test was not executed');
  process.exit(0);
}

const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-rejected-'));

function runContract(stubOverride) {
  const env = Object.assign({}, process.env);
  env.VELOTOK_THEME_DESIGN_VARIANTS_STUB = stubOverride;

  return spawnSync('php', [phpTestPath], { encoding: 'utf8', env });
}

function output(result) {
  return `${result.stdout || ''}${result.stderr || ''}`;
}

try {
  const green = runContract('');
  assert.strictEqual(green.status, 0, `Rejected variants contract test must pass with the stub:\n${output(green)}`);

  const missing = runContract(path.join(tempDir, 'missing-stub.php'));
  assert.notStrictEqual(missing.status, 0, 'Rejected variants contract test must fail without the stub');
  assert.ok(output(missing).includes('Не найден файл заглушки'), 'Missing stub failure must be explicit');

  const mutatedSource = source.replace(
    ": $contract['rejected_status'];",
    ": $contract['pending_status'];"
  );
  assert.notStrictEqual(
    mutatedSource,
    source,
    'Red-green mutation must target the unselected variant status branch'
  );

  const mutatedPath = path.join(tempDir, 'theme-design-variants-without-rejection.php');
  fs.writeFileSync(mutatedPath, mutatedSource);

  const red = runContract(mutatedPath);
  assert.notStrictEqual(
    red.status,
    0,
    'Rejected variants contract test must fail when unselected variants are not rejected'
  );
  assert.ok(
    output(red).includes('rejected'),
    'Red-green failure must mention the missing rejected status'
  );

  console.log('PASS: rejected variants contract test is green with the stub and red without rejection logic');
} finally {
  fs.rmSync(tempDir, { recursive: true, force: true });
}
