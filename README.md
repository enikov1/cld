# Omro

Универсальный движок онлайн-каталога контента: фильмы, сериалы, мультфильмы,
мультсериалы, аниме, дорамы, ТВ-шоу — на одном `content_type` (7 типов из коробки,
типы настраиваются в `config/series.php`). Laravel 12 + собственный TPL-движок
(`.tpl`-шаблоны, без Blade) + React-админка.

Движок не привязан ни к бренду, ни к типу контента: название сайта, слоган, логотип
задаются в админке (Настройки → Брендинг), типы контента — в `config/series.php`.

---

## Быстрая установка (одной командой)

Скрипт `install.sh` ставит PHP-FPM, MariaDB, Node.js, Composer, cron, скачивает
движок, создаёт БД, применяет миграции, собирает интерфейсы и — при наличии
ZIP-бэкапа — восстанавливает данные.

**Нужно заранее:**
1. VPS Ubuntu 24.04
2. Домен с A-записью на IP сервера
3. SSH от root (или `sudo`)

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

> **Веб-сервер не ставится** — движку всё равно, что стоит перед ним
> (nginx, Apache, Caddy, OpenLiteSpeed…). Требование одно: docroot → `public/`
> и PHP-FPM. Готовые конфиги — ниже, в разделе «Настройка веб-сервера».

После установки пароли и токен админки сохраняются в
`/root/omro-<SITE_ID>-credentials.txt`.

Откройте `https://ваш-сайт.ru` и админку `https://ваш-сайт.ru/admin/`.
Справка по параметрам: `sudo bash install.sh --help`

### Несколько сайтов на одном сервере

У каждого сайта — своя папка, БД, PHP-FPM pool, очередь и cron:

```bash
sudo DOMAIN=site1.ru bash install.sh
sudo DOMAIN=site2.ru bash install.sh
```

| | Пример для `site2.ru` |
|--|--|
| Папка | `/var/www/site2.ru` |
| БД / пользователь | `omro_site2_ru` |
| Очередь | `omro-queue-site2_ru` |
| Cron | `/etc/cron.d/omro-scheduler-site2_ru` |
| Учетные данные | `/root/omro-<SITE_ID>-credentials.txt` |

При необходимости явно: `APP_DIR=...` `SITE_ID=...` `DB_NAME=...` `DB_USER=...`

### Перенос со старого сервера (из бэкапа)

1. Скачайте ZIP из админки → «Бэкапы»
2. Загрузите на новый сервер, например в `/root/backup.zip`
3. Установите с восстановлением:

```bash
sudo DOMAIN=ваш-сайт.ru BACKUP_FILE=/root/backup.zip bash install.sh
```

Или положите ZIP в `storage/app/backups/` — скрипт возьмёт самый новый.

---

## Настройка веб-сервера

Скрипт установки не ставит веб-сервер: движок работает с любым. Общее для всех:

- **docroot** — папка `public/`
- PHP через **PHP-FPM** (сокет каждого сайта создаёт `install.sh`)
- порты 80/443 открывает `install.sh` (ufw); HTTPS — certbot или авто-HTTPS

Ниже готовые конфиги. Замените `example.com` на свой домен и
`omro_example_com.sock` — на сокет из вывода `install.sh`.

### nginx

```nginx
server {
    listen 80;
    server_name example.com www.example.com;
    root /var/www/example.com/public;
    index index.php;

    charset utf-8;
    client_max_body_size 64m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm-omro_example_com.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

Конфиг — в `/etc/nginx/sites-available/example.com`, включите симлинком в
`sites-enabled`, проверьте `nginx -t` и `systemctl reload nginx`.

HTTPS (certbot):

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d example.com -d www.example.com
```

### Apache

```apache
<VirtualHost *:80>
    ServerName example.com
    ServerAlias www.example.com
    DocumentRoot /var/www/example.com/public

    <Directory /var/www/example.com/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm-omro_example_com.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/example.com-error.log
    CustomLog ${APACHE_LOG_DIR}/example.com-access.log combined
</VirtualHost>
```

Включите `a2enmod proxy_fcgi setenvif rewrite headers`, добавьте конфиг в
`sites-available` + `a2ensite example.com`, затем `apachectl configtest` и
`systemctl reload apache2`. HTTPS — `certbot --apache -d example.com`.

### Caddy

