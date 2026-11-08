#!/usr/bin/env node
import assert from 'node:assert';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const runner = path.resolve(here, '../run-checks.sh');

function run(root) {
  const results = path.join(root, 'results.json');
  const proc = spawnSync('bash', [runner, root, results], {
    encoding: 'utf8',
  });
  return {
    status: proc.status,
    output: `${proc.stdout || ''}${proc.stderr || ''}`,
    results: JSON.parse(readFileSync(results, 'utf8')),
  };
}

function testPassingCheckoutIsGreen() {
  const root = mkdtempSync(path.join(tmpdir(), 'ci-checks-green-'));
  mkdirSync(path.join(root, 'wp/themes/velotok/tests'), { recursive: true });
  mkdirSync(path.join(root, 'wp/plugins/velotok-core/tests'), { recursive: true });
  writeFileSync(path.join(root, 'ok.php'), '<?php echo "ok";\n');
  writeFileSync(
    path.join(root, 'wp/themes/velotok/tests/ok-test.php'),
    '<?php echo "theme ok\\n";\n',
  );
  writeFileSync(
    path.join(root, 'wp/plugins/velotok-core/tests/ok.test.js'),
    'console.log("plugin ok");\n',
  );

  const result = run(root);
  assert.strictEqual(result.status, 0, result.output);
  assert.deepStrictEqual(result.results, {
    'php-lint': 'success',
    'php-tests': 'success',
    'plugin-tests': 'success',
  });
  console.log('PASS: a healthy checkout is green');
}

function testFailingCheckoutIsRed() {
  const root = mkdtempSync(path.join(tmpdir(), 'ci-checks-red-'));
  mkdirSync(path.join(root, 'wp/themes/velotok/tests'), { recursive: true });
  mkdirSync(path.join(root, 'wp/plugins/velotok-core/tests'), { recursive: true });
  writeFileSync(path.join(root, 'ok.php'), '<?php echo "ok";\n');
  writeFileSync(
    path.join(root, 'wp/themes/velotok/tests/broken-test.php'),
    '<?php fwrite(STDERR, "boom\\n"); exit(1);\n',
  );
  writeFileSync(
    path.join(root, 'wp/plugins/velotok-core/tests/ok.test.js'),
    'console.log("plugin ok");\n',
  );

  const result = run(root);
  assert.notStrictEqual(result.status, 0, 'a failing check must make the runner red');
  assert.strictEqual(result.results['php-lint'], 'success');
  assert.strictEqual(result.results['php-tests'], 'failure');
  assert.strictEqual(result.results['plugin-tests'], 'success');
  console.log('PASS: a failing check is reported as failure');
}

function testBrokenPhpSyntaxIsRed() {
  const root = mkdtempSync(path.join(tmpdir(), 'ci-checks-lint-'));
  mkdirSync(path.join(root, 'wp/themes/velotok/tests'), { recursive: true });
  mkdirSync(path.join(root, 'wp/plugins/velotok-core/tests'), { recursive: true });
  writeFileSync(path.join(root, 'broken.php'), '<?php function (\n');
  writeFileSync(
    path.join(root, 'wp/themes/velotok/tests/ok-test.php'),
    '<?php echo "theme ok\\n";\n',
  );
  writeFileSync(
    path.join(root, 'wp/plugins/velotok-core/tests/ok.test.js'),
    'console.log("plugin ok");\n',
  );

  const result = run(root);
  assert.notStrictEqual(result.status, 0, 'broken PHP syntax must make the runner red');
  assert.strictEqual(result.results['php-lint'], 'failure');
  console.log('PASS: broken PHP syntax is detected by php-lint');
}

function main() {
  testPassingCheckoutIsGreen();
  testFailingCheckoutIsRed();
  testBrokenPhpSyntaxIsRed();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
