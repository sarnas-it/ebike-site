#!/usr/bin/env node
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import {
  collectChecks,
  repairPrChecks,
  waitForChecks,
} from './lib.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const bundledWorkflow = path.resolve(here, '../../.github/workflows/ci.yml');

const USAGE = `Usage:
  repair-pr-checks.mjs --pr <number> [options]

Diagnoses and repairs "no checks reported" for a pull request.

Options:
  --repo <owner/name>     Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>           Pull request number (required)
  --sha <sha>             Commit to inspect (default: pull request head sha)
  --workflow-file <path>  Workflow content to commit when the head tree lacks it
  --wait                  Wait for the new/current head checks to finish
  --timeout-ms <ms>       Wait timeout (default: 1800000 = 30m)
  --interval-ms <ms>      Poll interval (default: 15000)
  --dry-run               Only diagnose, do not commit or dispatch
  -h, --help              Show this help

Authentication: set GITHUB_TOKEN (never printed). Reading public repositories
works without a token, but committing and dispatching require write access.
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: null,
    sha: null,
    workflowFile: null,
    wait: false,
    dryRun: false,
    timeoutMs: 30 * 60 * 1000,
    intervalMs: 15 * 1000,
  };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '-h' || arg === '--help') {
      options.help = true;
    } else if (arg === '--wait') {
      options.wait = true;
    } else if (arg === '--dry-run') {
      options.dryRun = true;
    } else if (arg === '--repo') {
      options.repo = argv[++i];
    } else if (arg === '--pr') {
      options.pr = Number(argv[++i]);
    } else if (arg === '--sha') {
      options.sha = argv[++i];
    } else if (arg === '--workflow-file') {
      options.workflowFile = argv[++i];
    } else if (arg === '--timeout-ms') {
      options.timeoutMs = Number(argv[++i]);
    } else if (arg === '--interval-ms') {
      options.intervalMs = Number(argv[++i]);
    } else {
      throw new Error(`unknown argument: ${arg}`);
    }
  }
  return options;
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  if (options.help) {
    process.stdout.write(USAGE);
    return 0;
  }
  if (!Number.isInteger(options.pr) || options.pr <= 0) {
    throw new Error('--pr <number> is required');
  }
  const [owner, repo] = options.repo.split('/');
  if (!owner || !repo) {
    throw new Error(`invalid --repo value: ${options.repo}`);
  }
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN || '';
  const log = (message) => process.stdout.write(`${message}\n`);

  if (options.dryRun) {
    const checks = await collectChecks({
      owner,
      repo,
      ref: options.sha,
      token,
    });
    log(
      `dry run: total=${checks.total} pending=${checks.pending.length} ` +
        `failed=${checks.failed.length} success=${checks.success}`,
    );
    return checks.success ? 0 : 1;
  }

  let workflowContent = null;
  if (options.workflowFile) {
    workflowContent = await readFile(options.workflowFile, 'utf8');
  } else {
    try {
      workflowContent = await readFile(bundledWorkflow, 'utf8');
    } catch {
      workflowContent = null;
    }
  }

  const result = await repairPrChecks({
    owner,
    repo,
    pullNumber: options.pr,
    token,
    workflowContent,
    log,
  });
  log(`repair result: ${result.status} (${result.action})`);

  if (!options.wait) {
    return 0;
  }

  const ref = result.newHeadSha || result.sha;
  const checks = await waitForChecks({
    owner,
    repo,
    ref,
    token,
    timeoutMs: options.timeoutMs,
    intervalMs: options.intervalMs,
    log,
  });
  log(`checks outcome: ${checks.outcome}`);
  return checks.outcome === 'success' ? 0 : 1;
}

main()
  .then((code) => {
    process.exitCode = code;
  })
  .catch((error) => {
    process.stderr.write(`error: ${error.message}\n`);
    process.exitCode = 1;
  });
