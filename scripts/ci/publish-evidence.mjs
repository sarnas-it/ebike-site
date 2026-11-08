#!/usr/bin/env node
import { readFile } from 'node:fs/promises';
import process from 'node:process';
import {
  RECOVERY_WORKFLOW_PATH,
  collectRecoveryEvidence,
  publishRecoveryEvidence,
  renderEvidenceComment,
} from './evidence.mjs';

const USAGE = `Usage:
  publish-evidence.mjs --pr <number> --sha <sha> [options]

Verifies that the pull request has a real, successful recovery workflow run,
green checks on the exact target commit and a completed merge, then writes or
updates a single evidence comment on the pull request.

Unlike a bare Checks API response, green check runs are not trusted on their
own: the verifier returns red unless they are backed by the recovery workflow
run that actually executed them.

Options:
  --repo <owner/name>   Repository (default: $GITHUB_REPOSITORY or sarnas-it/ebike-site)
  --pr <number>         Pull request number (required)
  --sha <sha>           Exact commit under test (required)
  --run-id <id>         Recovery workflow run to verify (default: latest successful)
  --workflow <path>     Recovery workflow path (default: ${RECOVERY_WORKFLOW_PATH})
  --comment-file <path> Markdown template to publish/update on the pull request
  --comment-marker <s>  Idempotency marker for the comment
  --allow-running       Accept the current run while it is still in progress
                        (used inside the recovery job itself)
  --dry-run             Print the evidence without writing to GitHub
  -h, --help            Show this help

Authentication: set GITHUB_TOKEN (never printed).
`;

function parseArgs(argv) {
  const options = {
    repo: process.env.GITHUB_REPOSITORY || 'sarnas-it/ebike-site',
    pr: null,
    sha: null,
    runId: process.env.GITHUB_RUN_ID || null,
    workflow: RECOVERY_WORKFLOW_PATH,
    commentFile: null,
    commentMarker: '<!-- testeb-63-recovery -->',
    allowRunning: false,
    dryRun: false,
  };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '-h' || arg === '--help') {
      options.help = true;
    } else if (arg === '--allow-running') {
      options.allowRunning = true;
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
    } else if (arg === '--workflow') {
      options.workflow = argv[++i];
    } else if (arg === '--comment-file') {
      options.commentFile = argv[++i];
    } else if (arg === '--comment-marker') {
      options.commentMarker = argv[++i];
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
  const [owner, repo] = options.repo.split('/');
  if (!owner || !repo) {
    throw new Error(`invalid --repo value: ${options.repo}`);
  }
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN || '';

  let template = '';
  if (options.commentFile) {
    template = await readFile(options.commentFile, 'utf8');
  }

  const shared = {
    owner,
    repo,
    pullNumber: options.pr,
    targetSha: options.sha,
    runId: options.runId,
    workflow: options.workflow,
    token,
    allowRunning: options.allowRunning,
  };

  if (options.dryRun) {
    const evidence = await collectRecoveryEvidence(shared);
    process.stdout.write(renderEvidenceComment(template, evidence));
    if (!evidence.ready) {
      process.stderr.write(
        `evidence is red: ${evidence.problems.join('; ')}\n`,
      );
      return 1;
    }
    return 0;
  }

  const result = await publishRecoveryEvidence({
    ...shared,
    template,
    marker: options.commentFile ? options.commentMarker : null,
  });

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

main()
  .then((code) => {
    process.exitCode = code;
  })
  .catch((error) => {
    process.stderr.write(`error: ${error.message}\n`);
    process.exitCode = 1;
  });
