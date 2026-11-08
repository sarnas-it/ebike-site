import { Buffer } from 'node:buffer';

export const WORKFLOW_PATH = '.github/workflows/ci.yml';

const API_ROOT = 'https://api.github.com';

function apiUrl(owner, repo, suffix) {
  return `${API_ROOT}/repos/${owner}/${repo}${suffix}`;
}

function headers(token) {
  const value = {
    Accept: 'application/vnd.github+json',
    'X-GitHub-Api-Version': '2022-11-28',
    'User-Agent': 'velotok-ci-repair',
  };
  if (token) {
    value.Authorization = `Bearer ${token}`;
  }
  return value;
}

export async function requestJson(fetchImpl, url, options = {}) {
  const response = await fetchImpl(url, options);
  const text = await response.text();
  let body = null;
  if (text) {
    try {
      body = JSON.parse(text);
    } catch {
      body = text;
    }
  }
  if (!response.ok) {
    const error = new Error(
      `GitHub API ${response.status} ${options.method || 'GET'} ${url}`,
    );
    error.status = response.status;
    error.body = body;
    throw error;
  }
  return body;
}

function encodePath(filePath) {
  return filePath.split('/').map(encodeURIComponent).join('/');
}

export async function getPullRequest({
  owner,
  repo,
  pullNumber,
  token,
  fetchImpl = globalThis.fetch,
}) {
  return requestJson(
    fetchImpl,
    apiUrl(owner, repo, `/pulls/${pullNumber}`),
    { headers: headers(token) },
  );
}

export async function getRepository({
  owner,
  repo,
  token,
  fetchImpl = globalThis.fetch,
}) {
  return requestJson(fetchImpl, apiUrl(owner, repo, ''), {
    headers: headers(token),
  });
}

export async function getContentAtRef({
  owner,
  repo,
  filePath,
  ref,
  token,
  fetchImpl = globalThis.fetch,
}) {
  const query = ref ? `?ref=${encodeURIComponent(ref)}` : '';
  try {
    return await requestJson(
      fetchImpl,
      apiUrl(owner, repo, `/contents/${encodePath(filePath)}${query}`),
      { headers: headers(token) },
    );
  } catch (error) {
    if (error.status === 404) {
      return null;
    }
    throw error;
  }
}

export async function putContentAtRef({
  owner,
  repo,
  filePath,
  branch,
  content,
  message,
  token,
  fetchImpl = globalThis.fetch,
}) {
  return requestJson(
    fetchImpl,
    apiUrl(owner, repo, `/contents/${encodePath(filePath)}`),
    {
      method: 'PUT',
      headers: { ...headers(token), 'Content-Type': 'application/json' },
      body: JSON.stringify({
        message,
        branch,
        content: Buffer.from(content, 'utf8').toString('base64'),
      }),
    },
  );
}

export async function dispatchWorkflow({
  owner,
  repo,
  workflow,
  ref,
  token,
  fetchImpl = globalThis.fetch,
}) {
  const url = apiUrl(
    owner,
    repo,
    `/actions/workflows/${encodeURIComponent(workflow)}/dispatches`,
  );
  return requestJson(fetchImpl, url, {
    method: 'POST',
    headers: { ...headers(token), 'Content-Type': 'application/json' },
    body: JSON.stringify({ ref }),
  });
}

export async function getWorkflowRuns({
  owner,
  repo,
  ref,
  token,
  fetchImpl = globalThis.fetch,
}) {
  const query = new URLSearchParams({ per_page: '100' });
  if (ref) {
    query.set('head_sha', ref);
  }
  return requestJson(
    fetchImpl,
    apiUrl(owner, repo, `/actions/runs?${query.toString()}`),
    { headers: headers(token) },
  );
}

export async function getWorkflows({
  owner,
  repo,
  token,
  fetchImpl = globalThis.fetch,
}) {
  return requestJson(
    fetchImpl,
    apiUrl(owner, repo, '/actions/workflows?per_page=100'),
    { headers: headers(token) },
  );
}

export async function getCheckRuns({
  owner,
  repo,
  ref,
  token,
  fetchImpl = globalThis.fetch,
}) {
  return requestJson(
    fetchImpl,
    apiUrl(owner, repo, `/commits/${encodeURIComponent(ref)}/check-runs?per_page=100`),
    { headers: headers(token) },
  );
}

export async function getCombinedStatus({
  owner,
  repo,
  ref,
  token,
  fetchImpl = globalThis.fetch,
}) {
  return requestJson(
    fetchImpl,
    apiUrl(owner, repo, `/commits/${encodeURIComponent(ref)}/status`),
    { headers: headers(token) },
  );
}

const FAILED_CONCLUSIONS = new Set([
  'failure',
  'cancelled',
  'timed_out',
  'action_required',
  'stale',
  'error',
]);

const PENDING_CONCLUSIONS = new Set([
  'queued',
  'in_progress',
  'pending',
  'waiting',
  'requested',
]);

