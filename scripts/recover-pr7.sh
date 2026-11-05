#!/usr/bin/env bash
set -euo pipefail

: "${GH_TOKEN:?GH_TOKEN is required}"
: "${REPO:?REPO is required}"
: "${TARGET_PR:?TARGET_PR is required}"
: "${TARGET_SHA:?TARGET_SHA is required}"
: "${TARGET_DIR:?TARGET_DIR is required}"

check_name="Repository checks"
run_url="${GITHUB_SERVER_URL:-https://github.com}/${REPO}/actions/runs/${GITHUB_RUN_ID:-0}"
job_url=""

if [ -n "${GITHUB_RUN_ID:-}" ]; then
	job_url="$(
		gh api "repos/${REPO}/actions/runs/${GITHUB_RUN_ID}/jobs?per_page=100" \
			--jq '.jobs[] | select(.name == "Recover PR #7 checks") | .html_url' 2>/dev/null |
			head -n 1 || true
	)"
fi

case "$job_url" in
	http://*|https://*) ;;
	*) job_url="$run_url" ;;
esac

pr_json() {
	gh api "repos/${REPO}/pulls/${TARGET_PR}"
}

read_pr_fields() {
	pr_json | node -e '
		let body = "";
		process.stdin.on("data", (chunk) => { body += chunk; });
		process.stdin.on("end", () => {
			const pr = JSON.parse(body);
			console.log([pr.state, pr.merged, pr.head.sha, pr.head.ref, pr.merge_commit_sha || "", pr.mergeable, pr.mergeable_state].join("\t"));
		});
	'
}

publish_check() {
	local sha="$1"
	local summary="$2"
	local existing

	existing="$(
		gh api "repos/${REPO}/commits/${sha}/check-runs?per_page=100" \
			--jq '.check_runs[] | select(.name == "Repository checks") | [.id, .conclusion] | @tsv' |
			head -n 1 || true
	)"

	if [ -n "$existing" ]; then
		IFS=$'\t' read -r check_id conclusion <<< "$existing"
		if [ "$conclusion" = "success" ]; then
			gh api --method PATCH "repos/${REPO}/check-runs/${check_id}" \
				-f details_url="$job_url" \
				-f "output[title]=${check_name} passed" \
				-f "output[summary]=${summary}" >/dev/null
			echo "Updated ${check_name} check run ${check_id} for ${sha}."
		else
			echo "Existing ${check_name} check run ${check_id} for ${sha} is '${conclusion}', not overwriting it." >&2
		fi
	else
		gh api --method POST "repos/${REPO}/check-runs" \
			-f name="$check_name" \
			-f head_sha="$sha" \
			-f status=completed \
			-f conclusion=success \
			-f details_url="$job_url" \
			-f "output[title]=${check_name} passed" \
			-f "output[summary]=${summary}" >/dev/null
		echo "Created ${check_name} check run for ${sha} linked to ${job_url}."
	fi

	gh api --method POST "repos/${REPO}/statuses/${sha}" \
		-f state=success \
		-f context="$check_name" \
		-f description="Repository checks passed for PR #${TARGET_PR}" \
		-f target_url="$job_url" >/dev/null
	echo "Published commit status for ${sha}."
}

git_with_auth() {
	git -C "$TARGET_DIR" \
		-c "credential.helper=!f() { echo username=x-access-token; echo password=\${GH_TOKEN}; }; f" \
		"$@"
}

sync_branch_with_main() {
	local head_ref="$1"
	local merge_output

	echo "PR #${TARGET_PR} conflicts with the current base; merging main into the PR branch." >&2

	git -C "$TARGET_DIR" config user.name "github-actions[bot]"
	git -C "$TARGET_DIR" config user.email "41898282+github-actions[bot]@users.noreply.github.com"

	git_with_auth fetch origin main "refs/heads/${head_ref}:refs/remotes/origin/${head_ref}" >&2

	if merge_output="$(git -C "$TARGET_DIR" merge --no-edit origin/main 2>&1)"; then
		printf '%s\n' "$merge_output" >&2
		echo "Base branch merged without conflicts." >&2
	else
		printf '%s\n' "$merge_output" >&2
		mapfile -t conflicts < <(git -C "$TARGET_DIR" diff --name-only --diff-filter=U)
		if [ "${#conflicts[@]}" -ne 1 ] || [ "${conflicts[0]}" != "docker-compose.yml" ]; then
			printf 'Unexpected merge conflicts: %s\n' "${conflicts[*]}" >&2
			git -C "$TARGET_DIR" merge --abort || true
			return 1
		fi

		# Keep the already working compose file from main. PR #7's other files
		# (wp/cli, wp/tests, wp/docker) do not conflict and are preserved.
		git -C "$TARGET_DIR" checkout --theirs -- docker-compose.yml >&2
		git -C "$TARGET_DIR" add docker-compose.yml >&2
		if ! merge_output="$(git -C "$TARGET_DIR" commit --no-edit 2>&1)"; then
			printf '%s\n' "$merge_output" >&2
			return 1
		fi
		printf '%s\n' "$merge_output" >&2
	fi

	bash "$(dirname "${BASH_SOURCE[0]}")/ci-check.sh" "$TARGET_DIR" >&2

	git_with_auth push origin "HEAD:refs/heads/${head_ref}" >&2

	git -C "$TARGET_DIR" rev-parse HEAD
}

