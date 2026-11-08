#!/usr/bin/env node
import process from 'node:process';
import { dispatchWorkflow } from './lib.mjs';

export const FINALIZER_WORKFLOW_PATH =
  '.github/workflows/finalize-pr71-evidence.yml';

const USAGE = `Usage:
  request-evidence-finalize.mjs --pr <number> --sha <sha> --run-id <id> [options]

Dispatches the finalizer workflow after the checks/merge step has finished. The
finalizer waits for this recovery run to complete and only then publishes the
"completed" evidence, so the pull request comment never records an in-progress
run as the final result.

Options:
  --repo <owner/name>   Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --ref <git-ref>       Ref that contains the finalizer workflow (default: $GITHUB_REF_NAME)
  --pr <number>         Pull request number (required)
  --sha <sha>           Exact commit under test (required)
  --run-id <id>         Recovery workflow run to finalize (required)
  --workflow <path>     Finalizer workflow path (default: ${FINALIZER_WORKFLOW_PATH})
  -h, --help            Show this help

Authentication: set GITHUB_TOKEN (never printed). Dispatching requires the
actions:write permission.
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    ref: process.env.GITHUB_REF_NAME || null,
    pr: null,
    sha: null,
    runId: process.env.GITHUB_RUN_ID || null,
    workflow: FINALIZER_WORKFLOW_PATH,
  };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '-h' || arg === '--help') {
      options.help = true;
    } else if (arg === '--repo') {
      options.repo = argv[++i];
    } else if (arg === '--ref') {
      options.ref = argv[++i];
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
  if (!Number.isInteger(options.pr) || options.pr <= 0) {
    throw new Error('--pr <number> is required');
  }
  if (!options.sha || !/^[0-9a-f]{7,40}$/.test(options.sha)) {
    throw new Error('--sha <commit sha> is required');
  }
  if (!options.runId || !/^[0-9]+$/.test(String(options.runId))) {
    throw new Error('--run-id <numeric run id> is required');
  }
  if (!options.ref) {
    throw new Error('--ref <git-ref> is required');
  }
  const [owner, repo] = options.repo.split('/');
  if (!owner || !repo) {
    throw new Error(`invalid --repo value: ${options.repo}`);
  }
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN || '';

  await dispatchWorkflow({
    owner,
    repo,
    workflow: options.workflow,
    ref: options.ref,
    inputs: {
      pr: String(options.pr),
      sha: options.sha,
      run_id: String(options.runId),
    },
    token,
  });
  process.stdout.write(
    `dispatched ${options.workflow} on ${options.ref} for run ${options.runId}\n`,
  );
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
