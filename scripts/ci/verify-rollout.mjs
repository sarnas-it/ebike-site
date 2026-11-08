#!/usr/bin/env node
import process from 'node:process';
import {
  BASE_BRANCH,
  EXPECTED_CI_JOBS,
  FIX_PR_NUMBER,
  collectRolloutEvidence,
} from './rollout.mjs';
import { WORKFLOW_PATH } from './lib.mjs';

const USAGE = `Usage:
  verify-rollout.mjs [options]

Verifies that the CI fix is present in pull request #${FIX_PR_NUMBER} and that CI
actually ran successfully on its real head commit. Exits 1 until all of the
following are true, so it can be used as a red-green gate for the rollout
criteria:

  * pull request #${FIX_PR_NUMBER} targets ${BASE_BRANCH};
  * before acceptance the pull request is open and mergeable with no conflicts
    in ${BASE_BRANCH} (the tracker merges it only after ACCEPT, so a merged
    state cannot be required before acceptance); after the merge a merge commit
    must exist instead;
  * ${WORKFLOW_PATH} exists in the pull request head tree (or on ${BASE_BRANCH}
    once merged);
  * the latest ci run on the real head commit completed successfully;
  * the run includes the full job set (${EXPECTED_CI_JOBS.join(', ')}).

A successful run on any other (for example an older) commit is rejected, so a
green run from a previous submission cannot stand in for the current head.

Options:
  --repo <owner/name>  Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>        Fix pull request number (default: ${FIX_PR_NUMBER})
  --sha <sha>          Fix commit to verify (default: pull request head sha)
  --run-id <id>        CI workflow run to verify (default: latest ci run for the sha)
  --base <branch>      Base branch (default: ${BASE_BRANCH})
  --workflow <path>    CI workflow path (default: ${WORKFLOW_PATH})
  -h, --help           Show this help

Authentication: set GITHUB_TOKEN (never printed); reading a public repository
works without one.
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: FIX_PR_NUMBER,
    sha: null,
    runId: null,
    base: BASE_BRANCH,
    workflow: WORKFLOW_PATH,
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
    } else if (arg === '--base') {
      options.base = argv[++i];
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
  if (!Number.isInteger(options.pr) || options.pr <= 0) {
    throw new Error('--pr <number> is required');
  }
  const [owner, repo] = options.repo.split('/');
  if (!owner || !repo) {
    throw new Error(`invalid --repo value: ${options.repo}`);
  }
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN || '';

  const evidence = await collectRolloutEvidence({
    owner,
    repo,
    fixPr: options.pr,
    targetSha: options.sha,
    runId: options.runId,
    baseBranch: options.base,
    workflow: options.workflow,
    token,
  });

  process.stdout.write(`fix pull request: #${evidence.fixPr}\n`);
  process.stdout.write(`base branch:      ${evidence.baseBranch}\n`);
  process.stdout.write(`fix commit:       ${evidence.targetSha ?? 'none'}\n`);
  process.stdout.write(
    `pull request:     merged=${evidence.pullRequest.merged} ` +
      `state=${evidence.pullRequest.state ?? 'none'} ` +
      `mergeable=${evidence.pullRequest.mergeable ?? 'unknown'} ` +
      `mergeable_state=${evidence.pullRequest.mergeableState ?? 'unknown'} ` +
      `base=${evidence.pullRequest.base ?? 'none'} ` +
      `merge=${evidence.pullRequest.mergeCommitSha ?? 'none'}\n`,
  );
  process.stdout.write(
    `workflow at:      ${evidence.workflowPresent ? evidence.workflowPath : 'missing'} ` +
      `(${evidence.workflowRef ?? 'unknown'})\n`,
  );
  process.stdout.write(
    `ci run:           ${evidence.run ? `#${evidence.run.id} ${evidence.run.url}` : 'none'}\n`,
  );
  process.stdout.write(
    `run result:       status=${evidence.run?.status ?? 'none'} ` +
      `conclusion=${evidence.run?.conclusion ?? 'none'} ` +
      `head=${evidence.run?.headSha ?? 'none'}\n`,
  );
  process.stdout.write(
    `jobs:             ${
      evidence.jobs
        .map((job) => `${job.name}=${job.conclusion ?? job.status ?? 'unknown'}`)
        .join(', ') || 'none'
    }\n`,
  );
  process.stdout.write(
    `check runs:       ${
      (evidence.checkRuns || [])
        .map(
          (check) =>
            `${check.name}=${check.conclusion ?? check.status ?? 'unknown'}` +
            `@${check.headSha ?? 'unknown'}/suite:${check.checkSuiteId ?? 'unknown'}`,
        )
        .join(', ') || 'none'
    }\n`,
  );

  if (!evidence.ready) {
    process.stdout.write(`problems:         ${evidence.problems.join('; ')}\n`);
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
