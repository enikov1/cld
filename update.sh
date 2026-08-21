#!/usr/bin/env bash
#
# Обновление LordSerial на VPS (Ubuntu 24.04)
#
# Запуск из каталога проекта:
#   cd /var/www/lordserialov.net && sudo bash update.sh
#   cd /var/www/site2.ru && sudo bash update.sh
#
# Или одной командой (скачать свежий скрипт и выполнить):
#   curl -fsSL https://raw.githubusercontent.com/enikov1/cld/main/update.sh \
#     | sudo APP_DIR=/var/www/site2.ru bash
#
# Несколько сайтов на одном сервере — обновляйте каждый отдельно:
#   sudo APP_DIR=/var/www/site1.ru bash update.sh
#   sudo APP_DIR=/var/www/site2.ru bash update.sh
#
# Опциональные переменные окружения:
#   APP_DIR=/var/www/lordserialov.net
#   SITE_ID=my_site     # обычно определяется автоматически
#   GIT_BRANCH=main
#   SKIP_BUILD=1        # пропустить composer/npm
#   SKIP_MIGRATE=1      # пропустить миграции
#   SKIP_MAINTENANCE=0  # включить режим обслуживания на время update
#   SKIP_SERVICES=1     # не трогать php-fpm/caddy/queue/cron
#
set -euo pipefail

# ─── Конфигурация ─────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
_USER_APP_DIR="${APP_DIR:-}"
_USER_SITE_ID="${SITE_ID:-}"
GIT_BRANCH="${GIT_BRANCH:-main}"
PHP_VERSION="8.3"
SKIP_BUILD="${SKIP_BUILD:-0}"
SKIP_MIGRATE="${SKIP_MIGRATE:-0}"
SKIP_MAINTENANCE="${SKIP_MAINTENANCE:-1}"
SKIP_SERVICES="${SKIP_SERVICES:-0}"

APP_DIR=""
SITE_ID=""
DOMAIN=""
QUEUE_UNIT_NAME=""
QUEUE_UNIT_PATH=""
CRON_FILE=""
PHP_POOL_NAME=""
PHP_FPM_SOCK=""

# ─── Утилиты ──────────────────────────────────────────────────────────────────

log()  { echo -e "\n\033[1;36m[update]\033[0m $*"; }
warn() { echo -e "\033[1;33m[warn]\033[0m $*"; }
die()  { echo -e "\033[1;31m[error]\033[0m $*" >&2; exit 1; }

