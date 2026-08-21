# LordSerial

Сайт на **Laravel 12** (PHP 8.2+) с шаблонной системой (`resources/tpl`) и React-админкой (`admin-ui`).

---

## Установка на сервер (VPS)

Скрипт `install.sh` сам ставит PHP, MariaDB, Caddy (HTTPS), Node.js, клонирует код, настраивает домен, очередь и cron.

**Нужно заранее:**
1. VPS с **Ubuntu 24.04**
2. Домен с A-записью на IP сервера
3. SSH от root (или `sudo`)

### Быстрая установка

```bash
# Чистый сервер, домен по умолчанию
curl -fsSL https://raw.githubusercontent.com/enikov1/cld/main/install.sh | sudo bash

# Со своим доменом
curl -fsSL https://raw.githubusercontent.com/enikov1/cld/main/install.sh \
  | sudo DOMAIN=ваш-сайт.ru bash
```

Если код уже скачан:

```bash
cd /var/www/ваш-сайт.ru
sudo DOMAIN=ваш-сайт.ru bash install.sh
```

После установки пароли и токен админки сохраняются в:
- `/root/lordserialov-credentials.txt` — дефолтный сайт
- `/root/lordserial-<SITE_ID>-credentials.txt` — остальные сайты

Откройте: `https://ваш-сайт.ru` и админку `https://ваш-сайт.ru/admin/`

Справка по параметрам: `sudo bash install.sh --help`

### Несколько сайтов на одном сервере

У каждого сайта — своя папка, БД, PHP-FPM pool, очередь и cron:

```bash
sudo DOMAIN=site1.ru bash install.sh
sudo DOMAIN=site2.ru bash install.sh
```

По умолчанию:
| | Пример для `site2.ru` |
|--|--|
| Папка | `/var/www/site2.ru` |
| БД / пользователь | `ls_site2_ru` |
| Очередь | `lordserial-queue-site2_ru` |
| Cron | `/etc/cron.d/lordserial-scheduler-site2_ru` |

При необходимости явно: `APP_DIR=...` `SITE_ID=...` `DB_NAME=...` `DB_USER=...`

### Обновление сайта

```bash
# Из папки сайта
cd /var/www/ваш-сайт.ru
sudo bash update.sh

# Или одной командой
curl -fsSL https://raw.githubusercontent.com/enikov1/cld/main/update.sh \
  | sudo APP_DIR=/var/www/ваш-сайт.ru bash
```

Несколько сайтов — обновляйте каждый отдельно:

```bash
sudo APP_DIR=/var/www/site1.ru bash update.sh
sudo APP_DIR=/var/www/site2.ru bash update.sh
```

Полезные флаги: `SKIP_BUILD=1`, `SKIP_MIGRATE=1`, `SKIP_SERVICES=1`, `GIT_BRANCH=main`

### Смена домена

```bash
cd /var/www/ваш-сайт.ru
sudo DOMAIN=новый-домен.ru SKIP_BUILD=1 bash install.sh
```

### Перенос со старого сервера (из бэкапа)

1. Скачайте ZIP из админки → «Бэкапы»
2. Загрузите на новый сервер, например в `/root/backup.zip`
3. Установите с восстановлением:

```bash
sudo DOMAIN=ваш-сайт.ru BACKUP_FILE=/root/backup.zip bash install.sh
```

Или положите ZIP в `storage/app/backups/` — скрипт возьмёт самый новый.

### Полезные команды на сервере

```bash
# Логи
tail -f /var/www/ваш-сайт.ru/storage/logs/laravel.log

# Очередь и сервисы
systemctl status caddy lordserial-queue
# для второго сайта:
systemctl status lordserial-queue-site2_ru

# Проверка
curl -sI https://ваш-сайт.ru/up
```

---

## Требования (локальная разработка)

| Компонент | Версия |
|-----------|--------|
| PHP | 8.2+ (расширения: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `gd` или `imagick`) |
| Composer | 2.x |
| Node.js | 18+ |
| MySQL / MariaDB | 8.0+ / 10.6+ |

---

## Локальная установка

```bash
# 1. Зависимости PHP
composer install

# 2. Окружение
cp .env.example .env
php artisan key:generate

# 3. База данных — создайте БД и укажите параметры в .env, затем:
php artisan migrate

# 4. Симлинк для загрузок (постеры, брендинг)
php artisan storage:link

# 5. Зависимости и сборка фронтенда
npm install
npm run build:theme
cd admin-ui && npm install && npm run build && cd ..
```

---

## Режим разработки

### Вариант A: OSPanel (рекомендуется для этого проекта)

1. Укажите **корень домена** на папку `public/`:
   ```
   C:\OSPanel6\home\lordserial.net\site\public
   ```
2. В `.env` задайте:
   ```env
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://lordserial.net
   DB_HOST=127.0.1.27
   DB_DATABASE=lordserial123
   DB_USERNAME=root
   DB_PASSWORD=
   ADMIN_TOKEN=ваш-секретный-токен
   ```
3. Перезапустите домен в OSPanel.

