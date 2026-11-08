#!/usr/bin/env node
import { appendFile } from 'node:fs/promises';
import process from 'node:process';
import { pathToFileURL } from 'node:url';
import {
  WORKFLOW_PATH,
  collectChecks,
  getPullRequest,
  getPullRequestsForCommit,
  getWorkflowRuns,
} from './lib.mjs';

const USAGE = `Usage:
  should-recover.mjs --pr <number> [options]

Decides whether recovery is needed for a pull request. A completed successful
CI run can still leave a stale pending duplicate check run on the commit (for
example after a superseded run); such a commit needs the recovered checks to be
published even though the job log itself was green.

A commit with no checks at all ("no_checks") needs recovery too, but only when
the ci workflow has no run for that commit. If a ci run already exists (even a
queued one), the regular workflow owns the result and recovery must not race it.

Options:
  --repo <owner/name>        Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>              Pull request number (optional when --sha is used)
  --sha <sha>                Commit used to resolve the pull request when --pr is absent
  --require-missing-ci-run   Recover only when the ci workflow has no run for the commit
  --workflow <path>          CI workflow path (default: ${WORKFLOW_PATH})
  --github-output <p>        Append pr/sha/needed/reason outputs (default: $GITHUB_OUTPUT)
  -h, --help                 Show this help

Authentication: set GITHUB_TOKEN (never printed).
`;

const PENDING = ['queued', 'in_progress', 'pending', 'waiting', 'requested'];

function isPendingRun(run) {
  if (run?.conclusion != null && !PENDING.includes(run.conclusion)) {
    return false;
  }
  if (run?.status && run.status !== 'completed') {
    return true;
  }
  return run?.conclusion == null || PENDING.includes(run.conclusion);
}

export function assessRecoveryNeed(checks = {}) {
  const value = checks || {};
  const runs = Array.isArray(value.runs) ? value.runs : [];
  const rawPending = runs.filter(isPendingRun);
  if (Array.isArray(value.failed) && value.failed.length > 0) {
    return {
      needed: false,
      failed: true,
      reason: `checks failed (${value.failed.length}); recovery must not hide a real failure`,
    };
  }
  if (rawPending.length > 0) {
    return {
      needed: true,
      failed: false,
      pending: rawPending.length,
      reason:
        `${rawPending.length} pending check run(s) remain on the commit; ` +
        'a superseded run likely left a stale in_progress duplicate',
    };
  }
  if (!value || value.total === 0) {
    return {
      needed: true,
      failed: false,
      pending: 0,
      reason: 'no check runs or commit statuses were reported for the commit',
    };
  }
  if (Array.isArray(value.pending) && value.pending.length > 0) {
    return {
      needed: true,
      failed: false,
      pending: value.pending.length,
      reason: 'checks are still pending after the CI run completed',
    };
  }
  return {
    needed: false,
    failed: false,
    pending: 0,
    reason: 'checks already completed successfully',
  };
}

function runPath(run) {
  return run?.path || run?.workflow_path || null;
}

export function ciRunsForCommit(runs = [], workflowPath = WORKFLOW_PATH) {
  return (Array.isArray(runs) ? runs : []).filter((run) => {
    if (!run) {
      return false;
    }
    const path = runPath(run);
    return path == null || path === workflowPath;
  });
}

// The no_checks class means GitHub never started the ci workflow for the
// commit, so there is no run to wait for. Recovery may only take over in that
// exact situation: as soon as a ci run exists (queued, running or completed)
// the regular workflow is responsible for publishing the result, and recovery
// must not race it or hide a real failure.
export function assessMissingCiRun(runs = [], workflowPath = WORKFLOW_PATH) {
  const matches = ciRunsForCommit(runs, workflowPath);
  if (matches.length > 0) {
    return {
      needed: false,
      reason:
        `the ci workflow already has ${matches.length} run(s) for the commit; ` +
        'the regular workflow owns the result',
    };
  }
  return {
    needed: true,
    reason:
      'the ci workflow has no run for the commit (no_checks); ' +
      'recovery must run the checks itself',
  };
}