require_root() {
    [[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Запустите скрипт от root: sudo bash update.sh"
}

read_env_value() {
    local key="$1" file="$2"
    grep -E "^${key}=" "$file" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' || true
}

normalize_domain() {
    local value="$1"
    value="${value#https://}"
    value="${value#http://}"
    value="${value%%/*}"
    value="${value#www.}"
    echo "$value"
}

derive_site_id() {
    local value="$1" id
    id="$(echo "$value" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/_/g; s/^_+|_+$//g; s/_+/_/g')"
    [[ -n "$id" ]] || id="site"
    echo "${id:0:48}"
}

unit_points_to_app_dir() {
    local unit_file="$1"
    [[ -f "$unit_file" ]] || return 1
    grep -qxF "WorkingDirectory=${APP_DIR}" "$unit_file" 2>/dev/null
}

resolve_service_names() {
    local use_legacy=0
    local legacy_unit="/etc/systemd/system/lordserial-queue.service"

    if unit_points_to_app_dir "$legacy_unit"; then
        use_legacy=1
    elif [[ "$SITE_ID" == "lordserial" ]]; then
        if [[ ! -f "$legacy_unit" ]]; then
            use_legacy=1
        elif unit_points_to_app_dir "$legacy_unit"; then
            use_legacy=1
        fi
    fi

    # По пулу PHP: если для этой папки уже есть старый sock в caddy — legacy
    if [[ "$use_legacy" != "1" ]] && [[ -f "/etc/php/${PHP_VERSION}/fpm/pool.d/lordserial.conf" ]]; then
        local snippet
        shopt -s nullglob
        for snippet in /etc/caddy/sites/*.caddy; do
            if grep -qF "${APP_DIR}/public" "$snippet" 2>/dev/null \
                && grep -qF "php${PHP_VERSION}-fpm-lordserial.sock" "$snippet" 2>/dev/null; then
                use_legacy=1
                break
            fi
        done
        shopt -u nullglob
    fi

    if [[ "$use_legacy" == "1" ]]; then
        QUEUE_UNIT_NAME="lordserial-queue"
        CRON_FILE="/etc/cron.d/lordserial-scheduler"
        PHP_POOL_NAME="lordserial"
    else
        QUEUE_UNIT_NAME="lordserial-queue-${SITE_ID}"
        CRON_FILE="/etc/cron.d/lordserial-scheduler-${SITE_ID}"
        PHP_POOL_NAME="lordserial-${SITE_ID}"
    fi

    QUEUE_UNIT_PATH="/etc/systemd/system/${QUEUE_UNIT_NAME}.service"
    PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm-${PHP_POOL_NAME}.sock"
}

resolve_site_instance() {
    if [[ -n "$_USER_APP_DIR" ]]; then
        APP_DIR="$_USER_APP_DIR"
    elif [[ -f "$SCRIPT_DIR/artisan" ]]; then
        APP_DIR="$SCRIPT_DIR"
    else
        die "Укажите каталог сайта: sudo APP_DIR=/var/www/example.com bash update.sh"
    fi

    if [[ -f "$APP_DIR/.env" ]]; then
        DOMAIN="$(normalize_domain "$(read_env_value APP_URL "$APP_DIR/.env")")"
    fi
    [[ -n "$DOMAIN" ]] || DOMAIN="$(basename "$APP_DIR")"

    if [[ -n "$_USER_SITE_ID" ]]; then
        SITE_ID="$(derive_site_id "$_USER_SITE_ID")"
    elif [[ "$DOMAIN" == "lordserialov.net" ]]; then
        SITE_ID="lordserial"
    else
        SITE_ID="$(derive_site_id "$DOMAIN")"
    fi

    resolve_service_names
}

ensure_git_safe() {
    git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
    if id www-data &>/dev/null; then
        sudo -u www-data git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
    fi
}

set_app_permissions() {
    chown -R root:www-data "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 775 {} \;
    find "$APP_DIR" -type f -exec chmod 664 {} \;
    chmod +x "$APP_DIR/artisan"

    chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    chmod -R ug+rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

    if [[ -d "$APP_DIR/public" ]]; then
        touch "$APP_DIR/public/sitemap.xml"
        chown www-data:www-data "$APP_DIR/public/sitemap.xml"
        chmod 664 "$APP_DIR/public/sitemap.xml"
    fi
}

generate_sitemap_if_possible() {
    [[ -f "$APP_DIR/artisan" ]] || return 0

    log "Генерация sitemap.xml..."
    if id www-data &>/dev/null; then
        if sudo -u www-data php "$APP_DIR/artisan" sitemap:generate --force; then
            chown www-data:www-data "$APP_DIR/public/sitemap.xml" 2>/dev/null || true
            return 0
        fi
    fi

    if php "$APP_DIR/artisan" sitemap:generate --force; then
        chown www-data:www-data "$APP_DIR/public/sitemap.xml" 2>/dev/null || true
    else
        warn "sitemap:generate не выполнен — сгенерируйте sitemap в админке"
    fi
}

resolve_php_bin() {
    if [[ -x "/usr/bin/php${PHP_VERSION}" ]]; then
        echo "/usr/bin/php${PHP_VERSION}"
        return
    fi
    if command -v php >/dev/null 2>&1; then
        command -v php
        return
    fi
    die "PHP не найден"
}

ensure_storage_link() {
    local link="${APP_DIR}/public/storage"

    mkdir -p "${APP_DIR}/storage/app/public"

    if [[ -L "$link" ]]; then
        php artisan storage:link --force >/dev/null 2>&1 || true
        return 0
    fi

    if [[ -e "$link" ]]; then
        warn "public/storage существует, но не является симлинком — storage:link пропущен"
        return 0
    fi

    php artisan storage:link
}

# Systemd queue worker + Laravel Scheduler (cron) — только для этого инстанса
setup_queue_and_scheduler() {
    local php_bin queue_log cron_line

    php_bin="$(resolve_php_bin)"
    queue_log="${APP_DIR}/storage/logs/queue.log"

    log "Systemd-сервис очереди (${QUEUE_UNIT_NAME})..."
    mkdir -p "${APP_DIR}/storage/logs"
    touch "$queue_log"
    chown www-data:www-data "$queue_log"
    chmod 664 "$queue_log"

    cat > "$QUEUE_UNIT_PATH" <<EOF
[Unit]
Description=LordSerial Queue Worker (${DOMAIN} / ${SITE_ID})
After=network.target mariadb.service php${PHP_VERSION}-fpm.service
Wants=mariadb.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=${APP_DIR}
ExecStart=${php_bin} artisan queue:work database --sleep=3 --tries=3 --max-time=3600
Nice=10
StandardOutput=append:${queue_log}
StandardError=append:${queue_log}

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable "$QUEUE_UNIT_NAME"
    systemctl restart "$QUEUE_UNIT_NAME"

    if systemctl is-active --quiet "$QUEUE_UNIT_NAME"; then
        log "Очередь: ${QUEUE_UNIT_NAME} активен (${php_bin})"
    else
        warn "${QUEUE_UNIT_NAME} не запустился — смотрите: journalctl -u ${QUEUE_UNIT_NAME} -n 50"
        systemctl status "$QUEUE_UNIT_NAME" --no-pager || true
    fi

    log "Cron для Laravel Scheduler..."
    if ! command -v cron >/dev/null 2>&1 && ! systemctl list-unit-files cron.service &>/dev/null; then
        apt-get install -y -qq cron || warn "Не удалось установить пакет cron"
    fi
    systemctl enable --now cron 2>/dev/null || systemctl enable --now crond 2>/dev/null || true

    cron_line="* * * * * www-data cd ${APP_DIR} && ${php_bin} artisan schedule:run >> /dev/null 2>&1"
    echo "$cron_line" > "$CRON_FILE"
    chmod 644 "$CRON_FILE"

    log "Scheduler: ${CRON_FILE}"
}

# ─── Подготовка ───────────────────────────────────────────────────────────────

require_root
resolve_site_instance

[[ -f "$APP_DIR/artisan" ]] || die "Laravel-проект не найден в ${APP_DIR}."
[[ -d "$APP_DIR/.git" ]]    || die "Каталог ${APP_DIR} не является git-репозиторием. Используйте install.sh."

cd "$APP_DIR"

ensure_git_safe

log "Каталог:  ${APP_DIR}"
log "Инстанс:  ${SITE_ID}"
log "Домен:    ${DOMAIN}"
log "Очередь:  ${QUEUE_UNIT_NAME}"
log "Ветка:    ${GIT_BRANCH}"

# ─── Режим обслуживания ───────────────────────────────────────────────────────

maintenance_up() {
    if [[ "$SKIP_MAINTENANCE" != "1" ]]; then
        php artisan up 2>/dev/null || true
    fi
}

if [[ "$SKIP_MAINTENANCE" != "1" ]]; then
    log "Включение режима обслуживания..."
    php artisan down --retry=60 --secret="lordserial-update" || true
    trap maintenance_up EXIT
fi

# ─── Git pull ─────────────────────────────────────────────────────────────────

if [[ -n "$(git status --porcelain 2>/dev/null)" ]]; then
    warn "Обнаружены локальные изменения — сохраняю в stash..."
    git stash push -u -m "update.sh auto-stash $(date -Iseconds)" || die "Не удалось сохранить локальные изменения (git stash)"
fi

OLD_REV="$(git rev-parse HEAD)"
log "Текущая версия: ${OLD_REV:0:8}"

log "Получение обновлений из origin/${GIT_BRANCH}..."
git fetch origin "$GIT_BRANCH"
git checkout "$GIT_BRANCH"
git pull --ff-only origin "$GIT_BRANCH"

NEW_REV="$(git rev-parse HEAD)"
if [[ "$OLD_REV" == "$NEW_REV" ]]; then
    log "Новых коммитов нет — обновляю зависимости и кэш на всякий случай."
else
    log "Обновлено: ${OLD_REV:0:8} → ${NEW_REV:0:8}"
    git --no-pager log --oneline "${OLD_REV}..${NEW_REV}" | head -20 || true
fi

# ─── Зависимости и сборка ─────────────────────────────────────────────────────

if [[ "$SKIP_BUILD" != "1" ]]; then
    log "Composer install (production)..."
    export COMPOSER_ALLOW_SUPERUSER=1
    composer install --no-dev --optimize-autoloader --no-interaction

    log "Сборка темы (npm)..."
    npm ci
    npm run build:theme

    log "Сборка админки (admin-ui)..."
    pushd admin-ui >/dev/null
    npm ci
    npm run build
    popd >/dev/null
else
    warn "SKIP_BUILD=1 — пропущены composer и npm"
fi

# ─── Миграции ─────────────────────────────────────────────────────────────────

if [[ "$SKIP_MIGRATE" != "1" ]]; then
    log "Миграции БД..."
    php artisan migrate --force
else
    warn "SKIP_MIGRATE=1 — миграции пропущены"
fi

log "Storage link..."
ensure_storage_link

# ─── Кэш Laravel ──────────────────────────────────────────────────────────────

log "Очистка и пересборка кэша..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ─── Права ────────────────────────────────────────────────────────────────────

log "Права на storage и cache..."
set_app_permissions
generate_sitemap_if_possible

# ─── Перезапуск сервисов + очередь/cron ───────────────────────────────────────

if [[ "$SKIP_SERVICES" != "1" ]]; then
    log "Перезапуск сервисов..."

    if systemctl is-active --quiet "php${PHP_VERSION}-fpm" 2>/dev/null; then
        systemctl reload "php${PHP_VERSION}-fpm" || systemctl restart "php${PHP_VERSION}-fpm"
    fi

    if systemctl is-active --quiet caddy 2>/dev/null; then
        systemctl reload caddy 2>/dev/null || systemctl restart caddy
    fi

    # Создаёт/обновляет unit и cron только для этого инстанса
    setup_queue_and_scheduler
else
    warn "SKIP_SERVICES=1 — сервисы, очередь и cron не обновлены"
fi

# ─── Выключение maintenance ───────────────────────────────────────────────────

if [[ "$SKIP_MAINTENANCE" != "1" ]]; then
    log "Выключение режима обслуживания..."
    php artisan up
    trap - EXIT
fi

# ─── Готово ───────────────────────────────────────────────────────────────────

echo ""
echo "════════════════════════════════════════════════════════════"
echo "  Обновление завершено!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "  Версия:   ${NEW_REV:0:8}"
echo "  Инстанс:  ${SITE_ID}"
echo "  Сайт:     https://${DOMAIN}"
echo "  Проверка: curl -sI https://${DOMAIN}/up"
echo ""
if [[ "$SKIP_MAINTENANCE" != "1" ]]; then
echo "  Во время обновления сайт был в maintenance."
echo "  Обход:    https://${DOMAIN}/lordserial-update"
echo ""
fi
echo "  Логи:     tail -f ${APP_DIR}/storage/logs/laravel.log"
echo "  Очередь:  systemctl status ${QUEUE_UNIT_NAME}"
echo "  Cron:     cat ${CRON_FILE}"
echo "════════════════════════════════════════════════════════════"
