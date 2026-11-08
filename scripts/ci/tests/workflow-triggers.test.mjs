#!/usr/bin/env node
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { WORKFLOW_PATH } from '../lib.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../../..');
const workflowPath = path.join(repoRoot, WORKFLOW_PATH);

function analyzeWorkflow(text) {
  const lines = text.split(/\r?\n/);
  const onIndex = lines.findIndex((line) => /^on:\s*(#.*)?$/.test(line));
  assert.ok(onIndex >= 0, 'workflow must declare an "on:" trigger block');

  const triggerLines = [];
  for (let i = onIndex + 1; i < lines.length; i += 1) {
    const line = lines[i];
    if (/^\S/.test(line) && line.trim() !== '') {
      break;
    }
    triggerLines.push(line);
  }
  const block = triggerLines.join('\n');
  const triggers = triggerLines
    .map((line) => line.match(/^  ([A-Za-z_]+):/))
    .filter(Boolean)
    .map((match) => match[1]);

  return {
    triggers,
    hasPathsFilter: /^\s+paths(?:-ignore)?:/m.test(block),
    hasBranchesFilter: /^\s+branches(?:-ignore)?:/m.test(block),
  };
}

function testBrokenWorkflowIsRed() {
  const broken = [
    'name: ci',
    'on:',
    '  push:',
    '    branches: [main]',
    'jobs:',
    '  lint:',
    '    runs-on: ubuntu-latest',
    '    steps:',
    '      - run: echo ok',
  ].join('\n');
  const analysis = analyzeWorkflow(broken);
  assert.ok(
    !analysis.triggers.includes('pull_request'),
    'the fixture without pull_request must be detected as broken',
  );
  console.log('PASS: a workflow without pull_request is detected as broken');
}

function testRepositoryWorkflowIsGreen() {
  const text = readFileSync(workflowPath, 'utf8');
  const analysis = analyzeWorkflow(text);

  assert.ok(
    analysis.triggers.includes('pull_request'),
    'ci must run on every pull_request so PR commits get checks',
  );
  assert.ok(
    analysis.triggers.includes('workflow_dispatch'),
    'ci must be dispatchable to recover a head that has no run',
  );
  assert.ok(
    analysis.triggers.includes('push'),
    'ci must still run on push to main',
  );
  assert.strictEqual(
    analysis.hasPathsFilter,
    false,
    'ci must not filter pull_request by paths, otherwise commits get no checks',
  );

  const jobNames = [...text.matchAll(/^  ([A-Za-z0-9_-]+):\s*$/gm)].map(
    (match) => match[1],
  );
  assert.ok(jobNames.includes('php-lint'), 'php-lint job must remain enabled');
  console.log('PASS: repository workflow triggers on pull_request without path filters');
}

function main() {
  testBrokenWorkflowIsRed();
  testRepositoryWorkflowIsGreen();
}

try {
  main();
} catch (error) {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
}
