#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const stubPath = path.join(root, 'includes', 'accounts.php');
const phpTestPath = path.join(root, 'tests', 'accounts-email-confirmation-test.php');

assert.ok(fs.existsSync(stubPath), `Accounts stub is missing: ${stubPath}`);
assert.ok(fs.existsSync(phpTestPath), `TESTEB-2 PHP test is missing: ${phpTestPath}`);

const source = fs.readFileSync(stubPath, 'utf8');
const broken = source
  .replace("'status'             => $contract['confirmation']['pending_status'],", "'status'             => $contract['confirmation']['active_status'],")
  .replace("'email_confirmed'    => false,", "'email_confirmed'    => true,");

assert.notStrictEqual(
  broken,
  source,
  'Failed to build the broken accounts stub that skips email confirmation'
);

const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-testeb-2-'));
const brokenPath = path.join(tmpDir, 'accounts.php');
fs.writeFileSync(brokenPath, broken);

const env = Object.assign({}, process.env, { VELOTOK_ACCOUNTS_STUB: brokenPath });

const brokenRun = spawnSync('php', [phpTestPath], { cwd: root, env, encoding: 'utf8' });
const realRun = spawnSync('php', [phpTestPath], { cwd: root, encoding: 'utf8' });

fs.rmSync(tmpDir, { recursive: true, force: true });

assert.ok(
  brokenRun.status !== 0,
  'Red-green proof failed: TESTEB-2 test passed against a stub that skips email confirmation'
);

assert.strictEqual(
  realRun.status,
  0,
  `TESTEB-2 test must pass against the real stub:\n${realRun.stdout}\n${realRun.stderr}`
);

console.log('PASS: TESTEB-2 test fails without email confirmation and passes with it');
