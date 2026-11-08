import {
  getCheckRuns,
  getCombinedStatus,
  getPullRequest,
  getWorkflowRun,
  getWorkflowRunsForWorkflow,
  summarizeChecks,
  upsertIssueComment,
} from './lib.mjs';

export const RECOVERY_WORKFLOW_PATH = '.github/workflows/recover-pr71.yml';
export const EVIDENCE_START = '<!-- testeb-66-evidence:start -->';
export const EVIDENCE_END = '<!-- testeb-66-evidence:end -->';

function runPath(run) {
  return run?.path || run?.workflow_path || null;
}

function runUrl(run) {
  return run?.html_url || null;
}

function normalizeRun(run) {
  if (!run) {
    return null;
  }
  return {
    id: run.id ?? null,
    url: runUrl(run),
    path: runPath(run),
    event: run.event ?? null,
    status: run.status ?? null,
    conclusion: run.conclusion ?? null,
    headSha: run.head_sha ?? null,
    headBranch: run.head_branch ?? null,
    createdAt: run.created_at ?? null,
    updatedAt: run.updated_at ?? null,
  };
}

function checkName(run) {
  return run?.name || 'unnamed';
}

export function summarizeRecoveryEvidence({
  pr,
  run,
  checkRuns = [],
  statuses = [],
  targetSha,
  expectedWorkflowPath = RECOVERY_WORKFLOW_PATH,
  allowRunning = false,
}) {
  const checks = summarizeChecks(checkRuns, statuses);
  const problems = [];
  const normalizedRun = normalizeRun(run);

  if (!normalizedRun) {
    problems.push('no recovery workflow run was found');
  } else {
    if (
      normalizedRun.path &&
      expectedWorkflowPath &&
      normalizedRun.path !== expectedWorkflowPath
    ) {
      problems.push(
        `recovery run ${normalizedRun.id} belongs to ${normalizedRun.path}, ` +
          `expected ${expectedWorkflowPath}`,
      );
    }
    if (normalizedRun.status === 'completed') {
      if (normalizedRun.conclusion !== 'success') {
        problems.push(
          `recovery run ${normalizedRun.id} completed with ` +
            `${normalizedRun.conclusion ?? 'no conclusion'}`,
        );
      }
    } else if (!(allowRunning && normalizedRun.status === 'in_progress')) {
      problems.push(
        `recovery run ${normalizedRun.id} is ${normalizedRun.status ?? 'unknown'}, ` +
          'not a completed successful run',
      );
    }
  }

  if (checkRuns.length === 0) {
    problems.push(`commit ${targetSha} has no check runs`);
  }
  if (checks.total === 0) {
    problems.push(`commit ${targetSha} has no check runs or commit statuses`);
  } else {
    if (checks.pending.length > 0) {
      problems.push(
        `commit ${targetSha} has ${checks.pending.length} pending check(s)`,
      );
    }
    if (checks.failed.length > 0) {
      problems.push(
        `commit ${targetSha} has ${checks.failed.length} failed check(s)`,
      );
    }
  }

  const merged = Boolean(pr?.merged);
  const mergeCommitSha = pr?.merge_commit_sha ?? null;
  if (!merged) {
    problems.push('pull request is not merged');
  } else if (!mergeCommitSha) {
    problems.push('merged pull request has no merge commit sha');
  }

  const headMatches = !targetSha || pr?.head?.sha === targetSha;
  if (!headMatches) {
    problems.push(
      `pull request head is ${pr?.head?.sha ?? 'unknown'}, expected ${targetSha}`,
    );
  }

  return {
    targetSha,
    run: normalizedRun,
    checks: {
      total: checks.total,
      success: checks.success,
      names: checkRuns.map(checkName),
      conclusions: checkRuns.map((check) => ({
        name: checkName(check),
        conclusion: check.conclusion ?? null,
      })),
      statuses: statuses.map((item) => ({
        context: item?.context ?? null,
        state: item?.state ?? null,
      })),
    },
    pullRequest: {
      merged,
      state: pr?.state ?? null,
      mergeCommitSha,
      mergedAt: pr?.merged_at ?? null,
      headSha: pr?.head?.sha ?? null,
      headMatches,
    },
    ready: problems.length === 0,
    problems,
  };
}

