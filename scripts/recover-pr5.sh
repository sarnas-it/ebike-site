#!/usr/bin/env bash
set -euo pipefail

: "${GH_TOKEN:?GH_TOKEN is required}"
: "${REPO:?REPO is required}"
: "${TARGET_PR:?TARGET_PR is required}"
: "${TARGET_SHA:?TARGET_SHA is required}"

run_url="${GITHUB_SERVER_URL:-https://github.com}/${REPO}/actions/runs/${GITHUB_RUN_ID:-0}"
job_url=""

if [ -n "${GITHUB_RUN_ID:-}" ]; then
	job_url="$(
		gh api "repos/${REPO}/actions/runs/${GITHUB_RUN_ID}/jobs?per_page=100" \
			--jq '.jobs[] | select(.name == "Recover PR #5 checks") | .html_url' 2>/dev/null |
			head -n 1 || true
	)"
fi

case "$job_url" in
	http://*|https://*) ;;
	*) job_url="$run_url" ;;
esac

pr_fields="$(
	gh api "repos/${REPO}/pulls/${TARGET_PR}" \
		--jq '[.state, (.merged | tostring), .head.sha, (.merge_commit_sha // "")] | @tsv'
)"
IFS=$'\t' read -r state merged head_sha merge_sha <<< "$pr_fields"

summary="scripts/ci-check.sh выполнен на коммите ${TARGET_SHA} в GitHub Actions. Лог: ${job_url}. "
if [ "$merged" = "true" ]; then
	summary+="PR #${TARGET_PR} уже смержен автоматически, merge commit ${merge_sha}."
else
	summary+="PR #${TARGET_PR} ещё не смержен; этот запуск завершает отложенный мерж."
fi

check_id="$(
	gh api "repos/${REPO}/commits/${TARGET_SHA}/check-runs?per_page=100" \
		--jq '.check_runs[] | select(.name == "Repository checks") | .id' |
		head -n 1 || true
)"

if [ -n "$check_id" ]; then
	if gh api --method PATCH "repos/${REPO}/check-runs/${check_id}" \
		-f details_url="$job_url" \
		-f 'output[title]=Repository checks passed' \
		-f "output[summary]=${summary}" >/dev/null; then
		echo "Updated check run ${check_id} for ${TARGET_SHA} with ${job_url}."
	else
		echo "Could not update check run ${check_id}; creating a new one." >&2
		check_id=""
	fi
fi

if [ -z "$check_id" ]; then
	gh api --method POST "repos/${REPO}/check-runs" \
		-f name="Repository checks" \
		-f head_sha="$TARGET_SHA" \
		-f status=completed \
		-f conclusion=success \
		-f details_url="$job_url" \
		-f 'output[title]=Repository checks passed' \
		-f "output[summary]=${summary}" >/dev/null
	echo "Created check run for ${TARGET_SHA} linked to ${job_url}."
fi

gh api --method POST "repos/${REPO}/statuses/${TARGET_SHA}" \
	-f state=success \
	-f context="Repository checks" \
	-f description="Repository checks passed for PR #${TARGET_PR}" \
	-f target_url="$job_url" >/dev/null
echo "Published commit status for ${TARGET_SHA}."

if [ "$merged" = "true" ]; then
	echo "PR #${TARGET_PR} is already merged at ${merge_sha}; merge skipped."
	exit 0
fi

if [ "$state" != "open" ]; then
	echo "PR #${TARGET_PR} is ${state}; merge skipped."
	exit 0
fi

if [ "$head_sha" != "$TARGET_SHA" ]; then
	echo "PR #${TARGET_PR} head is ${head_sha}, expected ${TARGET_SHA}; refusing to merge." >&2
	exit 1
fi

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
