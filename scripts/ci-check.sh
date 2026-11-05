#!/usr/bin/env bash
set -euo pipefail

# Usage: scripts/ci-check.sh [checkout-root]
# The optional root lets the recovery workflow run the same checks against an
# older checkout without copying files into that checkout.
root="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$root"

php_available=false
if command -v php >/dev/null 2>&1; then
	php_available=true
fi

if [ "$php_available" = true ]; then
	while IFS= read -r -d '' file; do
		php -l "$file"
	done < <(find . -type f -name '*.php' \
		-not -path './.git/*' \
		-not -path './node_modules/*' \
		-not -path './vendor/*' \
		-print0)

	while IFS= read -r -d '' file; do
		php -r '
			$path = $argv[1];
			$data = json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
			if ( ! is_array( $data ) || array() === $data ) {
				fwrite( STDERR, "Invalid data: {$path}\n" );
				exit( 1 );
			}
			echo "JSON OK: {$path}\n";
		' "$file"
	done < <(find . -type f -name '*.json' \
		-not -path './.git/*' \
		-not -path './node_modules/*' \
		-not -path './vendor/*' \
		-print0)
else
	echo "PHP is not installed; skipping PHP syntax and JSON validation." >&2
fi

while IFS= read -r -d '' script; do
	bash -n "$script"
done < <(find . -type f -name '*.sh' \
	-not -path './.git/*' \
	-not -path './node_modules/*' \
	-not -path './vendor/*' \
	-print0)

if [ -f wp/plugins/velotok-core/tests/spec-data-contract-test.php ] && [ "$php_available" = true ]; then
	php wp/plugins/velotok-core/tests/spec-data-contract-test.php
fi

if command -v node >/dev/null 2>&1; then
	while IFS= read -r -d '' test; do
		node "$test"
	done < <(find . -type f -name '*.test.js' \
		-not -path './.git/*' \
		-not -path './node_modules/*' \
		-not -path './vendor/*' \
		-print0)
fi

echo "OK: repository checks passed in ${root}."
