#!/usr/bin/env node
import process from 'node:process';
import {
  getCheckRuns,
  getPullRequest,
  summarizeRecovery,
} from './lib.mjs';

const USAGE = `Usage:
  verify-recovery.mjs [options]

Verifies that the stuck pull request was recovered: the exact target commit has
successful check runs, the pull request is merged, and a merge commit exists.
Exits 1 until that is true, so it can be used as a red-green gate.

Options:
  --repo <owner/name>  Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>        Pull request number (default: 62)
  --sha <sha>          Exact commit under test (default: 578e87b5a8587d6078aade92d26bd449448dd1d3)
  -h, --help           Show this help

Authentication: set GITHUB_TOKEN (never printed); reading a public repository
works without one.
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: 62,
    sha: '578e87b5a8587d6078aade92d26bd449448dd1d3',
  };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '-h' || arg === '--help') {
      options.help = true;
    } else if (arg === '--repo') {
      options.repo = argv[++i];
    } else if (arg === '--pr') {
      options.pr = Number(argv[++i]);
    } else if (arg === '--sha') {
      options.sha = argv[++i];
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
  const [owner, repo] = options.repo.split('/');
  if (!owner || !repo) {
    throw new Error(`invalid --repo value: ${options.repo}`);
  }
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN || '';
  const [pr, checks] = await Promise.all([
    getPullRequest({ owner, repo, pullNumber: options.pr, token }),
    getCheckRuns({ owner, repo, ref: options.sha, token }),
  ]);
  const summary = summarizeRecovery({
    pr,
    checkRuns: checks?.check_runs,
    expectedSha: options.sha,
  });

  process.stdout.write(`pull request: #${options.pr}\n`);
  process.stdout.write(`target sha:   ${options.sha}\n`);
  process.stdout.write(`merged:       ${summary.merged}\n`);
  process.stdout.write(`head:         ${summary.headSha}\n`);
  process.stdout.write(`merge commit: ${summary.mergeCommitSha ?? 'none'}\n`);
  process.stdout.write(
    `check runs:   total=${summary.checkCount} allGreen=${summary.allGreen} ` +
      `names=${summary.checkNames.join(',') || 'none'}\n`,
  );

  const ok =
    summary.merged &&
    summary.headMatches &&
    summary.allGreen &&
    Boolean(summary.mergeCommitSha);
  return ok ? 0 : 1;
}

main()
  .then((code) => {
    process.exitCode = code;
  })
  .catch((error) => {
    process.stderr.write(`error: ${error.message}\n`);
    process.exitCode = 1;
  });
