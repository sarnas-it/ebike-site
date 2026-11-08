#!/usr/bin/env node
import { readFile } from 'node:fs/promises';
import process from 'node:process';
import { pathToFileURL } from 'node:url';
import { getWorkflowRun, getWorkflowRuns } from './lib.mjs';
import {
  RECOVERY_WORKFLOW_PATH,
  publishRecoveryEvidence,
} from './evidence.mjs';

const USAGE = `Usage:
  wait-and-publish-evidence.mjs --pr <number> --sha <sha> [--run-id <id> | --trigger-sha <sha>] [options]

Waits for the recovery workflow run to finish, then verifies and publishes the
completed evidence. This is what makes the comment show a real final result
instead of an in-progress run.

The finalizer is triggered by the same push as recovery, so it normally finds
the matching recovery run by --trigger-sha instead of receiving a run id. The
run is discovered by its exact head SHA, never guessed from the incident report.

Options:
  --repo <owner/name>    Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>          Pull request number (required)
  --sha <sha>            Exact commit under test (required)
  --run-id <id>          Recovery workflow run to wait for (optional)
  --trigger-sha <sha>    Head SHA that triggered recovery; used to discover the
                         matching recovery run when --run-id is absent
  --workflow <path>      Recovery workflow path (default: ${RECOVERY_WORKFLOW_PATH})
  --comment-file <path>  Markdown template to publish/update on the pull request
  --comment-marker <s>   Idempotency marker for the comment
  --timeout-ms <ms>      Wait timeout (default: 2400000 = 40m)
  --interval-ms <ms>     Poll interval (default: 15000)
  --dry-run              Print the evidence without writing to GitHub
  -h, --help             Show this help

Authentication: set GITHUB_TOKEN (never printed).
`;

export function isSuccessfulRecoveryRun(run) {
  return run?.status === 'completed' && run?.conclusion === 'success';
}

function runPath(run) {
  return run?.path || run?.workflow_path || null;
}

export async function findRecoveryRunForTrigger({
  owner,
  repo,
  triggerSha,
  workflow = RECOVERY_WORKFLOW_PATH,
  token,
  fetchImpl = globalThis.fetch,
  timeoutMs = 40 * 60 * 1000,
  intervalMs = 15 * 1000,
  now = () => Date.now(),
  sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms)),
  log = () => {},
}) {
  const deadline = now() + timeoutMs;
  while (now() <= deadline) {
    const response = await getWorkflowRuns({
      owner,
      repo,
      ref: triggerSha,
      token,
      fetchImpl,
    });
    const candidates = (response?.workflow_runs || [])
      .filter((run) => runPath(run) === workflow)
      .sort((a, b) => {
        const left = Date.parse(a.created_at || 0);
        const right = Date.parse(b.created_at || 0);
        return right - left;
      });
    if (candidates.length > 0) {
      log(
        `found recovery run ${candidates[0].id} for trigger ${triggerSha} ` +
          `(${workflow})`,
      );
      return candidates[0];
    }
    log(`no recovery run yet for trigger ${triggerSha} (${workflow})`);
    if (now() >= deadline) {
      break;
    }
    await sleep(intervalMs);
  }
  return null;
}

export async function waitForRunCompletion({
  runId,
  getRun,
  timeoutMs = 40 * 60 * 1000,
  intervalMs = 15 * 1000,
  now = () => Date.now(),
  sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms)),
  log = () => {},
}) {
  const deadline = now() + timeoutMs;
  let last = null;
  while (now() <= deadline) {
    last = await getRun(runId);
    log(
      `recovery run ${runId}: status=${last?.status ?? 'unknown'} ` +
        `conclusion=${last?.conclusion ?? 'none'}`,
    );
    if (last?.status === 'completed') {
      return last;
    }
    if (now() >= deadline) {
      break;
    }
    await sleep(intervalMs);
  }
  return last;
}

