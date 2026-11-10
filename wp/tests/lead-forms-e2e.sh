#!/usr/bin/env bash
#
# End-to-end проверка заявок EBIKE-21.
#
# Скрипт поднимает WordPress (по умолчанию отдельным compose-проектом) либо
# подключается к уже работающему сайту, реально отправляет формы test-drive и
# consultation через REST API Contact Form 7 и требует одновременно:
#   1) status=mail_sent от Contact Form 7 (любой другой статус — провал, письмо
#      не ушло);
#   2) письмо с тем же маркером и получателем в SMTP-ловушке Mailpit;
#   3) запись заявки в Inbound Messages Flamingo со статусом mail_sent.
#
# Запуск из корня проекта:
#   bash wp/tests/lead-forms-e2e.sh
#
# Если сайт уже поднят (например, из smoke.sh), передайте
# LEAD_FORMS_E2E_ATTACH=1 и тот же COMPOSE_PROJECT_NAME, чтобы не запускать
# второй проект:
#   LEAD_FORMS_E2E_ATTACH=1 COMPOSE_PROJECT_NAME=velotok-smoke \
#     WORDPRESS_PORT=8080 MAILPIT_PORT=8025 bash wp/tests/lead-forms-e2e.sh
#
# Для уже установленного WordPress без Docker (например, на CI-стенде или в
# окружении без docker CLI) используйте внешнюю команду WP-CLI и отключите
# compose. Скрипт проверит тот же реальный путь «форма → письмо → Flamingo»:
#   LEAD_FORMS_E2E_NO_DOCKER=1 \
#   LEAD_FORMS_E2E_WP_CMD='wp --path=/srv/wordpress --allow-root' \
#   WORDPRESS_PORT=8080 MAILPIT_PORT=8025 \
#   bash wp/tests/lead-forms-e2e.sh
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

PORT="${WORDPRESS_PORT:-8080}"
MAILPIT_PORT="${MAILPIT_PORT:-8025}"
PROJECT="${COMPOSE_PROJECT_NAME:-velotok-lead-e2e}"
COMPOSE=(docker compose -p "$PROJECT")
ATTACH="${LEAD_FORMS_E2E_ATTACH:-0}"
BASE_URL="http://localhost:${PORT}"
MAILPIT_URL="${MAILPIT_URL:-http://localhost:${MAILPIT_PORT}}"
RESPONSE_FILE="$(mktemp)"
MAIL_FILE="$(mktemp)"
MAIL_DETAIL_FILE="$(mktemp)"
ERROR_FILE="$(mktemp)"
MAIL_CHECK="$ROOT_DIR/wp/cli/lead-forms-mail-check.php"

# Compose is the default, but the same script must be able to prove the real
# delivery path on an already installed WordPress where Docker is unavailable.
USE_COMPOSE=1
if [ "${LEAD_FORMS_E2E_NO_DOCKER:-0}" = "1" ] || [ -n "${LEAD_FORMS_E2E_WP_CMD:-}" ]; then
	USE_COMPOSE=0
fi

if [ "$USE_COMPOSE" = "1" ]; then
	WP_CLI_CMD=("${COMPOSE[@]}" run --rm -T wpcli)
else
	if [ -z "${LEAD_FORMS_E2E_WP_CMD:-}" ]; then
		echo "FAIL: LEAD_FORMS_E2E_NO_DOCKER=1 требует LEAD_FORMS_E2E_WP_CMD (команда WP-CLI для внешнего сайта)" >&2
		exit 1
	fi
	read -r -a WP_CLI_CMD <<<"${LEAD_FORMS_E2E_WP_CMD}"
fi

cleanup() {
	rm -f "$RESPONSE_FILE" "$MAIL_FILE" "$MAIL_DETAIL_FILE" "$ERROR_FILE"
	if [ "$USE_COMPOSE" = "1" ] && [ "$ATTACH" != "1" ]; then
		"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
	fi
}
trap cleanup EXIT

wp() {
	"${WP_CLI_CMD[@]}" "$@"
}

