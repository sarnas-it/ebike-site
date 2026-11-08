#!/usr/bin/env node
import { appendFile } from 'node:fs/promises';
import process from 'node:process';
import { pathToFileURL } from 'node:url';
import {
  collectChecks,
  getPullRequest,
  getPullRequestsForCommit,
} from './lib.mjs';

const USAGE = `Usage:
  should-recover.mjs --pr <number> [options]

Decides whether recovery is needed for a pull request. A completed successful
CI run can still leave a stale pending duplicate check run on the commit (for
example after a superseded run); such a commit needs the recovered checks to be
published even though the job log itself was green.

Options:
  --repo <owner/name>   Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>         Pull request number (optional when --sha is used)
  --sha <sha>           Commit used to resolve the pull request when --pr is absent
  --github-output <p>   Append pr/sha/needed/reason outputs (default: $GITHUB_OUTPUT)
  -h, --help            Show this help

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

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: null,
    sha: null,
    githubOutput: process.env.GITHUB_OUTPUT || null,
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
  const decision = assessRecoveryNeed(checks);

  process.stdout.write(`pull request: #${pullNumber}\n`);
  process.stdout.write(`target sha:   ${targetSha}\n`);
  process.stdout.write(
    `checks:       total=${checks.total} pending=${checks.pending.length} ` +
      `failed=${checks.failed.length}\n`,
  );
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
