#!/usr/bin/env node
import process from 'node:process';
import {
  DEFAULT_BASE_BRANCH,
  DEFAULT_FIX_PR,
  assessMergeability,
  renderMergeability,
} from './mergeability.mjs';
import { getPullRequest } from './lib.mjs';

const USAGE = `Usage:
  verify-mergeable.mjs [options]

Verifies criterion 3 for TESTEB-63: the fix pull request (default #${DEFAULT_FIX_PR})
targets the base branch (default ${DEFAULT_BASE_BRANCH}) and is mergeable with no
conflicts. It does not require a CI run on that pull request; the submitted
commit's CI run is verified separately by verify-rollout.mjs. Exits 1 while the
pull request has conflicts or its mergeability is unknown.

Options:
  --repo <owner/name>  Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>        Fix pull request number (default: ${DEFAULT_FIX_PR})
  --base <branch>      Base branch (default: ${DEFAULT_BASE_BRANCH})
  -h, --help           Show this help

Authentication: set GITHUB_TOKEN (never printed); reading a public repository
works without one.
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: DEFAULT_FIX_PR,
    base: DEFAULT_BASE_BRANCH,
  };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '-h' || arg === '--help') {
      options.help = true;
    } else if (arg === '--repo') {
      options.repo = argv[++i];
    } else if (arg === '--pr') {
      options.pr = Number(argv[++i]);
    } else if (arg === '--base') {
      options.base = argv[++i];
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

  const pr = await getPullRequest({
    owner,
    repo,
    pullNumber: options.pr,
    token,
  });
  const evidence = assessMergeability(pr, {
    prNumber: options.pr,
    baseBranch: options.base,
  });

  process.stdout.write(renderMergeability(evidence));
  return evidence.ready ? 0 : 1;
}

main()
  .then((code) => {
    process.exitCode = code;
  })
  .catch((error) => {
    process.stderr.write(`error: ${error.message}\n`);
    process.exitCode = 1;
  });