compose_logs() {
	if [ "$USE_COMPOSE" = "1" ]; then
		"${COMPOSE[@]}" logs "$@" >&2 || true
	fi
}

if [ "$USE_COMPOSE" = "1" ] && [ "$ATTACH" != "1" ]; then
	"${COMPOSE[@]}" up -d
fi

attempt=0
until curl -fsS "${BASE_URL}/" -o /dev/null; do
	attempt=$((attempt + 1))
	if [ "$attempt" -ge 90 ]; then
		echo "FAIL: сайт не ответил на ${BASE_URL}/" >&2
		compose_logs --tail=120
		exit 1
	fi
	sleep 2
done

if ! curl -fsS "${MAILPIT_URL}/api/v1/messages?limit=1" -o /dev/null; then
	echo "FAIL: SMTP-ловушка Mailpit недоступна на ${MAILPIT_URL}" >&2
	compose_logs mailpit --tail=120
	exit 1
fi

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
	recipient_address="$(printf '%s' "$recipient" | cut -d, -f1 | tr -d '[:space:]')"

	stamp="$(date +%s)-$$"
	marker="EBIKE21-${form}-${stamp}"
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

	# Статус сбоя почты означает, что письмо не ушло. Это провал критерия, а не успех.
	if ! status="$(php "$MAIL_CHECK" response "$RESPONSE_FILE" 2>"$ERROR_FILE")"; then
		echo "FAIL: форма «$form» не отправила письмо (CF7 response):" >&2
		cat "$ERROR_FILE" >&2 || true
		cat "$RESPONSE_FILE" >&2
		exit 1
	fi
	if [ "$status" != "mail_sent" ]; then
		echo "FAIL: форма «$form» вернула status=${status:-empty}, ожидался mail_sent" >&2
		cat "$RESPONSE_FILE" >&2
		exit 1
	fi

	echo "POST /contact-form-7/v1/contact-forms/${form_id}/feedback (${form}) -> status=${status}"

	# Проверяем фактическую доставку письма, а не только статус CF7.
	mail_id=""
	attempt=0
	while [ -z "$mail_id" ]; do
		attempt=$((attempt + 1))
		if [ "$attempt" -ge 30 ]; then
			echo "FAIL: Mailpit не получил письмо формы «$form» для ${recipient_address}" >&2
			cat "$MAIL_DETAIL_FILE" >&2 || true
			exit 1
		fi
		if curl -fsS --get "${MAILPIT_URL}/api/v1/search" \
			--data-urlencode "query=${email}" -o "$MAIL_FILE"; then
			candidate="$(php -r '
				$data = json_decode( file_get_contents( $argv[1] ), true );
				echo isset( $data["messages"][0]["ID"] ) ? $data["messages"][0]["ID"] : "";
			' "$MAIL_FILE")"
			if [ -n "$candidate" ] \
				&& curl -fsS "${MAILPIT_URL}/api/v1/message/${candidate}" -o "$MAIL_DETAIL_FILE" \
				&& php "$MAIL_CHECK" delivery "$MAIL_DETAIL_FILE" "$recipient_address" "$marker" 2>/dev/null; then
				mail_id="$candidate"
			fi
		fi
		if [ -z "$mail_id" ]; then
			sleep 1
		fi
	done

	echo "OK: письмо формы «$form» доставлено в Mailpit (${mail_id}) на ${recipient_address}"

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

	submission_status="$(wp post meta get "$message_id" _submission_status)"
	if [ "$submission_status" != "mail_sent" ]; then
		echo "FAIL: Flamingo сохранил заявку $message_id со статусом ${submission_status:-empty}, ожидался mail_sent" >&2
		exit 1
	fi

	echo "OK: форма «$form» отправлена (mail_sent), письмо доставлено, Flamingo Inbound Message ${message_id} сохранён (${email}), получатель ${recipient}"
	before="$after"
done

wp post list --post_type=flamingo_inbound --fields=ID,post_title,post_status --format=table
echo "OK: обе формы отправлены с mail_sent, письма доставлены в Mailpit и сохранены во Flamingo"