export function summarizeChecks(checkRuns = [], statuses = []) {
  const runs = Array.isArray(checkRuns) ? checkRuns : [];
  const legacy = Array.isArray(statuses) ? statuses : [];
  const conclusions = [
    ...runs.map((run) => run.conclusion),
    ...legacy.map((status) => status.state),
  ];
  const total = conclusions.length;
  const pending = conclusions.filter(
    (value) => value == null || PENDING_CONCLUSIONS.has(value),
  );
  const failed = conclusions.filter((value) => FAILED_CONCLUSIONS.has(value));
  return {
    total,
    pending,
    failed,
    success: total > 0 && pending.length === 0 && failed.length === 0,
    runs,
    statuses: legacy,
  };
}

export async function collectChecks({
  owner,
  repo,
  ref,
  token,
  fetchImpl = globalThis.fetch,
}) {
  const [checkRuns, status] = await Promise.all([
    getCheckRuns({ owner, repo, ref, token, fetchImpl }),
    getCombinedStatus({ owner, repo, ref, token, fetchImpl }),
  ]);
  return summarizeChecks(checkRuns?.check_runs, status?.statuses);
}

export function planRepair({ headRef, headSha, workflowExists }) {
  if (!headRef || !headSha) {
    throw new Error('pull request head ref and sha are required');
  }
  if (headRef.startsWith('refs/')) {
    throw new Error(`unexpected head ref format: ${headRef}`);
  }
  if (!workflowExists) {
    return {
      action: 'commit-workflow',
      branch: headRef,
      ref: headRef,
      sha: headSha,
      reason:
        'the workflow file is absent from the pull request head tree; GitHub ' +
        'does not run workflows retroactively, so the file must be committed ' +
        'onto the pull request head branch to create a new head',
    };
  }
  return {
    action: 'dispatch-workflow',
    branch: headRef,
    ref: headRef,
    sha: headSha,
    workflow: 'ci.yml',
    reason:
      'the workflow exists on the pull request head but no run was recorded; ' +
      'dispatch it on the head branch so a run is attached to the current head',
  };
}

export async function repairPrChecks({
  owner,
  repo,
  pullNumber,
  token,
  fetchImpl = globalThis.fetch,
  workflowContent = null,
  commitMessage = 'ci: run checks on pull request head (TESTEB-63)',
  log = () => {},
}) {
  const pr = await getPullRequest({
    owner,
    repo,
    pullNumber,
    token,
    fetchImpl,
  });
  const headRef = pr?.head?.ref;
  const headSha = pr?.head?.sha;
  const existing = await getContentAtRef({
    owner,
    repo,
    filePath: WORKFLOW_PATH,
    ref: headRef,
    token,
    fetchImpl,
  });
  const plan = planRepair({
    headRef,
    headSha,
    workflowExists: Boolean(existing),
  });
  log(`repair plan: ${plan.action} on ${plan.branch} (${plan.reason})`);

  if (plan.action === 'commit-workflow') {
    let content = workflowContent;
    if (content == null) {
      const repository = await getRepository({
        owner,
        repo,
        token,
        fetchImpl,
      });
      const source = await getContentAtRef({
        owner,
        repo,
        filePath: WORKFLOW_PATH,
        ref: repository?.default_branch,
        token,
        fetchImpl,
      });
      if (!source?.content) {
        throw new Error(
          `cannot find ${WORKFLOW_PATH} on the default branch; ` +
            'pass --workflow-file to provide its content',
        );
      }
      content = Buffer.from(source.content, 'base64').toString('utf8');
    }
    const result = await putContentAtRef({
      owner,
      repo,
      filePath: WORKFLOW_PATH,
      branch: plan.branch,
      content,
      message: commitMessage,
      token,
      fetchImpl,
    });
    return {
      ...plan,
      status: 'committed',
      newHeadSha: result?.commit?.sha ?? null,
    };
  }

  if (plan.action === 'dispatch-workflow') {
    await dispatchWorkflow({
      owner,
      repo,
      workflow: plan.workflow,
      ref: plan.ref,
      token,
      fetchImpl,
    });
    return { ...plan, status: 'dispatched' };
  }

  return { ...plan, status: 'noop' };
}

export async function waitForChecks({
  owner,
  repo,
  ref,
  token,
  fetchImpl = globalThis.fetch,
  timeoutMs = 30 * 60 * 1000,
  intervalMs = 15 * 1000,
  now = () => Date.now(),
  sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms)),
  log = () => {},
}) {
  const deadline = now() + timeoutMs;
  let last = null;
  while (now() <= deadline) {
    last = await collectChecks({ owner, repo, ref, token, fetchImpl });
    log(
      `checks for ${ref}: total=${last.total} pending=${last.pending.length} ` +
        `failed=${last.failed.length}`,
    );
    if (last.failed.length > 0) {
      return { ...last, outcome: 'failed' };
    }
    if (last.success) {
      return { ...last, outcome: 'success' };
    }
    if (now() >= deadline) {
      break;
    }
    await sleep(intervalMs);
  }
  return { ...(last ?? summarizeChecks()), outcome: 'timeout' };
}
