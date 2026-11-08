#!/usr/bin/env bash
# Run the repository checks against an arbitrary checkout.
#
# The same groups are used by .github/workflows/ci.yml and by the PR recovery
# workflow, so a recovered commit is verified with exactly the same checks as a
# regular pull request.
#
# Usage: run-checks.sh <root> [results-json]
set -uo pipefail

if [ "$#" -lt 1 ]; then
	echo "usage: $0 <root> [results-json]" >&2
	exit 2
fi

root="$(cd "$1" && pwd)"
results="${2:-}"
tmp="$(mktemp)"

cleanup() {
	rm -f "$tmp"
}
trap cleanup EXIT

record() {
	printf '%s\t%s\n' "$1" "$2" >>"$tmp"
}

run_group() {
	local name="$1"
	shift
	echo "== ${name}"
	if "$@"; then
		echo "PASS: ${name}"
		record "${name}" success
	else
		echo "FAIL: ${name}"
		record "${name}" failure
	fi
}

php_lint() {
	local found=0
	while IFS= read -r -d '' file; do
		found=1
		php -l "$file" >/dev/null || return 1
	done < <(find "$root" -type f -name '*.php' \
		-not -path '*/.git/*' -not -path '*/node_modules/*' -print0)
	[ "$found" -eq 1 ] || {
		echo "no PHP files found under ${root}" >&2
		return 1
	}
}

theme_tests() {
	local found=0 test
	for test in "$root"/wp/themes/velotok/tests/*-test.php; do
		[ -e "$test" ] || continue
		found=1
		echo "== ${test#"$root"/}"
		php "$test" || return 1
	done
	[ "$found" -eq 1 ] || {
		echo "no theme tests found under ${root}" >&2
		return 1
	}
}

plugin_tests() {
	local found=0 test
	for test in "$root"/wp/plugins/velotok-core/tests/*.test.js; do
		[ -e "$test" ] || continue
		found=1
		echo "== ${test#"$root"/}"
		node "$test" || return 1
	done
	[ "$found" -eq 1 ] || {
		echo "no plugin tests found under ${root}" >&2
		return 1
	}
}

plugin_php_tests() {
	local found=0 test acceptance
	acceptance="$root/wp/plugins/velotok-core/tests/accounts-contract-test.php"
	# The accounts acceptance command is mandatory for this task. Run it
	# explicitly so the group can never be satisfied by linting alone.
	if [ -e "$acceptance" ]; then
		found=1
		echo "== ${acceptance#"$root"/}"
		php "$acceptance" || return 1
	fi
	for test in "$root"/wp/plugins/velotok-core/tests/*-test.php; do
		[ -e "$test" ] || continue
		[ "$test" = "$acceptance" ] && continue
		found=1
		echo "== ${test#"$root"/}"
		php "$test" || return 1
	done
	[ "$found" -eq 1 ] || {
		echo "no plugin PHP tests found under ${root}" >&2
		return 1
	}
}

ci_tooling_tests() {
	local found=0 test
	for test in "$root"/scripts/ci/tests/*.test.mjs; do
		[ -e "$test" ] || continue
		found=1
		echo "== ${test#"$root"/}"
		node "$test" || return 1
	done
	[ "$found" -eq 1 ] || return 1
}

run_group php-lint php_lint
run_group php-tests theme_tests
run_group plugin-tests plugin_tests
run_group php-plugin-tests plugin_php_tests

# The stale commit targeted by the recovery workflow predates the CI tooling,
# so this group only runs when the tooling is actually part of the checkout.
if [ -d "$root/scripts/ci/tests" ]; then
	run_group ci-repair-tests ci_tooling_tests
fi

status=0
if [ -n "$results" ]; then
	{
		printf '{\n'
		first=1
		while IFS=$'\t' read -r name result; do
			[ -n "$name" ] || continue
			[ "$first" -eq 1 ] && first=0 || printf ',\n'
			printf '  "%s": "%s"' "$name" "$result"
		done <"$tmp"
		printf '\n}\n'
	} >"$results"
fi
if grep -q $'\tfailure$' "$tmp"; then
	status=1
fi

exit "$status"
