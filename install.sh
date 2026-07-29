#!/usr/bin/env bash
#
# ═══════════════════════════════════════════════════════════════════════════
#  Установка сайта LordSerial на VPS (Ubuntu)
# ═══════════════════════════════════════════════════════════════════════════
#
# ДЛЯ НОВИЧКА — что нужно заранее:
#   1. VPS с Ubuntu 24.04
#   2. Домен, у которого A-запись указывает на IP этого сервера
#   3. Вход по SSH от root (или через sudo)
#
# САМЫЙ ПРОСТОЙ СПОСОБ (чистый сервер, одной командой):
#   curl -fsSL https://raw.githubusercontent.com/enikov1/cld/main/install.sh | sudo bash
#
# Или со своим доменом:
#   curl -fsSL https://raw.githubusercontent.com/enikov1/cld/main/install.sh \
#     | sudo DOMAIN=ваш-сайт.ru bash
#
# Если код уже скачан:
#   cd /var/www/lordserialov.net && sudo bash install.sh
#   cd /var/www/lordserialov.net && sudo DOMAIN=ваш-сайт.ru bash install.sh
#
# ПЕРЕНОС СО СТАРОГО СЕРВЕРА (с бэкапом):
#   1. Скачайте ZIP-бэкап из админки (раздел «Бэкапы»)
#   2. Загрузите его на новый сервер, например в /root/backup.zip
#   3. Запустите:
#        sudo BACKUP_FILE=/root/backup.zip DOMAIN=ваш-сайт.ru bash install.sh
#   Или положите файл в папку проекта:
#        storage/app/backups/backup_....zip
#        (скрипт сам найдёт самый новый)
#
# СМЕНА ДОМЕНА на уже установленном сайте:
#   cd /var/www/lordserialov.net
#   sudo DOMAIN=новый-домен.ru SKIP_BUILD=1 bash install.sh
#
# СПРАВКА ПО ПАРАМЕТРАМ:
#   sudo bash install.sh --help
#
set -euo pipefail

# ─── Параметры (можно задать перед запуском) ─────────────────────────────────

DOMAIN="${DOMAIN:-lordserialov.net}"
REPO_URL="${REPO_URL:-https://github.com/enikov1/cld.git}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-/var/www/lordserialov.net}"
DB_NAME="${DB_NAME:-lordserial}"
DB_USER="${DB_USER:-lordserial}"
DB_PASSWORD="${DB_PASSWORD:-}"
ADMIN_TOKEN="${ADMIN_TOKEN:-}"
SKIP_BUILD="${SKIP_BUILD:-0}"
BACKUP_FILE="${BACKUP_FILE:-}"
SKIP_BACKUP_RESTORE="${SKIP_BACKUP_RESTORE:-0}"
PHP_VERSION="8.3"
CREDENTIALS_FILE="/root/lordserialov-credentials.txt"

STEP_CURRENT=0
STEP_TOTAL=12

# ─── Вывод для пользователя ──────────────────────────────────────────────────

log()  { echo -e "\n\033[1;32m✔\033[0m $*"; }
warn() { echo -e "\033[1;33m!\033[0m $*" >&2; }
die()  { echo -e "\n\033[1;31m✖ Ошибка:\033[0m $*\n" >&2; exit 1; }

step() {
    STEP_CURRENT=$((STEP_CURRENT + 1))
    echo ""
    echo -e "\033[1;36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
    echo -e "\033[1;36m  Шаг ${STEP_CURRENT}/${STEP_TOTAL}:\033[0m $*"
    echo -e "\033[1;36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
}

