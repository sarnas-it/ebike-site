#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const pluginRoot = path.resolve(__dirname, '..');
const testPath = path.join(__dirname, 'pvs-risk-highlighting-contract-test.php');
const scannerPath = path.join(pluginRoot, 'includes', 'class-pvs-scanner.php');

assert.ok(fs.existsSync(testPath), `Risk highlighting contract test is missing: ${testPath}`);
assert.ok(fs.existsSync(scannerPath), `PVS scanner stub is missing: ${scannerPath}`);

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status !== 0) {
  console.log('SKIP: PHP is not available, risk highlighting red-green test was not executed');
  process.exit(0);
}

const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'pvs-risk-highlighting-'));

function runContract(pluginDir) {
  const env = Object.assign({}, process.env);

  if (pluginDir) {
    env.PVS_PLUGIN_DIR = pluginDir;
  } else {
    delete env.PVS_PLUGIN_DIR;
  }

  return spawnSync('php', [testPath], { encoding: 'utf8', env });
}

function output(result) {
  return `${result.stdout || ''}${result.stderr || ''}`;
}

try {
  const green = runContract('');
  assert.strictEqual(green.status, 0, `Risk highlighting contract test must pass with the stub:\n${output(green)}`);

  const missing = runContract(path.join(tempDir, 'missing-stub'));
  assert.notStrictEqual(missing.status, 0, 'Risk highlighting contract test must fail without the stub');
  assert.ok(output(missing).includes('Не найден файл заглушки'), 'Missing stub failure must be explicit');

  const mutatedRoot = path.join(tempDir, 'mutated-pvs');
  fs.cpSync(path.join(pluginRoot, 'includes'), path.join(mutatedRoot, 'includes'), { recursive: true });

  const source = fs.readFileSync(scannerPath, 'utf8');
  const mutatedSource = source.replace(
    "$entry['highlight']            = $highlight['highlight'];",
    "$entry['highlight']            = '';"
  );

  assert.notStrictEqual(
    mutatedSource,
    source,
    'Red-green mutation must target the vulnerability highlight assignment'
  );

  fs.writeFileSync(path.join(mutatedRoot, 'includes', 'class-pvs-scanner.php'), mutatedSource);

  const red = runContract(mutatedRoot);
  assert.notStrictEqual(
    red.status,
    0,
    'Risk highlighting contract test must fail when vulnerability highlighting is removed'
  );
  assert.ok(
    output(red).includes('подсветк'),
    'Red-green failure must mention the missing risk highlight'
  );

  console.log('PASS: risk highlighting contract test is green with the stub and red without highlighting');
} finally {
  fs.rmSync(tempDir, { recursive: true, force: true });
}
