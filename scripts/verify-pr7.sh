#!/usr/bin/env bash
set -euo pipefail

repo="${REPO:-sarnas-it/ebike-site}"
target="92b31d9ed3ccc55a6195a45f91db56cbc3202951"
api="https://api.github.com/repos/${repo}"

auth=()
if [ -n "${GH_TOKEN:-}" ]; then
	auth=(-H "Authorization: Bearer ${GH_TOKEN}")
fi

fetch() {
	curl -fsSL -H 'Accept: application/vnd.github+json' -H 'User-Agent: ebike-ci-verify' "${auth[@]}" "$1"
}

pr_json="$(fetch "${api}/pulls/7")"
checks_json="$(fetch "${api}/commits/${target}/check-runs?per_page=100")"
runs_json="$(fetch "${api}/actions/runs?per_page=100")"

node - "$pr_json" "$checks_json" "$runs_json" "$target" <<'NODE'
const assert = require('assert');
const [prJson, checksJson, runsJson, target] = process.argv.slice(2);
const pr = JSON.parse(prJson);
const checks = JSON.parse(checksJson);
const runs = JSON.parse(runsJson);

assert.strictEqual(pr.merged, true, 'PR #7 is not merged');
assert.ok(pr.merge_commit_sha, 'PR #7 has no merge commit');

const check = checks.check_runs.find(
  (run) => run.name === 'Repository checks' && run.conclusion === 'success'
);
assert.ok(check, 'No successful Repository checks run for the target commit');
assert.ok(
  /^https:\/\/github\.com\//.test(check.details_url || ''),
  'Check run is not linked to GitHub Actions'
);

const recoveries = runs.workflow_runs
  .filter((run) => run.name === 'Recover PR #7 checks' && run.conclusion === 'success')
  .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
assert.ok(recoveries.length > 0, 'No successful Recover PR #7 checks workflow run');

const recovery = recoveries[0];
console.log(`PR #7 merged: ${pr.html_url}`);
console.log(`Merge commit: ${pr.merge_commit_sha}`);
console.log(`Target commit: ${target}`);
console.log(`Check run: ${check.html_url}`);
console.log(`Check details: ${check.details_url}`);
console.log(`Recovery run: ${recovery.html_url}`);
NODE
