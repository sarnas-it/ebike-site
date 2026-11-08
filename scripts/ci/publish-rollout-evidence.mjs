#!/usr/bin/env node
import process from 'node:process';
import {
  BASE_BRANCH,
  EXPECTED_CI_JOBS,
  FIX_PR_NUMBER,
  publishRolloutEvidence,
} from './rollout.mjs';
import { WORKFLOW_PATH } from './lib.mjs';

const USAGE = `Usage:
  publish-rollout-evidence.mjs [options]

Verifies that the fix pull request has a real successful ci run on its current
head (or is already merged) and that it is ready for the automated merge, then
writes or updates a single evidence comment pinned to that head. Exits 1 and
publishes nothing while the evidence is red.

Options:
  --repo <owner/name>   Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>         Fix pull request number (default: ${FIX_PR_NUMBER})
  --sha <sha>           Commit to verify (default: pull request real head)
  --run-id <id>         CI run to verify (default: latest ci run for the head)
  --base <branch>       Base branch (default: ${BASE_BRANCH})
  --workflow <path>     CI workflow path (default: ${WORKFLOW_PATH})
  --marker <text>       Idempotency marker for the comment
  --dry-run             Print the evidence without writing to GitHub
  -h, --help            Show this help

Authentication: set GITHUB_TOKEN (never printed).
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: FIX_PR_NUMBER,
    sha: null,
    runId: null,
    base: BASE_BRANCH,
    workflow: WORKFLOW_PATH,
    marker: '<!-- testeb-63-rollout -->',
    dryRun: false,
  };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '-h' || arg === '--help') {
      options.help = true;
    } else if (arg === '--dry-run') {
      options.dryRun = true;
    } else if (arg === '--repo') {
      options.repo = argv[++i];
    } else if (arg === '--pr') {
      options.pr = Number(argv[++i]);
    } else if (arg === '--sha') {
      options.sha = argv[++i];
    } else if (arg === '--run-id') {
      options.runId = argv[++i];
    } else if (arg === '--base') {
      options.base = argv[++i];
    } else if (arg === '--workflow') {
      options.workflow = argv[++i];
    } else if (arg === '--marker') {
      options.marker = argv[++i];
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

  const result = await publishRolloutEvidence({
    owner,
    repo,
    fixPr: options.pr,
    targetSha: options.sha,
    runId: options.runId,
    baseBranch: options.base,
    workflow: options.workflow,
    expectedJobs: EXPECTED_CI_JOBS,
    marker: options.marker,
    token,
  });

  process.stdout.write(result.body);

  if (!result.evidence.ready) {
    process.stderr.write(
      `evidence is red: ${result.evidence.problems.join('; ')}\n`,
    );
    return 1;
  }

  if (result.comment) {
    process.stdout.write(
      result.comment.created
        ? `rollout evidence comment created: ${result.comment.id}\n`
        : `rollout evidence comment updated: ${result.comment.id}\n`,
    );
  }
  return 0;
}

main()
  .then((code) => {
    process.exitCode = code;
  })
  .catch((error) => {
    process.stderr.write(`error: ${error.message}\n`);
    process.exitCode = 1;
  });
