import {
  WORKFLOW_PATH,
  getContentAtRef,
  getPullRequest,
  getRepository,
  getWorkflowRun,
  getWorkflowRunJobs,
  getWorkflowRuns,
  upsertIssueComment,
} from './lib.mjs';

export const FIX_PR_NUMBER = 65;
export const BASE_BRANCH = 'main';
export const EXPECTED_CI_JOBS = [
  'php-lint',
  'php-tests',
  'plugin-tests',
  'ci-repair-tests',
];

function normalizeRun(run) {
  if (!run) {
    return null;
  }
  return {
    id: run.id ?? null,
    url: run.html_url ?? null,
    path: run.path ?? run.workflow_path ?? null,
    name: run.name ?? null,
    event: run.event ?? null,
    status: run.status ?? null,
    conclusion: run.conclusion ?? null,
    headSha: run.head_sha ?? null,
    headBranch: run.head_branch ?? null,
    createdAt: run.created_at ?? null,
    updatedAt: run.updated_at ?? null,
  };
}

function normalizeJob(job) {
  return {
    id: job?.id ?? null,
    name: job?.name ?? null,
    status: job?.status ?? null,
    conclusion: job?.conclusion ?? null,
  };
}

function runTimestamp(run) {
  return Date.parse(run?.updated_at || run?.created_at || 0) || 0;
}

export function selectLatestCiRun(runs, targetSha, expectedWorkflowPath = WORKFLOW_PATH) {
  const candidates = (Array.isArray(runs) ? runs : []).filter((run) => {
    if (!run) {
      return false;
    }
    if (targetSha && run.head_sha && run.head_sha !== targetSha) {
      return false;
    }
    const runPath = run.path || run.workflow_path;
    if (runPath && expectedWorkflowPath && runPath !== expectedWorkflowPath) {
      return false;
    }
    return true;
  });
  candidates.sort((left, right) => runTimestamp(right) - runTimestamp(left));
  return candidates[0] ?? null;
}