print_help() {
    cat <<'EOF'

Установка LordSerial на Ubuntu VPS
══════════════════════════════════

Что делает скрипт автоматически:
  • ставит PHP, базу данных, веб-сервер Caddy, Node.js
  • скачивает сайт (если ещё не скачан)
  • настраивает домен и HTTPS (Let's Encrypt)
  • при наличии ZIP-бэкапа — восстанавливает данные

Минимальный запуск:
  sudo bash install.sh

Со своим доменом:
  sudo DOMAIN=example.com bash install.sh

С восстановлением из бэкапа:
  sudo DOMAIN=example.com BACKUP_FILE=/root/backup.zip bash install.sh

Полезные параметры (перед командой):
  DOMAIN=сайт.ru              домен сайта (без https://)
  APP_DIR=/var/www/...        папка установки
  DB_PASSWORD=секрет          пароль БД (иначе сгенерируется)
  ADMIN_TOKEN=секрет          токен входа в админку
  BACKUP_FILE=/path/file.zip  восстановить этот бэкап
  SKIP_BACKUP_RESTORE=1       не восстанавливать бэкап
  SKIP_BUILD=1                только смена домена / без пересборки

Смена домена на уже установленном сервере:
  cd /var/www/lordserialov.net
  sudo DOMAIN=новый.ru SKIP_BUILD=1 bash install.sh

После установки пароли и токены лежат в:
  /root/lordserialov-credentials.txt

EOF
}

print_banner() {
    echo ""
    echo "════════════════════════════════════════════════════════════"
    echo "  Установка LordSerial"
    echo "════════════════════════════════════════════════════════════"
    echo ""
    echo "  Домен:     ${DOMAIN}"
    echo "  Папка:     ${APP_DIR}"
    echo "  База:      ${DB_NAME}"
    if [[ -n "$BACKUP_FILE" ]]; then
        echo "  Бэкап:     ${BACKUP_FILE}"
    elif [[ "$SKIP_BACKUP_RESTORE" == "1" ]]; then
        echo "  Бэкап:     пропущен (SKIP_BACKUP_RESTORE=1)"
    else
        echo "  Бэкап:     будет восстановлен, если найдётся ZIP"
    fi
    if [[ "$SKIP_BUILD" == "1" ]]; then
        echo "  Режим:     быстрый (SKIP_BUILD=1) — без полной пересборки"
    fi
    echo ""
    echo "  Это займёт несколько минут. Не закрывайте окно."
    echo "════════════════════════════════════════════════════════════"
}

# ─── Вспомогательные функции ─────────────────────────────────────────────────

rand_secret() {
    openssl rand -base64 32 | tr -d '/+=' | head -c 32
}

require_root() {
    [[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Запустите от администратора:
  sudo bash install.sh"
}

check_ubuntu() {
    [[ -f /etc/os-release ]] || die "Не удалось определить операционную систему"
    # shellcheck source=/dev/null
    source /etc/os-release
    [[ "${ID}" == "ubuntu" ]] || die "Нужна Ubuntu. Сейчас: ${ID}
Установите Ubuntu 24.04 на VPS и запустите снова."
    [[ "${VERSION_ID}" == "24.04" ]] || warn "Скрипт проверен на Ubuntu 24.04 (у вас ${VERSION_ID}). Обычно всё равно работает."
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
        warn "Исправляю конфликт репозитория Node.js..."
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
    die "Не удалось обновить список пакетов (apt-get update).
Проверьте интернет на сервере и повторите."
}

install_nodejs() {
    if command -v node &>/dev/null && [[ "$(node -v | cut -d. -f1 | tr -d v)" -ge 18 ]]; then
        log "Node.js уже есть ($(node -v))"
        return
    fi

    log "Устанавливаю Node.js 20..."
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

    log "Создаю карту сайта (sitemap.xml)..."
    if id www-data &>/dev/null; then
        if sudo -u www-data php "$APP_DIR/artisan" sitemap:generate --force; then
            chown www-data:www-data "$APP_DIR/public/sitemap.xml" 2>/dev/null || true
            return 0
        fi
    fi

    if php "$APP_DIR/artisan" sitemap:generate --force; then
        chown www-data:www-data "$APP_DIR/public/sitemap.xml" 2>/dev/null || true
    else
        warn "Карту сайта не удалось создать сейчас — сделайте это позже в админке"
    fi
}

find_install_backup() {
    local candidate latest=""

    if [[ -n "$BACKUP_FILE" ]]; then
        if [[ -f "$BACKUP_FILE" ]]; then
            echo "$BACKUP_FILE"
            return 0
        fi
        warn "Указан BACKUP_FILE, но файл не найден: ${BACKUP_FILE}"
        return 1
    fi

    shopt -s nullglob
    for candidate in \
        "$APP_DIR"/storage/app/backups/backup_*.zip \
        "$APP_DIR"/backups/backup_*.zip \
        "$APP_DIR"/backup_*.zip
    do
        [[ -f "$candidate" ]] || continue
        if [[ -z "$latest" || "$candidate" -nt "$latest" ]]; then
            latest="$candidate"
        fi
    done
    shopt -u nullglob

    if [[ -n "$latest" ]]; then
        echo "$latest"
        return 0
    fi

    return 1
}

restore_backup_if_present() {
    local archive

    if [[ "$SKIP_BACKUP_RESTORE" == "1" ]]; then
        warn "Восстановление бэкапа отключено (SKIP_BACKUP_RESTORE=1)"
        return 0
    fi

    # При смене домена (SKIP_BUILD) без явного BACKUP_FILE данные не трогаем
    if [[ "$SKIP_BUILD" == "1" && -z "$BACKUP_FILE" ]]; then
        return 0
    fi

    if ! archive="$(find_install_backup)"; then
        log "Бэкап не найден — сайт ставится «с нуля» (пустая база)"
        return 0
    fi

    log "Найден бэкап: ${archive}"
    log "Восстанавливаю базу данных и файлы сайта..."
    echo "   (это может занять несколько минут)"

    mkdir -p "$APP_DIR/storage/app/backups"
    chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true

    if ! php "$APP_DIR/artisan" backup:restore --file="$archive" --trigger=cli; then
        die "Не удалось восстановить бэкап:
  ${archive}

Проверьте, что архив создан в админке LordSerial (раздел «Бэкапы»).
Или запустите установку без бэкапа:
  sudo SKIP_BACKUP_RESTORE=1 bash install.sh"
    fi

    log "Догоняю обновления базы после восстановления..."
    php "$APP_DIR/artisan" migrate --force

    chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
    RESTORED_BACKUP="$archive"
    log "Бэкап успешно восстановлен"
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
    die "PHP не найден после установки. Перезапустите скрипт."
}

setup_queue_and_scheduler() {
    local php_bin queue_unit cron_file cron_line queue_log

    php_bin="$(resolve_php_bin)"
    queue_unit="/etc/systemd/system/lordserial-queue.service"
    cron_file="/etc/cron.d/lordserial-scheduler"
    queue_log="${APP_DIR}/storage/logs/queue.log"

    log "Включаю фоновые задачи (уведомления, автобэкапы, синхронизации)..."
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
        log "Фоновая очередь работает"
    else
        warn "Очередь не запустилась. Диагностика:
  journalctl -u lordserial-queue -n 50"
        systemctl status lordserial-queue --no-pager || true
    fi

    if ! command -v cron >/dev/null 2>&1 && ! systemctl list-unit-files cron.service &>/dev/null; then
        apt-get install -y -qq cron || warn "Не удалось установить cron"
    fi
    systemctl enable --now cron 2>/dev/null || systemctl enable --now crond 2>/dev/null || true

    cron_line="* * * * * www-data cd ${APP_DIR} && ${php_bin} artisan schedule:run >> /dev/null 2>&1"
    echo "$cron_line" > "$cron_file"
    chmod 644 "$cron_file"

    log "Планировщик задач настроен (автобэкап, sitemap и т.д.)"
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

detect_previous_domain() {
    local from_env=""
    if [[ -f "$APP_DIR/.env" ]]; then
        from_env="$(normalize_domain "$(read_env_value APP_URL "$APP_DIR/.env")")"
    fi
    if [[ -n "$from_env" ]]; then
        echo "$from_env"
        return
    fi

    local snippet base
    shopt -s nullglob
    for snippet in /etc/caddy/sites/*.caddy; do
        [[ -f "$snippet" ]] || continue
        if grep -qF "${APP_DIR}/public" "$snippet" 2>/dev/null; then
            base="$(basename "$snippet" .caddy)"
            echo "$base"
            return
        fi
    done
    shopt -u nullglob
}

cleanup_stale_caddy_configs() {
    local keep_domain="$1"
    local caddy_main="${2:-/etc/caddy/Caddyfile}"
    local caddy_sites_dir="${3:-/etc/caddy/sites}"
    local snippet base

    shopt -s nullglob
    for snippet in "${caddy_sites_dir}"/*.caddy; do
        [[ -f "$snippet" ]] || continue
        base="$(basename "$snippet" .caddy)"
        if [[ "$base" == "$keep_domain" ]]; then
            continue
        fi
        if grep -qF "${APP_DIR}/public" "$snippet" 2>/dev/null; then
            log "Удаляю старый конфиг домена: ${base}"
            rm -f "$snippet"
            if [[ -f "$caddy_main" ]]; then
                sed -i "/sites\/${base}\\.caddy/d" "$caddy_main" || true
            fi
        fi
    done
    shopt -u nullglob
}

write_caddy_vhost() {
    local domain="$1"
    local snippet="$2"

    cat > "$snippet" <<EOF
${domain} {
    root * ${APP_DIR}/public
    encode gzip zstd

    @blocked {
        path /.env* /.git* /vendor/*
    }
    respond @blocked 404

    php_fastcgi unix//run/php/php${PHP_VERSION}-fpm-lordserial.sock {
        resolve_root_symlink
    }

    try_files {path} {path}/ /index.php?{query}
    file_server
}

# www → основной домен без www
www.${domain} {
    redir https://${domain}{uri} permanent
}
EOF
}

print_success() {
    local backup_note=""
    if [[ -n "${RESTORED_BACKUP:-}" ]]; then
        backup_note="
  Данные восстановлены из бэкапа:
    ${RESTORED_BACKUP}
"
    fi

    cat <<EOF

════════════════════════════════════════════════════════════
  Готово! Сайт установлен.
════════════════════════════════════════════════════════════

  Откройте в браузере:
    Сайт:     https://${DOMAIN}
    Админка:  https://${DOMAIN}/admin/
${backup_note}
  Важные пароли сохранены здесь:
    ${CREDENTIALS_FILE}

  Откройте этот файл командой:
    cat ${CREDENTIALS_FILE}

────────────────────────────────────────────────────────────
  Что сделать дальше (по порядку):
────────────────────────────────────────────────────────────
  1. В панели регистратора домена укажите A-запись
     домена ${DOMAIN} на IP этого VPS.
  2. Подождите 5–30 минут (DNS) и откройте сайт по HTTPS.
     SSL-сертификат Caddy получит сам.
  3. Войдите в админку и задайте настройки сайта.
  4. В разделе «Бэкапы» включите автобэкап (S3 / FTP / SFTP).

────────────────────────────────────────────────────────────
  Если что-то не открывается:
────────────────────────────────────────────────────────────
  • DNS ещё не обновился — подождите и проверьте:
      ping ${DOMAIN}
  • Логи сайта:
      tail -f ${APP_DIR}/storage/logs/laravel.log
  • Статус сервисов:
      systemctl status caddy lordserial-queue mariadb

────────────────────────────────────────────────────────────
  Полезные команды:
────────────────────────────────────────────────────────────
  sudo bash update.sh
      обновить сайт после git push

  sudo DOMAIN=новый.ru SKIP_BUILD=1 bash install.sh
      сменить домен

  sudo BACKUP_FILE=/root/backup.zip bash install.sh
      переустановить с восстановлением из бэкапа

════════════════════════════════════════════════════════════
EOF
}

# ─── Справка ─────────────────────────────────────────────────────────────────

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    print_help
    exit 0
fi

# ─── Старт ───────────────────────────────────────────────────────────────────

require_root
check_ubuntu

export DEBIAN_FRONTEND=noninteractive
RESTORED_BACKUP=""

fix_nodesource_apt_conflict || true

DOMAIN="$(normalize_domain "$DOMAIN")"
print_banner

# ─── Шаг 1. Код сайта ────────────────────────────────────────────────────────

step "Подготовка кода сайта"

if [[ -f "$SCRIPT_DIR/artisan" ]]; then
    APP_DIR="$SCRIPT_DIR"
    log "Использую уже скачанный проект: ${APP_DIR}"
elif [[ ! -f "$APP_DIR/artisan" ]]; then
    log "Скачиваю сайт из GitHub → ${APP_DIR}..."
    mkdir -p "$(dirname "$APP_DIR")"
    git clone --depth 1 "$REPO_URL" "$APP_DIR"
else
    log "Проект уже есть в ${APP_DIR}"
fi

[[ -f "$APP_DIR/artisan" ]] || die "В папке ${APP_DIR} нет сайта LordSerial.
Скачайте репозиторий или укажите правильный APP_DIR."

ensure_git_safe

# ─── Шаг 2. Пароли ───────────────────────────────────────────────────────────

step "Подготовка паролей и токена админки"

if [[ -f "$APP_DIR/.env" ]]; then
    if [[ -z "$DB_PASSWORD" ]]; then
        existing_db_password="$(read_env_value DB_PASSWORD "$APP_DIR/.env")"
        [[ -n "$existing_db_password" ]] && DB_PASSWORD="$existing_db_password"
    fi
    if [[ -z "$ADMIN_TOKEN" ]]; then
        existing_admin_token="$(read_env_value ADMIN_TOKEN "$APP_DIR/.env")"
        [[ -n "$existing_admin_token" ]] && ADMIN_TOKEN="$existing_admin_token"
    fi
fi

if [[ -z "$DB_PASSWORD" ]]; then
    DB_PASSWORD="$(rand_secret)"
    log "Сгенерирован пароль базы данных"
else
    log "Используется уже заданный пароль базы данных"
fi
if [[ -z "$ADMIN_TOKEN" ]]; then
    ADMIN_TOKEN="$(rand_secret)"
    log "Сгенерирован токен входа в админку"
else
    log "Используется уже заданный токен админки"
fi

PREVIOUS_DOMAIN="$(detect_previous_domain || true)"
if [[ -n "$PREVIOUS_DOMAIN" && "$PREVIOUS_DOMAIN" != "$DOMAIN" ]]; then
    warn "Меняю домен: ${PREVIOUS_DOMAIN} → ${DOMAIN}"
fi

# ─── Шаг 3. Системные пакеты ─────────────────────────────────────────────────

step "Установка системных программ (PHP, база, утилиты)"

log "Обновляю список пакетов..."
apt_get_update
log "Устанавливаю необходимые пакеты (это займёт пару минут)..."
apt-get install -y -qq \
    ca-certificates curl gnupg lsb-release software-properties-common \
    git unzip acl cron \
    mariadb-server mariadb-client \
    "php${PHP_VERSION}-cli" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-common" \
    "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-intl" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-readline" \
    "php${PHP_VERSION}-tokenizer" "php${PHP_VERSION}-fileinfo" \
    ufw
log "Системные пакеты установлены"

# ─── Шаг 4. Веб-сервер Caddy ─────────────────────────────────────────────────

step "Веб-сервер Caddy (сайт + HTTPS)"

if ! command -v caddy &>/dev/null; then
    log "Устанавливаю Caddy..."
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
        | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
        | tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null
    apt_get_update
    apt-get install -y -qq caddy
else
    log "Caddy уже установлен"
fi

# ─── Шаг 5. Node.js и Composer ───────────────────────────────────────────────

step "Инструменты сборки (Node.js и Composer)"

install_nodejs

if ! command -v composer &>/dev/null; then
    log "Устанавливаю Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
else
    log "Composer уже установлен"
fi

# ─── Шаг 6. База данных ──────────────────────────────────────────────────────

step "База данных MariaDB"

log "Создаю базу «${DB_NAME}» и пользователя «${DB_USER}»..."
systemctl enable --now mariadb

mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
log "База данных готова"

# ─── Шаг 7. PHP-FPM ──────────────────────────────────────────────────────────

step "Настройка PHP"

log "Настраиваю PHP-FPM для сайта..."
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
log "PHP готов"

# ─── Шаг 8. Настройки сайта (.env) ───────────────────────────────────────────

step "Настройки сайта (.env) и PHP-зависимости"

log "Записываю настройки в .env..."
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

log "Ставлю PHP-библиотеки сайта (composer)..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
    log "Сгенерирован секретный ключ приложения"
fi

# ─── Шаг 9. База + бэкап + сборка ────────────────────────────────────────────

step "База данных сайта, бэкап и сборка интерфейса"

if [[ "$SKIP_BUILD" != "1" ]]; then
    log "Создаю таблицы базы данных..."
    php artisan migrate --force

    restore_backup_if_present

    log "Подключаю папку загрузок..."
    php artisan storage:link 2>/dev/null || true

    log "Собираю оформление сайта (тема)..."
    npm ci
    npm run build:theme

    log "Собираю админ-панель..."
    pushd admin-ui >/dev/null
    npm ci
    npm run build
    popd >/dev/null

    log "Кэширую настройки для скорости..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    warn "Режим SKIP_BUILD=1 — полная пересборка пропущена"
    if [[ -n "$BACKUP_FILE" && -f "$APP_DIR/artisan" ]]; then
        restore_backup_if_present
    fi
fi

# ─── Шаг 10. Права и sitemap ─────────────────────────────────────────────────

step "Права доступа и карта сайта"

log "Настраиваю права на файлы..."
set_app_permissions
generate_sitemap_if_possible

# ─── Шаг 11. Домен в Caddy ───────────────────────────────────────────────────

step "Подключение домена ${DOMAIN}"

log "Пишу конфиг веб-сервера для ${DOMAIN}..."
CADDY_SITES_DIR="/etc/caddy/sites"
CADDY_SNIPPET="${CADDY_SITES_DIR}/${DOMAIN}.caddy"
CADDY_MAIN="/etc/caddy/Caddyfile"
mkdir -p "$CADDY_SITES_DIR"

cleanup_stale_caddy_configs "$DOMAIN" "$CADDY_MAIN" "$CADDY_SITES_DIR"
write_caddy_vhost "$DOMAIN" "$CADDY_SNIPPET"

if [[ ! -f "$CADDY_MAIN" ]] || [[ ! -s "$CADDY_MAIN" ]]; then
    cat > "$CADDY_MAIN" <<EOF
{
    email admin@${DOMAIN}
}

import sites/*.caddy
EOF
elif ! grep -qF "${DOMAIN}" "$CADDY_MAIN" && ! grep -qF "sites/${DOMAIN}.caddy" "$CADDY_MAIN"; then
    cp "$CADDY_MAIN" "${CADDY_MAIN}.bak.$(date +%Y%m%d%H%M%S)"
    printf '\nimport sites/%s.caddy\n' "$DOMAIN" >> "$CADDY_MAIN"
fi

if ! caddy validate --config "$CADDY_MAIN" 2>/tmp/lordserial-caddy-validate.err; then
    warn "Ошибка в конфиге Caddy:"
    cat /tmp/lordserial-caddy-validate.err >&2
    die "Исправьте /etc/caddy/Caddyfile и выполните:
  systemctl restart caddy"
fi

systemctl enable caddy
systemctl restart caddy
sleep 2

if ! curl -fsS -o /dev/null --max-time 10 -H "Host: ${DOMAIN}" http://127.0.0.1/up; then
    warn "Локальная проверка сайта не прошла.
Смотрите логи:
  journalctl -u caddy -u php${PHP_VERSION}-fpm -n 50"
else
    log "Сайт отвечает локально — веб-сервер работает"
fi

if [[ -n "$PREVIOUS_DOMAIN" && "$PREVIOUS_DOMAIN" != "$DOMAIN" ]]; then
    log "Обновляю кэш после смены домена..."
    php artisan config:clear
    php artisan config:cache
    php artisan route:cache
    warn "Проверьте DNS для ${DOMAIN} и обновите sitemap в админке"
fi

# ─── Шаг 12. Очередь, cron, фаервол ──────────────────────────────────────────

step "Фоновые задачи и безопасность"

setup_queue_and_scheduler

if command -v ufw &>/dev/null; then
    log "Открываю порты SSH (22), HTTP (80) и HTTPS (443)..."
    ufw allow 22/tcp comment 'SSH' || true
    ufw allow 80/tcp comment 'HTTP' || true
    ufw allow 443/tcp comment 'HTTPS' || true
    ufw --force enable || true
    ufw reload || true
fi

# ─── Сохранение учётных данных ───────────────────────────────────────────────

cat > "$CREDENTIALS_FILE" <<EOF
LordSerial — данные для входа
Создано: $(date -Iseconds)

Сайт:        https://${DOMAIN}
Админка:     https://${DOMAIN}/admin/
Папка:       ${APP_DIR}

База данных:
  Хост:      127.0.0.1
  База:      ${DB_NAME}
  Логин:     ${DB_USER}
  Пароль:    ${DB_PASSWORD}

Токен админки (ADMIN_TOKEN):
  ${ADMIN_TOKEN}

Как проверить сайт:
  curl -sI https://${DOMAIN}/up

Логи:
  tail -f ${APP_DIR}/storage/logs/laravel.log

Очередь:
  journalctl -u lordserial-queue -f
EOF
chmod 600 "$CREDENTIALS_FILE"

print_success