export function decideRecovery({
  checks = {},
  runs = null,
  requireMissingCiRun = false,
  workflowPath = WORKFLOW_PATH,
} = {}) {
  const decision = assessRecoveryNeed(checks);
  if (!requireMissingCiRun) {
    return { decision, ciRunDecision: null };
  }
  const ciRunDecision = assessMissingCiRun(runs, workflowPath);
  if (!ciRunDecision.needed) {
    const pending = Array.isArray(checks.pending) ? checks.pending.length : 0;
    return {
      decision: {
        needed: false,
        failed: false,
        pending,
        reason: ciRunDecision.reason,
      },
      ciRunDecision,
    };
  }
  return { decision, ciRunDecision };
}

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: null,
    sha: null,
    requireMissingCiRun: false,
    workflow: WORKFLOW_PATH,
    githubOutput: process.env.GITHUB_OUTPUT || null,
  };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '-h' || arg === '--help') {
      options.help = true;
    } else if (arg === '--require-missing-ci-run') {
      options.requireMissingCiRun = true;
    } else if (arg === '--repo') {
      options.repo = argv[++i];
    } else if (arg === '--pr') {
      options.pr = Number(argv[++i]);
    } else if (arg === '--sha') {
      options.sha = argv[++i];
    } else if (arg === '--workflow') {
      options.workflow = argv[++i];
    } else if (arg === '--github-output') {
      options.githubOutput = argv[++i];
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
  if ((!Number.isInteger(options.pr) || options.pr <= 0) && !options.sha) {
    throw new Error('--pr <number> or --sha <commit> is required');
  }
  const [owner, repo] = options.repo.split('/');
  if (!owner || !repo) {
    throw new Error(`invalid --repo value: ${options.repo}`);
  }
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN || '';

  let pullNumber = options.pr;
  if (!Number.isInteger(pullNumber) || pullNumber <= 0) {
    const pulls = await getPullRequestsForCommit({
      owner,
      repo,
      ref: options.sha,
      token,
    });
    const candidates = Array.isArray(pulls) ? pulls : [];
    const candidate =
      candidates.find((item) => item?.state === 'open') || candidates[0];
    if (!candidate?.number) {
      const reason = `no pull request is associated with commit ${options.sha}`;
      process.stdout.write(`recovery:     needed=false (${reason})\n`);
      if (options.githubOutput) {
        await appendFile(
          options.githubOutput,
          `pr=\nsha=${options.sha || ''}\nneeded=false\nreason=${reason}\n`,
          'utf8',
        );
      }
      return 0;
    }
    pullNumber = candidate.number;
  }

  const pr = await getPullRequest({
    owner,
    repo,
    pullNumber,
    token,
  });
  const targetSha = pr?.head?.sha ?? options.sha;
  if (!targetSha) {
    throw new Error(`pull request #${pullNumber} has no head sha`);
  }
  const checks = await collectChecks({
    owner,
    repo,
    ref: targetSha,
    token,
  });
  let runs = null;
  if (options.requireMissingCiRun) {
    const response = await getWorkflowRuns({
      owner,
      repo,
      ref: targetSha,
      token,
    });
    runs = response?.workflow_runs ?? [];
  }
  const { decision, ciRunDecision } = decideRecovery({
    checks,
    runs,
    requireMissingCiRun: options.requireMissingCiRun,
    workflowPath: options.workflow,
  });

  process.stdout.write(`pull request: #${pullNumber}\n`);
  process.stdout.write(`target sha:   ${targetSha}\n`);
  process.stdout.write(
    `checks:       total=${checks.total} pending=${checks.pending.length} ` +
      `failed=${checks.failed.length}\n`,
  );
  if (ciRunDecision) {
    process.stdout.write(
      `ci run:       ${ciRunDecision.needed ? 'missing (no_checks)' : 'present'}\n`,
    );
  }
  process.stdout.write(
    `recovery:     needed=${decision.needed} (${decision.reason})\n`,
  );

  if (options.githubOutput) {
    const lines = [
      `pr=${pullNumber}`,
      `sha=${targetSha}`,
      `needed=${decision.needed}`,
      `reason=${decision.reason}`,
      '',
    ];
    await appendFile(options.githubOutput, lines.join('\n'), 'utf8');
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
