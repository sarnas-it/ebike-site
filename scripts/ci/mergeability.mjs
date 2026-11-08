export const DEFAULT_FIX_PR = 65;
export const DEFAULT_BASE_BRANCH = 'main';

// Criterion 3 for TESTEB-63 only asks whether the fix pull request is
// mergeable into the base branch, i.e. it has no conflicts. It deliberately
// does not require a green CI run on that pull request: the run that must be
// green belongs to the commit in the current submission, which may be a
// different pull request. Keeping this check separate from the rollout gate
// makes both criteria independently provable on the submitted commit.
export function assessMergeability(
  pr,
  { prNumber = DEFAULT_FIX_PR, baseBranch = DEFAULT_BASE_BRANCH } = {},
) {
  const problems = [];
  const number = pr?.number ?? null;
  const state = pr?.state ?? null;
  const merged = Boolean(pr?.merged);
  const mergeable = pr?.mergeable ?? null;
  const mergeableState = pr?.mergeable_state ?? null;
  const base = pr?.base?.ref ?? null;
  const mergeCommitSha = merged ? (pr?.merge_commit_sha ?? null) : null;

  if (!pr) {
    problems.push(`pull request #${prNumber} was not found`);
  } else {
    if (number != null && number !== prNumber) {
      problems.push(`expected pull request #${prNumber}, got #${number}`);
    }
    if (base !== baseBranch) {
      problems.push(
        `pull request #${prNumber} targets ${base ?? 'unknown'}, expected ${baseBranch}`,
      );
    }
    if (merged) {
      if (!mergeCommitSha) {
        problems.push(`merged pull request #${prNumber} has no merge commit sha`);
      }
    } else {
      if (state !== 'open') {
        problems.push(
          `pull request #${prNumber} is ${state ?? 'unknown'}, expected open or merged`,
        );
      }
      if (mergeable !== true) {
        problems.push(
          `pull request #${prNumber} is not mergeable ` +
            `(mergeable=${mergeable ?? 'unknown'}, ` +
            `state=${mergeableState ?? 'unknown'})`,
        );
      }
      if (mergeableState === 'dirty') {
        problems.push(
          `pull request #${prNumber} has merge conflicts with ${baseBranch}`,
        );
      }
    }
  }

  return {
    prNumber,
    baseBranch,
    number,
    state,
    merged,
    mergeable,
    mergeableState,
    base,
    mergeCommitSha,
    ready: problems.length === 0,
    problems,
  };
}

export function renderMergeability(evidence) {
  return (
    `pull request:     #${evidence.number ?? evidence.prNumber}\n` +
    `base branch:      ${evidence.baseBranch}\n` +
    `state:            ${evidence.state ?? 'unknown'}\n` +
    `merged:           ${evidence.merged}\n` +
    `mergeable:        ${evidence.mergeable ?? 'unknown'}\n` +
    `mergeable_state:  ${evidence.mergeableState ?? 'unknown'}\n` +
    `merge commit:     ${evidence.mergeCommitSha ?? 'none'}\n` +
    (evidence.ready
      ? 'mergeable:        green\n'
      : `problems:         ${evidence.problems.join('; ')}\n`)
  );
}
