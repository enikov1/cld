#!/usr/bin/env bash
#
# ═══════════════════════════════════════════════════════════════════════════
#  Установка движка Omro на VPS (Ubuntu)
# ═══════════════════════════════════════════════════════════════════════════
#
# Скрипт ставит PHP-FPM, MariaDB, Node.js, Composer, cron, скачивает движок
# из git, создаёт БД, применяет миграции, собирает интерфейсы, при наличии
# ZIP-бэкапа восстанавливает данные.
#
# ВЕБ-СЕРВЕР НЕ СТАВИТСЯ: движку всё равно, что стоит перед ним
# (nginx / Apache / Caddy / OpenLiteSpeed). Требование одно — docroot = папка
# public/ + PHP-FPM-сокет конкретного сайта. Панель управления сервером или
# администратор настраивает веб-сервер после установки.
#
# Установка выполняется одной командой без параметров — см. README.md.
# Справка по параметрам (для автоматизации из панелей управления):
#   sudo bash install.sh --help
#
set -euo pipefail

# ─── Параметры (можно задать перед запуском) ─────────────────────────────────

DOMAIN="${DOMAIN:-example.com}"
REPO_URL="${REPO_URL:-https://github.com/enikov1/cld.git}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
_USER_APP_DIR="${APP_DIR:-}"
_USER_DB_NAME="${DB_NAME:-}"
_USER_DB_USER="${DB_USER:-}"
_USER_SITE_ID="${SITE_ID:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
ADMIN_TOKEN="${ADMIN_TOKEN:-}"
SKIP_BUILD="${SKIP_BUILD:-0}"
BACKUP_FILE="${BACKUP_FILE:-}"
SKIP_BACKUP_RESTORE="${SKIP_BACKUP_RESTORE:-0}"
PANEL_MODE="${PANEL_MODE:-0}"
PHP_VERSION="8.3"

APP_DIR=""
DB_NAME=""
DB_USER=""
SITE_ID=""
QUEUE_UNIT_NAME=""
QUEUE_UNIT_PATH=""
CRON_FILE=""
PHP_POOL_NAME=""
PHP_POOL_FILE=""
PHP_FPM_SOCK=""
CREDENTIALS_FILE=""

STEP_CURRENT=0
if [[ "$PANEL_MODE" == "1" ]]; then
    STEP_TOTAL=8
else
    STEP_TOTAL=10
fi

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

Установка движка Omro на Ubuntu VPS
════════════════════════════════════

Что делает скрипт автоматически:
  • ставит PHP-FPM, базу данных MariaDB, Node.js, Composer, cron
  • скачивает движок (если ещё не скачан)
  • создаёт БД, ставит зависимости, применяет миграции, собирает интерфейсы
  • при наличии ZIP-бэкапа — восстанавливает данные
  • на одном сервере можно поставить несколько сайтов

Чего скрипт НЕ делает:
  • не ставит веб-сервер (nginx / Apache / Caddy / …) — движку всё равно,
    какой перед ним стоит. Требование: docroot → папка public/ + PHP-FPM.
    Панель управления сервером или администратор настраивает веб-сервер
    после установки (скрипт печатает docroot и PHP-FPM-сокет сайта).

Минимальный запуск:
  sudo bash install.sh

Все параметры (домен, папка, БД, токен, бэкап) передаются переменными
окружения — это позволяет автоматизировать установку из панелей
управления сервером. Полный список ниже.

Со своим доменом:
  sudo DOMAIN=example.com bash install.sh

Второй (и следующие) сайты на том же сервере:
  sudo DOMAIN=site2.ru bash install.sh
  sudo DOMAIN=site3.ru APP_DIR=/var/www/site3.ru bash install.sh

С восстановлением из бэкапа:
  sudo DOMAIN=example.com BACKUP_FILE=/root/backup.zip bash install.sh

