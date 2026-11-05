#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const pluginRoot = path.resolve(__dirname, '..');
const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'velotok-checkout-negative-'));
const temporaryPlugin = path.join(temporaryRoot, 'velotok-core');

function removeStub() {
  fs.rmSync(temporaryPlugin, { recursive: true, force: true });
  fs.cpSync(pluginRoot, temporaryPlugin, { recursive: true });
  fs.rmSync(path.join(temporaryPlugin, 'includes', 'checkout-contracts.php'), { force: true });
}

try {
  removeStub();

  const jsResult = spawnSync(process.execPath, [path.join(temporaryPlugin, 'tests', 'checkout-contract.test.js')], {
    encoding: 'utf8',
  });

  assert.notStrictEqual(
    jsResult.status,
    0,
    'Contract test must fail when the checkout stub is removed'
  );

  const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

  if (php.status === 0) {
    const phpResult = spawnSync('php', [path.join(temporaryPlugin, 'tests', 'checkout-contract-test.php')], {
      encoding: 'utf8',
    });

    assert.notStrictEqual(
      phpResult.status,
      0,
      'PHP contract test must fail when the checkout stub is removed'
    );
  }

  console.log('PASS: contract test fails without the checkout stub');
} finally {
  fs.rmSync(temporaryRoot, { recursive: true, force: true });
}