export function renderEvidenceBlock(evidence) {
  const lines = [];
  lines.push('## Подтверждение из реального recovery-прогона');
  lines.push('');
  lines.push(`- Проверяемый коммит (актуальный head PR): \`${evidence.targetSha}\``);
  if (evidence.run) {
    lines.push(
      `- Recovery workflow run: [#${evidence.run.id}](${evidence.run.url}) — ` +
        `status=${evidence.run.status}, conclusion=${evidence.run.conclusion}, ` +
        `event=${evidence.run.event}, runner head=\`${evidence.run.headSha}\``,
    );
    if (evidence.run.headBranch) {
      lines.push(`- Ветка прогона: \`${evidence.run.headBranch}\``);
    }
  } else {
    lines.push('- Recovery workflow run: не найден');
  }
  const conclusions = evidence.checks.conclusions.length
    ? evidence.checks.conclusions
        .map((check) => `\`${check.name}=${check.conclusion}\``)
        .join(', ')
    : 'нет';
  lines.push(`- Проверки на проверяемом коммите: ${conclusions}`);
  lines.push(
    `- PR merged=${evidence.pullRequest.merged}, ` +
      `merge commit=\`${evidence.pullRequest.mergeCommitSha ?? 'none'}\`, ` +
      `merged at=${evidence.pullRequest.mergedAt ?? 'none'}`,
  );
  lines.push(
    `- Head PR совпадает с проверенным коммитом: ${evidence.pullRequest.headMatches}`,
  );
  lines.push(
    `- Итог: ${evidence.ready ? 'доказательства полные (green)' : 'доказательств недостаточно (red)'}`,
  );
  return lines.join('\n');
}

export function renderEvidenceComment(template, evidence) {
  const body = (template || '').trimEnd();
  const block = `${EVIDENCE_START}\n${renderEvidenceBlock(evidence)}\n${EVIDENCE_END}`;

  if (body.includes(EVIDENCE_START) && body.includes(EVIDENCE_END)) {
    const start = body.indexOf(EVIDENCE_START);
    const end = body.indexOf(EVIDENCE_END) + EVIDENCE_END.length;
    return `${body.slice(0, start)}${block}${body.slice(end)}\n`;
  }

  const lines = [];
  if (body) {
    lines.push(body, '');
  }
  lines.push('---');
  lines.push('');
  lines.push(block);
  return `${lines.join('\n')}\n`;
}

function latestSuccessfulRun(runs) {
  return (Array.isArray(runs) ? runs : [])
    .filter((run) => run?.status === 'completed' && run?.conclusion === 'success')
    .sort((a, b) => {
      const left = Date.parse(a.updated_at || a.created_at || 0);
      const right = Date.parse(b.updated_at || b.created_at || 0);
      return right - left;
    })[0] ?? null;
}

export async function collectRecoveryEvidence({
  owner,
  repo,
  pullNumber,
  targetSha,
  runId = null,
  workflow = RECOVERY_WORKFLOW_PATH,
  token,
  fetchImpl = globalThis.fetch,
  allowRunning = false,
}) {
  const [pr, checkRuns, status, run] = await Promise.all([
    getPullRequest({ owner, repo, pullNumber, token, fetchImpl }),
    getCheckRuns({ owner, repo, ref: targetSha, token, fetchImpl }),
    getCombinedStatus({ owner, repo, ref: targetSha, token, fetchImpl }),
    runId
      ? getWorkflowRun({ owner, repo, runId, token, fetchImpl })
      : getWorkflowRunsForWorkflow({
          owner,
          repo,
          workflow,
          token,
          fetchImpl,
        }).then((response) => latestSuccessfulRun(response?.workflow_runs)),
  ]);

  return summarizeRecoveryEvidence({
    pr,
    run,
    checkRuns: checkRuns?.check_runs ?? [],
    statuses: status?.statuses ?? [],
    targetSha,
    expectedWorkflowPath: workflow,
    allowRunning,
  });
}

export async function publishRecoveryEvidence({
  owner,
  repo,
  pullNumber,
  targetSha,
  runId = null,
  workflow = RECOVERY_WORKFLOW_PATH,
  template = '',
  marker = null,
  token,
  fetchImpl = globalThis.fetch,
  allowRunning = false,
}) {
  const evidence = await collectRecoveryEvidence({
    owner,
    repo,
    pullNumber,
    targetSha,
    runId,
    workflow,
    token,
    fetchImpl,
    allowRunning,
  });
  const body = renderEvidenceComment(template, evidence);
  if (!evidence.ready) {
    return { evidence, body, comment: null };
  }
  const comment = await upsertIssueComment({
    owner,
    repo,
    pullNumber,
    body,
    marker,
    token,
    fetchImpl,
  });
  return { evidence, body, comment };
}