Полезные параметры (перед командой):
  DOMAIN=сайт.ru              домен сайта (без https://)
  APP_DIR=/var/www/...        папка установки (по умолчанию /var/www/<домен>)
  SITE_ID=my_site             короткий id инстанса (БД, PHP-FPM, очередь)
  DB_NAME / DB_USER           имя БД и пользователя (иначе из SITE_ID)
  DB_PASSWORD=секрет          пароль БД (иначе сгенерируется)
  ADMIN_TOKEN=секрет          токен входа в админку
  BACKUP_FILE=/path/file.zip  восстановить этот бэкап
  SKIP_BACKUP_RESTORE=1       не восстанавливать бэкап
  SKIP_BUILD=1                только смена домена / без пересборки
  PANEL_MODE=1                установка в папку сайта, уже управляемого панелью
                              (HostPanel и др.): пул PHP-FPM, база и веб-сервер
                              не создаются — панель передаёт APP_DIR, DOMAIN,
                              DB_NAME, DB_USER, DB_PASSWORD, ADMIN_TOKEN и сама
                              настраивает vhost (docroot → public/)

Смена домена на уже установленном сервере:
  cd /var/www/example.com
  sudo DOMAIN=новый.ru SKIP_BUILD=1 bash install.sh

После установки пароли и токены лежат в:
  /root/omro-<SITE_ID>-credentials.txt

EOF
}

print_banner() {
    echo ""
    echo "════════════════════════════════════════════════════════════"
    echo "  Установка движка Omro"
    echo "════════════════════════════════════════════════════════════"
    echo ""
    echo "  Домен:     ${DOMAIN}"
    echo "  Инстанс:   ${SITE_ID}"
    echo "  Папка:     ${APP_DIR}"
    echo "  База:      ${DB_NAME}"
    echo "  Очередь:   ${QUEUE_UNIT_NAME}"
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
    if [[ "$PANEL_MODE" == "1" ]]; then
        echo "  Режим:     PANEL_MODE — сайт под управлением панели (пул и база не создаются)"
    fi
    echo ""
    echo "  Это займёт несколько минут. Не закрывайте окно."
    echo "════════════════════════════════════════════════════════════"
}

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
    if apt-get update -qq 2>/tmp/omro-apt-update.err; then
        return 0
    fi

    if grep -q 'Conflicting values set for option Signed-By' /tmp/omro-apt-update.err; then
        fix_nodesource_apt_conflict 1
        apt-get update -qq
        return 0
    fi

    cat /tmp/omro-apt-update.err >&2
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

Проверьте, что архив создан в админке Omro (раздел «Бэкапы»).
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

