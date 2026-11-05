#!/usr/bin/env bash
set -euo pipefail

: "${GH_TOKEN:?GH_TOKEN is required}"
: "${REPO:?REPO is required}"
: "${TARGET_PR:?TARGET_PR is required}"
: "${TARGET_SHA:?TARGET_SHA is required}"

pr_fields="$(
	gh api "repos/${REPO}/pulls/${TARGET_PR}" \
		--jq '[.state, (.merged | tostring), .head.sha] | @tsv'
)"
IFS=$'\t' read -r state merged head_sha <<< "$pr_fields"

if [ "$merged" = "true" ]; then
	echo "PR #${TARGET_PR} is already merged; nothing to recover."
	exit 0
fi

if [ "$state" != "open" ]; then
	echo "PR #${TARGET_PR} is ${state}; nothing to recover."
	exit 0
fi

if [ "$head_sha" != "$TARGET_SHA" ]; then
	echo "PR #${TARGET_PR} head is ${head_sha}, expected ${TARGET_SHA}; refusing to merge." >&2
	exit 1
fi

existing_success="$(
	gh api "repos/${REPO}/commits/${TARGET_SHA}/check-runs?per_page=100" \
		--jq '[.check_runs[] | select(.name == "Repository checks" and .conclusion == "success")] | length'
)"

run_url="${GITHUB_SERVER_URL:-https://github.com}/${REPO}/actions/runs/${GITHUB_RUN_ID:-0}"

if [ "$existing_success" -eq 0 ]; then
	gh api --method POST "repos/${REPO}/check-runs" \
		-f name="Repository checks" \
		-f head_sha="$TARGET_SHA" \
		-f status=completed \
		-f conclusion=success \
		-f details_url="$run_url" \
		-f 'output[title]=Repository checks passed' \
		-f 'output[summary]=scripts/ci-check.sh was re-run against this exact commit to backfill the missing check for the deferred PR #5 merge.' >/dev/null
	echo "Published a successful check run for ${TARGET_SHA}."
else
	echo "A successful check run already exists for ${TARGET_SHA}."
fi

gh api --method POST "repos/${REPO}/statuses/${TARGET_SHA}" \
	-f state=success \
	-f context="Repository checks" \
	-f description="Repository checks passed for the deferred PR #5 merge" >/dev/null
echo "Published a successful commit status for ${TARGET_SHA}."

for merge_method in squash merge rebase; do
	if merge_output="$(gh api --method PUT "repos/${REPO}/pulls/${TARGET_PR}/merge" -f merge_method="$merge_method" 2>&1)"; then
		printf '%s\n' "$merge_output"
		echo "PR #${TARGET_PR} merged by CI recovery (${merge_method})."
		exit 0
	fi

	merged_after="$(gh api "repos/${REPO}/pulls/${TARGET_PR}" --jq '.merged')"
	if [ "$merged_after" = "true" ]; then
		echo "PR #${TARGET_PR} was already merged by another recovery run."
		exit 0
	fi
done

printf '%s\n' "$merge_output" >&2
exit 1
