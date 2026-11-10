#!/usr/bin/env bash
#
# End-to-end проверка заявок EBIKE-21.
#
# Скрипт поднимает WordPress (по умолчанию отдельным compose-проектом),
# реально отправляет формы test-drive и consultation через REST API Contact
# Form 7 и проверяет, что Flamingo сохранил каждую заявку в Inbound Messages.
#
# Запуск из корня проекта:
#   bash wp/tests/lead-forms-e2e.sh
#
# Если сайт уже поднят (например, из smoke.sh), передайте
# LEAD_FORMS_E2E_ATTACH=1 и тот же COMPOSE_PROJECT_NAME, чтобы не запускать
# второй проект:
#   LEAD_FORMS_E2E_ATTACH=1 COMPOSE_PROJECT_NAME=velotok-smoke \
#     bash wp/tests/lead-forms-e2e.sh
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

PORT="${WORDPRESS_PORT:-8080}"
PROJECT="${COMPOSE_PROJECT_NAME:-velotok-lead-e2e}"
COMPOSE=(docker compose -p "$PROJECT")
ATTACH="${LEAD_FORMS_E2E_ATTACH:-0}"
BASE_URL="http://localhost:${PORT}"
RESPONSE_FILE="$(mktemp)"

cleanup() {
	rm -f "$RESPONSE_FILE"
	if [ "$ATTACH" != "1" ]; then
		"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
	fi
}
trap cleanup EXIT

wp() {
	"${COMPOSE[@]}" run --rm -T wpcli "$@"
}

if [ "$ATTACH" != "1" ]; then
	"${COMPOSE[@]}" up -d
fi

attempt=0
until curl -fsS "${BASE_URL}/" -o /dev/null; do
	attempt=$((attempt + 1))
	if [ "$attempt" -ge 90 ]; then
		echo "FAIL: сайт не ответил на ${BASE_URL}/" >&2
		"${COMPOSE[@]}" logs --tail=120 >&2 || true
		exit 1
	fi
	sleep 2
done

for plugin in contact-form-7 flamingo; do
	if ! wp plugin is-active "$plugin" >/dev/null 2>&1; then
		echo "FAIL: плагин $plugin не активирован" >&2
		wp plugin list >&2 || true
		exit 1
	fi
done

before="$(wp post list --post_type=flamingo_inbound --format=count | tr -dc '0-9')"
before="${before:-0}"

for form in test-drive consultation; do
	form_id="$(wp post list --post_type=wpcf7_contact_form --name="$form" --field=ID | head -n1 | tr -dc '0-9')"
	if [ -z "$form_id" ]; then
		echo "FAIL: форма Contact Form 7 «$form» не создана" >&2
		exit 1
	fi

	mail_json="$(wp post meta get "$form_id" _mail --format=json)"
	recipient="$(printf '%s' "$mail_json" | sed -n 's/.*"recipient":"\([^"]*\)".*/\1/p')"
	if [ -z "$recipient" ]; then
		echo "FAIL: у формы «$form» не задан получатель письма" >&2
		exit 1
	fi

	stamp="$(date +%s)-$$"
	marker="EBIKE-21 E2E ${form} ${stamp}"
	email="lead-e2e-${form}-${stamp}@example.test"
	fields=(
		-F "_wpcf7=${form_id}"
		-F "_wpcf7_version=6.1.7"
		-F "_wpcf7_locale=ru_RU"
		-F "_wpcf7_unit_tag=wpcf7-f${form_id}-p1-o1"
		-F "_wpcf7_container_post=0"
		-F "your-name=${marker}"
		-F "your-phone=+7 999 000-00-00"
		-F "your-email=${email}"
		-F "your-message=${marker}"
	)
	if [ "$form" = "test-drive" ]; then
		fields+=(-F "bike-model=Ривьера" -F "test-drive-date=2030-01-15")
	fi

	curl -sS --max-time 30 -X POST \
		"${BASE_URL}/index.php?rest_route=/contact-form-7/v1/contact-forms/${form_id}/feedback" \
		"${fields[@]}" -o "$RESPONSE_FILE"

	status="$(sed -n 's/.*"status":"\([^"]*\)".*/\1/p' "$RESPONSE_FILE" | head -n1)"
	if [ "$status" != "mail_sent" ] && [ "$status" != "mail_failed" ]; then
		echo "FAIL: форма «$form» отклонена Contact Form 7 (status=${status:-empty})" >&2
		cat "$RESPONSE_FILE" >&2
		exit 1
	fi
	if ! grep -q '"invalid_fields":\[\]' "$RESPONSE_FILE"; then
		echo "FAIL: форма «$form» вернула ошибки валидации" >&2
		cat "$RESPONSE_FILE" >&2
		exit 1
	fi

	echo "POST /contact-form-7/v1/contact-forms/${form_id}/feedback (${form}) -> status=${status}"
	if [ "$status" = "mail_failed" ]; then
		echo "NOTE: в контейнере нет MTA, но CF7 принял заявку; проверяем сохранение Flamingo"
	fi

	after="$before"
	attempt=0
	while [ "$after" -le "$before" ]; do
		attempt=$((attempt + 1))
		if [ "$attempt" -ge 30 ]; then
			echo "FAIL: Flamingo не сохранил заявку формы «$form»" >&2
			wp post list --post_type=flamingo_inbound --fields=ID,post_title,post_status >&2 || true
			exit 1
		fi
		sleep 2
		after="$(wp post list --post_type=flamingo_inbound --format=count | tr -dc '0-9')"
		after="${after:-0}"
	done

	message_id="$(wp post list --post_type=flamingo_inbound --meta_key=_field_your-email --meta_value="$email" --field=ID | head -n1 | tr -dc '0-9')"
	if [ -z "$message_id" ]; then
		echo "FAIL: в Flamingo нет заявки с email $email" >&2
		exit 1
	fi

	content="$(wp post get "$message_id" --field=post_content)"
	if ! printf '%s' "$content" | grep -qF "$marker"; then
		echo "FAIL: сохранённая заявка $message_id не содержит данных формы" >&2
		exit 1
	fi

	from_email="$(wp post meta get "$message_id" _from_email)"
	if [ "$from_email" != "$email" ]; then
		echo "FAIL: Flamingo сохранил неверный email отправителя: $from_email" >&2
		exit 1
	fi

	echo "OK: форма «$form» отправлена, Flamingo Inbound Message ${message_id} сохранён (${email}), получатель ${recipient}"
	before="$after"
done

wp post list --post_type=flamingo_inbound --fields=ID,post_title,post_status --format=table
echo "OK: обе формы реально отправлены и сохранены во Flamingo"