```caddyfile
example.com {
    root * /var/www/example.com/public
    encode gzip zstd

    @blocked path /.env* /.git* /vendor/*
    respond @blocked 404

    php_fastcgi unix//run/php/php8.3-fpm-omro_example_com.sock {
        resolve_root_symlink
    }

    try_files {path} {path}/ /index.php?{query}
    file_server
}
```

Caddy сам получит и продлит HTTPS-сертификат (авто-HTTPS). Конфиг — в
`/etc/caddy/Caddyfile`, затем `caddy validate --config /etc/caddy/Caddyfile`
и `systemctl reload caddy`.

---

## Обновление движка

### Через админку

Кнопка «Система» в шапке админки → блок «Версия движка»: движок сравнивает
рабочую копию с `origin` (любой git-хостинг) и показывает, есть ли обновления.
«Обновить движок» выполняет `git pull` → `composer install` → миграции →
сборку темы и перезагружает страницу.

### Через SSH

```bash
# Из папки сайта
cd /var/www/ваш-сайт.ru
sudo bash update.sh

# Или одной командой
curl -fsSL https://raw.githubusercontent.com/enikov1/cld/main/update.sh \
  | sudo APP_DIR=/var/www/ваш-сайт.ru bash
```

Несколько сайтов — обновляйте каждый отдельно. Полезные флаги:
`SKIP_BUILD=1`, `SKIP_MIGRATE=1`, `SKIP_SERVICES=1`, `GIT_BRANCH=main`.

### Смена домена

```bash
cd /var/www/ваш-сайт.ru
sudo DOMAIN=новый-домен.ru SKIP_BUILD=1 bash install.sh
```

---

## Локальная установка (разработка)

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

# 5. Сборка ассетов
npm install && npm run build:theme
cd admin-ui && npm install && npm run build && cd ..
```

В админке (`/admin/`, токен из `ADMIN_TOKEN`) — раздел «Шаблоны» для правки
`.tpl`, «TPL-DOC» для полной справки по тегам.

---

## Шаблоны

Единственный шаблон, который поставляется с движком, — `resources/tpl/default`.
Свои шаблоны вы делаете в отдельных папках (`resources/tpl/<имя>`), они не
попадают в git (см. `.gitignore`).

Структура стандартного шаблона:

```
resources/tpl/default/
├── layout.tpl          # каркас сайта (обязателен, в корне темы)
├── maintenance.tpl     # standalone-страница техработ
├── pages/              # страницы: home, catalog, search, series/show, ...
├── partials/           # партиалы (header, footer, карточки, фильтры...)
├── errors/             # фрагменты страниц ошибок (403/404/419/500/503)
├── modules/            # исходники JS-модулей темы
└── assets/             # css/js/шрифты/логотип темы
```

Функциональные JS-модули (модалки, фильтры, поиск, избранное, комментарии,
календарь, уведомления, плеер) вынесены из темы в движок: `public/omrotpl/omrotpl.js`
подключается автоматически в каждой теме, вместе с базовыми структурными стилями
`public/omrotpl/omrotpl.css`. Тема переопределяет внешний вид — js завязан не на
классы темы, а на контракт `data-omro-*`-атрибутов (легаси-селекторы стандартной
темы тоже работают). Полная таблица контракта — в админке, TPL-DOC.

### Сборка ассетов — шпаргалка

| Что меняли | Команда |
|------------|---------|
| `public/omrotpl/omrotpl.js` / `omrotpl.css` | `npm run build:theme` |
| `resources/tpl/default/assets/*` | `npm run build:theme` |
| React-админка (`admin-ui/src`) | `cd admin-ui && npm run build` |
| Полная установка с нуля | `composer setup` |

---

## Настройки и сервисы

- Админка: `ADMIN_TOKEN` (`.env`) и путь `/admin` (меняется настройкой
  `admin_path` в БД); подробнее в `.env.example`.
- Очередь и cron: systemd-юнит `omro-queue-<SITE_ID>` и
  `/etc/cron.d/omro-scheduler-<SITE_ID>` (создаются `install.sh` / `update.sh`).
- Проверка: `curl -sI https://ваш-сайт.ru/up`.
- Логи: `tail -f /var/www/ваш-сайт.ru/storage/logs/laravel.log`.

## Команды сервера

```bash
# Статус очереди
systemctl status omro-queue-<SITE_ID>

# Планировщик
cat /etc/cron.d/omro-scheduler-<SITE_ID>

# Логи сайта
tail -f /var/www/ваш-сайт.ru/storage/logs/laravel.log
```
