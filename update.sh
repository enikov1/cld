#!/usr/bin/env bash
#
# Обновление LordSerial на VPS (Ubuntu 24.04)
#
# Запуск из каталога проекта:
#   cd /var/www/lordserialov.net && sudo bash update.sh
#
# Или одной командой (скачать свежий скрипт и выполнить):
#   curl -fsSL https://raw.githubusercontent.com/enikov1/cld/main/update.sh | sudo bash
#
# Опциональные переменные окружения:
#   APP_DIR=/var/www/lordserialov.net
#   GIT_BRANCH=main
#   SKIP_BUILD=1        # пропустить composer/npm
#   SKIP_MIGRATE=1      # пропустить миграции
#   SKIP_MAINTENANCE=0  # включить режим обслуживания на время update
#   SKIP_SERVICES=1     # не трогать php-fpm/caddy/queue/cron
#
set -euo pipefail

# ─── Конфигурация ─────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$SCRIPT_DIR}"
GIT_BRANCH="${GIT_BRANCH:-main}"
PHP_VERSION="8.3"
SKIP_BUILD="${SKIP_BUILD:-0}"
SKIP_MIGRATE="${SKIP_MIGRATE:-0}"
SKIP_MAINTENANCE="${SKIP_MAINTENANCE:-1}"
SKIP_SERVICES="${SKIP_SERVICES:-0}"

# ─── Утилиты ──────────────────────────────────────────────────────────────────

log()  { echo -e "\n\033[1;36m[update]\033[0m $*"; }
warn() { echo -e "\033[1;33m[warn]\033[0m $*"; }
die()  { echo -e "\033[1;31m[error]\033[0m $*" >&2; exit 1; }

require_root() {
    [[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Запустите скрипт от root: sudo bash update.sh"
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

# Systemd queue worker + Laravel Scheduler (cron)
setup_queue_and_scheduler() {
    local php_bin queue_unit cron_file cron_line queue_log

    php_bin="$(resolve_php_bin)"
    queue_unit="/etc/systemd/system/lordserial-queue.service"
    cron_file="/etc/cron.d/lordserial-scheduler"
    queue_log="${APP_DIR}/storage/logs/queue.log"

    log "Systemd-сервис очереди (lordserial-queue)..."
    mkdir -p "${APP_DIR}/storage/logs"
    touch "$queue_log"
    chown www-data:www-data "$queue_log"
    chmod 664 "$queue_log"

    cat > "$queue_unit" <<EOF
[Unit]
Description=LordSerial Laravel Queue Worker
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
    systemctl enable lordserial-queue
    systemctl restart lordserial-queue

    if systemctl is-active --quiet lordserial-queue; then
        log "Очередь: lordserial-queue активен (${php_bin})"
    else
        warn "lordserial-queue не запустился — смотрите: journalctl -u lordserial-queue -n 50"
        systemctl status lordserial-queue --no-pager || true
    fi

    log "Cron для Laravel Scheduler..."
    if ! command -v cron >/dev/null 2>&1 && ! systemctl list-unit-files cron.service &>/dev/null; then
        apt-get install -y -qq cron || warn "Не удалось установить пакет cron"
    fi
    systemctl enable --now cron 2>/dev/null || systemctl enable --now crond 2>/dev/null || true

    cron_line="* * * * * www-data cd ${APP_DIR} && ${php_bin} artisan schedule:run >> /dev/null 2>&1"
    echo "$cron_line" > "$cron_file"
    chmod 644 "$cron_file"

    log "Scheduler: ${cron_file}"
}

# ─── Подготовка ───────────────────────────────────────────────────────────────

require_root

[[ -f "$APP_DIR/artisan" ]] || die "Laravel-проект не найден в ${APP_DIR}."
[[ -d "$APP_DIR/.git" ]]    || die "Каталог ${APP_DIR} не является git-репозиторием. Используйте install.sh."

cd "$APP_DIR"

ensure_git_safe

log "Каталог: ${APP_DIR}"
log "Ветка:   ${GIT_BRANCH}"

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
php artisan storage:link 2>/dev/null || true

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

    # Создаёт/обновляет unit и cron, если их ещё не было (systemd вариант A)
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

DOMAIN="$(grep -E '^APP_URL=' .env 2>/dev/null | cut -d= -f2- | tr -d '"' | sed 's|https\?://||' || echo 'lordserialov.net')"

echo ""
echo "════════════════════════════════════════════════════════════"
echo "  Обновление завершено!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "  Версия:   ${NEW_REV:0:8}"
echo "  Сайт:     https://${DOMAIN}"
echo "  Проверка: curl -sI https://${DOMAIN}/up"
echo ""
if [[ "$SKIP_MAINTENANCE" != "1" ]]; then
echo "  Во время обновления сайт был в maintenance."
echo "  Обход:    https://${DOMAIN}/lordserial-update"
echo ""
fi
echo "  Логи:     tail -f ${APP_DIR}/storage/logs/laravel.log"
echo "  Очередь:  systemctl status lordserial-queue"
echo "  Cron:     cat /etc/cron.d/lordserial-scheduler"
echo "════════════════════════════════════════════════════════════"
