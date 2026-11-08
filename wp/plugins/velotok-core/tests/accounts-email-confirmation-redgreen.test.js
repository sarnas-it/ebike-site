#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const phpTestPath = path.join(root, 'tests', 'accounts-email-confirmation-redgreen-test.php');

if (!fs.existsSync(phpTestPath)) {
  console.error(`TESTEB-2 PHP red-green test is missing: ${phpTestPath}`);
  process.exit(1);
}

const php = spawnSync('php', [phpTestPath], { cwd: root, encoding: 'utf8' });

if (php.error && php.error.code === 'ENOENT') {
  console.log('PASS: TESTEB-2 red-green proof skipped (php is not available)');
  process.exit(0);
}

if (php.stdout) {
  process.stdout.write(php.stdout);
}

if (php.stderr) {
  process.stderr.write(php.stderr);
}

process.exit(php.status === null ? 1 : php.status);
