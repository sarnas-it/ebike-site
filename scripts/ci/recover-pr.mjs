#!/usr/bin/env node
import { readFile } from 'node:fs/promises';
import process from 'node:process';
import { recoverPullRequest } from './lib.mjs';

const USAGE = `Usage:
  recover-pr.mjs --pr <number> --sha <sha> --results <file> [options]

Publishes the results of scripts/ci/run-checks.sh as check runs and a commit
status on the exact commit under test, then completes the deferred merge of the
pull request when every recovered check passed.

Options:
  --repo <owner/name>   Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>         Pull request number (required)
  --sha <sha>           Exact commit that was checked (required)
  --results <file>      JSON produced by scripts/ci/run-checks.sh (required)
  --details-url <url>   Link back to the recovery job (default: $CI_RUN_URL)
  --summary <text>      Check run summary
  --comment-file <path> Markdown comment to post once on the pull request
  --comment-marker <s>  Idempotency marker for the comment
  --merge-method <m>    First merge method to try (default: squash)
  --dry-run             Print the plan without writing to GitHub
  -h, --help            Show this help

Authentication: set GITHUB_TOKEN (never printed).
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: null,
    sha: null,
    results: null,
    detailsUrl: process.env.CI_RUN_URL || '',
    summary: '',
    commentFile: null,
    commentMarker: '<!-- testeb-67-recovery -->',
    mergeMethod: 'squash',
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
    } else if (arg === '--results') {
      options.results = argv[++i];
    } else if (arg === '--details-url') {
      options.detailsUrl = argv[++i];
    } else if (arg === '--summary') {
      options.summary = argv[++i];
    } else if (arg === '--comment-file') {
      options.commentFile = argv[++i];
    } else if (arg === '--comment-marker') {
      options.commentMarker = argv[++i];
    } else if (arg === '--merge-method') {
      options.mergeMethod = argv[++i];
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
  if (!options.sha || !/^[0-9a-f]{40}$/.test(options.sha)) {
    throw new Error('--sha <40-char commit sha> is required');
  }
  if (!options.results) {
    throw new Error('--results <file> is required');
  }
  const [owner, repo] = options.repo.split('/');
  if (!owner || !repo) {
    throw new Error(`invalid --repo value: ${options.repo}`);
  }
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN || '';
  const log = (message) => process.stdout.write(`${message}\n`);

  const results = JSON.parse(await readFile(options.results, 'utf8'));
  const names = Object.keys(results);
  if (names.length === 0) {
    throw new Error(`no check results found in ${options.results}`);
  }
  if (options.dryRun) {
    log(
      `dry run: would publish ${names
        .map((name) => `${name}=${results[name]}`)
        .join(', ')} for ${options.sha} on #${options.pr}`,
    );
    return 0;
  }

  let commentBody = null;
  if (options.commentFile) {
    commentBody = await readFile(options.commentFile, 'utf8');
    if (options.detailsUrl && !commentBody.includes(options.detailsUrl)) {
      commentBody += `\n\n[Recovery run](${options.detailsUrl})\n`;
    }
  }

  const methods = [
    options.mergeMethod,
    ...['squash', 'merge', 'rebase'].filter(
      (method) => method !== options.mergeMethod,
    ),
  ];

  const result = await recoverPullRequest({
    owner,
    repo,
    pullNumber: options.pr,
    targetSha: options.sha,
    results,
    detailsUrl: options.detailsUrl || undefined,
    summary: options.summary || undefined,
    mergeMethods: methods,
    commentBody,
    commentMarker: commentBody ? options.commentMarker : null,
    token,
    log,
  });

  log(
    `recovery outcome: merged=${result.merged}` +
      (result.skipped ? ` skipped=${result.skipped}` : '') +
      (result.mergeCommitSha ? ` merge=${result.mergeCommitSha}` : ''),
  );
  return result.merged || result.skipped === 'already-merged' ? 0 : 1;
}

main()
  .then((code) => {
    process.exitCode = code;
  })
  .catch((error) => {
    process.stderr.write(`error: ${error.message}\n`);
    process.exitCode = 1;
  });
