#!/usr/bin/env bash
#
# Установка LordSerial на VPS с Ubuntu 24.04
# Веб-сервер: Caddy | Домен: lordserialov.net
#
# Установка с GitHub (одной командой на чистом VPS):
#   curl -fsSL https://raw.githubusercontent.com/enikov1/cld/main/install.sh | sudo bash
#
# Или вручную:
#   git clone https://github.com/enikov1/cld.git /var/www/lordserialov.net
#   cd /var/www/lordserialov.net && sudo bash install.sh
#
# Опциональные переменные окружения:
#   DOMAIN=lordserialov.net
#   APP_DIR=/var/www/lordserialov.net
#   REPO_URL=https://github.com/enikov1/cld.git
#   DB_NAME=lordserial
#   DB_USER=lordserial
#   DB_PASSWORD=секрет          # если не задан — сгенерируется
#   ADMIN_TOKEN=секрет          # если не задан — сгенерируется
#   SKIP_BUILD=1                # пропустить npm/composer build (для отладки)
#
set -euo pipefail

# ─── Конфигурация ─────────────────────────────────────────────────────────────

DOMAIN="${DOMAIN:-lordserialov.net}"
REPO_URL="${REPO_URL:-https://github.com/enikov1/cld.git}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-/var/www/lordserialov.net}"
DB_NAME="${DB_NAME:-lordserial}"
DB_USER="${DB_USER:-lordserial}"
DB_PASSWORD="${DB_PASSWORD:-}"
ADMIN_TOKEN="${ADMIN_TOKEN:-}"
SKIP_BUILD="${SKIP_BUILD:-0}"
PHP_VERSION="8.3"
CREDENTIALS_FILE="/root/lordserialov-credentials.txt"

# ─── Утилиты ──────────────────────────────────────────────────────────────────

log()  { echo -e "\n\033[1;32m[install]\033[0m $*"; }
warn() { echo -e "\033[1;33m[warn]\033[0m $*"; }
die()  { echo -e "\033[1;31m[error]\033[0m $*" >&2; exit 1; }

rand_secret() {
    openssl rand -base64 32 | tr -d '/+=' | head -c 32
}