setup_queue_and_scheduler() {
    local php_bin queue_log cron_line

    php_bin="$(resolve_php_bin)"
    queue_log="${APP_DIR}/storage/logs/queue.log"

    log "Включаю фоновые задачи (уведомления, автобэкапы, синхронизации)..."
    mkdir -p "${APP_DIR}/storage/logs"
    touch "$queue_log"
    chown www-data:www-data "$queue_log"
    chmod 664 "$queue_log"

    cat > "$QUEUE_UNIT_PATH" <<EOF
[Unit]
Description=Omro Queue Worker (${DOMAIN} / ${SITE_ID})
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
        log "Фоновая очередь работает (${QUEUE_UNIT_NAME})"
    else
        warn "Очередь не запустилась. Диагностика:
  journalctl -u ${QUEUE_UNIT_NAME} -n 50"
        systemctl status "$QUEUE_UNIT_NAME" --no-pager || true
    fi

    if ! command -v cron >/dev/null 2>&1 && ! systemctl list-unit-files cron.service &>/dev/null; then
        apt-get install -y -qq cron || warn "Не удалось установить cron"
    fi
    systemctl enable --now cron 2>/dev/null || systemctl enable --now crond 2>/dev/null || true

    cron_line="* * * * * www-data cd ${APP_DIR} && ${php_bin} artisan schedule:run >> /dev/null 2>&1"
    echo "$cron_line" > "$CRON_FILE"
    chmod 644 "$CRON_FILE"

    log "Планировщик задач настроен (${CRON_FILE})"
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

# Короткий id инстанса: example.com → example_com (для имён БД, unit, pool)
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

# Имена PHP-FPM / queue / cron / credentials для этого инстанса.
# Совместимость: если сайт уже обслуживается старыми lordserial-* именами — оставляем их.
resolve_service_names() {
    local use_legacy=0
    local legacy_unit="/etc/systemd/system/lordserial-queue.service"

    if unit_points_to_app_dir "$legacy_unit"; then
        use_legacy=1
    elif [[ "$SITE_ID" == "lordserial" && ! -f "/etc/systemd/system/omro-queue-lordserial.service" ]]; then
        use_legacy=1
    fi

    if [[ "$use_legacy" == "1" ]]; then
        QUEUE_UNIT_NAME="lordserial-queue"
        CRON_FILE="/etc/cron.d/lordserial-scheduler"
        PHP_POOL_NAME="lordserial"
        CREDENTIALS_FILE="/root/lordserialov-credentials.txt"
    else
        QUEUE_UNIT_NAME="omro-queue-${SITE_ID}"
        CRON_FILE="/etc/cron.d/omro-scheduler-${SITE_ID}"
        PHP_POOL_NAME="omro-${SITE_ID}"
        CREDENTIALS_FILE="/root/omro-${SITE_ID}-credentials.txt"
    fi

    QUEUE_UNIT_PATH="/etc/systemd/system/${QUEUE_UNIT_NAME}.service"
    PHP_POOL_FILE="/etc/php/${PHP_VERSION}/fpm/pool.d/${PHP_POOL_NAME}.conf"
    PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm-${PHP_POOL_NAME}.sock"
}

resolve_site_instance() {
    if [[ -n "$_USER_SITE_ID" ]]; then
        SITE_ID="$(derive_site_id "$_USER_SITE_ID")"
    else
        SITE_ID="$(derive_site_id "$DOMAIN")"
    fi

    if [[ -n "$_USER_APP_DIR" ]]; then
        APP_DIR="$_USER_APP_DIR"
    elif [[ -f "$SCRIPT_DIR/artisan" ]]; then
        APP_DIR="$SCRIPT_DIR"
    else
        APP_DIR="/var/www/${DOMAIN}"
    fi

    if [[ -n "$_USER_DB_NAME" ]]; then
        DB_NAME="$_USER_DB_NAME"
    elif [[ -f "$APP_DIR/.env" ]]; then
        DB_NAME="$(read_env_value DB_DATABASE "$APP_DIR/.env")"
    fi
    if [[ -z "$DB_NAME" ]]; then
        DB_NAME="omro_${SITE_ID}"
    fi

    if [[ -n "$_USER_DB_USER" ]]; then
        DB_USER="$_USER_DB_USER"
    elif [[ -f "$APP_DIR/.env" ]]; then
        DB_USER="$(read_env_value DB_USERNAME "$APP_DIR/.env")"
    fi
    if [[ -z "$DB_USER" ]]; then
        DB_USER="omro_${SITE_ID:0:24}"
    fi

    resolve_service_names
}

detect_previous_domain() {
    if [[ -f "$APP_DIR/.env" ]]; then
        normalize_domain "$(read_env_value APP_URL "$APP_DIR/.env")"
    fi
}

print_success() {
    local backup_note=""
    if [[ -n "${RESTORED_BACKUP:-}" ]]; then
        backup_note="
  Данные восстановлены из бэкапа:
    ${RESTORED_BACKUP}
"
    fi

    local web_hint=""
    if [[ "$PANEL_MODE" == "1" ]]; then
        web_hint="  2. Подключите сайт в панели управления: docroot = ${APP_DIR}/public,
     PHP ${PHP_VERSION}-FPM (пул панели), затем выпустите SSL."
    else
        web_hint="  2. Настройте веб-сервер: docroot = ${APP_DIR}/public,
     PHP-FPM-сокет ${PHP_FPM_SOCK}
     (это делает панель управления сервером или администратор)."
    fi

    cat <<EOF

════════════════════════════════════════════════════════════
  Готово! Движок Omro установлен.
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
${web_hint}
  3. Подождите DNS (5–30 минут) и откройте сайт по HTTPS.
  4. Войдите в админку и задайте настройки сайта.
  5. В разделе «Бэкапы» включите автобэкап (S3 / FTP / SFTP).

────────────────────────────────────────────────────────────
  Если что-то не открывается:
────────────────────────────────────────────────────────────
  • DNS ещё не обновился — подождите и проверьте:
      ping ${DOMAIN}
  • Логи сайта:
      tail -f ${APP_DIR}/storage/logs/laravel.log
  • Статус сервисов:
      systemctl status ${QUEUE_UNIT_NAME} mariadb

────────────────────────────────────────────────────────────
  Полезные команды:
────────────────────────────────────────────────────────────
  cd ${APP_DIR} && sudo bash update.sh
      обновить этот сайт

  sudo DOMAIN=другой.ru bash install.sh
      поставить ещё один сайт на этот же сервер

  sudo DOMAIN=новый.ru SKIP_BUILD=1 bash install.sh
      сменить домен (из папки сайта)

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
resolve_site_instance
print_banner

# ─── Шаг 1. Код движка ───────────────────────────────────────────────────────

step "Подготовка кода движка"

if [[ -f "$SCRIPT_DIR/artisan" ]]; then
    if [[ -z "$_USER_APP_DIR" ]]; then
        APP_DIR="$SCRIPT_DIR"
        resolve_service_names
    fi
    log "Использую уже скачанный проект: ${APP_DIR}"
elif [[ ! -f "$APP_DIR/artisan" ]]; then
    log "Скачиваю движок из GitHub → ${APP_DIR}..."
    mkdir -p "$(dirname "$APP_DIR")"
    git clone --depth 1 "$REPO_URL" "$APP_DIR"
else
    log "Проект уже есть в ${APP_DIR}"
fi

[[ -f "$APP_DIR/artisan" ]] || die "В папке ${APP_DIR} нет движка Omro.
Скачайте репозиторий или укажите правильный APP_DIR.

Для второго сайта на этом сервере:
  sudo DOMAIN=другой-сайт.ru bash install.sh"

if [[ -z "$_USER_DB_NAME" && -f "$APP_DIR/.env" ]]; then
    from_env="$(read_env_value DB_DATABASE "$APP_DIR/.env")"
    [[ -n "$from_env" ]] && DB_NAME="$from_env"
fi
if [[ -z "$_USER_DB_USER" && -f "$APP_DIR/.env" ]]; then
    from_env="$(read_env_value DB_USERNAME "$APP_DIR/.env")"
    [[ -n "$from_env" ]] && DB_USER="$from_env"
fi
resolve_service_names

ensure_git_safe

# ─── Шаг 2. Пароли ───────────────────────────────────────────────────────────

step "Подготовка паролей и токена админки"

if [[ -f "$APP_DIR/.env" ]]; then
    if [[ -z "$DB_PASSWORD" ]]; then
        from_env="$(read_env_value DB_PASSWORD "$APP_DIR/.env")"
        [[ -n "$from_env" ]] && DB_PASSWORD="$from_env"
    fi
    if [[ -z "$ADMIN_TOKEN" ]]; then
        from_env="$(read_env_value ADMIN_TOKEN "$APP_DIR/.env")"
        [[ -n "$from_env" ]] && ADMIN_TOKEN="$from_env"
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

# ─── Шаг 4. Node.js и Composer ───────────────────────────────────────────────

step "Инструменты сборки (Node.js и Composer)"

install_nodejs

if ! command -v composer &>/dev/null; then
    log "Устанавливаю Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
else
    log "Composer уже установлен"
fi

# ─── Шаг 5. База данных ──────────────────────────────────────────────────────

if [[ "$PANEL_MODE" == "1" ]]; then
    # База уже создана панелью управления — ждём её креды.
    if [[ -z "$DB_NAME" || -z "$DB_USER" || -z "$DB_PASSWORD" ]]; then
        die "PANEL_MODE=1: панель должна передать DB_NAME, DB_USER и DB_PASSWORD
(у панели уже есть своя база данных для этого сайта)."
    fi
    log "База данных панели: ${DB_NAME} (создание пропущено)"
else
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
fi

# ─── Шаг 6. PHP-FPM ──────────────────────────────────────────────────────────

if [[ "$PANEL_MODE" != "1" ]]; then
    step "Настройка PHP"

    log "Настраиваю PHP-FPM для сайта (${PHP_POOL_NAME})..."
    sed -i 's/^;*cgi.fix_pathinfo=.*/cgi.fix_pathinfo=0/' "/etc/php/${PHP_VERSION}/fpm/php.ini" || true

    cat > "$PHP_POOL_FILE" <<EOF
[${PHP_POOL_NAME}]
user = www-data
group = www-data
listen = ${PHP_FPM_SOCK}
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
    log "PHP готов (сокет: ${PHP_FPM_SOCK})"
else
    log "PHP-FPM пул не создаётся — сайт под управлением панели (PANEL_MODE)"
fi

# ─── Шаг 7. .env + composer ──────────────────────────────────────────────────

step "Настройки сайта (.env) и PHP-зависимости"

log "Записываю настройки в .env..."
cd "$APP_DIR"

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

env_set APP_NAME        "Omro"                    .env
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

# ─── Шаг 8. Миграции + бэкап + сборка ────────────────────────────────────────

step "Миграции, бэкап и сборка интерфейсов"

if [[ "$SKIP_BUILD" != "1" ]]; then
    log "Создаю таблицы базы данных..."
    php artisan migrate --force

    restore_backup_if_present

    log "Подключаю папку загрузок..."
    ensure_storage_link

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

# ─── Шаг 9. Права, sitemap, веб-сервер ───────────────────────────────────────

step "Права доступа, карта сайта, веб-сервер"

log "Настраиваю права на файлы..."
set_app_permissions
generate_sitemap_if_possible

if [[ "$PANEL_MODE" == "1" ]]; then
    log "Веб-сервер настраивается панелью управления: docroot = ${APP_DIR}/public, PHP ${PHP_VERSION}-FPM"
elif command -v caddy &>/dev/null; then
    log "Обнаружен Caddy — настройте vhost: docroot = ${APP_DIR}/public, PHP-FPM-сокет ${PHP_FPM_SOCK}"
elif command -v nginx &>/dev/null; then
    log "Обнаружен nginx — настройте vhost: docroot = ${APP_DIR}/public, PHP-FPM-сокет ${PHP_FPM_SOCK}"
elif command -v apache2 &>/dev/null; then
    log "Обнаружен Apache — настройте vhost: docroot = ${APP_DIR}/public, PHP-FPM-сокет ${PHP_FPM_SOCK}"
else
    log "Веб-сервер не обнаружен — панель управления или администратор настроит его после установки.
  Требуется: docroot = ${APP_DIR}/public, PHP-FPM-сокет ${PHP_FPM_SOCK}"
fi

# ─── Шаг 10. Очередь, cron, фаервол ──────────────────────────────────────────

step "Фоновые задачи и безопасность"

setup_queue_and_scheduler

if [[ "$PANEL_MODE" != "1" ]] && command -v ufw &>/dev/null; then
    log "Открываю порты SSH (22), HTTP (80) и HTTPS (443)..."
    ufw allow 22/tcp comment 'SSH' || true
    ufw allow 80/tcp comment 'HTTP' || true
    ufw allow 443/tcp comment 'HTTPS' || true
    ufw --force enable || true
    ufw reload || true
fi

# ─── Сохранение учётных данных ───────────────────────────────────────────────

cat > "$CREDENTIALS_FILE" <<EOF
Omro — данные для входа
Создано: $(date -Iseconds)

Сайт:        https://${DOMAIN}
Админка:     https://${DOMAIN}/admin/
Папка:       ${APP_DIR}
Инстанс:     ${SITE_ID}

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
  journalctl -u ${QUEUE_UNIT_NAME} -f

Планировщик:
  cat ${CRON_FILE}
EOF
chmod 600 "$CREDENTIALS_FILE"

print_success

