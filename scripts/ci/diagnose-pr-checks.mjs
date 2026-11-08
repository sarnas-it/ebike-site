#!/usr/bin/env node
import process from 'node:process';
import {
  collectChecks,
  getPullRequest,
  getWorkflowRuns,
  getWorkflows,
} from './lib.mjs';

const USAGE = `Usage:
  diagnose-pr-checks.mjs --pr <number> [options]

Reports why a pull request has "no checks". Exits 1 while the target commit has
no successful check runs, so it can be used as a red-green gate.

Options:
  --repo <owner/name>  Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>        Pull request number (required)
  --sha <sha>          Commit to inspect (default: pull request head sha)
  -h, --help           Show this help

Authentication: set GITHUB_TOKEN (never printed).
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: null,
    sha: null,
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
  const headSha = options.sha || pr.head.sha;
  const [checks, runs, workflows] = await Promise.all([
    collectChecks({ owner, repo, ref: headSha, token }),
    getWorkflowRuns({ owner, repo, ref: headSha, token }),
    getWorkflows({ owner, repo, token }),
  ]);

  const active = (workflows?.workflows || []).filter(
    (workflow) => workflow.state === 'active',
  );
  const ci = active.find((workflow) => workflow.name === 'ci');

  process.stdout.write(`pull request: #${options.pr}\n`);
  process.stdout.write(`head branch:  ${pr.head.ref}\n`);
  process.stdout.write(`target sha:   ${headSha}\n`);
  process.stdout.write(`runs for sha: ${runs?.total_count ?? 0}\n`);
  process.stdout.write(
    `checks:       total=${checks.total} pending=${checks.pending.length} ` +
      `failed=${checks.failed.length}\n`,
  );
  process.stdout.write(
    `workflow ci:  ${ci ? `active (#${ci.id}, ${ci.path})` : 'not active or missing'}\n`,
  );

  if (checks.total === 0) {
    process.stdout.write(
      'cause: no workflow run or check run exists for this commit. The workflow ' +
        'must exist in the commit tree and a new commit on the pull request head ' +
        'branch is required, because GitHub does not run workflows retroactively.\n',
    );
    return 1;
  }
  return checks.success ? 0 : 1;
}

main()
  .then((code) => {
    process.exitCode = code;
  })
  .catch((error) => {
    process.stderr.write(`error: ${error.message}\n`);
    process.exitCode = 1;
  });
