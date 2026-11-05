#!/usr/bin/env bash
#
# Дымовой тест локального сайта «Велоток».
# Поднимает docker compose, ждёт HTTP-ответ, проверяет активные тему и плагин,
# а также наличие демо-товаров при активном WooCommerce.
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

PORT="${WORDPRESS_PORT:-8080}"
PROJECT="${COMPOSE_PROJECT_NAME:-velotok-smoke}"
COMPOSE=(docker compose -p "$PROJECT")
PAGE_FILE="$(mktemp)"

wp_cli() {
	"${COMPOSE[@]}" run --rm -T --entrypoint wp wpcli --allow-root "$@"
}

cleanup() {
	rm -f "$PAGE_FILE"
	"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

"${COMPOSE[@]}" up -d

attempt=0
until curl -fsS "http://localhost:${PORT}/" -o "$PAGE_FILE"; do
	attempt=$((attempt + 1))
	if [ "$attempt" -ge 90 ]; then
		echo "FAIL: сайт не ответил на http://localhost:${PORT}/ за 180 секунд" >&2
		"${COMPOSE[@]}" logs --tail=120 >&2 || true
		exit 1
	fi
	sleep 2
done

if ! grep -Eqi 'Велоток|электровелосипед|velotok' "$PAGE_FILE"; then
	echo "FAIL: главная страница не содержит признаков магазина «Велоток»" >&2
	exit 1
fi

if ! grep -q 'wp-content/themes/velotok/assets/css/main.css' "$PAGE_FILE"; then
	echo "FAIL: главная страница не подключает стили темы velotok" >&2
	exit 1
fi

if ! grep -q 'class="site-header"' "$PAGE_FILE" || ! grep -q 'class="model-card"' "$PAGE_FILE"; then
	echo "FAIL: главная страница не содержит разметку витрины темы velotok" >&2
	exit 1
fi

if ! wp_cli theme list --status=active --field=name | grep -qx 'velotok'; then
	echo "FAIL: тема velotok не активирована" >&2
	exit 1
fi

installed_themes="$(wp_cli theme list --field=name)"

for variant in velotok-bold velotok-minimal; do
	if ! printf '%s\n' "$installed_themes" | grep -qx "$variant"; then
		echo "FAIL: вариант дизайна $variant не виден в wp theme list" >&2
		exit 1
	fi
done

if ! wp_cli eval-file wp-content/themes/velotok/tests/theme-contract-test.php; then
	echo "FAIL: контракт темы velotok не выполняется" >&2
	exit 1
fi

if ! wp_cli eval-file wp-content/themes/velotok/tests/theme-design-variants-rejected-contract-test.php; then
	echo "FAIL: невыбранные варианты дизайна темы не помечаются rejected" >&2
	exit 1
fi

if ! wp_cli plugin list --status=active --field=name | grep -qx 'velotok-core'; then
	echo "FAIL: плагин velotok-core не активирован" >&2
	exit 1
fi

if wp_cli plugin is-active woocommerce >/dev/null 2>&1; then
	products="$(wp_cli post list --post_type=product --post_status=publish --format=count | tr -dc '0-9')"
	if [ "${products:-0}" -lt 1 ]; then
		echo "FAIL: в каталоге нет опубликованных товаров" >&2
		exit 1
	fi
fi

echo "OK: локальный сайт «Велоток» запускается и отдаёт витрину"