export function summarizeRolloutEvidence({
  pr,
  repository,
  workflow,
  run,
  jobs = [],
  targetSha = null,
  fixPr = FIX_PR_NUMBER,
  baseBranch = BASE_BRANCH,
  expectedWorkflowPath = WORKFLOW_PATH,
  expectedJobs = EXPECTED_CI_JOBS,
  workflowRef = baseBranch,
}) {
  const problems = [];
  const normalizedRun = normalizeRun(run);
  const jobList = (Array.isArray(jobs) ? jobs : []).map(normalizeJob);

  const prNumber = pr?.number ?? null;
  const merged = Boolean(pr?.merged);
  const state = pr?.state ?? null;
  const mergeable = pr?.mergeable ?? null;
  const mergeableState = pr?.mergeable_state ?? null;
  const mergeCommitSha = pr?.merge_commit_sha ?? null;
  const headSha = pr?.head?.sha ?? null;
  const prBase = pr?.base?.ref ?? null;
  const resolvedSha = targetSha || headSha;

  if (!pr) {
    problems.push(`pull request #${fixPr} was not found`);
  } else {
    if (prNumber != null && prNumber !== fixPr) {
      problems.push(`expected pull request #${fixPr}, got #${prNumber}`);
    }
    if (prBase !== baseBranch) {
      problems.push(
        `pull request #${fixPr} targets ${prBase ?? 'unknown'}, expected ${baseBranch}`,
      );
    }
    if (merged) {
      // The tracker merges the fix pull request only after acceptance, so the
      // merged state is not a precondition for this gate. It must still be
      // consistent once the merge has happened.
      if (!mergeCommitSha) {
        problems.push(`merged pull request #${fixPr} has no merge commit sha`);
      }
    } else {
      // Before acceptance the fix lives in the open pull request. Readiness for
      // the automated merge is proven by green CI on the real head commit and
      // by the absence of conflicts with the base branch.
      if (state !== 'open') {
        problems.push(
          `pull request #${fixPr} is ${state ?? 'unknown'}, expected open or merged`,
        );
      }
      if (mergeable !== true) {
        problems.push(
          `pull request #${fixPr} is not mergeable ` +
            `(mergeable=${mergeable ?? 'unknown'}, ` +
            `state=${mergeableState ?? 'unknown'})`,
        );
      }
      if (mergeableState === 'dirty') {
        problems.push(
          `pull request #${fixPr} has merge conflicts with ${baseBranch}`,
        );
      }
    }
    if (targetSha) {
      const allowed = [headSha, mergeCommitSha].filter(Boolean);
      if (allowed.length > 0 && !allowed.includes(targetSha)) {
        problems.push(
          `target commit ${targetSha} is neither the pull request head ` +
            `${headSha ?? 'unknown'} nor the merge commit ${mergeCommitSha ?? 'unknown'}`,
        );
      }
    }
  }

  if (!repository) {
    problems.push('repository metadata is missing');
  } else if (repository.default_branch !== baseBranch) {
    problems.push(
      `default branch is ${repository.default_branch ?? 'unknown'}, expected ${baseBranch}`,
    );
  }

  const workflowPresent = Boolean(workflow);
  if (!workflowPresent) {
    problems.push(`${expectedWorkflowPath} is missing on ${workflowRef}`);
  }

  if (!resolvedSha) {
    problems.push('no target commit could be resolved');
  }
  if (!normalizedRun) {
    problems.push(`no CI run was found for ${resolvedSha ?? 'the target commit'}`);
  } else {
    if (
      normalizedRun.path &&
      expectedWorkflowPath &&
      normalizedRun.path !== expectedWorkflowPath
    ) {
      problems.push(
        `CI run ${normalizedRun.id} belongs to ${normalizedRun.path}, ` +
          `expected ${expectedWorkflowPath}`,
      );
    }
    if (resolvedSha && normalizedRun.headSha !== resolvedSha) {
      problems.push(
        `CI run ${normalizedRun.id} is for ${normalizedRun.headSha ?? 'unknown'}, ` +
          `expected ${resolvedSha}`,
      );
    }
    if (normalizedRun.status !== 'completed') {
      problems.push(
        `CI run ${normalizedRun.id} is ${normalizedRun.status ?? 'unknown'}, ` +
          'not a completed run',
      );
    } else if (normalizedRun.conclusion !== 'success') {
      problems.push(
        `CI run ${normalizedRun.id} completed with ` +
          `${normalizedRun.conclusion ?? 'no conclusion'}`,
      );
    }
  }

  if (jobList.length === 0) {
    problems.push(
      `CI run ${normalizedRun?.id ?? 'unknown'} has no jobs`,
    );
  } else {
    for (const name of expectedJobs) {
      const job = jobList.find((candidate) => candidate.name === name);
      if (!job) {
        problems.push(`CI run is missing the ${name} job`);
      } else if (job.conclusion !== 'success') {
        problems.push(
          `CI job ${name} is ${job.conclusion ?? job.status ?? 'unknown'}, expected success`,
        );
      }
    }
  }

  return {
    fixPr,
    baseBranch,
    targetSha: resolvedSha,
    workflowPath: expectedWorkflowPath,
    workflowRef,
    workflowPresent,
    run: normalizedRun,
    jobs: jobList,
    pullRequest: {
      number: prNumber,
      state,
      merged,
      mergeable,
      mergeableState,
      base: prBase,
      headSha,
      mergeCommitSha,
      mergedAt: pr?.merged_at ?? null,
    },
    ready: problems.length === 0,
    problems,
  };
}