export async function finalizeRecoveryEvidence({
  owner,
  repo,
  pullNumber,
  targetSha,
  runId = null,
  triggerSha = null,
  workflow = RECOVERY_WORKFLOW_PATH,
  template = '',
  marker = null,
  token,
  fetchImpl = globalThis.fetch,
  timeoutMs,
  intervalMs,
  now,
  sleep,
  log = () => {},
}) {
  let resolvedRunId = runId;
  if (!resolvedRunId) {
    if (!triggerSha) {
      throw new Error('either --run-id or --trigger-sha is required');
    }
    const discovered = await findRecoveryRunForTrigger({
      owner,
      repo,
      triggerSha,
      workflow,
      token,
      fetchImpl,
      timeoutMs,
      intervalMs,
      now,
      sleep,
      log,
    });
    if (!discovered) {
      return {
        run: null,
        published: false,
        reason: `no recovery run found for trigger ${triggerSha}`,
      };
    }
    resolvedRunId = discovered.id;
  }

  const run = await waitForRunCompletion({
    runId: resolvedRunId,
    getRun: (id) =>
      getWorkflowRun({ owner, repo, runId: id, token, fetchImpl }),
    timeoutMs,
    intervalMs,
    now,
    sleep,
    log,
  });

  if (!run || run.status !== 'completed') {
    return {
      run,
      published: false,
      reason: `recovery run ${resolvedRunId} did not complete in time`,
    };
  }
  if (!isSuccessfulRecoveryRun(run)) {
    return {
      run,
      published: false,
      reason:
        `recovery run ${resolvedRunId} completed with ` +
        `${run.conclusion ?? 'no conclusion'}`,
    };
  }

  const result = await publishRecoveryEvidence({
    owner,
    repo,
    pullNumber,
    targetSha,
    runId: resolvedRunId,
    workflow,
    template,
    marker,
    token,
    fetchImpl,
    allowRunning: false,
  });
  return { run, published: true, ...result };
}

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: null,
    sha: null,
    runId: process.env.GITHUB_RUN_ID || null,
    triggerSha: null,
    workflow: RECOVERY_WORKFLOW_PATH,
    commentFile: null,
    commentMarker: '<!-- testeb-66-recovery -->',
    timeoutMs: 40 * 60 * 1000,
    intervalMs: 15 * 1000,
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
    } else if (arg === '--trigger-sha') {
      options.triggerSha = argv[++i];
    } else if (arg === '--workflow') {
      options.workflow = argv[++i];
    } else if (arg === '--comment-file') {
      options.commentFile = argv[++i];
    } else if (arg === '--comment-marker') {
      options.commentMarker = argv[++i];
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
  if (!options.sha || !/^[0-9a-f]{7,40}$/.test(options.sha)) {
    throw new Error('--sha <commit sha> is required');
  }
  if (options.runId && !/^[0-9]+$/.test(String(options.runId))) {
    throw new Error('--run-id must be numeric when provided');
  }
  if (!options.runId) {
    if (!options.triggerSha || !/^[0-9a-f]{7,40}$/i.test(options.triggerSha)) {
      throw new Error(
        'either --run-id <numeric run id> or --trigger-sha <commit sha> is required',
      );
    }
  }
  const [owner, repo] = options.repo.split('/');
  if (!owner || !repo) {
    throw new Error(`invalid --repo value: ${options.repo}`);
  }
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN || '';
  const log = (message) => process.stdout.write(`${message}\n`);

  let template = '';
  if (options.commentFile) {
    template = await readFile(options.commentFile, 'utf8');
  }

  if (options.dryRun) {
    let runId = options.runId;
    if (!runId) {
      const discovered = await findRecoveryRunForTrigger({
        owner,
        repo,
        triggerSha: options.triggerSha,
        workflow: options.workflow,
        token,
        timeoutMs: options.timeoutMs,
        intervalMs: options.intervalMs,
        log,
      });
      if (!discovered) {
        process.stderr.write(
          `no recovery run found for trigger ${options.triggerSha}\n`,
        );
        return 1;
      }
      runId = discovered.id;
    }
    const run = await waitForRunCompletion({
      runId,
      getRun: (id) =>
        getWorkflowRun({ owner, repo, runId: id, token }),
      timeoutMs: options.timeoutMs,
      intervalMs: options.intervalMs,
      log,
    });
    log(
      `recovery run ${runId}: status=${run?.status ?? 'unknown'} ` +
        `conclusion=${run?.conclusion ?? 'none'}`,
    );
    return isSuccessfulRecoveryRun(run) ? 0 : 1;
  }

  const result = await finalizeRecoveryEvidence({
    owner,
    repo,
    pullNumber: options.pr,
    targetSha: options.sha,
    runId: options.runId,
    triggerSha: options.triggerSha,
    workflow: options.workflow,
    template,
    marker: options.commentFile ? options.commentMarker : null,
    token,
    log,
  });

  if (!result.published) {
    process.stderr.write(`evidence not published: ${result.reason}\n`);
    return 1;
  }
  process.stdout.write(`${result.body.trimEnd()}\n`);
  if (!result.evidence.ready) {
    process.stderr.write(
      `evidence is red: ${result.evidence.problems.join('; ')}\n`,
    );
    return 1;
  }
  if (result.comment) {
    process.stdout.write(
      result.comment.created
        ? `evidence comment created: ${result.comment.id}\n`
        : `evidence comment updated: ${result.comment.id}\n`,
    );
  }
  return 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main()
    .then((code) => {
      process.exitCode = code;
    })
    .catch((error) => {
      process.stderr.write(`error: ${error.message}\n`);
      process.exitCode = 1;
    });
}