require_root() {
    [[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Запустите скрипт от root: sudo bash install.sh"
}

check_ubuntu() {
    [[ -f /etc/os-release ]] || die "Не удалось определить ОС"
    source /etc/os-release
    [[ "${ID}" == "ubuntu" ]] || die "Скрипт рассчитан на Ubuntu (обнаружено: ${ID})"
    [[ "${VERSION_ID}" == "24.04" ]] || warn "Тестировался на Ubuntu 24.04 (обнаружено: ${VERSION_ID})"
}

env_set() {
    local key="$1" value="$2" file="$3"
    local escaped="${value//\\/\\\\}"
    escaped="${escaped//&/\\&}"
    if grep -q "^${key}=" "$file" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${escaped}|" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

fix_nodesource_apt_conflict() {
    local force="${1:-0}"
    local ns_files keyrings

    ns_files=$(grep -rl 'deb.nodesource.com' /etc/apt/sources.list.d/ 2>/dev/null || true)
    keyrings=0
    [[ -f /usr/share/keyrings/nodejs.gpg ]] && keyrings=$((keyrings + 1))
    [[ -f /usr/share/keyrings/nodesource.gpg ]] && keyrings=$((keyrings + 1))

    if [[ "$force" == "1" ]] || [[ "$keyrings" -gt 1 ]] || [[ "$(echo "$ns_files" | grep -c . || echo 0)" -gt 1 ]]; then
        warn "Исправление конфликта репозитория NodeSource..."
        rm -f /etc/apt/sources.list.d/nodesource*.list /etc/apt/sources.list.d/nodesource*.sources
        rm -f /etc/apt/sources.list.d/node_*.list /etc/apt/sources.list.d/node_*.sources
        rm -f /usr/share/keyrings/nodejs.gpg /usr/share/keyrings/nodesource.gpg
        return 0
    fi
    return 1
}

apt_get_update() {
    if apt-get update -qq 2>/tmp/lordserial-apt-update.err; then
        return 0
    fi

    if grep -q 'Conflicting values set for option Signed-By' /tmp/lordserial-apt-update.err; then
        fix_nodesource_apt_conflict 1
        apt-get update -qq
        return 0
    fi

    cat /tmp/lordserial-apt-update.err >&2
    die "apt-get update завершился с ошибкой"
}

install_nodejs() {
    if command -v node &>/dev/null && [[ "$(node -v | cut -d. -f1 | tr -d v)" -ge 18 ]]; then
        log "Node.js $(node -v) уже установлен — пропуск"
        return
    fi

    log "Установка Node.js 20 LTS..."
    rm -f /etc/apt/sources.list.d/nodesource*.list /etc/apt/sources.list.d/nodesource*.sources
    rm -f /etc/apt/sources.list.d/node_*.list /etc/apt/sources.list.d/node_*.sources
    rm -f /usr/share/keyrings/nodejs.gpg /usr/share/keyrings/nodesource.gpg

    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /usr/share/keyrings/nodesource.gpg
    echo "deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list

    apt_get_update
    apt-get install -y -qq nodejs
}

ensure_git_safe() {
    git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
}

set_app_permissions() {
    # root владеет .git (git pull от root), www-data — запись в storage/cache
    chown -R root:www-data "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 775 {} \;
    find "$APP_DIR" -type f -exec chmod 664 {} \;
    chmod +x "$APP_DIR/artisan"

    chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    chmod -R ug+rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
}

# ─── Подготовка ───────────────────────────────────────────────────────────────

require_root
check_ubuntu

export DEBIAN_FRONTEND=noninteractive

fix_nodesource_apt_conflict || true

if [[ -f "$SCRIPT_DIR/artisan" ]]; then
    APP_DIR="$SCRIPT_DIR"
elif [[ ! -f "$APP_DIR/artisan" ]]; then
    log "Клонирование ${REPO_URL} → ${APP_DIR}..."
    mkdir -p "$(dirname "$APP_DIR")"
    git clone --depth 1 "$REPO_URL" "$APP_DIR"
fi

[[ -f "$APP_DIR/artisan" ]] || die "Laravel-проект не найден в ${APP_DIR}."

ensure_git_safe

if [[ -z "$DB_PASSWORD" ]]; then
    DB_PASSWORD="$(rand_secret)"
fi
if [[ -z "$ADMIN_TOKEN" ]]; then
    ADMIN_TOKEN="$(rand_secret)"
fi

log "Домен:     ${DOMAIN}"
log "Каталог:   ${APP_DIR}"
log "База:      ${DB_NAME} (пользователь: ${DB_USER})"

# ─── Системные пакеты ───────────────────────────────────────────────────────

log "Обновление пакетов и установка зависимостей..."
apt_get_update
apt-get install -y -qq \
    ca-certificates curl gnupg lsb-release software-properties-common \
    git unzip acl \
    mariadb-server mariadb-client \
    "php${PHP_VERSION}-cli" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-common" \
    "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-intl" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-readline" \
    "php${PHP_VERSION}-tokenizer" "php${PHP_VERSION}-fileinfo" \
    ufw

# ─── Caddy ──────────────────────────────────────────────────────────────────

if ! command -v caddy &>/dev/null; then
    log "Установка Caddy..."
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
        | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
        | tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null
    apt_get_update
    apt-get install -y -qq caddy
else
    log "Caddy уже установлен — пропуск"
fi

# ─── Node.js 20 LTS ─────────────────────────────────────────────────────────

install_nodejs

# ─── Composer ───────────────────────────────────────────────────────────────

if ! command -v composer &>/dev/null; then
    log "Установка Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
else
    log "Composer уже установлен — пропуск"
fi

# ─── MariaDB ──────────────────────────────────────────────────────────────────

log "Настройка MariaDB..."
systemctl enable --now mariadb

mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# ─── PHP-FPM ──────────────────────────────────────────────────────────────────

log "Настройка PHP-FPM..."
sed -i 's/^;*cgi.fix_pathinfo=.*/cgi.fix_pathinfo=0/' "/etc/php/${PHP_VERSION}/fpm/php.ini" || true

cat > "/etc/php/${PHP_VERSION}/fpm/pool.d/lordserial.conf" <<EOF
[lordserial]
user = www-data
group = www-data
listen = /run/php/php${PHP_VERSION}-fpm-lordserial.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[memory_limit] = 256M
EOF

systemctl enable "php${PHP_VERSION}-fpm"
systemctl restart "php${PHP_VERSION}-fpm"

# ─── Laravel .env ───────────────────────────────────────────────────────────

log "Настройка .env..."
cd "$APP_DIR"

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

env_set APP_NAME        "LordSerial"              .env
env_set APP_ENV         "production"              .env
env_set APP_DEBUG       "false"                   .env
env_set APP_URL         "https://${DOMAIN}"       .env
env_set DB_CONNECTION   "mysql"                   .env
env_set DB_HOST         "127.0.0.1"               .env
env_set DB_PORT         "3306"                    .env
env_set DB_DATABASE     "${DB_NAME}"              .env
env_set DB_USERNAME     "${DB_USER}"              .env
env_set DB_PASSWORD     "${DB_PASSWORD}"          .env
env_set SESSION_DRIVER  "database"                .env
env_set CACHE_STORE     "database"                .env
env_set QUEUE_CONNECTION "database"               .env
env_set ADMIN_TOKEN     "${ADMIN_TOKEN}"          .env

mkdir -p "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
chmod -R ug+rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true

log "Composer install (production)..."
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    sudo -u www-data php artisan key:generate --force
fi

# ─── Зависимости и сборка ─────────────────────────────────────────────────────

if [[ "$SKIP_BUILD" != "1" ]]; then
    log "Миграции БД..."
    sudo -u www-data php artisan migrate --force

    log "Storage link..."
    sudo -u www-data php artisan storage:link 2>/dev/null || true

    log "Сборка темы (npm)..."
    sudo -u www-data npm ci
    sudo -u www-data npm run build:theme

    log "Сборка админки (admin-ui)..."
    pushd admin-ui >/dev/null
    sudo -u www-data npm ci
    sudo -u www-data npm run build
    popd >/dev/null

    log "Кэширование конфигурации..."
    sudo -u www-data php artisan config:cache
    sudo -u www-data php artisan route:cache
    sudo -u www-data php artisan view:cache
else
    warn "SKIP_BUILD=1 — пропущены composer/npm и миграции"
fi

# ─── Права ────────────────────────────────────────────────────────────────────

log "Финальная настройка прав..."
set_app_permissions

# ─── Caddy vhost ──────────────────────────────────────────────────────────────

log "Конфигурация Caddy для ${DOMAIN}..."
cat > "/etc/caddy/Caddyfile" <<EOF
{
    email admin@${DOMAIN}
}

${DOMAIN} {
    root * ${APP_DIR}/public
    encode gzip zstd

    @blocked {
        path /.env* /.git* /storage/* /vendor/*
    }
    respond @blocked 404

    php_fastcgi unix//run/php/php${PHP_VERSION}-fpm-lordserial.sock {
        resolve_root_symlink
    }

    try_files {path} {path}/ /index.php?{query}
    file_server
}

www.${DOMAIN} {
    redir https://${DOMAIN}{uri} permanent
}
EOF

systemctl enable caddy
systemctl reload caddy || systemctl restart caddy

# ─── Queue worker (systemd) ───────────────────────────────────────────────────

log "Systemd-сервис очереди..."
cat > /etc/systemd/system/lordserial-queue.service <<EOF
[Unit]
Description=LordSerial Queue Worker
After=network.target mariadb.service php${PHP_VERSION}-fpm.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:${APP_DIR}/storage/logs/queue.log
StandardError=append:${APP_DIR}/storage/logs/queue.log

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable lordserial-queue
systemctl restart lordserial-queue

# ─── Cron (scheduler) ─────────────────────────────────────────────────────────

log "Cron для Laravel Scheduler..."
CRON_LINE="* * * * * www-data cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
CRON_FILE="/etc/cron.d/lordserial-scheduler"
echo "$CRON_LINE" > "$CRON_FILE"
chmod 644 "$CRON_FILE"

# ─── Firewall ─────────────────────────────────────────────────────────────────

if command -v ufw &>/dev/null; then
    log "Настройка UFW (22, 80, 443)..."
    ufw --force enable 2>/dev/null || true
    ufw allow OpenSSH 2>/dev/null || ufw allow 22/tcp 2>/dev/null || true
    ufw allow 80/tcp  2>/dev/null || true
    ufw allow 443/tcp 2>/dev/null || true
fi

# ─── Сохранение учётных данных ────────────────────────────────────────────────

cat > "$CREDENTIALS_FILE" <<EOF
LordSerial — учётные данные установки
Сгенерировано: $(date -Iseconds)

Сайт:        https://${DOMAIN}
Админка:     https://${DOMAIN}/admin/
Каталог:     ${APP_DIR}

База данных:
  DB_HOST=127.0.0.1
  DB_DATABASE=${DB_NAME}
  DB_USERNAME=${DB_USER}
  DB_PASSWORD=${DB_PASSWORD}

ADMIN_TOKEN (заголовок X-Admin-Token):
  ${ADMIN_TOKEN}

Проверка:    curl -sI https://${DOMAIN}/up
Логи:        tail -f ${APP_DIR}/storage/logs/laravel.log
Очередь:     journalctl -u lordserial-queue -f
EOF
chmod 600 "$CREDENTIALS_FILE"

# ─── Готово ───────────────────────────────────────────────────────────────────

echo ""
echo "════════════════════════════════════════════════════════════"
echo "  Установка завершена!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "  Сайт:     https://${DOMAIN}"
echo "  Админка:  https://${DOMAIN}/admin/"
echo ""
echo "  Учётные данные сохранены в: ${CREDENTIALS_FILE}"
echo ""
echo "  Убедитесь, что DNS A-запись ${DOMAIN} указывает на IP этого VPS."
echo "  Caddy автоматически получит SSL-сертификат Let's Encrypt."
echo ""
echo "  Полезные команды:"
echo "    systemctl status caddy lordserial-queue mariadb"
echo "    sudo bash update.sh        # обновление после git push"
echo "    php artisan config:clear   # после смены .env"
echo "════════════════════════════════════════════════════════════"