При `APP_DEBUG=true` сайт отдаёт **исходные** файлы темы (`site.js`, `site.css`). Минифицированные `.min.*` версии не используются.

#### После изменений в теме

Если нужно проверить поведение как на продакшене:

```bash
npm run build:theme
```

#### Админка в dev-режиме

Собранная админка доступна по адресу `/admin/` (или по пути из `ADMIN_PATH` / настроек сайта).

Для разработки React-интерфейса:

```bash
# Терминал 1 — сайт (OSPanel или artisan serve)
# Терминал 2 — Vite dev-server админки
cd admin-ui
npm run dev
```

По умолчанию Vite проксирует API на `http://127.0.0.1:8085`. Если OSPanel слушает другой порт:

```bash
VITE_BACKEND_URL=http://lordserial.net admin-ui$ npm run dev
```

### Вариант B: встроенный dev-сервер Laravel

```bash
composer dev
```

Команда параллельно запускает `php artisan serve`, очередь, логи (`pail`) и Vite. Подходит, если не используете OSPanel.

Или по отдельности:

```bash
php artisan serve
php artisan queue:listen
npm run dev          # Vite для resources/js (если используется)
```

---

## Продакшен (ручная настройка)

> Для Ubuntu VPS предпочтителен `install.sh` (см. сверху). Ниже — если настраиваете сервер сами.

### 1. Настройка `.env`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lordserial.net

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

ADMIN_TOKEN=длинный-случайный-токен
# ADMIN_PATH=secret-admin   # опционально, путь к админке
```

> **Важно:** при `APP_DEBUG=false` сайт автоматически отдаёт **минифицированные** ассеты темы (`site.min.js`, `site.min.css`). Если обновили `site.js`, но не пересобрали `.min` — часть JS на сайте работать не будет.

### 2. Сборка на сервере

```bash
composer install --no-dev --optimize-autoloader

php artisan migrate --force
php artisan storage:link

npm ci
npm run build:theme

cd admin-ui
npm ci
npm run build
cd ..

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Веб-сервер

Document root — только `public/`:

```
/path/to/site/public
```

Для Apache нужен `mod_rewrite` (файл `public/.htaccess` уже есть).

Пример для Nginx:

```nginx
server {
    listen 80;
    server_name lordserial.net;
    root /path/to/site/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 4. Права на каталоги

```bash
chmod -R ug+rwx storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

На Windows/OSPanel обычно достаточно прав записи для пользователя веб-сервера.

### 5. Планировщик (cron)

```cron
* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Очередь

В `.env` указано `QUEUE_CONNECTION=database`. Запустите воркер через supervisor или systemd:

```bash
php artisan queue:work --sleep=3 --tries=3
```

---

## Сборка ассетов — шпаргалка

| Что меняли | Команда |
|------------|---------|
| `resources/tpl/default/assets/site.js` или `site.css` | `npm run build:theme` |
| React-админка (`admin-ui/src`) | `cd admin-ui && npm run build` |
| Полная установка с нуля | `composer setup` (composer + migrate + npm build) |

Скрипт `build:theme` минифицирует CSS/JS всех тем в `resources/tpl/*/assets/*.min.*`.

---

## Админ-панель

| Параметр | Описание |
|----------|----------|
| URL | `/admin/` по умолчанию, можно сменить через `ADMIN_PATH` в `.env` или в настройках сайта |
| API-токен | `ADMIN_TOKEN` в `.env` — передаётся в заголовке `X-Admin-Token` |
| Сборка | `cd admin-ui && npm run build` → файлы в `public/_admin_ui/` |

Если админка не открывается и показывает «Admin UI не собран» — выполните сборку `admin-ui`.

---

## Полезные команды

```bash
# Очистка кэша (после смены .env или настроек)
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Проверка здоровья
curl http://lordserial.net/up

# Тесты
composer test
```

---

## Типичные проблемы

### 500 при открытии страницы сериала

Проверьте лог `storage/logs/laravel.log`. Частая причина — отсутствующий `use` для фасадов в контроллерах.

### Кнопки на сайте не реагируют (голосование, списки и т.д.)

На продакшене используется `site.min.js`. После правок в `site.js` обязательно:

```bash
npm run build:theme
```

### Постеры / картинки не отображаются

```bash
php artisan storage:link
```

Убедитесь, что `storage/app/public` доступен для записи.

### Админка: 401 / «ADMIN_TOKEN не задан»

Задайте `ADMIN_TOKEN` в `.env` и перезапустите PHP (или выполните `php artisan config:clear`).

---

## Структура проекта

```
site/
├── app/                  # Laravel: контроллеры, сервисы, модели
├── admin-ui/             # React-админка (Vite)
├── database/migrations/  # Миграции БД
├── public/               # Document root веб-сервера
│   └── admin/            # Собранная админка
├── resources/tpl/        # Темы сайта (шаблоны + assets)
├── routes/               # web.php, api.php
├── scripts/              # build-theme-assets.mjs
└── storage/              # Логи, кэш, загрузки
```
