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

if ! "${COMPOSE[@]}" run --rm -T wpcli theme list --status=active --field=name | grep -qx 'velotok'; then
	echo "FAIL: тема velotok не активирована" >&2
	exit 1
fi

if ! "${COMPOSE[@]}" run --rm -T wpcli plugin list --status=active --field=name | grep -qx 'velotok-core'; then
	echo "FAIL: плагин velotok-core не активирован" >&2
	exit 1
fi

if "${COMPOSE[@]}" run --rm -T wpcli plugin is-active woocommerce >/dev/null 2>&1; then
	products="$("${COMPOSE[@]}" run --rm -T wpcli post list --post_type=product --post_status=publish --format=count | tr -dc '0-9')"
	if [ "${products:-0}" -lt 1 ]; then
		echo "FAIL: в каталоге нет опубликованных товаров" >&2
		exit 1
	fi
fi

echo "OK: локальный сайт «Велоток» запускается и отдаёт витрину"