export async function collectRolloutEvidence({
  owner,
  repo,
  fixPr = FIX_PR_NUMBER,
  targetSha = null,
  runId = null,
  baseBranch = BASE_BRANCH,
  workflow = WORKFLOW_PATH,
  expectedJobs = EXPECTED_CI_JOBS,
  token,
  fetchImpl = globalThis.fetch,
}) {
  const [pr, repository] = await Promise.all([
    getPullRequest({ owner, repo, pullNumber: fixPr, token, fetchImpl }),
    getRepository({ owner, repo, token, fetchImpl }),
  ]);

  // Before the tracker merges the fix pull request (the merge happens only after
  // acceptance) the CI workflow lives in the pull request head tree, not in the
  // base branch. Check it where the head run actually reads it from.
  const workflowRef = pr?.merged
    ? baseBranch
    : pr?.head?.sha || pr?.head?.ref || baseBranch;
  const workflowContent = await getContentAtRef({
    owner,
    repo,
    filePath: workflow,
    ref: workflowRef,
    token,
    fetchImpl,
  });

  const resolvedSha = targetSha || pr?.head?.sha || null;
  let run = null;
  if (runId) {
    run = await getWorkflowRun({ owner, repo, runId, token, fetchImpl });
  } else if (resolvedSha) {
    const runs = await getWorkflowRuns({
      owner,
      repo,
      ref: resolvedSha,
      token,
      fetchImpl,
    });
    run = selectLatestCiRun(runs?.workflow_runs, resolvedSha, workflow);
  }

  let jobs = [];
  if (run?.id) {
    const response = await getWorkflowRunJobs({
      owner,
      repo,
      runId: run.id,
      token,
      fetchImpl,
    });
    jobs = response?.jobs ?? [];
  }

  return summarizeRolloutEvidence({
    pr,
    repository,
    workflow: workflowContent,
    run,
    jobs,
    targetSha: resolvedSha,
    fixPr,
    baseBranch,
    expectedWorkflowPath: workflow,
    expectedJobs,
    workflowRef,
  });
}

export function renderRolloutComment(evidence, marker = '<!-- testeb-63-rollout -->') {
  const lines = [];
  if (marker) {
    lines.push(marker, '');
  }
  lines.push('## Готовность починки CI (TESTEB-63, PR #' + evidence.fixPr + ')');
  lines.push('');
  lines.push(
    `- PR #${evidence.fixPr} → ${evidence.baseBranch}: ` +
      `state=${evidence.pullRequest.state ?? 'unknown'}, ` +
      `merged=${evidence.pullRequest.merged}, ` +
      `mergeable=${evidence.pullRequest.mergeable ?? 'unknown'}, ` +
      `mergeable_state=${evidence.pullRequest.mergeableState ?? 'unknown'}`,
  );
  lines.push(`- Проверяемый head-коммит: \`${evidence.targetSha ?? 'none'}\``);
  if (evidence.run) {
    lines.push(
      `- Штатный прогон ci: [#${evidence.run.id}](${evidence.run.url}) — ` +
        `status=${evidence.run.status}, conclusion=${evidence.run.conclusion}, ` +
        `event=${evidence.run.event}, head=\`${evidence.run.headSha}\``,
    );
  } else {
    lines.push('- Штатный прогон ci: не найден');
  }
  lines.push(
    `- Workflow: ${evidence.workflowPresent ? evidence.workflowPath : 'missing'} ` +
      `(${evidence.workflowRef ?? 'unknown'})`,
  );
  lines.push(
    `- Job: ${
      evidence.jobs
        .map((job) => `\`${job.name}=${job.conclusion ?? job.status ?? 'unknown'}\``)
        .join(', ') || 'нет'
    }`,
  );
  lines.push(
    `- Итог: ${evidence.ready ? 'green — CI на реальном head зелёный, PR готов к автомержу' : 'red — доказательств недостаточно'}`,
  );
  if (!evidence.ready) {
    lines.push(`- Проблемы: ${evidence.problems.join('; ')}`);
  }
  return `${lines.join('\n')}\n`;
}

export async function publishRolloutEvidence({
  owner,
  repo,
  fixPr = FIX_PR_NUMBER,
  targetSha = null,
  runId = null,
  baseBranch = BASE_BRANCH,
  workflow = WORKFLOW_PATH,
  expectedJobs = EXPECTED_CI_JOBS,
  marker = '<!-- testeb-63-rollout -->',
  token,
  fetchImpl = globalThis.fetch,
}) {
  const evidence = await collectRolloutEvidence({
    owner,
    repo,
    fixPr,
    targetSha,
    runId,
    baseBranch,
    workflow,
    expectedJobs,
    token,
    fetchImpl,
  });
  const body = renderRolloutComment(evidence, marker);
  if (!evidence.ready) {
    return { evidence, body, comment: null };
  }
  const comment = await upsertIssueComment({
    owner,
    repo,
    pullNumber: fixPr,
    body,
    marker,
    token,
    fetchImpl,
  });
  return { evidence, body, comment };
}
