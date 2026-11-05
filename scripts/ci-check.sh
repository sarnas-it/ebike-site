#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

if command -v php >/dev/null 2>&1; then
	while IFS= read -r -d '' file; do
		php -l "$file"
	done < <(find . -type f -name '*.php' -not -path './.git/*' -not -path './node_modules/*' -print0)

	php -r '
		$path = $argv[1];
		$data = json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) || array() === $data ) {
			fwrite( STDERR, "Invalid data: {$path}\n" );
			exit( 1 );
		}
		echo "JSON OK: {$path}\n";
	' content/products.json
else
	echo "PHP is not installed; skipping PHP syntax and JSON validation." >&2
fi

while IFS= read -r -d '' script; do
	bash -n "$script"
done < <(find scripts -type f -name '*.sh' -print0)

if [ -f wp/plugins/velotok-core/tests/spec-data-contract-test.php ] && command -v php >/dev/null 2>&1; then
	php wp/plugins/velotok-core/tests/spec-data-contract-test.php
fi

if command -v node >/dev/null 2>&1; then
	while IFS= read -r -d '' test; do
		node "$test"
	done < <(find . -type f -name '*.test.js' -not -path './.git/*' -not -path './node_modules/*' -print0)
fi
