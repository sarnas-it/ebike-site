#!/usr/bin/env node
import { appendFile } from 'node:fs/promises';
import process from 'node:process';
import { pathToFileURL } from 'node:url';
import { getPullRequest } from './lib.mjs';

const USAGE = `Usage:
  resolve-pr-target.mjs --pr <number> [options]

Resolves the commit that recovery must check from the pull request's *current*
head, instead of pinning the SHA from the incident report. A pull request head
can move after the task was filed; recovery must always follow the head that is
about to be merged.

The incident SHA is still accepted with --expected-sha for auditing. When it no
longer matches the current head the script reports drifted=true and proceeds
with the current head.

Options:
  --repo <owner/name>   Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>         Pull request number (required)
  --expected-sha <sha>  Incident SHA to compare against (optional)
  --github-output <p>   Append sha/head_ref/drifted outputs (default: $GITHUB_OUTPUT)
  -h, --help            Show this help

Authentication: set GITHUB_TOKEN (never printed); reading a public repository
works without one.
`;

export function resolveTargetFromPullRequest(pr, { expectedSha = null } = {}) {
  const headSha = pr?.head?.sha ?? null;
  if (!headSha) {
    throw new Error(`pull request ${pr?.number ?? ''} has no head sha`.trim());
  }
  const normalizedExpected = expectedSha
    ? String(expectedSha).toLowerCase()
    : null;
  return {
    prNumber: pr.number ?? null,
    sha: headSha,
    headSha,
    headRef: pr?.head?.ref ?? null,
    expectedSha: normalizedExpected,
    drifted: Boolean(
      normalizedExpected && normalizedExpected !== headSha.toLowerCase(),
    ),
    merged: Boolean(pr?.merged),
    state: pr?.state ?? null,
  };
}

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: null,
    expectedSha: null,
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
    } else if (arg === '--expected-sha') {
      options.expectedSha = argv[++i];
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
  const target = resolveTargetFromPullRequest(pr, {
    expectedSha: options.expectedSha,
  });

  process.stdout.write(`pull request: #${target.prNumber ?? options.pr}\n`);
  process.stdout.write(`head branch:  ${target.headRef ?? 'unknown'}\n`);
  process.stdout.write(`target sha:   ${target.sha}\n`);
  if (target.expectedSha) {
    process.stdout.write(
      `incident sha: ${target.expectedSha} ` +
        `(drifted=${target.drifted})\n`,
    );
  }
  process.stdout.write(`merged:       ${target.merged}\n`);

  if (options.githubOutput) {
    const lines = [
      `sha=${target.sha}`,
      `head_ref=${target.headRef ?? ''}`,
      `drifted=${target.drifted}`,
      `expected_sha=${target.expectedSha ?? ''}`,
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
