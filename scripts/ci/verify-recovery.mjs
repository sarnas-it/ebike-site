#!/usr/bin/env node
import process from 'node:process';
import {
  RECOVERY_WORKFLOW_PATH,
  collectRecoveryEvidence,
  recoveryWorkflowForPullRequest,
} from './evidence.mjs';
import { getPullRequest } from './lib.mjs';
import { resolveTargetFromPullRequest } from './resolve-pr-target.mjs';

const USAGE = `Usage:
  verify-recovery.mjs [options]

Verifies that the stuck pull request was recovered by a real CI run: a completed
successful recovery workflow run exists, the exact target commit has green
check runs, the pull request is merged and a merge commit exists. Exits 1 until
that is true, so it can be used as a red-green gate.

Green check runs alone are not enough: they must be backed by the successful
recovery workflow run that executed them.

Options:
  --repo <owner/name>  Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>        Pull request number (default: 62)
  --sha <sha>          Pin the commit under test (default: the current PR head,
                       so a moved head is verified instead of the stale incident SHA)
  --run-id <id>        Recovery workflow run to verify (default: latest successful)
  --workflow <path>    Recovery workflow path (default: per pull request, ${RECOVERY_WORKFLOW_PATH} otherwise)
  -h, --help           Show this help

Authentication: set GITHUB_TOKEN (never printed); reading a public repository
works without one.
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: 62,
    sha: null,
    runId: null,
    workflow: null,
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
    } else if (arg === '--run-id') {
      options.runId = argv[++i];
    } else if (arg === '--workflow') {
      options.workflow = argv[++i];
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
  const workflow = options.workflow || recoveryWorkflowForPullRequest(options.pr);
  let targetSha = options.sha;
  if (!targetSha) {
    const pr = await getPullRequest({
      owner,
      repo,
      pullNumber: options.pr,
      token,
    });
    targetSha = resolveTargetFromPullRequest(pr).sha;
  }
  const evidence = await collectRecoveryEvidence({
    owner,
    repo,
    pullNumber: options.pr,
    targetSha,
    runId: options.runId,
    workflow,
    token,
  });

  process.stdout.write(`pull request: #${options.pr}\n`);
  process.stdout.write(`target sha:   ${targetSha}\n`);
  process.stdout.write(
    `workflow run: ${evidence.run ? `#${evidence.run.id} ${evidence.run.url}` : 'none'}\n`,
  );
  process.stdout.write(
    `run result:   status=${evidence.run?.status ?? 'none'} ` +
      `conclusion=${evidence.run?.conclusion ?? 'none'}\n`,
  );
  process.stdout.write(`merged:       ${evidence.pullRequest.merged}\n`);
  process.stdout.write(`head:         ${evidence.pullRequest.headSha}\n`);
  process.stdout.write(
    `merge commit: ${evidence.pullRequest.mergeCommitSha ?? 'none'}\n`,
  );
  process.stdout.write(
    `check runs:   total=${evidence.checks.total} ` +
      `success=${evidence.checks.success} names=${evidence.checks.names.join(',') || 'none'}\n`,
  );

  if (!evidence.ready) {
    process.stdout.write(`problems:     ${evidence.problems.join('; ')}\n`);
  }
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