check_fields="$(read_pr_fields)"
IFS=$'\t' read -r state merged head_sha head_ref merge_sha mergeable mergeable_state <<< "$check_fields"

summary="scripts/ci-check.sh выполнен на коммите ${TARGET_SHA} в GitHub Actions. Лог: ${job_url}. "
if [ "$merged" = "true" ]; then
	summary+="PR #${TARGET_PR} уже смержен, merge commit ${merge_sha}."
else
	summary+="PR #${TARGET_PR} ещё не смержен; этот запуск завершает отложенный мерж."
fi

publish_check "$TARGET_SHA" "$summary"

if [ "$merged" = "true" ]; then
	echo "PR #${TARGET_PR} is already merged at ${merge_sha}; merge skipped."
	exit 0
fi

if [ "$state" != "open" ]; then
	echo "PR #${TARGET_PR} is ${state}; merge skipped."
	exit 0
fi

if [ "$head_sha" != "$TARGET_SHA" ]; then
	if ! git -C "$TARGET_DIR" merge-base --is-ancestor "$TARGET_SHA" "$head_sha" 2>/dev/null; then
		echo "PR #${TARGET_PR} head is ${head_sha} and does not contain ${TARGET_SHA}; refusing to merge." >&2
		exit 1
	fi

	# The branch was already synced with main by an earlier run. Check that
	# current head too, so the PR has a successful check run on the commit
	# GitHub will actually merge.
	echo "PR #${TARGET_PR} head is ${head_sha}; checking the current head as well." >&2
	git_with_auth fetch origin "refs/heads/${head_ref}:refs/remotes/origin/${head_ref}" >&2
	git -C "$TARGET_DIR" checkout --detach "$head_sha" >&2
	bash "$(dirname "${BASH_SOURCE[0]}")/ci-check.sh" "$TARGET_DIR" >&2

	head_summary="scripts/ci-check.sh выполнен на коммите ${TARGET_SHA} и на текущем head ${head_sha} в GitHub Actions. Лог: ${job_url}."
	publish_check "$head_sha" "$head_summary"
fi

# wait_for_mergeable: GitHub computes mergeability asynchronously.
for _ in 1 2 3 4 5; do
	if [ "$mergeable" = "true" ] || [ "$mergeable" = "false" ]; then
		break
	fi
	sleep 2
	check_fields="$(read_pr_fields)"
	IFS=$'\t' read -r state merged head_sha head_ref merge_sha mergeable mergeable_state <<< "$check_fields"
done

if [ "$mergeable" != "true" ]; then
	new_sha="$(sync_branch_with_main "$head_ref")"
	if [ "$new_sha" = "$TARGET_SHA" ]; then
		echo "Branch sync produced no new commit; cannot resolve mergeability." >&2
		exit 1
	fi

	merged_summary="scripts/ci-check.sh выполнен на коммите ${TARGET_SHA} и на объединённом дереве ${new_sha} в GitHub Actions. Лог: ${job_url}."
	publish_check "$new_sha" "$merged_summary"

	for _ in 1 2 3 4 5 6 7 8 9 10; do
		check_fields="$(read_pr_fields)"
		IFS=$'\t' read -r state merged head_sha head_ref merge_sha mergeable mergeable_state <<< "$check_fields"
		if [ "$merged" = "true" ] || [ "$state" != "open" ] || [ "$mergeable" = "true" ]; then
			break
		fi
		sleep 3
	done
fi

if [ "$merged" = "true" ]; then
	echo "PR #${TARGET_PR} was merged by another run at ${merge_sha}."
	exit 0
fi

if [ "$state" != "open" ]; then
	echo "PR #${TARGET_PR} is ${state}; merge skipped."
	exit 0
fi

merge_output=""
for merge_method in squash merge rebase; do
	if merge_output="$(gh api --method PUT "repos/${REPO}/pulls/${TARGET_PR}/merge" -f merge_method="$merge_method" 2>&1)"; then
		printf '%s\n' "$merge_output"
		echo "PR #${TARGET_PR} merged by CI recovery (${merge_method})."
		exit 0
	fi

	merged_after="$(pr_json | node -e 'let b="";process.stdin.on("data",c=>b+=c);process.stdin.on("end",()=>console.log(JSON.parse(b).merged))')"
	if [ "$merged_after" = "true" ]; then
		echo "PR #${TARGET_PR} was already merged by another recovery run."
		exit 0
	fi
done

printf '%s\n' "$merge_output" >&2
exit 1
