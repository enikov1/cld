(function() {
    'use strict';

    var siteCfg = (function() {
        var el = document.getElementById('site-config');
        if (!el) return {};
        try {
            return JSON.parse(el.textContent || '{}');
        } catch (e) {
            return {};
        }
    })();

    function cfg(key, fallback) {
        var val = siteCfg[key];
        return val === undefined || val === null || val === '' ? fallback : val;
    }

    function cfgBool(key, fallback) {
        if (!(key in siteCfg)) return !!fallback;
        var val = siteCfg[key];
        return val === true || val === 1 || val === '1';
    }

    function seriesApiPath(seriesId) {
        return '/api/series/' + encodeURIComponent(String(seriesId));
    }

    function flashNotice(text, isError) {
        var existing = document.querySelector('.ls-flash-notice');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.className = 'ls-flash-notice' + (isError ? ' ls-flash-notice--error' : '');
        el.setAttribute('role', 'status');
        el.textContent = text;
        el.style.cssText = 'position:fixed;z-index:9999;left:50%;bottom:24px;transform:translateX(-50%);' +
            'padding:10px 16px;border-radius:8px;background:' + (isError ? '#5c1a1a' : '#1a3a2a') +
            ';color:#fff;font-size:14px;box-shadow:0 4px 16px rgba(0,0,0,.35);max-width:90vw;';
        document.body.appendChild(el);
        setTimeout(function() { el.remove(); }, 3200);
    }

    var LS_FAV_KEY = 'ls_favourites';
    var LS_HISTORY_KEY = 'ls_watch_history';
    var LS_GUEST_KEY = 'ls_guest_key';

    function getGuestLibKey() {
        try {
            var existing = localStorage.getItem(LS_GUEST_KEY);
            if (existing && /^[a-f0-9]{32,64}$/i.test(existing)) {
                persistGuestLibKey(existing.toLowerCase());
                return existing.toLowerCase();
            }
            var bytes = new Uint8Array(32);
            if (window.crypto && window.crypto.getRandomValues) {
                window.crypto.getRandomValues(bytes);
            } else {
                for (var i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
            }
            var key = Array.prototype.map.call(bytes, function(b) {
                return ('0' + b.toString(16)).slice(-2);
            }).join('');
            persistGuestLibKey(key);
            return key;
        } catch (e) {
            return '';
        }
    }

    function persistGuestLibKey(key) {
        if (!key) return;
        try {
            localStorage.setItem(LS_GUEST_KEY, key);
        } catch (e) {}
        try {
            document.cookie = LS_GUEST_KEY + '=' + encodeURIComponent(key) +
                '; path=/; max-age=' + String(60 * 60 * 24 * 365) +
                '; samesite=lax';
        } catch (e) {}
    }

    function guestLibraryPayload(extra) {
        var payload = Object.assign({}, extra || {});
        if (!isSiteLoggedIn()) {
            var guestKey = getGuestLibKey();
            if (guestKey) payload.guest_key = guestKey;
        }
        return payload;
    }

    function readLocalJson(key, fallback) {
        try {
            var raw = localStorage.getItem(key);
            if (!raw) return fallback;
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function writeLocalJson(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {}
    }

    function getLocalFavourites() {
        return readLocalJson(LS_FAV_KEY, []).map(function(id) {
            return parseInt(id, 10);
        }).filter(function(id) { return id > 0; });
    }

    function setLocalFavourites(ids) {
        writeLocalJson(LS_FAV_KEY, ids);
    }

    function isLocalFavourite(seriesId) {
        return getLocalFavourites().indexOf(parseInt(seriesId, 10)) !== -1;
    }

    function setLocalFavourite(seriesId, active) {
        var id = parseInt(seriesId, 10);
        if (!id) return;
        var ids = getLocalFavourites().filter(function(item) { return item !== id; });
        if (active) ids.unshift(id);
        setLocalFavourites(ids.slice(0, cfgInt('favourites_list_limit', 100)));
        updateHeaderFavouritesCount(ids.length);
    }

    function updateHeaderFavouritesCount(count) {
        var n = parseInt(count, 10);
        if (isNaN(n) || n < 0) n = 0;
        var label = n > 99 ? '99+' : String(n);

        var headerCount = document.getElementById('headerFavCount');
        if (headerCount) {
            if (n > 0) {
                headerCount.textContent = label;
                headerCount.hidden = false;
            } else {
                headerCount.hidden = true;
            }
        }

        var mobileCount = document.getElementById('mobileFavCount');
        if (mobileCount) {
            if (n > 0) {
                mobileCount.textContent = label;
                mobileCount.hidden = false;
            } else {
                mobileCount.hidden = true;
            }
        }
    }

    function refreshHeaderFavouritesCount() {
        if (!cfgBool('favourites_enabled', true)) return;

        if (!isSiteLoggedIn()) {
            updateHeaderFavouritesCount(getLocalFavourites().length);
        }

        var url = '/api/favourites';
        if (!isSiteLoggedIn()) {
            var guestKey = getGuestLibKey();
            var params = [];
            if (guestKey) params.push('guest_key=' + encodeURIComponent(guestKey));
            getLocalFavourites().forEach(function(id) {
                params.push('ids[]=' + encodeURIComponent(String(id)));
            });
            if (params.length) url += '?' + params.join('&');
        }

        fetchJson(url)
            .then(function(data) {
                if (data && typeof data.count === 'number') {
                    updateHeaderFavouritesCount(data.count);
                    if (!isSiteLoggedIn() && Array.isArray(data.items)) {
                        setLocalFavourites(data.items.map(function(item) {
                            return parseInt(item.id, 10);
                        }).filter(function(id) { return id > 0; }));
                    }
                }
            })
            .catch(function() {
                if (!isSiteLoggedIn()) {
                    updateHeaderFavouritesCount(getLocalFavourites().length);
                }
            });
    }

    function isSiteLoggedIn() {
        return document.body.getAttribute('data-logged-in') === '1';
    }

    function getLocalHistory() {
        return readLocalJson(LS_HISTORY_KEY, []).map(function(id) {
            return parseInt(id, 10);
        }).filter(function(id) { return id > 0; });
    }

    function pushLocalHistory(seriesId) {
        var id = parseInt(seriesId, 10);
        if (!id) return;
        var ids = getLocalHistory().filter(function(item) { return item !== id; });
        ids.unshift(id);
        writeLocalJson(LS_HISTORY_KEY, ids.slice(0, cfgInt('watch_history_max_items', 100)));
    }

    function cfgInt(key, fallback) {
        var val = parseInt(cfg(key, fallback), 10);
        return isNaN(val) ? fallback : val;
    }

    function updateFavouriteButton(btn, isFavourite) {
        if (!btn) return;
        btn.classList.toggle('is-active', !!isFavourite);
        btn.setAttribute('aria-pressed', isFavourite ? 'true' : 'false');
        var label = btn.querySelector('[data-favourite-label]');
        if (label) {
            label.textContent = isFavourite ?
                cfg('favourites_ui_remove_label', 'В избранном') :
                cfg('favourites_ui_add_label', 'В избранное');
        }
    }

    function mergeGuestLibrary() {
        if (!cfgBool('favourites_enabled', true) && !cfgBool('watch_history_enabled', true)) return;

        postJson('/api/user-library/merge-guest', guestLibraryPayload({
            favourites: getLocalFavourites(),
            history: getLocalHistory(),
        })).then(function() {
            refreshHeaderFavouritesCount();
        }).catch(function() {
            flashNotice('Не удалось перенести локальную библиотеку.', true);
        });
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function setCsrfToken(token) {
        if (!token) return;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        document.querySelectorAll('input[name="_token"]').forEach(function(input) {
            input.value = token;
        });
    }

    function readXsrfCookie() {
        var match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        if (!match) return '';
        try {
            return decodeURIComponent(match[1]);
        } catch (e) {
            return match[1] || '';
        }
    }

    var csrfRefreshPromise = null;
    var csrfReadyPromise = null;

    function refreshCsrfToken() {
        if (csrfRefreshPromise) return csrfRefreshPromise;
        csrfRefreshPromise = fetch('/api/csrf', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
            .then(function(r) {
                if (!r.ok) throw new Error('csrf refresh failed');
                return r.json();
            })
            .then(function(data) {
                if (data && data.token) setCsrfToken(data.token);
                return csrfToken() || readXsrfCookie();
            })
            .catch(function() {
                return csrfToken() || readXsrfCookie();
            })
            .finally(function() {
                csrfRefreshPromise = null;
            });
        return csrfRefreshPromise;
    }

    function ensureCsrfToken() {
        // Always sync with the current session. A leftover XSRF-TOKEN cookie can be
        // stale when laravel-session was rotated/lost, which caused 419 on login.
        if (!csrfReadyPromise) {
            csrfReadyPromise = refreshCsrfToken();
        }
        return csrfReadyPromise;
    }

    function csrfHeaders(extra) {
        var headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        // Prefer plain meta token: Laravel checks X-CSRF-TOKEN before X-XSRF-TOKEN.
        // Stale encrypted XSRF cookies must not be the only token we send.
        var metaToken = csrfToken();
        if (metaToken) {
            headers['X-CSRF-TOKEN'] = metaToken;
        } else {
            var xsrf = readXsrfCookie();
            if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
        }
        if (!isSiteLoggedIn()) {
            var guestKey = getGuestLibKey();
            if (guestKey) headers['X-Guest-Key'] = guestKey;
        }
        return Object.assign(headers, extra || {});
    }

    function fetchWithCsrf(url, options, retried) {
        options = options || {};

        var run = function() {
            var headers = csrfHeaders(options.headers);
            if (options.body && !headers['Content-Type']) {
                headers['Content-Type'] = 'application/json';
            }

            return fetch(url, Object.assign({}, options, {
                credentials: 'same-origin',
                headers: headers,
            })).then(function(response) {
                if (response.status === 419 && !retried) {
                    csrfReadyPromise = null;
                    return refreshCsrfToken().then(function() {
                        csrfReadyPromise = Promise.resolve(csrfToken() || readXsrfCookie());
                        return fetchWithCsrf(url, options, true);
                    });
                }
                return response;
            });
        };

        if (retried) return run();
        return ensureCsrfToken().then(run);
    }

    function postJson(url, body) {
        return fetchWithCsrf(url, {
            method: 'POST',
            body: JSON.stringify(body || {}),
        });
    }

    function deleteJson(url) {
        return fetchWithCsrf(url, { method: 'DELETE' });
    }

    function readJsonResponse(response) {
        return response.json().then(function(data) {
            return { ok: response.ok, status: response.status, data: data };
        }).catch(function() {
            return {
                ok: false,
                status: response.status,
                data: { message: cfg('ui_msg_server_error', 'Ошибка сервера. Попробуйте позже.') },
            };
        });
    }

    function humanizeApiMessage(message) {
        if (!message) return message;
        if (/csrf token mismatch/i.test(message)) {
            return cfg('ui_msg_session_expired', 'Сессия истекла. Обновите страницу и попробуйте снова.');
        }
        var known = {
            'The email field must be a valid email address.': 'Укажите корректный адрес email.',
            'The email field is required.': 'Укажите email.',
            'The password field is required.': 'Укажите пароль.',
            'The name field is required.': 'Укажите имя.',
            'The password confirmation field is required.': 'Повторите пароль.',
            'The current password field is required.': 'Укажите текущий пароль.',
        };
        if (known[message]) return known[message];
        return message;
    }

    function fieldLabel(input) {
        var label = input.closest('label');
        if (label) {
            var span = label.querySelector('span');
            if (span && span.textContent.trim()) return span.textContent.trim();
        }
        var placeholder = input.getAttribute('placeholder');
        if (placeholder) return placeholder;
        return input.name;
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function validateFormFields(form) {
        var errors = {};
        var passwordInput = form.querySelector('input[name="password"]');
        var passwordConfirm = form.querySelector('input[name="password_confirmation"]');

        form.querySelectorAll('input, textarea, select').forEach(function(input) {
            if (!input.name || input.type === 'hidden' || input.disabled) return;

            var value = String(input.value || '').trim();
            var label = fieldLabel(input);

            if (input.required && !value) {
                errors[input.name] = ['Заполните поле «' + label + '».'];
                return;
            }

            if (input.type === 'email' && value && !isValidEmail(value)) {
                errors[input.name] = ['Укажите корректный адрес email.'];
            }
        });

        if (passwordInput && passwordConfirm) {
            var pass = String(passwordInput.value || '');
            var confirm = String(passwordConfirm.value || '');
            if (pass && confirm && pass !== confirm) {
                errors.password_confirmation = ['Пароли не совпадают.'];
            }
        }

        return errors;
    }

    function splitFormErrors(form, errors) {
        var fieldErrors = {};
        var general = [];

        if (!errors) {
            return { fieldErrors: fieldErrors, general: general };
        }

        Object.keys(errors).forEach(function(field) {
            var input = form.querySelector('[name="' + field + '"]');
            var val = errors[field];
            var msgs = Array.isArray(val) ? val.slice() : [String(val)];
            msgs = msgs.map(humanizeApiMessage).filter(Boolean);

            if (input && msgs.length) {
                fieldErrors[field] = msgs;
            } else if (msgs.length) {
                general = general.concat(msgs);
            }
        });

        return { fieldErrors: fieldErrors, general: general };
    }

    function showFormErrors(form, feedback, errors) {
        var split = splitFormErrors(form, errors);
        applyFieldErrors(form, split.fieldErrors);

        if (split.general.length) {
            showFeedback(feedback, split.general.join(' '), true);
        } else if (Object.keys(split.fieldErrors).length) {
            showFeedback(feedback, '', false, true);
        } else {
            showFeedback(feedback, humanizeApiMessage('Произошла ошибка'), true);
        }
    }

    function parseApiErrors(data) {
        if (!data) return humanizeApiMessage('Произошла ошибка');
        if (data.errors) {
            var parts = [];
            Object.keys(data.errors).forEach(function(key) {
                var val = data.errors[key];
                if (Array.isArray(val)) parts = parts.concat(val);
                else if (val) parts.push(String(val));
            });
            if (parts.length) return humanizeApiMessage(parts.map(humanizeApiMessage).join(' '));
        }
        return humanizeApiMessage(data.message || 'Произошла ошибка');
    }

    function showFeedback(el, message, isError, hideOnly) {
        if (!el) return;
        if (hideOnly || !message) {
            el.hidden = true;
            el.textContent = '';
            el.classList.remove('auth-errors', 'auth-notice', 'profile-flash--error', 'profile-flash--success');
            return;
        }
        el.hidden = false;
        el.textContent = message;
        el.classList.remove('auth-errors', 'auth-notice', 'profile-flash--error', 'profile-flash--success');
        if (el.classList.contains('profile-flash')) {
            el.classList.add(isError ? 'profile-flash--error' : 'profile-flash--success');
        } else {
            el.classList.add(isError ? 'auth-errors' : 'auth-notice');
        }
    }

    function clearFieldErrors(form) {
        form.querySelectorAll('.field-error').forEach(function(el) { el.remove(); });
        form.querySelectorAll('.is-invalid').forEach(function(el) { el.classList.remove('is-invalid'); });
    }

    function applyFieldErrors(form, errors) {
        if (!errors) return;
        Object.keys(errors).forEach(function(field) {
            var input = form.querySelector('[name="' + field + '"]');
            if (!input) return;
            input.classList.add('is-invalid');
            var msg = document.createElement('div');
            msg.className = 'field-error';
            msg.textContent = Array.isArray(errors[field]) ? humanizeApiMessage(errors[field][0]) : humanizeApiMessage(String(errors[field]));
            var host = input.closest('.login-form__field, .profile-field, label') || input.parentElement;
            if (host) host.appendChild(msg);
        });
    }

    function formToObject(form) {
        var fd = new FormData(form);
        var out = {};
        fd.forEach(function(value, key) {
            if (key === '_token') return;
            if (out[key] !== undefined) {
                if (!Array.isArray(out[key])) out[key] = [out[key]];
                out[key].push(value);
            } else {
                out[key] = value;
            }
        });
        var remember = form.querySelector('input[name="remember"]');
        if (remember) out.remember = remember.checked;
        return out;
    }

    function bindAjaxForm(form, opts) {
        if (!form || form.getAttribute('data-ajax-bound') === '1') return;
        form.setAttribute('data-ajax-bound', '1');
        form.setAttribute('novalidate', 'novalidate');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var action = form.getAttribute('action');
            if (!action) return;

            var feedback = opts.feedback ||
                form.querySelector('[data-form-feedback]') ||
                (form.closest('[data-auth-panel]') ? form.closest('[data-auth-panel]').querySelector('.auth-form-feedback') : null);
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            clearFieldErrors(form);
            showFeedback(feedback, '', false, true);

            var clientErrors = validateFormFields(form);
            if (Object.keys(clientErrors).length) {
                showFormErrors(form, feedback, clientErrors);
                if (submitBtn) submitBtn.disabled = false;
                return;
            }

            postJson(form.getAttribute('action'), formToObject(form))
                .then(readJsonResponse)
                .then(function(res) {
                    if (!res.ok) {
                        showFormErrors(form, feedback, res.data.errors || {});
                        if (!res.data.errors && (res.data.message || res.data.error)) {
                            showFeedback(feedback, humanizeApiMessage(res.data.message || res.data.error), true);
                        }
                        return;
                    }
                    var data = res.data || {};
                    if (data.ok === false) {
                        showFormErrors(form, feedback, data.errors || {});
                        if (!data.errors && (data.message || data.error)) {
                            showFeedback(feedback, humanizeApiMessage(data.message || data.error), true);
                        }
                        return;
                    }
                    if (data.message) showFeedback(feedback, data.message, false);
                    if (typeof opts.onSuccess === 'function') opts.onSuccess(data, form);
                    if (data.close_auth) {
                        window.setTimeout(function() {
                            closeAuthModal();
                            form.reset();
                        }, 900);
                    }
                    if (data.redirect && !data.close_auth) {
                        window.location.href = data.redirect;
                    } else if (data.reload) {
                        window.location.reload();
                    } else if (data.panel && window.lordSerialOpenAuth) {
                        window.lordSerialOpenAuth(data.panel);
                    }
                })
                .catch(function() {
                    showFeedback(feedback, 'Не удалось отправить форму. Проверьте соединение.', true);
                })
                .finally(function() {
                    if (submitBtn) submitBtn.disabled = false;
                });
        });
    }

    function openAuthPanel(name) {
        var overlay = document.getElementById('loginOverlay');
        if (!overlay) return;
        overlay.classList.add('is-active');
        overlay.querySelectorAll('[data-auth-panel]').forEach(function(panel) {
            panel.hidden = panel.getAttribute('data-auth-panel') !== name;
        });
    }

    function closeAuthModal() {
        var overlay = document.getElementById('loginOverlay');
        if (overlay) overlay.classList.remove('is-active');
    }

    function markLoggedInRoots() {
        document.querySelectorAll('.fmain[data-series-id], #commentsSection').forEach(function(el) {
            if (!el.querySelector('[data-logged-in]')) {
                var marker = document.createElement('span');
                marker.hidden = true;
                marker.setAttribute('data-logged-in', '1');
                el.insertBefore(marker, el.firstChild);
            }
        });
    }

    function cleanAuthUrlParams() {
        var url = new URL(window.location.href);
        var changed = false;
        ['auth', 'token', 'email'].forEach(function(key) {
            if (!url.searchParams.has(key)) return;
            url.searchParams.delete(key);
            changed = true;
        });
        if (changed) {
            window.history.replaceState({}, '', url.pathname + url.search + url.hash);
        }
        document.body.removeAttribute('data-auth-panel');
    }

    function updateHeaderAuthState(data) {
        var actions = document.querySelector('.ls-actions');
        if (!actions) return;

        document.body.setAttribute('data-logged-in', '1');

        var loginBtn = actions.querySelector('.js-login-open');
        if (loginBtn) loginBtn.remove();

        var themeBtn = actions.querySelector('.js-theme-toggle');
        var insertBefore = themeBtn || null;

        if (data && data.is_admin) {
            var adminUrl = (data.admin_url || cfg('admin_url', '/admin')).replace(/\/?$/, '/');
            if (!document.getElementById('headerAdminLink')) {
                var adminLink = document.createElement('a');
                adminLink.className = 'dontusebuttonclass ls-action ls-action--admin';
                adminLink.id = 'headerAdminLink';
                adminLink.href = adminUrl;
                adminLink.title = 'Админ-панель';
                adminLink.innerHTML = '<span class="fa fa-cog"></span>';
                var favLink = actions.querySelector('.ls-action--favourites');
                var afterFav = favLink && favLink.nextSibling;
                actions.insertBefore(adminLink, afterFav || insertBefore);
            }
            if (!document.getElementById('mobileAdminLink')) {
                var mobileMenu = document.querySelector('.ls-mobile-accordion');
                if (mobileMenu) {
                    var mobileAdmin = document.createElement('a');
                    mobileAdmin.className = 'ls-mobile-link ls-mobile-link--admin';
                    mobileAdmin.id = 'mobileAdminLink';
                    mobileAdmin.href = adminUrl;
                    mobileAdmin.innerHTML = '<span class="fa fa-cog" aria-hidden="true"></span> Админ-панель';
                    mobileMenu.appendChild(mobileAdmin);
                }
            }
        }

        if (!actions.querySelector('a.ls-action[href="/profile/"]') && cfgBool('auth_profile_enabled', true)) {
            var profileLink = document.createElement('a');
            profileLink.className = 'dontusebuttonclass ls-action';
            profileLink.href = '/profile/';
            profileLink.title = cfg('auth_ui_header_profile', 'Профиль');
            profileLink.innerHTML = '<span class="fa fa-user"></span>';
            var notifyBtnExisting = document.getElementById('headerNotifyBtn');
            actions.insertBefore(profileLink, notifyBtnExisting || insertBefore);
        }

        if (cfgBool('notifications_enabled', true) && !document.getElementById('headerNotifyBtn')) {
            var bellBtn = document.createElement('button');
            bellBtn.type = 'button';
            bellBtn.className = 'dontusebuttonclass ls-action js-notify-btn js-series-bell';
            bellBtn.id = 'headerNotifyBtn';
            bellBtn.title = 'Уведомления';
            bellBtn.innerHTML = '<span class="fa fa-bell"></span><span class="series-bell-count" id="headerNotifyCount" hidden></span>';
            actions.insertBefore(bellBtn, insertBefore);
            initHeaderNotifications();
        } else if (document.getElementById('headerNotifyBtn')) {
            initHeaderNotifications();
            var countEl = document.getElementById('headerNotifyCount');
            if (countEl) {
                fetchJson('/api/notifications/').then(function(payload) {
                    if (!countEl) return;
                    var unread = (payload && payload.unread) || 0;
                    if (unread > 0) {
                        countEl.textContent = unread > 99 ? '99+' : String(unread);
                        countEl.hidden = false;
                    } else {
                        countEl.hidden = true;
                    }
                }).catch(function() {});
            }
        }

        refreshHeaderFavouritesCount();
    }

    function upgradeCommentsComposeForm() {
        var section = document.getElementById('commentsSection');
        if (!section) return;

        var compose = section.querySelector('.comments-compose');
        if (!compose) return;

        var guestForm = compose.querySelector('.comment-form--guest');
        if (!guestForm) return;

        var form = document.createElement('form');
        form.className = 'comment-form';
        form.setAttribute('data-comment-form', 'root');
        form.setAttribute('action', '#');
        form.setAttribute('novalidate', 'novalidate');

        var label = document.createElement('label');
        label.className = 'comment-form__label';
        label.setAttribute('for', 'comment-body-root');
        label.textContent = cfg('comments_ui_label', 'Комментарий');
        form.appendChild(label);

        var textarea = document.createElement('textarea');
        textarea.id = 'comment-body-root';
        textarea.name = 'body';
        textarea.placeholder = cfg('comments_ui_placeholder', '');
        textarea.rows = 4;
        form.appendChild(textarea);

        var footer = document.createElement('div');
        footer.className = 'comment-form__footer';
        var submitBtn = document.createElement('button');
        submitBtn.type = 'button';
        submitBtn.className = 'dontusebuttonclass comment-form__submit';
        submitBtn.setAttribute('data-comment-submit', '');
        submitBtn.textContent = cfg('comments_ui_submit', 'Отправить');
        footer.appendChild(submitBtn);
        form.appendChild(footer);

        compose.innerHTML = '';
        compose.appendChild(form);
        document.dispatchEvent(new CustomEvent('lordserial:comments-compose-upgrade'));
    }

    function applyAuthSession(data) {
        if (!data || !data.logged_in) return;
        markLoggedInRoots();
        updateHeaderAuthState(data);
        upgradeCommentsComposeForm();
        cleanAuthUrlParams();
        document.dispatchEvent(new CustomEvent('lordserial:auth-login', { detail: data || {} }));
        mergeGuestLibrary();
    }

    function initAuthModal() {
        var overlay = document.getElementById('loginOverlay');
        if (!overlay) return;

        document.querySelectorAll('.js-login-open').forEach(function(btn) {
            btn.addEventListener('click', function() { openAuthPanel('login'); });
        });

        document.querySelectorAll('.js-login-close, .login-modal__close').forEach(function(el) {
            el.addEventListener('click', function() {
                overlay.classList.remove('is-active');
            });
        });

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.classList.remove('is-active');
        });

        document.querySelectorAll('.js-auth-switch').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openAuthPanel(btn.getAttribute('data-auth-open') || 'login');
            });
        });

        var params = new URLSearchParams(window.location.search);
        var panel = params.get('auth') || document.body.getAttribute('data-auth-panel') || '';
        if (panel === 'login' || panel === 'register' || panel === 'forgot' || panel === 'reset') {
            openAuthPanel(panel);
        }

        if (panel === 'reset' || params.get('token')) {
            var tokenField = document.getElementById('resetTokenField');
            var emailField = document.getElementById('resetEmailField');
            if (tokenField && !tokenField.value && params.get('token')) {
                tokenField.value = params.get('token');
            }
            if (emailField && !emailField.value && params.get('email')) {
                emailField.value = params.get('email');
            }
        }

        if (document.body.getAttribute('data-auth-panel') || params.get('auth') || params.get('token')) {
            var notice = document.querySelector('.auth-notice');
            if (notice) {
                openAuthPanel(panel || 'login');
            }
        }

        window.lordSerialOpenAuth = openAuthPanel;

        overlay.querySelectorAll('.login-form').forEach(function(form) {
            bindAjaxForm(form, {
                feedback: form.parentElement ? form.parentElement.querySelector('.auth-form-feedback') : null,
                onSuccess: function(data) {
                    if (data.logged_in) applyAuthSession(data);
                },
            });
        });
    }

    function initHeaderSearch() {
        var header = document.getElementById('header');
        if (!header) return;

        var searchForm = header.querySelector('.ls-search');
        var openBtn = header.querySelector('.js-header-search-open');
        var closeBtn = header.querySelector('.js-header-search-close');
        var input = searchForm ? searchForm.querySelector('input[name="q"]') : null;
        if (!searchForm || !input) return;

        function isCompactSearchMode() {
            return window.matchMedia('(max-width: 640px)').matches;
        }

        function openHeaderSearch() {
            header.classList.add('search-is-open');
            if (closeBtn) closeBtn.hidden = false;
            window.setTimeout(function() {
                input.focus();
                var len = input.value.length;
                try {
                    input.setSelectionRange(len, len);
                } catch (e) {}
            }, 30);
        }

        function closeHeaderSearch() {
            if (!header.classList.contains('search-is-open')) return;
            header.classList.remove('search-is-open');
            if (closeBtn) closeBtn.hidden = true;
            var panel = searchForm.querySelector('.ls-search__panel');
            if (panel) {
                panel.hidden = true;
                panel.innerHTML = '';
                input.setAttribute('aria-expanded', 'false');
            }
            if (openBtn && isCompactSearchMode()) openBtn.focus();
        }

        if (openBtn) {
            openBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openHeaderSearch();
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeHeaderSearch();
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && header.classList.contains('search-is-open')) {
                closeHeaderSearch();
            }
        });

        document.addEventListener('click', function(e) {
            if (!header.classList.contains('search-is-open')) return;
            if (searchForm.contains(e.target) || (openBtn && openBtn.contains(e.target))) return;
            closeHeaderSearch();
        });

        window.addEventListener('resize', function() {
            if (!header.classList.contains('search-is-open')) return;
            if (!isCompactSearchMode()) {
                closeHeaderSearch();
            }
        });
    }

    function initQuickSearch() {
        document.querySelectorAll('.js-quick-search').forEach(function(form) {
            var input = form.querySelector('input[name="q"]');
            var panel = form.querySelector('.ls-search__panel');
            if (!input || !panel) return;

            var timer = null;
            var requestId = 0;
            var abortController = null;
            var lastFetchedQuery = '';
            var minChars = parseInt(cfg('search_suggest_min_chars', 2), 10) || 2;
            var debounceMs = parseInt(cfg('search_suggest_debounce_ms', 450), 10) || 450;

            function closePanel() {
                panel.hidden = true;
                panel.innerHTML = '';
                input.setAttribute('aria-expanded', 'false');
            }

            function openPanel() {
                panel.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            }

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function iconFor(type) {
                var map = {
                    series: 'fa-play',
                    studios: 'fa-building-o',
                    collections: 'fa-folder-open-o',
                    genres: 'fa-tags',
                    years: 'fa-calendar',
                    actors: 'fa-user',
                    directors: 'fa-user',
                };
                return map[type] || 'fa-search';
            }

            function render(data) {
                if (!data.groups || !data.groups.length) {
                    panel.innerHTML = '<div class="ls-search__empty">Ничего не найдено</div>';
                    openPanel();
                    return;
                }

                var html = '';
                data.groups.forEach(function(group) {
                    html += '<div class="ls-search__group">';
                    html += '<div class="ls-search__group-title">' + escapeHtml(group.label) + '</div>';
                    html += '<div class="ls-search__group-list">';
                    group.items.forEach(function(item) {
                        html += '<a class="ls-search__item ls-search__item--' + escapeHtml(group.type) + '" href="' + escapeHtml(item.url) + '">';
                        if (item.image) {
                            html += '<span class="ls-search__item-media"><img src="' + escapeHtml(item.image) + '" alt="" loading="lazy"></span>';
                        } else {
                            html += '<span class="ls-search__item-media ls-search__item-media--icon"><span class="fa ' + iconFor(group.type) + '"></span></span>';
                        }
                        html += '<span class="ls-search__item-body">';
                        html += '<span class="ls-search__item-title">' + escapeHtml(item.title) + '</span>';
                        if (item.subtitle) {
                            html += '<span class="ls-search__item-sub">' + escapeHtml(item.subtitle) + '</span>';
                        }
                        html += '</span></a>';
                    });
                    html += '</div></div>';
                });
                html += '<a class="ls-search__all" href="/search?q=' + encodeURIComponent(data.query) + '">Все результаты по запросу «' + escapeHtml(data.query) + '»</a>';
                panel.innerHTML = html;
                openPanel();
            }

            function fetchSuggest() {
                var q = input.value.trim();
                if (q.length < minChars) {
                    lastFetchedQuery = '';
                    closePanel();
                    return;
                }

                if (q === lastFetchedQuery && panel.innerHTML && !panel.querySelector('.ls-search__loading')) {
                    openPanel();
                    return;
                }

                if (abortController) {
                    abortController.abort();
                }
                abortController = new AbortController();

                var current = ++requestId;
                panel.innerHTML = '<div class="ls-search__loading">Ищем...</div>';
                openPanel();

                fetch('/api/search/suggest?q=' + encodeURIComponent(q), {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        signal: abortController.signal,
                    })
                    .then(readJsonResponse)
                    .then(function(res) {
                        if (current !== requestId) return;
                        if (!res.ok || !res.data) {
                            closePanel();
                            return;
                        }
                        lastFetchedQuery = q;
                        render(res.data);
                    })
                    .catch(function(err) {
                        if (err && err.name === 'AbortError') return;
                        if (current === requestId) closePanel();
                    });
            }

            input.addEventListener('input', function() {
                clearTimeout(timer);
                timer = setTimeout(fetchSuggest, debounceMs);
            });

            input.addEventListener('focus', function() {
                var q = input.value.trim();
                if (q.length >= minChars) {
                    if (panel.innerHTML) openPanel();
                    else fetchSuggest();
                }
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closePanel();
            });

            document.addEventListener('click', function(e) {
                if (!form.contains(e.target)) closePanel();
            });
        });
    }

    function initMobileMenu() {
        var menu = document.getElementById('lsMobileMenu');
        var overlay = document.querySelector('.ls-mobile-overlay');
        if (!menu) return;

        function closeMobileSections() {
            menu.querySelectorAll('.ls-mobile-section.is-open').forEach(function(section) {
                section.classList.remove('is-open');
                var toggle = section.querySelector('.js-mobile-section-toggle');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        }

        function openMobileMenu() {
            var header = document.getElementById('header');
            if (header) {
                header.classList.remove('search-is-open');
                var closeBtn = header.querySelector('.js-header-search-close');
                if (closeBtn) closeBtn.hidden = true;
            }
            document.body.classList.add('ls-menu-open');
            menu.setAttribute('aria-hidden', 'false');
            if (overlay) overlay.setAttribute('aria-hidden', 'false');
        }

        function closeMobileMenu() {
            document.body.classList.remove('ls-menu-open');
            menu.setAttribute('aria-hidden', 'true');
            if (overlay) overlay.setAttribute('aria-hidden', 'true');
            closeMobileSections();
        }

        document.querySelectorAll('.js-mobile-menu-open').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (document.body.classList.contains('ls-menu-open')) {
                    closeMobileMenu();
                } else {
                    openMobileMenu();
                }
            });
        });

        document.querySelectorAll('.js-mobile-menu-close').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                closeMobileMenu();
            });
        });

        menu.querySelectorAll('.js-mobile-section-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var section = btn.closest('.js-mobile-section');
                if (!section) return;

                var willOpen = !section.classList.contains('is-open');
                menu.querySelectorAll('.js-mobile-section.is-open').forEach(function(openSection) {
                    if (openSection !== section) {
                        openSection.classList.remove('is-open');
                        var openToggle = openSection.querySelector('.js-mobile-section-toggle');
                        if (openToggle) openToggle.setAttribute('aria-expanded', 'false');
                    }
                });

                section.classList.toggle('is-open', willOpen);
                btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
        });

        menu.querySelectorAll('.ls-mobile-link, .ls-mobile-section__body a').forEach(function(link) {
            link.addEventListener('click', function() {
                closeMobileMenu();
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.body.classList.contains('ls-menu-open')) {
                closeMobileMenu();
            }
        });
    }

    function initThemeToggle() {
        var body = document.body;
        var themeToggle = document.querySelector('.js-theme-toggle');
        if (!themeToggle) return;

        var storageKey = 'darkTheme';

        function enableDarkTheme() {
            body.classList.add('dt');
            themeToggle.classList.remove('fa-moon-o');
            themeToggle.classList.add('fa-sun-o');
            localStorage.setItem(storageKey, '1');
        }

        function disableDarkTheme() {
            body.classList.remove('dt');
            themeToggle.classList.remove('fa-sun-o');
            themeToggle.classList.add('fa-moon-o');
            localStorage.setItem(storageKey, '0');
        }

        if (localStorage.getItem(storageKey) === '1') {
            enableDarkTheme();
        } else {
            disableDarkTheme();
        }

        themeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            if (themeToggle.classList.contains('fa-moon-o')) {
                enableDarkTheme();
            } else {
                disableDarkTheme();
            }
        });
    }

    function loadHomeCarouselsAssets() {
        if (typeof window.lsInitHomeCarousels === 'function') {
            return Promise.resolve();
        }

        if (window.__lsHomeCarouselsLoading) {
            return window.__lsHomeCarouselsLoading;
        }

        var jsUrl = cfg('home_carousels_js', '');
        if (!jsUrl) {
            return Promise.resolve();
        }

        window.__lsHomeCarouselsLoading = new Promise(function(resolve, reject) {
            var cssUrl = cfg('home_carousels_css', '');
            if (cssUrl && !document.querySelector('link[data-ls-home-carousels]')) {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = cssUrl;
                link.setAttribute('data-ls-home-carousels', '1');
                document.head.appendChild(link);
            }

            var script = document.createElement('script');
            script.src = jsUrl;
            script.async = true;
            script.setAttribute('data-ls-home-carousels', '1');
            script.onload = function() {
                resolve();
            };
            script.onerror = function() {
                window.__lsHomeCarouselsLoading = null;
                reject(new Error('home-carousels load failed'));
            };
            document.head.appendChild(script);
        });

        return window.__lsHomeCarouselsLoading;
    }

    function initHomeCarousel() {
        if (!document.querySelector('[data-carou]')) {
            return;
        }

        loadHomeCarouselsAssets()
            .then(function() {
                if (typeof window.lsInitHomeCarousels === 'function') {
                    window.lsInitHomeCarousels();
                }
            })
            .catch(function() {});
    }

    function initHomeSectionTabs() {
        document.querySelectorAll('[data-home-section-type]').forEach(function(sect) {
            var tabs = sect.querySelector('[data-section-tabs]');
            var cards = sect.querySelector('[data-section-cards]');
            if (!tabs || !cards) return;

            var sectionType = sect.getAttribute('data-home-section-type');
            var sectionId = sect.getAttribute('data-home-section-id');
            var activeSort = null;

            tabs.querySelectorAll('[data-sort]').forEach(function(tab) {
                if (tab.classList.contains('is-active')) {
                    activeSort = tab.getAttribute('data-sort');
                }
            });
            if (!activeSort) activeSort = 'latest';

            function setActiveTab(sort) {
                tabs.querySelectorAll('[data-sort]').forEach(function(tab) {
                    tab.classList.toggle('is-active', tab.getAttribute('data-sort') === sort);
                });
            }

            function loadSort(sort) {
                if (!sectionType || !sectionId || sort === activeSort) return;
                activeSort = sort;
                setActiveTab(sort);
                cards.classList.add('is-loading');

                fetch('/api/home/sections/' + encodeURIComponent(sectionType) + '/' + encodeURIComponent(sectionId) + '/series?sort=' + encodeURIComponent(sort), {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    })
                    .then(function(r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data) {
                        cards.innerHTML = data && data.html ? data.html : '';
                    })
                    .catch(function() {
                        cards.innerHTML = '<p class="home-section-error">Не удалось загрузить список. Попробуйте ещё раз.</p>';
                    })
                    .finally(function() {
                        cards.classList.remove('is-loading');
                    });
            }

            tabs.querySelectorAll('[data-sort]').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    loadSort(tab.getAttribute('data-sort') || 'latest');
                });
                tab.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        loadSort(tab.getAttribute('data-sort') || 'latest');
                    }
                });
            });
        });
    }

    function initHomeBlockTabs() {
        document.querySelectorAll('[data-home-block-id]').forEach(function(sect) {
            var tabs = sect.querySelector('[data-section-tabs]');
            var cards = sect.querySelector('[data-section-cards]');
            if (!tabs || !cards) return;

            var blockId = sect.getAttribute('data-home-block-id');
            var activeSort = null;

            tabs.querySelectorAll('[data-sort]').forEach(function(tab) {
                if (tab.classList.contains('is-active')) {
                    activeSort = tab.getAttribute('data-sort');
                }
            });
            if (!activeSort) activeSort = 'latest';

            function setActiveTab(sort) {
                tabs.querySelectorAll('[data-sort]').forEach(function(tab) {
                    tab.classList.toggle('is-active', tab.getAttribute('data-sort') === sort);
                });
            }

            function loadSort(sort) {
                if (!blockId || sort === activeSort) return;
                activeSort = sort;
                setActiveTab(sort);
                cards.classList.add('is-loading');

                fetch('/api/home/blocks/' + encodeURIComponent(blockId) + '/series?sort=' + encodeURIComponent(sort), {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    })
                    .then(function(r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data) {
                        cards.innerHTML = data && data.html ? data.html : '';
                    })
                    .catch(function() {
                        cards.innerHTML = '<p class="home-section-error">Не удалось загрузить список. Попробуйте ещё раз.</p>';
                    })
                    .finally(function() {
                        cards.classList.remove('is-loading');
                    });
            }

            tabs.querySelectorAll('[data-sort]').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    loadSort(tab.getAttribute('data-sort') || 'latest');
                });
                tab.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        loadSort(tab.getAttribute('data-sort') || 'latest');
                    }
                });
            });
        });
    }

    function initHomeContentTypeTabs() {
        document.querySelectorAll('[data-home-content-type]').forEach(function(sect) {
            if (sect.getAttribute('data-home-section-type') || sect.getAttribute('data-home-block-id')) {
                return;
            }

            var tabs = sect.querySelector('[data-section-tabs]');
            var cards = sect.querySelector('[data-section-cards]');
            if (!tabs || !cards) return;

            var contentType = sect.getAttribute('data-home-content-type');
            var activeSort = null;

            tabs.querySelectorAll('[data-sort]').forEach(function(tab) {
                if (tab.classList.contains('is-active')) {
                    activeSort = tab.getAttribute('data-sort');
                }
            });
            if (!activeSort) activeSort = 'latest';

            function setActiveTab(sort) {
                tabs.querySelectorAll('[data-sort]').forEach(function(tab) {
                    tab.classList.toggle('is-active', tab.getAttribute('data-sort') === sort);
                });
            }

            function loadSort(sort) {
                if (!contentType || sort === activeSort) return;
                activeSort = sort;
                setActiveTab(sort);
                cards.classList.add('is-loading');

                fetch('/api/home/content-types/' + encodeURIComponent(contentType) + '/series?sort=' + encodeURIComponent(sort), {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    })
                    .then(function(r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data) {
                        cards.innerHTML = data && data.html ? data.html : '';
                    })
                    .catch(function() {
                        cards.innerHTML = '<p class="home-section-error">Не удалось загрузить список. Попробуйте ещё раз.</p>';
                    })
                    .finally(function() {
                        cards.classList.remove('is-loading');
                    });
            }

            tabs.querySelectorAll('[data-sort]').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    loadSort(tab.getAttribute('data-sort') || 'latest');
                });
                tab.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        loadSort(tab.getAttribute('data-sort') || 'latest');
                    }
                });
            });
        });
    }

    function initWatchlistDropdown() {
        document.querySelectorAll('[data-watchlist-toggle]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var el = btn.closest('[data-watchlist-root]');
                if (!el) return;
                document.querySelectorAll('[data-watchlist-root].is-open').forEach(function(open) {
                    if (open !== el) open.classList.remove('is-open');
                });
                el.classList.toggle('is-open');
            });
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('[data-watchlist-root]')) return;
            document.querySelectorAll('[data-watchlist-root].is-open').forEach(function(el) {
                el.classList.remove('is-open');
            });
        });
    }

    function updateWatchlistLabel(lists, listIds) {
        var label = document.querySelector('[data-watchlist-label]');
        if (!label) return;

        if (!lists || !lists.length) {
            label.textContent = 'Добавить в список';
            return;
        }

        var active = lists.filter(function(l) {
            return listIds.indexOf(l.id) !== -1;
        });

        if (active.length === 0) {
            label.textContent = 'Добавить в список';
        } else if (active.length === 1) {
            label.textContent = active[0].name;
        } else {
            label.textContent = 'В списках (' + active.length + ')';
        }
    }

    function renderWatchlistMenu(menu, lists, listIds, loggedIn, seriesId) {
        if (!menu) return;
        menu.innerHTML = '';

        if (!loggedIn) {
            var hint = document.createElement('p');
            hint.className = 'serial-list-dropdown__hint';
            hint.textContent = 'Войдите, чтобы сохранять списки';
            var loginBtn = document.createElement('button');
            loginBtn.type = 'button';
            loginBtn.className = 'dontusebuttonclass watchlist-btn';
            loginBtn.textContent = 'Войти';
            loginBtn.addEventListener('click', function() {
                if (window.lordSerialOpenAuth) window.lordSerialOpenAuth('login');
            });
            menu.appendChild(hint);
            menu.appendChild(loginBtn);
            return;
        }

        lists.forEach(function(list) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dontusebuttonclass watchlist-btn';
            btn.setAttribute('data-list-id', String(list.id));
            btn.textContent = list.name;
            if (listIds.indexOf(list.id) !== -1) {
                btn.classList.add('active');
            }
            btn.addEventListener('click', function() {
                postJson('/api/series/' + encodeURIComponent(seriesId) + '/watchlist', {
                        list_id: list.id,
                        action: 'toggle',
                    })
                    .then(function(r) {
                        if (r.status === 401) {
                            if (window.lordSerialOpenAuth) window.lordSerialOpenAuth('login');
                            return null;
                        }
                        return r.json();
                    })
                    .then(function(data) {
                        if (!data) return;
                        renderWatchlistMenu(menu, lists, data.list_ids || [], true, seriesId);
                        updateWatchlistLabel(lists, data.list_ids || []);
                    })
                    .catch(function() {
                        flashNotice('Не удалось обновить список. Попробуйте ещё раз.', true);
                    });
            });
            menu.appendChild(btn);
        });
    }

    function initSeriesEngagement() {
        var root = document.querySelector('.serial-vote[data-series-id]');
        if (!root) return;

        var seriesId = root.getAttribute('data-series-id');
        if (!seriesId) return;

        var menu = document.querySelector('[data-watchlist-menu]');

        function updateUserRating(userRating) {
            var mark = document.querySelector('.serial-poster-card__mark');
            if (!mark) return;

            var ratingEl = mark.querySelector('[data-user-rating]');
            if (userRating) {
                if (ratingEl) ratingEl.textContent = userRating;
                mark.hidden = false;
            } else {
                mark.hidden = true;
            }
        }

        function refreshEngagement() {
            fetch('/api/series/' + encodeURIComponent(seriesId) + '/engagement', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var likes = root.querySelector('[data-likes]');
                    var dislikes = root.querySelector('[data-dislikes]');
                    if (likes) likes.textContent = data.likes;
                    if (dislikes) dislikes.textContent = data.dislikes;
                    updateUserRating(data.user_rating);

                    root.querySelectorAll('.vote-btn').forEach(function(btn) {
                        btn.classList.remove('active-like', 'active-dislike');
                        var v = parseInt(btn.getAttribute('data-vote'), 10);
                        if (data.user_vote === v) {
                            btn.classList.add(v === 1 ? 'active-like' : 'active-dislike');
                        }
                    });

                    var lists = data.lists || [];
                    var listIds = data.list_ids || [];
                    renderWatchlistMenu(menu, lists, listIds, data.logged_in, seriesId);
                    updateWatchlistLabel(lists, listIds);

                    var favBtn = document.querySelector('[data-favourite-toggle][data-series-id="' + seriesId + '"]');
                    if (favBtn) {
                        var isFav = !!data.is_favourite;
                        if (!data.logged_in && isLocalFavourite(seriesId)) {
                            isFav = true;
                        }
                        updateFavouriteButton(favBtn, isFav);
                        setLocalFavourite(seriesId, isFav);
                    }
                })
                .catch(function() {
                    flashNotice('Не удалось обновить данные. Обновите страницу.', true);
                });
        }

        root.querySelectorAll('.vote-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                postJson('/api/series/' + encodeURIComponent(seriesId) + '/vote', {
                        value: parseInt(btn.getAttribute('data-vote'), 10),
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data && data.ok) {
                            var likes = root.querySelector('[data-likes]');
                            var dislikes = root.querySelector('[data-dislikes]');
                            if (likes) likes.textContent = data.likes;
                            if (dislikes) dislikes.textContent = data.dislikes;
                            updateUserRating(data.user_rating);
                        }
                        refreshEngagement();
                    })
                    .catch(function() {
                        flashNotice('Не удалось сохранить голос. Попробуйте ещё раз.', true);
                    });
            });
        });

        refreshEngagement();
        document.addEventListener('lordserial:auth-login', refreshEngagement);

        var favBtn = document.querySelector('[data-favourite-toggle][data-series-id="' + seriesId + '"]');
        if (favBtn && cfgBool('favourites_enabled', true)) {
            if (isLocalFavourite(seriesId)) {
                updateFavouriteButton(favBtn, true);
            }

            favBtn.addEventListener('click', function() {
                var nextState = !favBtn.classList.contains('is-active');

                // Guests: update UI/localStorage immediately so избранное works
                // even if session cookies are missing on plain HTTP.
                if (!isSiteLoggedIn()) {
                    updateFavouriteButton(favBtn, nextState);
                    setLocalFavourite(seriesId, nextState);
                }

                postJson(seriesApiPath(seriesId) + '/favourite', guestLibraryPayload({
                        active: nextState,
                    }))
                    .then(readJsonResponse)
                    .then(function(res) {
                        if (!res.ok) {
                            if (!isSiteLoggedIn()) {
                                // Local state already applied for guests.
                                return;
                            }
                            var errMsg = (res.data && res.data.message) || '';
                            if (res.status === 419 || /419|csrf/i.test(errMsg)) {
                                flashNotice(cfg('ui_msg_session_expired', 'Сессия истекла. Обновите страницу и попробуйте снова.'), true);
                            } else {
                                flashNotice('Не удалось обновить избранное.', true);
                            }
                            return;
                        }
                        var isFav = !!(res.data && res.data.is_favourite);
                        // For guests keep the requested state; server may lag on flaky sessions.
                        if (!isSiteLoggedIn()) {
                            isFav = nextState;
                        }
                        updateFavouriteButton(favBtn, isFav);
                        setLocalFavourite(seriesId, isFav);
                        if (!isSiteLoggedIn()) {
                            updateHeaderFavouritesCount(getLocalFavourites().length);
                        } else if (res.data && typeof res.data.count === 'number') {
                            updateHeaderFavouritesCount(res.data.count);
                        }
                    })
                    .catch(function() {
                        if (!isSiteLoggedIn()) return;
                        flashNotice('Не удалось обновить избранное. Попробуйте ещё раз.', true);
                    });
            });
        }
    }

    function initProfileForms() {
        var flash = document.getElementById('profileFlash');

        document.querySelectorAll(
            '.profile-form, .profile-new-list, .profile-rename-list, .profile-delete-list, .profile-logout'
        ).forEach(function(form) {
            bindAjaxForm(form, {
                feedback: form.querySelector('[data-form-feedback]') || flash,
                onSuccess: function(data, form) {
                    if (data.profile) {
                        document.querySelectorAll('.profile-sidebar__name').forEach(function(el) {
                            el.textContent = data.profile.name;
                        });
                        var initial = document.querySelector('.profile-avatar');
                        if (initial && data.profile.name) {
                            initial.textContent = data.profile.name.charAt(0).toUpperCase();
                        }
                    }
                    if (form.querySelector('input[name="current_password"]')) {
                        form.reset();
                    }
                    if (data.reload || data.redirect) return;
                    if (flash && data.message) {
                        flash.hidden = false;
                        flash.textContent = data.message;
                        flash.className = 'profile-flash profile-flash--success';
                    }
                },
            });
        });
    }

    function initProfileWatchlistRemove() {
        var flash = document.getElementById('profileFlash');

        document.querySelectorAll('[data-watchlist-remove]').forEach(function(btn) {
            if (btn.getAttribute('data-bound') === '1') return;
            btn.setAttribute('data-bound', '1');

            btn.addEventListener('click', function() {
                var listId = btn.getAttribute('data-list-id');
                var seriesId = btn.getAttribute('data-series-id');
                if (!listId || !seriesId) return;

                btn.disabled = true;

                postJson('/profile/lists/' + encodeURIComponent(listId) + '/remove-item', {
                        series_id: parseInt(seriesId, 10),
                    })
                    .then(readJsonResponse)
                    .then(function(res) {
                        if (!res.ok || (res.data && res.data.ok === false)) {
                            if (flash) {
                                flash.hidden = false;
                                flash.textContent = parseApiErrors(res.data || {});
                                flash.className = 'profile-flash profile-flash--error';
                            }
                            btn.disabled = false;
                            return;
                        }

                        var data = res.data || {};
                        var item = btn.closest('.profile-watchlist-item');
                        var block = btn.closest('[data-watchlist-id]');

                        if (item) {
                            item.classList.add('is-removing');
                            window.setTimeout(function() {
                                item.remove();
                                if (block) {
                                    var itemsWrap = block.querySelector('[data-watchlist-items]');
                                    var emptyEl = block.querySelector('[data-watchlist-empty]');
                                    var hasItems = itemsWrap && itemsWrap.querySelector('.profile-watchlist-item');
                                    if (emptyEl) emptyEl.hidden = !!hasItems;
                                }
                            }, 180);
                        }

                        if (block) {
                            var countEl = block.querySelector('[data-watchlist-count]');
                            if (countEl && data.items_count != null) {
                                countEl.textContent = data.items_count;
                            }
                        }

                        if (data.stats && data.stats.items != null) {
                            var statEl = document.querySelector('[data-profile-stat-items]');
                            if (statEl) statEl.textContent = data.stats.items;
                        }

                        if (flash && data.message) {
                            flash.hidden = false;
                            flash.textContent = data.message;
                            flash.className = 'profile-flash profile-flash--success';
                        }
                    })
                    .catch(function() {
                        if (flash) {
                            flash.hidden = false;
                            flash.textContent = 'Не удалось убрать сериал из списка.';
                            flash.className = 'profile-flash profile-flash--error';
                        }
                        btn.disabled = false;
                    });
            });
        });
    }

    function initProfileTabs() {
        var tabs = document.querySelectorAll('[data-profile-tab]');
        if (!tabs.length) return;

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var name = tab.getAttribute('data-profile-tab');
                tabs.forEach(function(t) { t.classList.toggle('is-active', t === tab); });
                document.querySelectorAll('[data-profile-panel]').forEach(function(panel) {
                    var active = panel.getAttribute('data-profile-panel') === name;
                    panel.hidden = !active;
                    panel.classList.toggle('is-active', active);
                });
            });
        });

        var hash = (window.location.hash || '').replace('#', '');
        if (hash) {
            var targetTab = document.querySelector('[data-profile-tab="' + hash + '"]');
            if (targetTab) targetTab.click();
        }
    }

    function initEngagementTabs() {
        var root = document.getElementById('engagementSection');
        if (!root) return;
        var tabs = root.querySelectorAll('[data-engagement-tab]');
        var panels = root.querySelectorAll('[data-engagement-panel]');
        if (!tabs.length || !panels.length) return;

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var name = tab.getAttribute('data-engagement-tab');
                if (!name) return;
                tabs.forEach(function (btn) {
                    var active = btn === tab;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panels.forEach(function (panel) {
                    var match = panel.getAttribute('data-engagement-panel') === name;
                    panel.classList.toggle('is-active', match);
                    panel.setAttribute('aria-hidden', match ? 'false' : 'true');
                    // Keep markup in DOM for SEO — do not set the HTML hidden attribute.
                    if (panel.hasAttribute('hidden')) panel.removeAttribute('hidden');
                });
            });
        });
    }

    function initReviews() {
        var section = document.getElementById('reviewsSection');
        if (!section) return;

        var seriesId = section.getAttribute('data-series-id');
        if (!seriesId) {
            var engagement = document.getElementById('engagementSection');
            seriesId = engagement && engagement.getAttribute('data-series-id');
        }
        if (!seriesId) return;

        var listEl = section.querySelector('[data-reviews-list]');
        var noticeEl = section.querySelector('[data-reviews-notice]');
        var rootForm = section.querySelector('[data-review-form="root"]');
        var countEl = section.querySelector('[data-reviews-count]');
        var sortEl = section.querySelector('[data-reviews-sort]');
        var currentSort = sortEl && sortEl.getAttribute('data-reviews-sort-current') === 'rating' ? 'rating' : 'date';
        var reviewsSsr = listEl && listEl.getAttribute('data-reviews-ssr') === '1';
        var selectedRating = 0;
        var hasOwnReview = !!section.querySelector('[data-reviews-own-hint]');
        var minBodyLength = Number(cfg('reviews_body_min_length', 20)) || 20;
        var linkPattern = /(?:https?:\/\/|ftp:\/\/|www\.)\S+|mailto:\S+|\[(?:url|link)(?:=|\])|<a\s[\s\S]*?>|(?<![\w@\/])(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:com|ru|net|org|info|biz|me|io|tv|cc|su|ua|by|kz)(?:\/\S*)?/i;

        function flashNotice(text, isError) {
            if (!noticeEl) return;
            if (!text) {
                noticeEl.hidden = true;
                noticeEl.textContent = '';
                return;
            }
            noticeEl.textContent = text;
            noticeEl.className = 'comments-notice' + (isError ? ' comments-notice--error' : ' comments-notice--success');
            noticeEl.hidden = false;
            window.clearTimeout(flashNotice._t);
            flashNotice._t = window.setTimeout(function () {
                noticeEl.hidden = true;
            }, 4500);
        }

        function markOwnReview(message) {
            hasOwnReview = true;
            var compose = section.querySelector('[data-reviews-compose]') || section.querySelector('.reviews-compose');
            if (!compose) return;
            if (rootForm) rootForm.hidden = true;
            var hint = compose.querySelector('[data-reviews-own-hint]');
            if (!hint) {
                hint = document.createElement('p');
                hint.className = 'review-login-hint';
                hint.setAttribute('data-reviews-own-hint', '1');
                compose.insertBefore(hint, compose.firstChild);
            }
            hint.hidden = false;
            hint.textContent = message || cfg('reviews_msg_already_exists', 'Вы уже оставили рецензию на этот сериал.');
        }

        function reviewEffectiveBody(text) {
            return String(text || '')
                .replace(/\[spoiler\]([\s\S]*?)\[\/spoiler\]/gi, '$1')
                .trim();
        }

        function insertSpoilerTag(textarea) {
            if (!textarea) return;
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var val = textarea.value;
            var open = '[spoiler]';
            var close = '[/spoiler]';

            if (start !== end) {
                textarea.value = val.slice(0, start) + open + val.slice(start, end) + close + val.slice(end);
                textarea.selectionStart = start + open.length;
                textarea.selectionEnd = end + open.length;
            } else {
                textarea.value = val.slice(0, start) + open + close + val.slice(start);
                textarea.selectionStart = textarea.selectionEnd = start + open.length;
            }
            textarea.focus();
        }

        function buildReviewToolbar() {
            var toolbar = document.createElement('div');
            toolbar.className = 'comment-form__toolbar';
            toolbar.setAttribute('data-review-toolbar', '1');

            var spoilerBtn = document.createElement('button');
            spoilerBtn.type = 'button';
            spoilerBtn.className = 'comment-form__tool dontusebuttonclass';
            spoilerBtn.setAttribute('data-review-spoiler', '1');
            spoilerBtn.title = cfg('reviews_ui_spoiler', 'Спойлер');
            spoilerBtn.innerHTML = '<span class="fa fa-eye" aria-hidden="true"></span> ' + cfg('reviews_ui_spoiler', 'Спойлер');
            toolbar.appendChild(spoilerBtn);
            return toolbar;
        }

        function enhanceReviewForm(form) {
            if (!form) return;
            var textarea = form.querySelector('textarea[name="body"]');
            if (!textarea || form.querySelector('[data-review-toolbar]')) return;
            textarea.parentNode.insertBefore(buildReviewToolbar(), textarea);
        }

        function paintStars(hoverValue) {
            var stars = section.querySelectorAll('[data-review-star]');
            var active = hoverValue || selectedRating || 0;
            stars.forEach(function (star) {
                var n = parseInt(star.getAttribute('data-review-star'), 10) || 0;
                star.classList.toggle('is-active', !hoverValue && selectedRating > 0 && n <= selectedRating);
                star.classList.toggle('is-hover', !!hoverValue && n <= hoverValue);
            });
            var hidden = section.querySelector('[data-review-rating-value]');
            if (hidden) hidden.value = selectedRating ? String(selectedRating) : '';
            var label = section.querySelector('[data-review-rating-label]');
            if (label) label.textContent = selectedRating ? (selectedRating + '/10') : '';
        }

        function setRating(value) {
            selectedRating = value || 0;
            paintStars(0);
        }

        function starsHtml(rating) {
            var html = '<span class="review-stars" aria-label="' + rating + ' из 10">';
            for (var i = 1; i <= 10; i++) {
                html += '<span class="review-stars__star' + (i <= rating ? ' review-stars__star--filled' : '') + '" aria-hidden="true">★</span>';
            }
            return html + '</span>';
        }

        function renderReviewBody(raw) {
            var body = document.createElement('div');
            body.className = 'review-body comment-body';
            var text = String(raw || '');
            var parts = text.split(/(\[spoiler\][\s\S]*?\[\/spoiler\])/gi);
            parts.forEach(function (part) {
                var match = part.match(/^\[spoiler\]([\s\S]*)\[\/spoiler\]$/i);
                if (match) {
                    var spoiler = document.createElement('span');
                    spoiler.className = 'comment-spoiler';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'comment-spoiler__toggle dontusebuttonclass';
                    btn.setAttribute('aria-expanded', 'false');
                    btn.textContent = cfg('reviews_ui_spoiler_reveal', 'Спойлер');
                    var hidden = document.createElement('span');
                    hidden.className = 'comment-spoiler__text';
                    hidden.hidden = true;
                    hidden.textContent = match[1];
                    spoiler.appendChild(btn);
                    spoiler.appendChild(hidden);
                    body.appendChild(spoiler);
                    return;
                }
                if (part) body.appendChild(document.createTextNode(part));
            });
            return body;
        }

        function renderReview(item) {
            var article = document.createElement('article');
            article.className = 'review-item' + (item.is_editorial ? ' review-item--editorial' : '');
            article.setAttribute('data-review-id', String(item.id || ''));

            var rating = Math.max(1, Math.min(10, parseInt(item.rating, 10) || 0));
            var author = item.author || cfg('reviews_label_user', 'Пользователь');
            var initial = author ? author.charAt(0).toUpperCase() : '?';
            var hue = 0;
            for (var i = 0; i < author.length; i++) hue = author.charCodeAt(i) + ((hue << 5) - hue);
            hue = Math.abs(hue) % 360;

            article.innerHTML =
                '<div class="review-item__inner">' +
                    '<div class="comment-avatar" aria-hidden="true" style="--avatar-hue: ' + hue + '">' + initial + '</div>' +
                    '<div class="review-content">' +
                        '<header class="review-head">' +
                            '<div class="review-head__meta">' +
                                '<strong class="review-author"></strong>' +
                                (item.is_editorial ? '<span class="review-editorial-badge">' + cfg('reviews_ui_editorial', 'Редакция') + '</span>' : '') +
                                '<time class="review-date"></time>' +
                            '</div>' +
                            '<div class="review-rating" title="' + rating + '/10">' +
                                starsHtml(rating) +
                                '<span class="review-rating__value">' + rating + '/10</span>' +
                            '</div>' +
                        '</header>' +
                    '</div>' +
                '</div>';

            article.querySelector('.review-author').textContent = author;
            article.querySelector('.review-date').textContent = item.created_at || '';
            article.querySelector('.review-content').appendChild(renderReviewBody(item.body || ''));
            return article;
        }

        function updateReviewsCount(countOrItems) {
            var count = Array.isArray(countOrItems) ? countOrItems.length : (parseInt(countOrItems, 10) || 0);
            if (countEl) {
                if (count <= 0) {
                    countEl.hidden = true;
                    countEl.textContent = '';
                } else {
                    var mod10 = count % 10;
                    var mod100 = count % 100;
                    var label;
                    if (mod100 >= 11 && mod100 <= 14) label = count + ' рецензий';
                    else if (mod10 === 1) label = count + ' рецензия';
                    else if (mod10 >= 2 && mod10 <= 4) label = count + ' рецензии';
                    else label = count + ' рецензий';
                    countEl.hidden = false;
                    countEl.textContent = /^\(/.test(String(countEl.textContent || '')) || countEl.closest('.comms-t')
                        ? ('(' + count + ')')
                        : label;
                }
            }
            var tabCount = document.querySelector('[data-engagement-reviews-count]');
            if (tabCount) {
                if (count > 0) {
                    tabCount.textContent = '(' + count + ')';
                    tabCount.hidden = false;
                } else {
                    tabCount.hidden = true;
                }
            }
        }

        function setReviewsSort(nextSort) {
            currentSort = nextSort === 'rating' ? 'rating' : 'date';
            if (!sortEl) return;
            sortEl.querySelectorAll('[data-reviews-sort-value]').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-reviews-sort-value') === currentSort);
            });
        }

        function loadReviews() {
            if (!listEl) return;
            listEl.removeAttribute('data-reviews-ssr');
            listEl.innerHTML = '<p class="comment-loading">' + cfg('reviews_ui_loading', 'Загрузка рецензий...') + '</p>';
            fetch('/api/series/' + encodeURIComponent(seriesId) + '/reviews?sort=' + encodeURIComponent(currentSort), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('load failed');
                    return r.json();
                })
                .then(function (data) {
                    listEl.innerHTML = '';
                    if (data.sort) setReviewsSort(data.sort);
                    if (data.has_own_review) {
                        markOwnReview(data.own_review_message || cfg(
                            data.own_review_pending ? 'reviews_msg_pending' : 'reviews_msg_already_exists',
                            data.own_review_pending ? 'Рецензия отправлена на модерацию.' : 'Вы уже оставили рецензию на этот сериал.'
                        ));
                    }
                    var items = data.items || [];
                    updateReviewsCount(typeof data.total === 'number' ? data.total : items.length);
                    if (!items.length) {
                        listEl.innerHTML = '<p class="comment-empty">' + cfg('reviews_ui_empty', 'Пока нет рецензий. Будьте первым!') + '</p>';
                        return;
                    }
                    items.forEach(function (item) {
                        listEl.appendChild(renderReview(item));
                    });
                })
                .catch(function () {
                    listEl.innerHTML = '<p class="comment-empty">' + cfg('reviews_ui_load_error', 'Не удалось загрузить рецензии.') + '</p>';
                });
        }

        if (rootForm) enhanceReviewForm(rootForm);
        setRating(0);

        section.addEventListener('mouseover', function (e) {
            var star = e.target.closest('[data-review-star]');
            if (!star || !section.contains(star)) return;
            paintStars(parseInt(star.getAttribute('data-review-star'), 10) || 0);
        });
        section.addEventListener('mouseout', function (e) {
            var ratingBox = e.target.closest('[data-review-rating]');
            if (!ratingBox || !section.contains(ratingBox)) return;
            if (e.relatedTarget && ratingBox.contains(e.relatedTarget)) return;
            paintStars(0);
        });

        section.addEventListener('click', function (e) {
            var star = e.target.closest('[data-review-star]');
            if (star && section.contains(star)) {
                setRating(parseInt(star.getAttribute('data-review-star'), 10) || 0);
                return;
            }

            var spoilerBtn = e.target.closest('[data-review-spoiler]');
            if (spoilerBtn && section.contains(spoilerBtn)) {
                var form = spoilerBtn.closest('[data-review-form]');
                var textarea = form && form.querySelector('textarea[name="body"]');
                if (textarea) insertSpoilerTag(textarea);
                return;
            }

            var toggleBtn = e.target.closest('.comment-spoiler__toggle');
            if (toggleBtn && section.contains(toggleBtn)) {
                var spoiler = toggleBtn.closest('.comment-spoiler');
                var text = spoiler && spoiler.querySelector('.comment-spoiler__text');
                if (!text) return;
                var reveal = cfg('reviews_ui_spoiler_reveal', 'Спойлер');
                var hide = cfg('reviews_ui_spoiler_hide', 'Скрыть спойлер');
                var expanded = text.hidden;
                text.hidden = !expanded;
                toggleBtn.textContent = expanded ? hide : reveal;
                toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                return;
            }

            var submitBtn = e.target.closest('[data-review-submit]');
            if (!submitBtn || !section.contains(submitBtn)) return;
            if (hasOwnReview) {
                flashNotice(cfg('reviews_msg_already_exists', 'Вы уже оставили рецензию на этот сериал.'), true);
                return;
            }
            var submitForm = submitBtn.closest('[data-review-form]');
            if (!submitForm) return;

            var bodyInput = submitForm.querySelector('textarea[name="body"]');
            var body = bodyInput ? bodyInput.value.trim() : '';
            if (!selectedRating) {
                flashNotice(cfg('reviews_msg_rating_required', 'Поставьте оценку от 1 до 10.'), true);
                return;
            }
            if (linkPattern.test(body)) {
                flashNotice(cfg('reviews_msg_links_forbidden', 'Ссылки в рецензиях запрещены.'), true);
                return;
            }
            if (reviewEffectiveBody(body).length < minBodyLength) {
                flashNotice(cfg('reviews_msg_too_short', 'Рецензия слишком короткая.'), true);
                return;
            }

            submitBtn.disabled = true;
            csrfReadyPromise = null;
            refreshCsrfToken()
                .then(function () {
                    return postJson('/api/series/' + encodeURIComponent(seriesId) + '/reviews', {
                        body: body,
                        rating: selectedRating,
                    });
                })
                .then(readJsonResponse)
                .then(function (res) {
                    if (!res.ok) {
                        if (res.status === 419 || /419|csrf/i.test((res.data && res.data.message) || '')) {
                            flashNotice(cfg('ui_msg_session_expired', 'Сессия истекла. Обновите страницу и попробуйте снова.'), true);
                            return;
                        }
                        if (res.data && res.data.has_own_review) {
                            markOwnReview(res.data.message);
                        }
                        flashNotice(humanizeApiMessage((res.data && (res.data.message || parseApiErrors(res.data))) || '') || cfg('reviews_msg_submit_failed', 'Не удалось отправить рецензию.'), true);
                        return;
                    }
                    flashNotice((res.data && res.data.message) || cfg('reviews_msg_published', 'Рецензия опубликована.'), false);
                    if (bodyInput) bodyInput.value = '';
                    setRating(0);
                    markOwnReview((res.data && res.data.message) || cfg('reviews_msg_already_exists', 'Вы уже оставили рецензию на этот сериал.'));
                    if (!(res.data && res.data.pending)) {
                        loadReviews();
                    }
                })
                .catch(function () {
                    flashNotice(cfg('reviews_msg_submit_failed', 'Не удалось отправить рецензию.'), true);
                })
                .finally(function () {
                    submitBtn.disabled = false;
                });
        });

        if (sortEl) {
            sortEl.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-reviews-sort-value]');
                if (!btn || !sortEl.contains(btn)) return;
                var nextSort = btn.getAttribute('data-reviews-sort-value');
                if (!nextSort || nextSort === currentSort) return;
                currentSort = nextSort === 'rating' ? 'rating' : 'date';
                setReviewsSort(currentSort);
                loadReviews();
            });
        }

        if (!reviewsSsr) {
            loadReviews();
        }
    }

    function initComments() {
        var section = document.getElementById('commentsSection');
        if (!section) return;

        var seriesId = section.getAttribute('data-series-id');
        if (!seriesId) return;

        function isCommentsLoggedIn() {
            return !!section.querySelector('[data-logged-in]');
        }
        var listEl = section.querySelector('[data-comments-list]');
        var noticeEl = section.querySelector('[data-comments-notice]');
        var rootForm = section.querySelector('[data-comment-form="root"]');
        var countEl = section.querySelector('[data-comments-count]');
        var sortEl = section.querySelector('[data-comments-sort]');
        var currentSort = sortEl && sortEl.getAttribute('data-comments-sort-current') === 'rating' ? 'rating' : 'date';
        var commentsSsr = listEl && listEl.getAttribute('data-comments-ssr') === '1';
        var openReplyId = null;
        var spoilerPattern = /\[spoiler\][\s\S]*?\[\/spoiler\]/gi;
        var linkPattern = /(?:https?:\/\/|ftp:\/\/|www\.)\S+|mailto:\S+|\[(?:url|link)(?:=|\])|<a\s[\s\S]*?>|(?<![\w@\/])(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:com|ru|net|org|info|biz|me|io|tv|cc|su|ua|by|kz)(?:\/\S*)?/i;

        function commentHasLink(text) {
            return linkPattern.test(text || '');
        }

        function commentEffectiveBody(text) {
            return String(text || '')
                .replace(/\[spoiler\]([\s\S]*?)\[\/spoiler\]/gi, '$1')
                .trim();
        }

        function insertSpoilerTag(textarea) {
            if (!textarea) return;
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var val = textarea.value;
            var open = '[spoiler]';
            var close = '[/spoiler]';

            if (start !== end) {
                textarea.value = val.slice(0, start) + open + val.slice(start, end) + close + val.slice(end);
                textarea.selectionStart = start + open.length;
                textarea.selectionEnd = end + open.length;
            } else {
                textarea.value = val.slice(0, start) + open + close + val.slice(start);
                textarea.selectionStart = textarea.selectionEnd = start + open.length;
            }

            textarea.focus();
        }

        function buildCommentToolbar() {
            var toolbar = document.createElement('div');
            toolbar.className = 'comment-form__toolbar';
            toolbar.setAttribute('data-comment-toolbar', '1');

            var spoilerBtn = document.createElement('button');
            spoilerBtn.type = 'button';
            spoilerBtn.className = 'comment-form__tool dontusebuttonclass';
            spoilerBtn.setAttribute('data-comment-spoiler', '1');
            spoilerBtn.title = cfg('comments_ui_spoiler', 'Спойлер');
            spoilerBtn.innerHTML = '<span class="fa fa-eye" aria-hidden="true"></span> ' + cfg('comments_ui_spoiler', 'Спойлер');
            toolbar.appendChild(spoilerBtn);

            return toolbar;
        }

        function enhanceCommentForm(form) {
            if (!form) return;
            var textarea = form.querySelector('textarea[name="body"]');
            if (!textarea || form.querySelector('[data-comment-toolbar]')) return;

            var toolbar = buildCommentToolbar();
            textarea.parentNode.insertBefore(toolbar, textarea);
        }

        function renderCommentBody(raw) {
            var body = document.createElement('div');
            body.className = 'comment-body';

            var text = String(raw || '');
            var lastIndex = 0;
            var match;

            spoilerPattern.lastIndex = 0;
            while ((match = spoilerPattern.exec(text)) !== null) {
                if (match.index > lastIndex) {
                    body.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
                }

                var spoiler = document.createElement('span');
                spoiler.className = 'comment-spoiler';

                var toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'comment-spoiler__toggle dontusebuttonclass';
                toggle.setAttribute('aria-expanded', 'false');
                toggle.textContent = cfg('comments_ui_spoiler_reveal', 'Спойлер');

                var content = document.createElement('span');
                content.className = 'comment-spoiler__text';
                content.hidden = true;
                content.textContent = match[0]
                    .replace(/^\[spoiler\]/i, '')
                    .replace(/\[\/spoiler\]$/i, '');

                spoiler.appendChild(toggle);
                spoiler.appendChild(content);
                body.appendChild(spoiler);
                lastIndex = spoilerPattern.lastIndex;
            }

            if (lastIndex < text.length) {
                body.appendChild(document.createTextNode(text.slice(lastIndex)));
            }

            return body;
        }

        function authorInitial(name) {
            var n = (name || '').trim();
            if (!n || n.toLowerCase() === String(cfg('comments_label_anonymous', 'аноним')).toLowerCase()) return '?';
            return n.charAt(0).toUpperCase();
        }

        function authorHue(name) {
            var hash = 0;
            var s = name || '';
            for (var i = 0; i < s.length; i++) {
                hash = s.charCodeAt(i) + ((hash << 5) - hash);
            }
            return Math.abs(hash) % 360;
        }

        function pluralComments(n) {
            var mod10 = n % 10;
            var mod100 = n % 100;
            if (mod100 >= 11 && mod100 <= 14) return n + ' комментариев';
            if (mod10 === 1) return n + ' комментарий';
            if (mod10 >= 2 && mod10 <= 4) return n + ' комментария';
            return n + ' комментариев';
        }

        function countComments(items) {
            var total = 0;
            (items || []).forEach(function walk(c) {
                total += 1;
                if (c.children && c.children.length) c.children.forEach(walk);
            });
            return total;
        }

        function updateCommentsCount(items) {
            if (!countEl) return;
            var total = countComments(items);
            if (total > 0) {
                countEl.textContent = pluralComments(total);
                countEl.hidden = false;
            } else {
                countEl.hidden = true;
            }
        }

        function showNotice(msg, isError) {
            if (!noticeEl) return;
            if (!msg) {
                noticeEl.hidden = true;
                noticeEl.textContent = '';
                return;
            }
            noticeEl.textContent = msg;
            noticeEl.className = 'comments-notice' + (isError ? ' comments-notice--error' : ' comments-notice--success');
            noticeEl.hidden = false;
        }

        function parseErrors(payload) {
            return parseApiErrors(payload);
        }

        function submitComment(form, parentId) {
            var bodyInput = form.querySelector('textarea[name="body"]');
            var body = bodyInput ? bodyInput.value.trim() : '';
            if (commentEffectiveBody(body).length < Number(cfg('comments_body_min_length', 2))) {
                showNotice(cfg('comments_msg_too_short', 'Комментарий слишком короткий.'), true);
                return Promise.resolve();
            }
            if (commentHasLink(body)) {
                showNotice(cfg('comments_msg_links_forbidden', 'Ссылки в комментариях запрещены.'), true);
                return Promise.resolve();
            }

            var payload = { body: body };
            if (parentId) payload.parent_id = parentId;

            if (!isCommentsLoggedIn()) {
                var anon = form.querySelector('input[name="is_anonymous"]');
                var guestName = form.querySelector('input[name="guest_name"]');
                payload.is_anonymous = anon && anon.checked;
                payload.guest_name = guestName ? guestName.value.trim() : '';
            }

            var submitBtn = form.querySelector('[data-comment-submit]');
            if (submitBtn) submitBtn.disabled = true;

            return postJson('/api/series/' + encodeURIComponent(seriesId) + '/comments', payload)
                .then(readJsonResponse)
                .then(function(res) {
                    if (!res.ok) {
                        showNotice(parseApiErrors(res.data), true);
                        return;
                    }
                    showNotice(res.data.message || 'Готово', false);
                    if (bodyInput) bodyInput.value = '';
                    if (!isCommentsLoggedIn()) {
                        var guestNameInput = form.querySelector('input[name="guest_name"]');
                        if (guestNameInput) guestNameInput.value = '';
                    }
                    closeReplyForms();
                    if (!res.data.pending) {
                        loadComments();
                    }
                })
                .catch(function() {
                    showNotice(cfg('comments_msg_submit_failed', 'Не удалось отправить комментарий.'), true);
                })
                .finally(function() {
                    if (submitBtn) submitBtn.disabled = false;
                });
        }

        function openReplyForm(commentId, contentEl) {
            if (openReplyId === commentId) {
                closeReplyForms();
                return;
            }
            closeReplyForms();
            openReplyId = commentId;
            var replyWrap = buildReplyForm(commentId);
            contentEl.appendChild(replyWrap);
            var ta = replyWrap.querySelector('textarea');
            if (ta) ta.focus();
        }

        function closeReplyForms() {
            openReplyId = null;
            section.querySelectorAll('[data-comment-reply-wrap]').forEach(function(el) {
                el.remove();
            });
        }

        function buildReplyForm(parentId) {
            var wrap = document.createElement('div');
            wrap.className = 'comment-reply-wrap';
            wrap.setAttribute('data-comment-reply-wrap', '1');

            var form = document.createElement('form');
            form.className = 'comment-form comment-form--reply';
            form.setAttribute('data-comment-form', 'reply');
            form.setAttribute('data-parent-id', String(parentId));
            form.setAttribute('action', '#');

            if (!isCommentsLoggedIn()) {
                var guestRow = document.createElement('div');
                guestRow.className = 'comment-form__guest';
                guestRow.innerHTML =
                    '<input type="text" name="guest_name" placeholder="Ваше имя" maxlength="120">' +
                    '<label class="comment-form__anon"><input type="checkbox" name="is_anonymous" value="1"><span>Анонимно</span></label>';
                form.appendChild(guestRow);
            }

            var textarea = document.createElement('textarea');
            textarea.name = 'body';
            textarea.placeholder = cfg('comments_ui_reply_placeholder', 'Ваш ответ...');
            textarea.rows = 3;
            form.appendChild(buildCommentToolbar());
            form.appendChild(textarea);

            var actions = document.createElement('div');
            actions.className = 'comment-form__actions';
            actions.innerHTML =
                '<button type="button" class="dontusebuttonclass comment-form__submit" data-comment-submit>' + cfg('comments_ui_reply', 'Ответить') + '</button>' +
                '<button type="button" class="dontusebuttonclass comment-form__cancel">' + cfg('comments_ui_cancel', 'Отмена') + '</button>';
            form.appendChild(actions);

            actions.querySelector('.comment-form__cancel').addEventListener('click', function() {
                closeReplyForms();
            });

            wrap.appendChild(form);
            return wrap;
        }

        function commentVoteScope(article) {
            for (var i = 0; i < article.children.length; i++) {
                var child = article.children[i];
                if (child.classList && child.classList.contains('comment-item__inner')) {
                    return child;
                }
            }
            return article;
        }

        function updateVoteUi(article, data) {
            var scope = commentVoteScope(article);
            var likes = scope.querySelector('[data-comment-likes]');
            var dislikes = scope.querySelector('[data-comment-dislikes]');
            if (likes) likes.textContent = String(data.likes);
            if (dislikes) dislikes.textContent = String(data.dislikes);
            scope.querySelectorAll('[data-comment-vote]').forEach(function(btn) {
                btn.classList.remove('active-like', 'active-dislike');
                var v = parseInt(btn.getAttribute('data-comment-vote'), 10);
                if (data.user_vote === v) {
                    btn.classList.add(v === 1 ? 'active-like' : 'active-dislike');
                }
            });
        }

        function renderComment(c, depth) {
            var article = document.createElement('article');
            article.className = 'comment-item' + (depth > 0 ? ' comment-item--reply' : '');
            if (c.is_pinned) {
                article.classList.add('comment-item--pinned');
            }
            article.dataset.commentId = String(c.id);

            var inner = document.createElement('div');
            inner.className = 'comment-item__inner';

            var avatar = document.createElement('div');
            avatar.className = 'comment-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            avatar.style.setProperty('--avatar-hue', String(authorHue(c.author)));
            avatar.textContent = authorInitial(c.author);
            inner.appendChild(avatar);

            var content = document.createElement('div');
            content.className = 'comment-content';

            var head = document.createElement('header');
            head.className = 'comment-head';
            head.innerHTML =
                '<div class="comment-head__meta">' +
                '<strong class="comment-author"></strong>' +
                (c.is_pinned ? '<span class="comment-pinned-badge">' + cfg('comments_ui_pinned', 'Закреплён') + '</span>' : '') +
                '<time class="comment-date"></time>' +
                '</div>';
            head.querySelector('.comment-author').textContent = c.author;
            head.querySelector('.comment-date').textContent = c.created_at;
            content.appendChild(head);

            content.appendChild(renderCommentBody(c.body));

            var footer = document.createElement('footer');
            footer.className = 'comment-footer';

            var voteWrap = document.createElement('div');
            voteWrap.className = 'comment-vote';
            voteWrap.innerHTML =
                '<button type="button" class="comment-vote__btn comment-vote__btn--like dontusebuttonclass" data-comment-vote="1" title="Нравится">' +
                '<span class="fa fa-thumbs-up" aria-hidden="true"></span> <span data-comment-likes>0</span></button>' +
                '<button type="button" class="comment-vote__btn comment-vote__btn--dislike dontusebuttonclass" data-comment-vote="-1" title="Не нравится">' +
                '<span data-comment-dislikes>0</span> <span class="fa fa-thumbs-down" aria-hidden="true"></span></button>';
            footer.appendChild(voteWrap);

            var replyBtn = document.createElement('button');
            replyBtn.type = 'button';
            replyBtn.className = 'dontusebuttonclass comment-reply-btn';
            replyBtn.textContent = cfg('comments_ui_reply', 'Ответить');
            footer.appendChild(replyBtn);
            content.appendChild(footer);

            inner.appendChild(content);
            article.appendChild(inner);

            updateVoteUi(article, c);

            if (c.children && c.children.length) {
                var replies = document.createElement('div');
                replies.className = 'comment-replies';
                c.children.forEach(function(child) {
                    replies.appendChild(renderComment(child, depth + 1));
                });
                article.appendChild(replies);
            }

            return article;
        }

        function bindListEvents() {
            if (!listEl) return;

            listEl.addEventListener('click', function(e) {
                var voteBtn = e.target.closest('[data-comment-vote]');
                if (!voteBtn) return;
                var article = voteBtn.closest('.comment-item');
                if (!article) return;
                var commentId = article.dataset.commentId;
                if (!commentId) return;

                postJson('/api/comments/' + encodeURIComponent(commentId) + '/vote', {
                        value: parseInt(voteBtn.getAttribute('data-comment-vote'), 10),
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data && data.ok) updateVoteUi(article, data);
                    })
                    .catch(function() {
                        flashNotice('Не удалось сохранить оценку комментария.', true);
                    });
            });
        }

        function setCommentsSort(nextSort) {
            currentSort = nextSort === 'rating' ? 'rating' : 'date';
            if (!sortEl) return;
            sortEl.querySelectorAll('[data-comments-sort-value]').forEach(function(btn) {
                var value = btn.getAttribute('data-comments-sort-value');
                btn.classList.toggle('is-active', value === currentSort);
            });
        }

        function loadComments() {
            if (!listEl) return;
            listEl.removeAttribute('data-comments-ssr');
            listEl.innerHTML = '<p class="comment-loading">' + cfg('comments_ui_loading', 'Загрузка комментариев...') + '</p>';

            var url = '/api/series/' + encodeURIComponent(seriesId) + '/comments?sort=' + encodeURIComponent(currentSort);
            fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    listEl.innerHTML = '';
                    if (data.sort) {
                        setCommentsSort(data.sort);
                    }
                    var items = data.items || [];
                    updateCommentsCount(items);
                    if (!items.length) {
                        listEl.innerHTML = '<p class="comment-empty">' + cfg('comments_ui_empty', 'Пока нет комментариев. Будьте первым!') + '</p>';
                        return;
                    }
                    items.forEach(function(c) {
                        listEl.appendChild(renderComment(c, 0));
                    });
                })
                .catch(function() {
                    listEl.innerHTML = '<p class="comment-empty">' + cfg('comments_ui_load_error', 'Не удалось загрузить комментарии.') + '</p>';
                });
        }

        if (sortEl) {
            sortEl.addEventListener('click', function(e) {
                var btn = e.target.closest('[data-comments-sort-value]');
                if (!btn || !sortEl.contains(btn)) return;
                var nextSort = btn.getAttribute('data-comments-sort-value');
                if (!nextSort || nextSort === currentSort) return;
                currentSort = nextSort === 'rating' ? 'rating' : 'date';
                setCommentsSort(currentSort);
                loadComments();
            });
        }

        section.addEventListener('submit', function(e) {
            var form = e.target.closest('[data-comment-form]');
            if (!form || !section.contains(form)) return;
            e.preventDefault();
        });

        section.addEventListener('click', function(e) {
            var spoilerBtn = e.target.closest('[data-comment-spoiler]');
            if (spoilerBtn && section.contains(spoilerBtn)) {
                var form = spoilerBtn.closest('[data-comment-form]');
                var textarea = form && form.querySelector('textarea[name="body"]');
                if (textarea) insertSpoilerTag(textarea);
                return;
            }

            var toggleBtn = e.target.closest('.comment-spoiler__toggle');
            if (toggleBtn && section.contains(toggleBtn)) {
                var spoiler = toggleBtn.closest('.comment-spoiler');
                var text = spoiler && spoiler.querySelector('.comment-spoiler__text');
                if (!text) return;

                var reveal = cfg('comments_ui_spoiler_reveal', 'Спойлер');
                var hide = cfg('comments_ui_spoiler_hide', 'Скрыть спойлер');
                var expanded = text.hidden;
                text.hidden = !expanded;
                toggleBtn.textContent = expanded ? hide : reveal;
                toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                return;
            }

            var replyBtn = e.target.closest('.comment-reply-btn');
            if (replyBtn && section.contains(replyBtn)) {
                var article = replyBtn.closest('.comment-item');
                var content = article && article.querySelector('.comment-content');
                var commentId = article ? parseInt(article.dataset.commentId, 10) : 0;
                if (content && commentId > 0) {
                    openReplyForm(commentId, content);
                }
                return;
            }

            var btn = e.target.closest('[data-comment-submit]');
            if (!btn || !section.contains(btn)) return;
            var form = btn.closest('[data-comment-form]');
            if (!form) return;
            var parentAttr = form.getAttribute('data-parent-id');
            var parentId = parentAttr ? parseInt(parentAttr, 10) : null;
            submitComment(form, parentId);
        });

        bindListEvents();
        section.querySelectorAll('[data-comment-form]').forEach(enhanceCommentForm);
        document.addEventListener('lordserial:comments-compose-upgrade', function() {
            section.querySelectorAll('[data-comment-form]').forEach(enhanceCommentForm);
        });
        if (!commentsSsr) {
            loadComments();
        }
    }

    function initCommentSpoilers() {
        document.addEventListener('click', function(e) {
            var toggleBtn = e.target.closest('.comment-spoiler__toggle');
            if (!toggleBtn || toggleBtn.closest('#commentsSection') || toggleBtn.closest('#reviewsSection')) return;

            var spoiler = toggleBtn.closest('.comment-spoiler');
            var text = spoiler && spoiler.querySelector('.comment-spoiler__text');
            if (!text) return;

            var reveal = cfg('comments_ui_spoiler_reveal', 'Спойлер');
            var hide = cfg('comments_ui_spoiler_hide', 'Скрыть спойлер');
            var expanded = text.hidden;
            text.hidden = !expanded;
            toggleBtn.textContent = expanded ? hide : reveal;
            toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }

    function initEpisodesModal() {
        var modal = document.querySelector('[data-episodes-modal]');
        if (!modal) return;

        function openModal() {
            modal.hidden = false;
            modal.classList.add('is-active');
            document.body.classList.add('episodes-lock');
        }

        function closeModal() {
            modal.hidden = true;
            modal.classList.remove('is-active');
            document.body.classList.remove('episodes-lock');
        }

        document.querySelectorAll('[data-episodes-open]').forEach(function(btn) {
            btn.addEventListener('click', openModal);
        });

        modal.querySelectorAll('[data-episodes-close]').forEach(function(el) {
            el.addEventListener('click', closeModal);
        });

        modal.querySelectorAll('.episodes-season__toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var season = btn.closest('.episodes-season');
                if (season) season.classList.toggle('is-open');
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('is-active')) {
                closeModal();
            }
        });
    }

    function initReactionsWidget() {
        var widget = document.querySelector('[data-reactions-widget]');
        if (!widget) return;

        var seriesId = widget.getAttribute('data-series-id');
        if (!seriesId) return;

        var feedbackEl = widget.querySelector('[data-reactions-feedback]');
        var voting = false;

        function showFeedback(message, isError) {
            if (!feedbackEl) return;
            if (!message) {
                feedbackEl.hidden = true;
                feedbackEl.textContent = '';
                feedbackEl.classList.remove('is-error');
                return;
            }
            feedbackEl.hidden = false;
            feedbackEl.textContent = message;
            feedbackEl.classList.toggle('is-error', !!isError);
        }

        function render(data) {
            if (!data || !data.items) return;

            var totalEl = widget.querySelector('[data-reactions-total]');
            if (totalEl) totalEl.textContent = data.total_label || '0 голосов';

            widget.querySelectorAll('[data-reaction-card]').forEach(function(card) {
                var id = parseInt(card.getAttribute('data-reaction-id'), 10);
                var item = (data.items || []).find(function(x) { return x.id === id; });
                if (!item) return;

                var selected = !!item.is_selected;
                card.classList.toggle('is-selected', selected);
                card.setAttribute('aria-pressed', selected ? 'true' : 'false');

                var countEl = card.querySelector('[data-reaction-count]');
                var barEl = card.querySelector('[data-reaction-bar]');
                var percentEl = card.querySelector('[data-reaction-percent]');

                if (countEl) countEl.textContent = item.count_label || '0 голосов';
                if (barEl) barEl.style.width = String(item.percent || 0) + '%';
                if (percentEl) percentEl.textContent = String(item.percent || 0) + '%';
            });
        }

        function setLoading(state) {
            widget.classList.toggle('is-loading', state);
            voting = state;
        }

        function vote(reactionId) {
            if (!reactionId || voting) return;

            setLoading(true);
            showFeedback('', false);

            postJson('/api/series/' + encodeURIComponent(seriesId) + '/reactions', {
                    reaction_type_id: reactionId,
                })
                .then(readJsonResponse)
                .then(function(res) {
                    if (!res.ok) {
                        showFeedback(parseApiErrors(res.data), true);
                        return;
                    }
                    render(res.data);
                })
                .catch(function() {
                    showFeedback('Не удалось сохранить оценку. Попробуйте ещё раз.', true);
                })
                .finally(function() {
                    setLoading(false);
                });
        }

        widget.addEventListener('click', function(e) {
            var card = e.target.closest('[data-reaction-card]');
            if (!card || !widget.contains(card)) return;
            var reactionId = parseInt(card.getAttribute('data-reaction-id'), 10);
            vote(reactionId);
        });

        widget.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var card = e.target.closest('[data-reaction-card]');
            if (!card || !widget.contains(card)) return;
            e.preventDefault();
            var reactionId = parseInt(card.getAttribute('data-reaction-id'), 10);
            vote(reactionId);
        });

        fetch('/api/series/' + encodeURIComponent(seriesId) + '/reactions', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.enabled !== false) render(data);
            })
            .catch(function() {
                flashNotice('Не удалось загрузить реакции.', true);
            });
    }

    function fetchJson(url, options) {
        return fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        }, options || {})).then(function(r) { return r.json(); });
    }

    function initHeaderNotifications() {
        var bellBtn = document.getElementById('headerNotifyBtn');
        var dropdown = document.getElementById('seriesDropdown');
        var listEl = document.getElementById('seriesDropdownList');
        var countEl = document.getElementById('headerNotifyCount');
        var clearBtn = document.getElementById('clearAllNotifi');
        if (!bellBtn || !dropdown || !listEl || bellBtn.getAttribute('data-notify-mounted') === '1') return;
        bellBtn.setAttribute('data-notify-mounted', '1');

        function updateCount(unread) {
            if (!countEl) return;
            if (unread > 0) {
                countEl.textContent = unread > 99 ? '99+' : String(unread);
                countEl.hidden = false;
            } else {
                countEl.hidden = true;
            }
        }

        function renderItems(items) {
            if (!items || !items.length) {
                dropdown.classList.add('is-empty');
                listEl.innerHTML = '';
                return;
            }

            dropdown.classList.remove('is-empty');
            listEl.innerHTML = items.map(function(item) {
                return '<article class="series-item' + (item.read ? '' : ' is-unread') + '" data-notification-id="' + item.id + '">' +
                    '<button class="series-item__remove" type="button" data-dismiss-notification="' + item.id + '" aria-label="Удалить">×</button>' +
                    '<img class="series-item__poster" src="' + item.poster_url + '" alt="">' +
                    '<div class="series-item__content">' +
                    '<div class="series-item__date">' + item.created_at + '</div>' +
                    '<div class="series-item__title">' + item.title + '</div>' +
                    '<a class="series-item__link" href="' + item.series_url + '">' + item.series_title + '</a>' +
                    (item.voice ? '<div class="series-item__voice">' + item.voice + '</div>' : '') +
                    '</div></article>';
            }).join('');
        }

        function positionDropdown() {
            var rect = bellBtn.getBoundingClientRect();
            dropdown.style.top = (rect.bottom + 8) + 'px';
            dropdown.style.right = Math.max(12, window.innerWidth - rect.right) + 'px';
        }

        function loadNotifications() {
            if (!isSiteLoggedIn()) {
                renderItems([]);
                updateCount(0);
                return Promise.resolve();
            }
            return fetchJson('/api/notifications/').then(function(data) {
                renderItems(data.items || []);
                updateCount(data.unread || 0);
            }).catch(function() {
                flashNotice('Не удалось загрузить уведомления.', true);
            });
        }

        function closeDropdown() {
            dropdown.classList.remove('is-active');
        }

        function openDropdown() {
            positionDropdown();
            dropdown.classList.add('is-active');
            loadNotifications().then(function() {
                return postJson('/api/notifications/read', { all: true });
            }).then(function() {
                updateCount(0);
                listEl.querySelectorAll('.series-item.is-unread').forEach(function(el) {
                    el.classList.remove('is-unread');
                });
            });
        }

        bellBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!isSiteLoggedIn()) {
                closeDropdown();
                if (window.lordSerialOpenAuth) window.lordSerialOpenAuth('login');
                return;
            }
            if (dropdown.classList.contains('is-active')) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.classList.contains('is-active')) return;
            if (dropdown.contains(e.target) || bellBtn.contains(e.target)) return;
            closeDropdown();
        });

        window.addEventListener('resize', function() {
            if (dropdown.classList.contains('is-active')) positionDropdown();
        });

        listEl.addEventListener('click', function(e) {
            var dismissBtn = e.target.closest('[data-dismiss-notification]');
            if (!dismissBtn) return;
            e.preventDefault();
            e.stopPropagation();
            var id = dismissBtn.getAttribute('data-dismiss-notification');
            var itemEl = dismissBtn.closest('.series-item');
            deleteJson('/api/notifications/' + encodeURIComponent(id))
                .then(function(response) {
                    if (!response.ok) return loadNotifications();
                    if (itemEl) itemEl.remove();
                    var remaining = listEl.querySelectorAll('.series-item').length;
                    if (!remaining) {
                        dropdown.classList.add('is-empty');
                    }
                    return loadNotifications();
                })
                .catch(function() {
                    return loadNotifications();
                });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function(e) {
                e.preventDefault();
                postJson('/api/notifications/clear', {}).then(function() {
                    return loadNotifications();
                });
            });
        }

        if (isSiteLoggedIn()) {
            loadNotifications();
        }
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function ensurePushServiceWorker() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return Promise.reject(new Error('push unsupported'));
        }
        return navigator.serviceWorker.register('/sw.js').then(function(reg) {
            return navigator.serviceWorker.ready.then(function() { return reg; });
        });
    }

    function enableWebPush() {
        if (!cfgBool('notifications_enabled', true) || !isSiteLoggedIn()) {
            return Promise.resolve(false);
        }
        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            return Promise.resolve(false);
        }

        var publicKey = cfg('vapid_public_key', '');
        var keyPromise = publicKey ?
            Promise.resolve(publicKey) :
            fetchJson('/api/push/vapid-public-key').then(function(data) {
                return data && data.publicKey ? data.publicKey : '';
            });

        return keyPromise.then(function(key) {
            if (!key) return false;
            return Notification.requestPermission().then(function(permission) {
                if (permission !== 'granted') {
                    flashNotice(cfg('notifications_msg_push_denied', 'Разрешите уведомления в браузере, чтобы получать push.'), true);
                    return false;
                }
                return ensurePushServiceWorker().then(function(reg) {
                    return reg.pushManager.getSubscription().then(function(existing) {
                        if (existing) return existing;
                        return reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(key),
                        });
                    });
                }).then(function(subscription) {
                    var json = subscription.toJSON();
                    return postJson('/api/push/subscribe', {
                        endpoint: json.endpoint,
                        keys: json.keys || {},
                        contentEncoding: 'aes128gcm',
                    }).then(readJsonResponse).then(function(res) {
                        return !!(res.ok && res.data && res.data.ok !== false);
                    });
                });
            });
        }).catch(function() {
            return false;
        });
    }

    function initProfileNotifications() {
        var prefsForm = document.getElementById('notificationPrefsForm');
        if (!prefsForm) return;

        prefsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var submitBtn = prefsForm.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            var pushChecked = !!prefsForm.querySelector('[name="notify_via_push"]')?.checked;

            var savePrefs = function() {
                return postJson('/api/notifications/preferences', {
                    notify_via_email: !!prefsForm.querySelector('[name="notify_via_email"]')?.checked,
                    notify_via_site: !!prefsForm.querySelector('[name="notify_via_site"]')?.checked,
                    notify_via_push: pushChecked,
                });
            };

            var chain = Promise.resolve();
            if (pushChecked) {
                chain = enableWebPush();
            }

            chain.then(function() {
                    return savePrefs();
                })
                .then(readJsonResponse)
                .then(function(res) {
                    var flash = document.getElementById('profileFlash');
                    if (!res.ok || (res.data && res.data.ok === false)) {
                        if (flash) {
                            flash.hidden = false;
                            flash.textContent = parseApiErrors(res.data);
                            flash.className = 'profile-flash profile-flash--error';
                        }
                        return;
                    }
                    if (flash && res.data && res.data.message) {
                        flash.hidden = false;
                        flash.textContent = res.data.message;
                        flash.className = 'profile-flash profile-flash--success';
                    }
                })
                .catch(function() {
                    var flash = document.getElementById('profileFlash');
                    if (flash) {
                        flash.hidden = false;
                        flash.textContent = 'Не удалось сохранить настройки уведомлений.';
                        flash.className = 'profile-flash profile-flash--error';
                    }
                })
                .finally(function() {
                    if (submitBtn) submitBtn.disabled = false;
                });
        });

        document.querySelectorAll('[data-unsubscribe-series]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var seriesId = btn.getAttribute('data-unsubscribe-series');
                if (!seriesId || !window.confirm('Отключить уведомления для этого сериала?')) return;
                deleteJson('/api/notifications/series/' + encodeURIComponent(seriesId)).then(function() {
                    window.location.reload();
                });
            });
        });
    }

    function initPlayerTabs() {
        var root = document.querySelector('[data-trailer-box]');
        if (!root) return;

        var tabsWrap = root.querySelector('[data-player-tabs]');
        if (!tabsWrap) return;

        var tabsScroller = root.querySelector('[data-trailer-tabs]');
        var prevBtn = tabsScroller ? tabsScroller.querySelector('.trailer-tabs-nav--prev') : null;
        var nextBtn = tabsScroller ? tabsScroller.querySelector('.trailer-tabs-nav--next') : null;

        var tabs = tabsWrap.querySelectorAll('[data-player-index]');
        var panels = root.querySelectorAll('[data-player-panel]');
        if (!tabs.length || !panels.length) return;

        function scrollActiveTabIntoView() {
            var active = tabsWrap.querySelector('.trailer-tabs__btn.is-active');
            if (!active) return;
            active.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
        }

        function updateNavVisibility() {
            if (!tabsWrap) return;

            var maxScroll = Math.max(0, tabsWrap.scrollWidth - tabsWrap.clientWidth);
            var canScroll = maxScroll > 1;

            if (tabsScroller) {
                tabsScroller.classList.toggle('is-scrollable', canScroll);
            }

            if (!prevBtn || !nextBtn) return;

            if (!canScroll) {
                prevBtn.hidden = true;
                nextBtn.hidden = true;
                return;
            }

            prevBtn.hidden = tabsWrap.scrollLeft <= 1;
            nextBtn.hidden = tabsWrap.scrollLeft >= maxScroll - 1;
        }

        function scrollTabsBy(delta) {
            tabsWrap.scrollBy({ left: delta, behavior: 'smooth' });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                scrollTabsBy(-Math.max(180, Math.round(tabsWrap.clientWidth * 0.6)));
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                scrollTabsBy(Math.max(180, Math.round(tabsWrap.clientWidth * 0.6)));
            });
        }

        tabsWrap.addEventListener('scroll', updateNavVisibility, { passive: true });
        window.addEventListener('resize', updateNavVisibility);
        window.requestAnimationFrame(function() {
            updateNavVisibility();
            scrollActiveTabIntoView();
            window.requestAnimationFrame(updateNavVisibility);
        });

        function activate(index) {
            tabs.forEach(function(tab) {
                var active = tab.getAttribute('data-player-index') === String(index);
                tab.classList.toggle('is-active', active);
            });

            panels.forEach(function(panel) {
                var active = panel.getAttribute('data-player-panel') === String(index);
                panel.hidden = !active;
                panel.classList.toggle('is-active', active);

                if (active) {
                    var iframe = panel.querySelector('.player-iframe');
                    if (iframe && !iframe.getAttribute('src')) {
                        var url = iframe.getAttribute('data-player-url');
                        if (url) iframe.setAttribute('src', url);
                    }
                }
            });

            scrollActiveTabIntoView();
            window.requestAnimationFrame(updateNavVisibility);
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                activate(tab.getAttribute('data-player-index'));
            });
        });
    }

    function initPlayerLight() {
        var toggles = document.querySelectorAll('[data-player-light]');
        if (!toggles.length) return;

        var overlay = document.querySelector('[data-light-overlay]');

        function setLightsOff(on) {
            document.body.classList.toggle('light-off', on);
            toggles.forEach(function(input) {
                input.checked = on;
            });
        }

        toggles.forEach(function(input) {
            input.addEventListener('change', function() {
                setLightsOff(!!input.checked);
            });
        });

        if (overlay) {
            overlay.addEventListener('click', function() {
                setLightsOff(false);
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.body.classList.contains('light-off')) {
                setLightsOff(false);
            }
        });
    }

    function initPlayerReport() {
        var openBtns = document.querySelectorAll('[data-player-report]');
        var modal = document.querySelector('[data-player-report-modal]');
        if (!openBtns.length || !modal) return;

        var closeBtns = modal.querySelectorAll('[data-player-report-close]');
        var issues = modal.querySelector('[data-player-report-issues]');
        var messageEl = modal.querySelector('[data-player-report-message]');
        var submitBtn = modal.querySelector('[data-player-report-submit]');
        var feedbackEl = modal.querySelector('[data-player-report-feedback]');
        var selectedReason = null;
        var sending = false;

        var reasonLabels = {
            player_not_shown: 'Плеер не отображается (только колесо загрузки либо сообщение)',
            video_not_start: 'Видео не запускается или черный экран после запуска',
            audio_desync: 'Звук и видео не совпадают',
            description_error: 'Ошибка в описании',
            other: 'Другое',
        };

        function seriesId() {
            var root = document.querySelector('[data-series-id]');
            return root ? root.getAttribute('data-series-id') : '';
        }

        function activePlayerLabel() {
            var active = document.querySelector('[data-trailer-box] [data-player-tabs] .trailer-tabs__btn.is-active');
            return active ? String(active.textContent || '').trim() : '';
        }

        function setFeedback(text, isError) {
            if (!feedbackEl) return;
            if (!text) {
                feedbackEl.hidden = true;
                feedbackEl.textContent = '';
                feedbackEl.classList.remove('is-error', 'is-success');
                return;
            }
            feedbackEl.hidden = false;
            feedbackEl.textContent = text;
            feedbackEl.classList.toggle('is-error', !!isError);
            feedbackEl.classList.toggle('is-success', !isError);
        }

        function resetForm() {
            selectedReason = null;
            if (issues) {
                issues.querySelectorAll('.report-item').forEach(function(item) {
                    item.classList.remove('active');
                });
            }
            if (messageEl) {
                messageEl.value = '';
                messageEl.classList.remove('error');
            }
            setFeedback('');
        }

        function openModal() {
            resetForm();
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            if (messageEl) {
                window.setTimeout(function() {
                    messageEl.focus();
                }, 50);
            }
        }

        function closeModal() {
            modal.hidden = true;
            document.body.style.overflow = '';
            resetForm();
        }

        openBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                openModal();
            });
        });

        closeBtns.forEach(function(btn) {
            btn.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !modal.hidden) closeModal();
        });

        if (issues) {
            issues.addEventListener('click', function(e) {
                var item = e.target.closest('[data-reason]');
                if (!item || !issues.contains(item)) return;
                selectedReason = item.getAttribute('data-reason');
                issues.querySelectorAll('.report-item').forEach(function(el) {
                    el.classList.toggle('active', el === item);
                });
                setFeedback('');
                if (messageEl) {
                    messageEl.classList.remove('error');
                    if (selectedReason === 'other') messageEl.focus();
                }
            });
        }

        function submitReport() {
            if (sending) return;
            var id = seriesId();
            if (!id) {
                setFeedback('Не удалось определить сериал', true);
                return;
            }
            if (!selectedReason) {
                setFeedback('Выберите причину жалобы', true);
                return;
            }
            var message = messageEl ? String(messageEl.value || '').trim() : '';
            if (selectedReason === 'other' && !message) {
                if (messageEl) messageEl.classList.add('error');
                setFeedback('Для пункта «Другое» опишите проблему в поле ниже', true);
                return;
            }
            if (messageEl) messageEl.classList.remove('error');

            sending = true;
            if (submitBtn) submitBtn.disabled = true;
            setFeedback('Отправка…', false);

            postJson('/api/series/' + encodeURIComponent(id) + '/player-report', {
                    reason: selectedReason,
                    reason_label: reasonLabels[selectedReason] || selectedReason,
                    message: message,
                    player_label: activePlayerLabel(),
                })
                .then(readJsonResponse)
                .then(function(res) {
                    if (!res.ok) {
                        var msg = (res.data && (res.data.message || (res.data.errors && Object.values(res.data.errors)[0]))) || 'Не удалось отправить жалобу';
                        if (Array.isArray(msg)) msg = msg[0];
                        setFeedback(String(msg), true);
                        return;
                    }
                    setFeedback((res.data && res.data.message) || 'Спасибо! Жалоба отправлена.', false);
                    window.setTimeout(closeModal, 1200);
                })
                .catch(function() {
                    setFeedback('Не удалось отправить жалобу', true);
                })
                .then(function() {
                    sending = false;
                    if (submitBtn) submitBtn.disabled = false;
                });
        }

        if (submitBtn) {
            submitBtn.addEventListener('click', submitReport);
        }
    }

    function initNotifyModal() {
        var notifyOverlay = document.getElementById('notifyOverlay');
        var notifyBtn = document.getElementById('notifyOpenBtn');
        var notifyForm = document.getElementById('notifyForm');
        var unsubscribeBtn = document.getElementById('notifyUnsubscribeBtn');
        var subscribeBox = document.getElementById('seriesSubscribeBox');
        var subscribedBadge = document.getElementById('notifySubscribedBadge');
        var subscribeFeedback = document.getElementById('notifySubscribeFeedback');
        var root = document.querySelector('.fmain[data-series-id]');
        if (!root) return;

        var seriesId = root.getAttribute('data-series-id');
        if (!seriesId) return;
        var subscribed = subscribeBox && subscribeBox.getAttribute('data-subscribed') === '1';
        var settingsRequestId = 0;
        var subscribeInFlight = false;

        function isLoggedIn() {
            return !!root.querySelector('[data-logged-in]');
        }

        function showSubscribeFeedback(message, isError) {
            if (!subscribeFeedback) return;
            if (!message) {
                subscribeFeedback.hidden = true;
                subscribeFeedback.textContent = '';
                subscribeFeedback.classList.remove('is-error', 'is-success');
                return;
            }
            subscribeFeedback.hidden = false;
            subscribeFeedback.textContent = message;
            subscribeFeedback.classList.toggle('is-error', !!isError);
            subscribeFeedback.classList.toggle('is-success', !isError);
        }

        function updateSubscribeUi(data) {
            subscribed = !!(data && data.subscribed);
            var showSubscribed = subscribed && isLoggedIn();
            if (subscribeBox) {
                subscribeBox.classList.toggle('is-subscribed', showSubscribed);
                subscribeBox.setAttribute('data-subscribed', showSubscribed ? '1' : '0');
            }
            if (subscribedBadge) {
                subscribedBadge.hidden = !showSubscribed;
            }
            if (!notifyBtn) return;
            notifyBtn.disabled = false;
            notifyBtn.setAttribute('data-action', subscribed ? 'unsubscribe' : 'subscribe');
            if (!isLoggedIn()) {
                notifyBtn.textContent = cfg('notifications_ui_subscribe_btn_guest', 'Войти и подписаться');
                return;
            }
            notifyBtn.textContent = subscribed ?
                cfg('notifications_ui_unsubscribe_btn', 'Отписаться') :
                cfg('notifications_ui_subscribe_btn', 'Подписаться');
            if (subscribedBadge) {
                subscribedBadge.style.cursor = showSubscribed ? 'pointer' : '';
                subscribedBadge.title = showSubscribed ? 'Настроить озвучки' : '';
            }
        }

        function openNotify() {
            if (notifyOverlay) notifyOverlay.hidden = false;
        }

        function closeNotify() {
            if (notifyOverlay) notifyOverlay.hidden = true;
        }

        function loadSettings() {
            var requestId = ++settingsRequestId;
            if (!isLoggedIn()) {
                updateSubscribeUi({ subscribed: false });
                return Promise.resolve({ subscribed: false });
            }
            return fetchJson('/api/series/' + encodeURIComponent(seriesId) + '/notifications').then(function(data) {
                if (requestId !== settingsRequestId) return data;
                if (notifyForm) {
                    var anyInput = notifyForm.querySelector('input[name="notify_any"]');
                    if (anyInput) anyInput.checked = data.notify_any !== false;
                    var voices = data.voices || [];
                    notifyForm.querySelectorAll('input[name="voices[]"]').forEach(function(cb) {
                        cb.checked = voices.indexOf(cb.value) !== -1;
                    });
                }
                if (unsubscribeBtn) unsubscribeBtn.hidden = !data.subscribed;
                updateSubscribeUi(data);
                return data;
            }).catch(function() {
                if (requestId !== settingsRequestId) return { subscribed: subscribed };
                return { subscribed: subscribed };
            });
        }

        function quickSubscribe() {
            if (subscribeInFlight) return Promise.resolve();
            subscribeInFlight = true;
            showSubscribeFeedback('', false);
            if (notifyBtn) {
                notifyBtn.disabled = true;
                notifyBtn.textContent = 'Подписываем…';
            }

            return postJson('/api/series/' + encodeURIComponent(seriesId) + '/notifications', {
                    notify_any: true,
                    voices: [],
                })
                .then(readJsonResponse)
                .then(function(res) {
                    subscribeInFlight = false;
                    if (!res.ok) {
                        updateSubscribeUi({ subscribed: false });
                        showSubscribeFeedback(parseApiErrors(res.data) || 'Не удалось подписаться.', true);
                        return;
                    }
                    settingsRequestId++;
                    updateSubscribeUi({ subscribed: true });
                    if (unsubscribeBtn) unsubscribeBtn.hidden = false;
                    showSubscribeFeedback(res.data.message || cfg('notifications_msg_saved', 'Настройки уведомлений сохранены.'), false);
                    enableWebPush().then(function(ok) {
                        if (ok) {
                            showSubscribeFeedback(
                                (res.data.message || cfg('notifications_msg_saved', 'Настройки уведомлений сохранены.')) +
                                ' ' + cfg('notifications_msg_push_enabled', 'Push-уведомления включены.'),
                                false
                            );
                        }
                    });
                })
                .catch(function() {
                    subscribeInFlight = false;
                    updateSubscribeUi({ subscribed: false });
                    showSubscribeFeedback('Не удалось подписаться. Попробуйте ещё раз.', true);
                });
        }

        function quickUnsubscribe() {
            if (subscribeInFlight) return Promise.resolve();
            subscribeInFlight = true;
            showSubscribeFeedback('', false);
            if (notifyBtn) {
                notifyBtn.disabled = true;
                notifyBtn.textContent = 'Отписываем…';
            }

            return postJson('/api/series/' + encodeURIComponent(seriesId) + '/notifications', { enabled: false })
                .then(readJsonResponse)
                .then(function(res) {
                    subscribeInFlight = false;
                    if (!res.ok) {
                        showSubscribeFeedback(parseApiErrors(res.data) || 'Не удалось отписаться.', true);
                        updateSubscribeUi({ subscribed: true });
                        return;
                    }
                    settingsRequestId++;
                    updateSubscribeUi({ subscribed: false });
                    if (unsubscribeBtn) unsubscribeBtn.hidden = true;
                    showSubscribeFeedback(res.data.message || cfg('notifications_msg_unsubscribed', 'Уведомления отключены.'), false);
                })
                .catch(function() {
                    subscribeInFlight = false;
                    updateSubscribeUi({ subscribed: true });
                    showSubscribeFeedback('Не удалось отписаться. Попробуйте ещё раз.', true);
                });
        }

        function handleSubscribeClick() {
            showSubscribeFeedback('', false);
            if (!isLoggedIn()) {
                if (window.lordSerialOpenAuth) window.lordSerialOpenAuth('login');
                return;
            }
            if (subscribed) {
                quickUnsubscribe();
                return;
            }
            quickSubscribe();
        }

        function openNotifySettings() {
            if (!isLoggedIn() || !subscribed) return;
            loadSettings().finally(openNotify);
        }

        if (notifyBtn) notifyBtn.addEventListener('click', handleSubscribeClick);

        if (subscribedBadge) {
            subscribedBadge.addEventListener('click', openNotifySettings);
        }

        var subscribeIcon = subscribeBox && subscribeBox.querySelector('.series-subscribe-box__icon');
        if (subscribeIcon) {
            subscribeIcon.addEventListener('click', openNotifySettings);
        }

        document.querySelectorAll('[data-notify-close], .notify-close, .notify-cancel').forEach(function(el) {
            el.addEventListener('click', closeNotify);
        });

        if (unsubscribeBtn) {
            unsubscribeBtn.addEventListener('click', function() {
                postJson('/api/series/' + encodeURIComponent(seriesId) + '/notifications', { enabled: false })
                    .then(readJsonResponse)
                    .then(function(res) {
                        var notifyFeedback = document.getElementById('notifyFeedback');
                        if (!res.ok) {
                            showFeedback(notifyFeedback, parseApiErrors(res.data), true);
                            return;
                        }
                        settingsRequestId++;
                        showFeedback(notifyFeedback, res.data.message || 'Отключено', false);
                        updateSubscribeUi({ subscribed: false });
                        showSubscribeFeedback(res.data.message || 'Уведомления отключены.', false);
                        unsubscribeBtn.hidden = true;
                        setTimeout(closeNotify, 900);
                    });
            });
        }

        if (notifyForm) {
            var notifyFeedback = document.getElementById('notifyFeedback');
            notifyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var voices = [];
                notifyForm.querySelectorAll('input[name="voices[]"]:checked').forEach(function(cb) {
                    voices.push(cb.value);
                });
                var notifyAnyInput = notifyForm.querySelector('input[name="notify_any"]');
                var notifyAny = notifyAnyInput ? notifyAnyInput.checked : true;

                postJson('/api/series/' + encodeURIComponent(seriesId) + '/notifications', {
                        voices: voices,
                        notify_any: notifyAny,
                    })
                    .then(readJsonResponse)
                    .then(function(res) {
                        if (!res.ok) {
                            showFeedback(notifyFeedback, parseApiErrors(res.data), true);
                            return;
                        }
                        settingsRequestId++;
                        showFeedback(notifyFeedback, res.data.message || 'Сохранено', false);
                        updateSubscribeUi({ subscribed: true });
                        showSubscribeFeedback(res.data.message || 'Сохранено', false);
                        if (unsubscribeBtn) unsubscribeBtn.hidden = false;
                        enableWebPush();
                        setTimeout(closeNotify, 900);
                    })
                    .catch(function() {
                        showFeedback(notifyFeedback, 'Не удалось сохранить настройки.', true);
                    });
            });
        }

        document.addEventListener('lordserial:auth-login', function() {
            updateSubscribeUi({ subscribed: subscribed });
            loadSettings();
        });

        updateSubscribeUi({ subscribed: subscribed });
        loadSettings();
    }

    function initCatalogFilters() {
        var root = document.getElementById('catalogRoot');
        if (!root) return;

        var browseApi = root.getAttribute('data-browse-api') || '/api/catalog/browse';
        var taxonomyType = root.getAttribute('data-taxonomy-type') || '';
        var taxonomySlug = root.getAttribute('data-taxonomy-slug') || '';
        var primaryFilterKey = taxonomyType === 'person' ?
            'actor' :
            (taxonomyType === 'year' ? 'year_from' : taxonomyType);
        var filtersWrap = root.querySelector('[data-catalog-filters-wrap]');
        var gridWrap = root.querySelector('[data-catalog-grid-wrap]');
        var paginationWrap = root.querySelector('[data-catalog-pagination-wrap]');
        var countEl = root.querySelector('[data-catalog-count]');

        var state = { page: 1 };
        var loading = false;
        var requestId = 0;
        var rangeTimer = null;

        function filtersEl() {
            return root.querySelector('[data-catalog-filters]');
        }

        function ensureStateKeys() {
            var el = filtersEl();
            if (!el) return;
            el.querySelectorAll('[data-filter]').forEach(function(node) {
                var key = node.getAttribute('data-filter');
                if (key && state[key] === undefined) {
                    state[key] = '';
                }
            });
        }

        function isDefaultCatalogFilter(key, value) {
            if (key === 'popularity_sort' || key === 'user_rating_sort' || key === 'views_sort' || key === 'comments_sort') {
                return !value || value === 'desc';
            }
            return !value;
        }

        function secondaryFilterParams() {
            var params = {};
            Object.keys(state).forEach(function(key) {
                if (key === 'page') return;
                if (taxonomyType && key === primaryFilterKey) return;
                if (taxonomyType === 'year' && (key === 'year_from' || key === 'year_to')) return;
                if (!isDefaultCatalogFilter(key, state[key])) {
                    params[key] = state[key];
                }
            });
            return params;
        }

        function buildQueryString(params) {
            var parts = [];
            Object.keys(params).forEach(function(key) {
                if (params[key]) {
                    parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
                }
            });
            return parts.length ? parts.join('&') : '';
        }

        function updateBrowserUrl() {
            if (!taxonomyType || !taxonomySlug) return;

            var secondary = secondaryFilterParams();
            var qs = buildQueryString(secondary);
            var path = '/' + taxonomyType + '/' + encodeURIComponent(taxonomySlug) + '/';
            if (state.page > 1) {
                path = '/' + taxonomyType + '/' + encodeURIComponent(taxonomySlug) + '/page/' + state.page + '/';
            }
            var url = path + (qs ? '?' + qs : '');
            var current = window.location.pathname + window.location.search;
            if (current !== url) {
                history.replaceState(null, '', url);
            }
        }

        function navigateForPrimaryFilter(nextSlug) {
            var secondary = secondaryFilterParams();
            var qs = buildQueryString(secondary);

            if (!nextSlug) {
                window.location.href = '/' + (qs ? '?' + qs : '');
                return;
            }

            if (nextSlug !== taxonomySlug) {
                window.location.href = '/' + taxonomyType + '/' + encodeURIComponent(nextSlug) + '/' + (qs ? '?' + qs : '');
            }
        }

        function updateRangeOutput(slider) {
            var key = slider.getAttribute('data-filter');
            var wrap = filtersEl();
            if (!wrap || !key) return;
            var output = wrap.querySelector('[data-range-output="' + key + '"]');
            var min = parseFloat(slider.getAttribute('min') || '0');
            var max = parseFloat(slider.getAttribute('max') || '100');
            var val = parseFloat(slider.value);
            var pct = max > min ? ((val - min) / (max - min)) * 100 : 0;
            slider.style.setProperty('--range-progress', pct + '%');
            if (!output) return;
            var suffix = slider.getAttribute('data-filter-suffix') || '';
            if (isNaN(val) || val <= min) {
                output.textContent = 'Любой';
                return;
            }
            var text = val % 1 === 0 ? String(Math.round(val)) : String(val);
            output.textContent = suffix ? text + suffix : text;
        }

        function bindRangeOutputs() {
            var el = filtersEl();
            if (!el) return;
            el.querySelectorAll('[data-filter-type="range"]').forEach(updateRangeOutput);
        }

        function readFiltersFromDom() {
            var el = filtersEl();
            if (!el) return;
            ensureStateKeys();
            el.querySelectorAll('[data-filter]').forEach(function(node) {
                var key = node.getAttribute('data-filter');
                if (!key) return;
                var type = node.getAttribute('data-filter-type') || 'select';
                if (type === 'range') {
                    var min = parseFloat(node.getAttribute('min') || '0');
                    var val = parseFloat(node.value);
                    state[key] = !isNaN(val) && val > min ? String(val) : '';
                    return;
                }
                state[key] = node.value ? String(node.value) : '';
            });
        }

        function readPageFromDom() {
            if (!paginationWrap) return;
            var current = paginationWrap.querySelector('.pagination__current');
            if (current) {
                state.page = parseInt(current.textContent, 10) || 1;
            }
        }

        function parsePageFromHref(href) {
            if (!href) return 1;
            var match = href.match(/\/page\/(\d+)\/?/);
            return match ? parseInt(match[1], 10) : 1;
        }

        function buildBrowseUrl() {
            var params = new URLSearchParams();
            Object.keys(state).forEach(function(key) {
                if (key === 'page') return;
                if (taxonomyType && key === primaryFilterKey) return;
                if (taxonomyType === 'year' && (key === 'year_from' || key === 'year_to')) return;
                if (!isDefaultCatalogFilter(key, state[key])) {
                    params.set(key, state[key]);
                }
            });
            if (state.page > 1) params.set('page', String(state.page));
            var qs = params.toString();
            if (browseApi) {
                return browseApi + (qs ? '?' + qs : '');
            }
            return '/api/catalog/browse' + (qs ? '?' + qs : '');
        }

        function setLoading(isLoading) {
            loading = isLoading;
            root.classList.toggle('is-loading', isLoading);
            if (countEl) countEl.classList.toggle('is-loading', isLoading);
        }

        function scrollToResults() {
            var results = root.querySelector('[data-catalog-results]');
            if (!results) return;
            var top = results.getBoundingClientRect().top + window.pageYOffset - 80;
            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        }

        function hasActiveSecondaryFilters() {
            return Object.keys(state).some(function(key) {
                if (key === 'page') return false;
                if (taxonomyType && key === primaryFilterKey) return false;
                if (taxonomyType === 'year' && (key === 'year_from' || key === 'year_to')) return false;
                return !isDefaultCatalogFilter(key, state[key]);
            });
        }

        function pluralSeriesWord(n) {
            var mod10 = n % 10;
            var mod100 = n % 100;
            if (mod100 >= 11 && mod100 <= 14) return 'сериалов';
            if (mod10 === 1) return 'сериал';
            if (mod10 >= 2 && mod10 <= 4) return 'сериала';
            return 'сериалов';
        }

        function updateCount(total) {
            if (!countEl) return;

            var numEl = countEl.querySelector('[data-catalog-count-num]');
            var textEl = countEl.querySelector('[data-catalog-count-text]');
            countEl.classList.remove('is-empty', 'is-loading');

            if (total > 0) {
                countEl.hidden = false;
                if (numEl && textEl) {
                    numEl.textContent = String(total);
                    textEl.textContent = pluralSeriesWord(total);
                } else {
                    countEl.textContent = total + ' ' + pluralSeriesWord(total);
                }
                return;
            }

            if (!hasActiveSecondaryFilters()) {
                countEl.hidden = true;
                countEl.classList.remove('is-empty');
                if (numEl) numEl.textContent = '';
                if (textEl) textEl.textContent = '';
                return;
            }

            countEl.hidden = false;
            countEl.classList.add('is-empty');
            if (numEl) numEl.textContent = '';
            if (textEl) {
                textEl.textContent = 'Ничего не найдено';
            } else {
                countEl.textContent = 'Ничего не найдено';
            }
        }

        function resetFilters() {
            Object.keys(state).forEach(function(key) {
                if (key === 'page') return;
                if (taxonomyType === 'year' && (key === 'year_from' || key === 'year_to')) {
                    state[key] = taxonomySlug;
                    return;
                }
                if (taxonomyType && key === primaryFilterKey) {
                    state[key] = taxonomySlug;
                    return;
                }
                if (key === 'popularity_sort' || key === 'user_rating_sort' || key === 'views_sort' || key === 'comments_sort') {
                    state[key] = 'desc';
                    return;
                }
                state[key] = '';
            });

            var el = filtersEl();
            if (el) {
                el.querySelectorAll('[data-filter]').forEach(function(node) {
                    var key = node.getAttribute('data-filter');
                    if (!key) return;
                    if (taxonomyType === 'year' && (key === 'year_from' || key === 'year_to')) {
                        node.value = taxonomySlug;
                        return;
                    }
                    if (taxonomyType && key === primaryFilterKey) {
                        node.value = taxonomySlug;
                        return;
                    }
                    if (key === 'popularity_sort' || key === 'user_rating_sort' || key === 'views_sort' || key === 'comments_sort') {
                        node.value = 'desc';
                        return;
                    }
                    var type = node.getAttribute('data-filter-type') || 'select';
                    if (type === 'range') {
                        node.value = node.getAttribute('min') || '0';
                    } else {
                        node.value = '';
                    }
                });
            }
            bindRangeOutputs();
        }

        function loadCatalog(page, scroll) {
            if (loading) return;

            state.page = page || 1;
            var currentRequest = ++requestId;
            setLoading(true);

            fetch(buildBrowseUrl(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                .then(readJsonResponse)
                .then(function(res) {
                    if (currentRequest !== requestId) return;
                    setLoading(false);
                    if (!res.ok || !res.data || !res.data.ok) return;

                    var data = res.data;
                    if (filtersWrap && data.filters_html !== undefined) {
                        filtersWrap.innerHTML = data.filters_html;
                    }
                    if (gridWrap && data.grid_html !== undefined) {
                        gridWrap.innerHTML = data.grid_html;
                    }
                    if (paginationWrap) {
                        paginationWrap.innerHTML = data.pagination_html || '';
                    }

                    readFiltersFromDom();
                    bindRangeOutputs();
                    updateCount(data.total || 0);
                    updateBrowserUrl();
                    if (scroll) scrollToResults();
                })
                .catch(function() {
                    if (currentRequest !== requestId) return;
                    setLoading(false);
                });
        }

        root.addEventListener('change', function(e) {
            var el = e.target.closest('[data-filter]');
            if (!el || !root.contains(el)) return;
            if (el.getAttribute('data-filter-type') === 'range') return;
            readFiltersFromDom();

            var changedKey = el.getAttribute('data-filter');
            if (taxonomyType === 'year' && (changedKey === 'year_from' || changedKey === 'year_to')) {
                navigateForPrimaryFilter(state[changedKey] || '');
                return;
            }
            if (taxonomyType && changedKey === primaryFilterKey) {
                navigateForPrimaryFilter(state[primaryFilterKey] || '');
                return;
            }

            loadCatalog(1, true);
        });

        root.addEventListener('input', function(e) {
            var slider = e.target.closest('[data-filter-type="range"]');
            if (!slider || !root.contains(slider)) return;
            updateRangeOutput(slider);
            clearTimeout(rangeTimer);
            rangeTimer = setTimeout(function() {
                readFiltersFromDom();
                loadCatalog(1, false);
            }, 350);
        });

        root.addEventListener('click', function(e) {
            var resetBtn = e.target.closest('[data-catalog-reset]');
            if (resetBtn && root.contains(resetBtn)) {
                e.preventDefault();
                resetFilters();
                loadCatalog(1, false);
                return;
            }

            var pageLink = e.target.closest('.pagination a');
            if (pageLink && paginationWrap && paginationWrap.contains(pageLink)) {
                e.preventDefault();
                loadCatalog(parsePageFromHref(pageLink.getAttribute('href')), true);
            }
        });

        ensureStateKeys();
        readFiltersFromDom();
        readPageFromDom();
        bindRangeOutputs();
        updateCount(parseInt(root.getAttribute('data-total'), 10) || 0);
    }

    function initBookmarkHint() {
        var modal = document.querySelector('[data-bookmark-modal]');
        var toast = document.getElementById('bookmarkToast');
        var toastTimer = null;
        var isMac = /Mac|iPhone|iPad|iPod/i.test(navigator.userAgent || '');

        function openModal() {
            if (!modal) return;
            modal.hidden = false;
            modal.classList.add('is-active');
            document.body.classList.add('bookmark-lock');
        }

        function closeModal() {
            if (!modal) return;
            modal.hidden = true;
            modal.classList.remove('is-active');
            document.body.classList.remove('bookmark-lock');
        }

        function showThanks() {
            if (!toast) return;
            toast.textContent = cfg('series_ui_bookmark_thanks', 'Спасибо! Сайт добавлен в закладки — вы больше ничего не пропустите.');
            toast.hidden = false;
            toast.classList.add('is-visible');
            if (toastTimer) window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(function() {
                toast.classList.remove('is-visible');
                window.setTimeout(function() {
                    toast.hidden = true;
                }, 220);
            }, 4200);
        }

        document.querySelectorAll('[data-bookmark-open]').forEach(function(btn) {
            btn.addEventListener('click', openModal);
        });

        if (modal) {
            modal.querySelectorAll('[data-bookmark-close]').forEach(function(el) {
                el.addEventListener('click', closeModal);
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('is-active')) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            var modKey = isMac ? e.metaKey : e.ctrlKey;
            if (!modKey || e.altKey || e.shiftKey) return;
            if (e.key !== 'd' && e.key !== 'D' && e.key !== 'в' && e.key !== 'В') return;
            if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable)) {
                return;
            }
            showThanks();
        });
    }

    function initSeriesPreview() {
        var tip = document.createElement('div');
        tip.className = 'movie-tip';
        tip.setAttribute('role', 'dialog');
        tip.setAttribute('aria-modal', 'true');
        tip.setAttribute('aria-hidden', 'true');
        document.body.appendChild(tip);

        var cache = Object.create(null);
        var activeItem = null;
        var loadToken = 0;
        var showTimer = null;
        var hideTimer = null;
        var mobileOpen = false;
        var mobileQuery = window.matchMedia('(max-width: 760px)');

        function isMobileLayout() {
            return mobileQuery.matches;
        }

        function clearHoverTimers() {
            clearTimeout(showTimer);
            clearTimeout(hideTimer);
        }

        function scheduleShow(item, seriesId) {
            clearTimeout(hideTimer);
            if (!item || !seriesId) return;
            if (tip.classList.contains('is-show') && activeItem === item) return;
            clearTimeout(showTimer);
            showTimer = setTimeout(function() {
                openPreview(seriesId, item);
            }, 120);
        }

        function scheduleHide() {
            clearTimeout(showTimer);
            clearTimeout(hideTimer);
            hideTimer = setTimeout(function() {
                closeTip();
            }, 300);
        }

        tip.addEventListener('mouseenter', function() {
            if (!isMobileLayout()) {
                clearTimeout(hideTimer);
            }
        });

        tip.addEventListener('mouseleave', function(e) {
            if (isMobileLayout()) return;
            var related = e.relatedTarget;
            if (related && related.closest && related.closest('[data-series-info]')) return;
            scheduleHide();
        });

        function closeTip() {
            tip.classList.remove('is-show');
            tip.setAttribute('aria-hidden', 'true');
            mobileOpen = false;
            clearHoverTimers();
            if (activeItem) {
                activeItem.classList.remove('is-info-active');
                activeItem.querySelectorAll('[data-series-info]').forEach(function(btn) {
                    btn.classList.remove('is-active');
                });
                activeItem = null;
            }
            window.setTimeout(function() {
                if (!tip.classList.contains('is-show')) {
                    tip.innerHTML = '';
                }
            }, 180);
        }

        function positionTip(item) {
            if (isMobileLayout()) {
                tip.style.left = '';
                tip.style.right = '';
                tip.style.top = '';
                tip.style.bottom = '';
                tip.removeAttribute('data-side');
                return;
            }

            var rect = item.getBoundingClientRect();
            var tipW = tip.offsetWidth || 355;
            var tipH = tip.offsetHeight || 280;
            var gap = 6;
            var pad = 12;
            var side = 'right';
            var left = rect.right + gap;
            var top = rect.top + (rect.height / 2) - (tipH / 2);

            if (left + tipW > window.innerWidth - pad) {
                side = 'left';
                left = rect.left - gap - tipW;
            }

            if (left < pad) {
                side = 'bottom';
                left = rect.left + (rect.width / 2) - (tipW / 2);
                top = rect.bottom + gap;
                if (top + tipH > window.innerHeight - pad) {
                    side = 'top';
                    top = rect.top - gap - tipH;
                }
            }

            left = Math.max(pad, Math.min(left, window.innerWidth - tipW - pad));
            top = Math.max(pad, Math.min(top, window.innerHeight - tipH - pad));

            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
            tip.style.right = 'auto';
            tip.style.bottom = 'auto';
            tip.setAttribute('data-side', side);

            if (side === 'right' || side === 'left') {
                var arrowTop = rect.top + (rect.height / 2) - top - 6;
                arrowTop = Math.max(14, Math.min(arrowTop, tipH - 14));
                tip.style.setProperty('--arrow-top', arrowTop + 'px');
            } else {
                var arrowLeft = rect.left + (rect.width / 2) - left - 6;
                arrowLeft = Math.max(14, Math.min(arrowLeft, tipW - 14));
                tip.style.setProperty('--arrow-left', arrowLeft + 'px');
            }
        }

        function showLoading(item) {
            tip.innerHTML = '<div class="movie-tip__status"><span class="movie-tip__loader"></span><span>Загрузка...</span></div>';
            tip.classList.add('is-show');
            tip.setAttribute('aria-hidden', 'false');
            positionTip(item);
        }

        function showError() {
            tip.innerHTML = '<div class="movie-tip__content"><div class="movie-tip__status">Не удалось загрузить информацию о сериале.</div><div class="movie-tip__actions"><button type="button" class="movie-tip__btn movie-tip__btn--ghost" data-movie-tip-close>Закрыть</button></div></div>';
            tip.classList.add('is-show');
            tip.setAttribute('aria-hidden', 'false');
            if (activeItem) {
                window.requestAnimationFrame(function() {
                    positionTip(activeItem);
                });
            }
        }

        function handleInfoClick(e) {
            if (!isMobileLayout()) return;

            var btn = e.target.closest('[data-series-info]');
            if (!btn) return;

            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }

            var item = btn.closest('.th-item');
            var seriesId = item && item.getAttribute('data-series-id');
            if (!seriesId || !item) return;

            if (mobileOpen && activeItem === item && tip.classList.contains('is-show')) {
                closeTip();
                return;
            }

            mobileOpen = true;
            btn.classList.add('is-active');
            openPreview(seriesId, item);
        }

        function handleDesktopHover(e) {
            if (isMobileLayout()) return;

            var btn = e.target.closest('[data-series-info]');
            if (btn) {
                var item = btn.closest('.th-item[data-series-id]');
                if (item) {
                    scheduleShow(item, item.getAttribute('data-series-id'));
                }
                return;
            }

            if (tip.contains(e.target)) {
                clearTimeout(hideTimer);
            }
        }

        function handleDesktopLeave(e) {
            if (isMobileLayout()) return;

            var related = e.relatedTarget;
            var fromBtn = e.target.closest('[data-series-info]');
            var fromTip = tip.contains(e.target);

            if (!fromBtn && !fromTip) return;

            if (related) {
                if (fromBtn && (fromBtn.contains(related) || tip.contains(related))) return;
                if (fromTip && (tip.contains(related) || related.closest('[data-series-info]'))) return;
            }

            scheduleHide();
        }

        function openPreview(seriesId, item) {
            seriesId = String(seriesId || '');
            if (!seriesId || !item) return;

            if (activeItem && activeItem !== item) {
                activeItem.classList.remove('is-info-active');
            }

            activeItem = item;
            activeItem.classList.add('is-info-active');

            if (cache[seriesId]) {
                tip.innerHTML = cache[seriesId];
                tip.classList.add('is-show');
                tip.setAttribute('aria-hidden', 'false');
                window.requestAnimationFrame(function() {
                    positionTip(item);
                });
                return;
            }

            var token = ++loadToken;
            showLoading(item);

            fetchJson(seriesApiPath(seriesId) + '/preview')
                .then(function(data) {
                    if (token !== loadToken) return;
                    if (!data || !data.ok || !data.html) {
                        showError();
                        return;
                    }
                    cache[seriesId] = data.html;
                    tip.innerHTML = data.html;
                    tip.classList.add('is-show');
                    tip.setAttribute('aria-hidden', 'false');
                    window.requestAnimationFrame(function() {
                        positionTip(item);
                    });
                })
                .catch(function() {
                    if (token !== loadToken) return;
                    showError();
                });
        }

        document.addEventListener('mouseover', handleDesktopHover);
        document.addEventListener('mouseout', handleDesktopLeave);
        document.addEventListener('click', handleInfoClick, true);

        document.addEventListener('click', function(e) {
            if (e.target.closest('[data-movie-tip-close]')) {
                e.preventDefault();
                closeTip();
                return;
            }

            if (!isMobileLayout()) return;

            if (tip.classList.contains('is-show') && !e.target.closest('.movie-tip') && !e.target.closest('[data-series-info]')) {
                closeTip();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && tip.classList.contains('is-show')) {
                closeTip();
            }
        });

        window.addEventListener('resize', function() {
            if (activeItem && tip.classList.contains('is-show')) {
                positionTip(activeItem);
            }
        });

        window.addEventListener('scroll', function() {
            if (activeItem && tip.classList.contains('is-show')) {
                positionTip(activeItem);
            }
        }, true);

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', closeTip);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(closeTip);
        }
    }

    function initPersonPhotoHints() {
        var links = document.querySelectorAll('.serial-detail__link--person[data-person-photo]');
        if (!links.length) return;

        var hint = document.createElement('div');
        hint.className = 'person-photo-hint';
        hint.hidden = true;
        var hintImg = document.createElement('img');
        hintImg.alt = '';
        hint.appendChild(hintImg);
        document.body.appendChild(hint);

        function hideHint() {
            hint.classList.remove('is-visible');
            window.setTimeout(function() {
                if (!hint.classList.contains('is-visible')) {
                    hint.hidden = true;
                }
            }, 160);
        }

        function showHint(link) {
            var photo = link.getAttribute('data-person-photo');
            if (!photo) return;
            hintImg.src = photo;
            hint.hidden = false;
            var rect = link.getBoundingClientRect();
            var hintW = 128;
            var hintH = 168;
            var left = rect.left + rect.width / 2 - hintW / 2;
            var top = rect.top - hintH - 10;
            if (top < 8) {
                top = rect.bottom + 10;
            }
            left = Math.max(8, Math.min(left, window.innerWidth - hintW - 8));
            top = Math.max(8, Math.min(top, window.innerHeight - hintH - 8));
            hint.style.left = left + 'px';
            hint.style.top = top + 'px';
            window.requestAnimationFrame(function() {
                hint.classList.add('is-visible');
            });
        }

        links.forEach(function(link) {
            link.addEventListener('mouseenter', function() {
                showHint(link);
            });
            link.addEventListener('mouseleave', hideHint);
            link.addEventListener('focus', function() {
                showHint(link);
            });
            link.addEventListener('blur', hideHint);
        });
    }

    function initAnticipationVotes() {
        function applyPayload(root, data) {
            if (!root || !data) return;

            root.querySelectorAll('[data-anticipation-percent]').forEach(function(el) {
                var isRatingStrong = el.tagName === 'STRONG' && el.closest('.expected-rating');
                el.textContent = isRatingStrong ? String(data.percent) : String(data.percent) + '%';
            });

            root.querySelectorAll('[data-anticipation-votes]').forEach(function(el) {
                el.textContent = data.votes_label || '';
            });

            root.querySelectorAll('[data-anticipation-bar]').forEach(function(el) {
                el.style.width = String(data.percent) + '%';
            });

            root.querySelectorAll('[data-anticipation-vote="1"]').forEach(function(btn) {
                btn.classList.toggle('is-active', !!data.watch_active);
                btn.classList.toggle('success', !!data.wait_active);
            });

            root.querySelectorAll('[data-anticipation-vote="-1"]').forEach(function(btn) {
                btn.classList.toggle('success', !!data.nowait_active);
            });
        }

        function vote(seriesId, value, roots) {
            postJson(seriesApiPath(seriesId) + '/anticipation', { value: value })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.ok) return;
                    roots.forEach(function(root) { applyPayload(root, data); });
                })
                .catch(function() {
                    flashNotice('Не удалось сохранить оценку ожидания.', true);
                });
        }

        var seriesMap = {};

        document.querySelectorAll('[data-anticipation-root], [data-anticipation-card]').forEach(function(root) {
            var seriesId = root.getAttribute('data-series-id');
            if (!seriesId) return;
            if (!seriesMap[seriesId]) seriesMap[seriesId] = [];
            seriesMap[seriesId].push(root);
        });

        Object.keys(seriesMap).forEach(function(seriesId) {
            var roots = seriesMap[seriesId];

            fetch(seriesApiPath(seriesId) + '/anticipation', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    roots.forEach(function(root) { applyPayload(root, data); });
                })
                .catch(function() {
                    // Silent: widget can stay at server-rendered defaults.
                });

            roots.forEach(function(root) {
                root.querySelectorAll('[data-anticipation-vote]').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var value = parseInt(btn.getAttribute('data-anticipation-vote'), 10);
                        vote(seriesId, value, roots);
                    });
                });
            });
        });
    }

    function initHomeWatchHistory() {
        var section = document.querySelector('[data-watch-history-root]');
        if (!section || !cfgBool('watch_history_enabled', true)) return;

        var cards = section.querySelector('[data-watch-history-cards]');
        if (!cards) return;

        fetch('/api/watch-history', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.count || !data.html) return;

                cards.innerHTML = data.html;

                if (cards.children.length > 0) {
                    section.hidden = false;
                    initHomeCarousel();
                }
            })
            .catch(function() {});
    }

    function initSeriesWatchHistory() {
        var root = document.querySelector('.fmain[data-series-id]');
        if (!root || !cfgBool('watch_history_enabled', true)) return;

        var seriesId = root.getAttribute('data-series-id');
        if (!seriesId) return;

        pushLocalHistory(seriesId);
    }

    function initCsrfRefresh() {
        ensureCsrfToken();

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                csrfReadyPromise = null;
                // Re-read cookie; only hit /api/csrf if cookie is missing.
                ensureCsrfToken();
            }
        });

        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                csrfReadyPromise = null;
                ensureCsrfToken();
            }
        });
    }

    function initScheduleCalendar() {
        var root = document.querySelector('[data-schedule-calendar]');
        if (!root || root.getAttribute('data-cal-bound') === '1') return;
        root.setAttribute('data-cal-bound', '1');

        var apiUrl = root.getAttribute('data-api-url') || '/api/home/episode-calendar';
        var withDetails = root.getAttribute('data-details') === '1';
        var syncUrl = root.getAttribute('data-sync-url') || '';
        var gridEl = root.querySelector('[data-cal-grid]');
        var labelEl = root.querySelector('[data-cal-month-label]');
        var dayTitleEl = root.querySelector('[data-cal-day-title]');
        var dayListEl = root.querySelector('[data-cal-day-list]');
        var timelineEl = document.querySelector('[data-cal-timeline]');
        var timelineTitleEl = document.querySelector('.schedule-timeline__toolbar-title');
        var timelineCountEl = document.querySelector('.schedule-timeline__toolbar-count');
        var prevBtn = root.querySelector('[data-cal-prev]');
        var nextBtn = root.querySelector('[data-cal-next]');
        var initialNode = root.querySelector('[data-cal-initial]');

        if (!gridEl || !labelEl || !dayTitleEl || !dayListEl) return;

        var state = null;
        var selectedDate = null;
        var loading = false;

        function pad2(n) {
            return n < 10 ? '0' + n : String(n);
        }

        function formatRuDate(iso) {
            var parts = String(iso || '').split('-');
            if (parts.length !== 3) return iso || '';
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        }

        function escapeHtml(str) {
            return String(str == null ? '' : str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function parseInitial() {
            if (!initialNode) return null;
            try {
                return JSON.parse(initialNode.textContent || '{}');
            } catch (e) {
                return null;
            }
        }

        function defaultSelectDate(data) {
            if (!data) return null;
            var today = data.today;
            if (today && data.days && data.days[today]) return today;
            var keys = data.days ? Object.keys(data.days) : [];
            if (!keys.length) return null;
            keys.sort();
            if (today) {
                var upcoming = keys.filter(function(k) { return k >= today; });
                if (upcoming.length) return upcoming[0];
            }
            return keys[keys.length - 1];
        }

        function syncBrowserUrl(data) {
            if (!syncUrl || !data || !window.history || !window.history.replaceState) return;
            var now = new Date();
            var isCurrent = Number(data.year) === now.getFullYear() && Number(data.month) === (now.getMonth() + 1);
            var nextUrl = syncUrl;
            if (!isCurrent) {
                nextUrl += '?year=' + encodeURIComponent(data.year) + '&month=' + encodeURIComponent(data.month);
            }
            if (window.location.pathname + window.location.search !== nextUrl) {
                window.history.replaceState({}, '', nextUrl);
            }
        }

        function highlightTimeline(dateIso) {
            if (!timelineEl) return;
            var groups = timelineEl.querySelectorAll('[data-cal-day]');
            groups.forEach(function(group) {
                group.classList.toggle('is-selected', group.getAttribute('data-cal-day') === dateIso);
            });
        }

        function scrollTimelineTo(dateIso) {
            if (!timelineEl || !dateIso) return;
            var group = timelineEl.querySelector('[data-cal-day="' + dateIso + '"]');
            if (!group) return;
            window.requestAnimationFrame(function() {
                group.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }

        function renderEpisodeCard(item) {
            var poster = item.poster_url
                ? '<div class="schedule-timeline__poster"><img src="' + escapeHtml(item.poster_url) + '" alt="" loading="lazy"></div>'
                : '<div class="schedule-timeline__poster schedule-timeline__poster--empty"><span class="fa fa-film"></span></div>';
            var original = item.title_original
                ? '<div class="schedule-timeline__original">' + escapeHtml(item.title_original) + '</div>'
                : '';
            var metaParts = [];
            if (item.year) metaParts.push(item.year);
            if (item.age_label) metaParts.push(escapeHtml(item.age_label));
            if (item.genres_label) metaParts.push(escapeHtml(item.genres_label));
            if (item.countries_label) metaParts.push(escapeHtml(item.countries_label));
            var meta = metaParts.length
                ? '<div class="schedule-timeline__meta">' + metaParts.join(' · ') + '</div>'
                : '';
            var episode = '<div class="schedule-timeline__episode">' +
                item.season_number + ' сезон / ' + item.episode_number + ' эпизод' +
                (item.episode_title ? ' · ' + escapeHtml(item.episode_title) : '') +
                '</div>';
            var statusClass = item.is_released ? ' is-released' : '';
            var statusText = item.is_released ? 'Вышла' : 'Ожидается';
            var channel = item.channel_name
                ? '<span class="schedule-timeline__channel">' + escapeHtml(item.channel_name) + '</span>'
                : '';
            var ratings = '';
            if (item.kp_rating) {
                ratings += '<span class="schedule-timeline__rate schedule-timeline__rate--kp" title="Кинопоиск">' + escapeHtml(item.kp_rating) + '</span>';
            }
            if (item.imdb_rating) {
                ratings += '<span class="schedule-timeline__rate schedule-timeline__rate--imdb" title="IMDb">' + escapeHtml(item.imdb_rating) + '</span>';
            }

            return '<a class="schedule-timeline__item" href="' + escapeHtml(item.series_url) + '">' +
                poster +
                '<div class="schedule-timeline__body">' +
                '<div class="schedule-timeline__title">' + escapeHtml(item.series_title) + '</div>' +
                original + meta + episode +
                '<div class="schedule-timeline__foot">' +
                '<span class="schedule-cal__item-status' + statusClass + '">' + statusText + '</span>' +
                channel +
                '</div></div>' +
                (ratings ? '<div class="schedule-timeline__ratings">' + ratings + '</div>' : '') +
                '</a>';
        }

        function renderTimeline(dateIso) {
            if (!timelineEl || !state) return;
            var days = state.timeline && state.timeline.length
                ? state.timeline
                : Object.keys(state.days || {}).sort().map(function(date) {
                    return {
                        date: date,
                        date_label: formatRuDate(date),
                        weekday: '',
                        is_today: state.today === date,
                        count: (state.days[date] || []).length,
                        episodes: state.days[date] || []
                    };
                });

            if (timelineTitleEl && state.month_label) {
                timelineTitleEl.innerHTML = '<span class="fa fa-list"></span> Серии за ' + escapeHtml(state.month_label);
            }
            if (timelineCountEl) {
                timelineCountEl.textContent = state.episode_count || 0;
                timelineCountEl.hidden = !state.episode_count;
            }

            if (!days.length) {
                timelineEl.innerHTML = '<div class="schedule-timeline__empty">В этом месяце серий нет</div>';
                return;
            }

            timelineEl.innerHTML = days.map(function(day) {
                var classes = ['schedule-timeline__day'];
                if (day.is_today) classes.push('is-today');
                if (dateIso && day.date === dateIso) classes.push('is-selected');
                return '<section class="' + classes.join(' ') + '" data-cal-day="' + escapeHtml(day.date) + '" id="cal-day-' + escapeHtml(day.date) + '">' +
                    '<h2 class="schedule-timeline__heading">' +
                    '<span class="schedule-timeline__date">' + escapeHtml(day.date_label) + '</span>' +
                    (day.weekday ? '<span class="schedule-timeline__weekday">' + escapeHtml(day.weekday) + '</span>' : '') +
                    '<span class="schedule-timeline__count">' + day.count + '</span>' +
                    '</h2>' +
                    '<div class="schedule-timeline__list">' +
                    (day.episodes || []).map(renderEpisodeCard).join('') +
                    '</div></section>';
            }).join('');
        }

        function renderDayPanel(dateIso) {
            selectedDate = dateIso;
            highlightTimeline(dateIso);
            if (!dateIso) {
                dayTitleEl.textContent = 'Выберите день';
                dayListEl.innerHTML = '<div class="schedule-cal__empty">Нажмите на день, чтобы увидеть серии</div>';
                return;
            }

            dayTitleEl.textContent = 'Серии за ' + formatRuDate(dateIso);
            var items = (state && state.days && state.days[dateIso]) ? state.days[dateIso] : [];
            if (!items.length) {
                dayListEl.innerHTML = '<div class="schedule-cal__empty">В этот день серий нет</div>';
                return;
            }

            dayListEl.innerHTML = items.map(function(item) {
                var poster = item.poster_url ?
                    '<div class="schedule-cal__poster"><img src="' + escapeHtml(item.poster_url) + '" alt="" loading="lazy"></div>' :
                    '<div class="schedule-cal__poster schedule-cal__poster--empty"><span class="fa fa-film"></span></div>';
                var statusClass = item.is_released ? ' is-released' : '';
                var statusText = item.is_released ? 'Вышла' : 'Ожидается';
                var meta = 'S' + item.season_number + 'E' + item.episode_number +
                    (item.episode_title ? ' · ' + escapeHtml(item.episode_title) : '');

                return '<a class="schedule-cal__item" href="' + escapeHtml(item.series_url) + '">' +
                    poster +
                    '<div class="schedule-cal__item-body">' +
                    '<div class="schedule-cal__item-title">' + escapeHtml(item.series_title) + '</div>' +
                    '<div class="schedule-cal__item-meta">' + meta + '</div>' +
                    '<span class="schedule-cal__item-status' + statusClass + '">' + statusText + '</span>' +
                    '</div></a>';
            }).join('');
        }

        function renderGrid() {
            if (!state) return;
            labelEl.textContent = state.month_label || '';
            var year = Number(state.year);
            var month = Number(state.month);
            var first = new Date(year, month - 1, 1);
            var startOffset = (first.getDay() + 6) % 7; // Monday-first
            var daysInMonth = new Date(year, month, 0).getDate();
            var prevDays = new Date(year, month - 1, 0).getDate();
            var html = '';
            var totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;

            for (var i = 0; i < totalCells; i++) {
                var dayNum;
                var iso;
                var classes = ['schedule-cal__day'];
                var inMonth = false;

                if (i < startOffset) {
                    dayNum = prevDays - startOffset + i + 1;
                    var pm = month === 1 ? 12 : month - 1;
                    var py = month === 1 ? year - 1 : year;
                    iso = py + '-' + pad2(pm) + '-' + pad2(dayNum);
                    classes.push('is-other');
                } else if (i >= startOffset + daysInMonth) {
                    dayNum = i - startOffset - daysInMonth + 1;
                    var nm = month === 12 ? 1 : month + 1;
                    var ny = month === 12 ? year + 1 : year;
                    iso = ny + '-' + pad2(nm) + '-' + pad2(dayNum);
                    classes.push('is-other');
                } else {
                    dayNum = i - startOffset + 1;
                    iso = year + '-' + pad2(month) + '-' + pad2(dayNum);
                    inMonth = true;
                }

                var dayEps = (state.days && state.days[iso]) ? state.days[iso] : [];
                var epsCount = dayEps.length;
                var hasEps = epsCount > 0;
                if (state.today === iso) classes.push('is-today');
                if (hasEps) classes.push('has-eps');
                if (selectedDate === iso) classes.push('is-selected');

                html += '<button type="button" class="dontusebuttonclass ' + classes.join(' ') + '"' +
                    ' data-cal-date="' + iso + '"' +
                    (inMonth ? '' : ' disabled tabindex="-1"') +
                    (state.today === iso ? ' aria-current="date"' : '') +
                    (selectedDate === iso ? ' aria-pressed="true"' : ' aria-pressed="false"') +
                    ' aria-label="' + formatRuDate(iso) +
                    (state.today === iso ? ', сегодня' : '') +
                    (selectedDate === iso ? ', выбран' : '') +
                    (hasEps ? (', серий: ' + epsCount) : '') +
                    '"' +
                    '>' +
                    '<span>' + dayNum + '</span>' +
                    (hasEps ? '<span class="schedule-cal__count" aria-hidden="true">' + epsCount + '</span>' : '') +
                    '</button>';
            }

            gridEl.innerHTML = html;
        }

        function scrollToDayPanelIfMobile() {
            if (!window.matchMedia('(max-width: 991px)').matches) return;
            var panel = root.querySelector('.schedule-cal__day-panel');
            if (!panel) return;
            window.requestAnimationFrame(function() {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        function applyData(data, preferDate) {
            state = data;
            if (preferDate && data.days && data.days[preferDate]) {
                selectedDate = preferDate;
            } else if (!selectedDate || !(data.days && data.days[selectedDate])) {
                selectedDate = defaultSelectDate(data);
            }
            renderGrid();
            renderDayPanel(selectedDate);
            renderTimeline(selectedDate);
            syncBrowserUrl(data);
        }

        function setLoading(isLoading) {
            loading = isLoading;
            if (prevBtn) prevBtn.disabled = isLoading;
            if (nextBtn) nextBtn.disabled = isLoading;
            root.classList.toggle('is-loading', isLoading);
        }

        function loadMonth(year, month, preferDate) {
            if (loading) return;
            setLoading(true);
            var url = apiUrl + '?year=' + encodeURIComponent(year) + '&month=' + encodeURIComponent(month) +
                (withDetails ? '&details=1' : '');
            fetch(url, { headers: { Accept: 'application/json' } })
                .then(function(res) {
                    if (!res.ok) throw new Error('calendar load failed');
                    return res.json();
                })
                .then(function(data) {
                    applyData(data, preferDate || null);
                })
                .catch(function() {
                    dayListEl.innerHTML = '<div class="schedule-cal__empty">Не удалось загрузить календарь</div>';
                })
                .finally(function() {
                    setLoading(false);
                });
        }

        function shiftMonth(delta) {
            if (!state || loading) return;
            var y = Number(state.year);
            var m = Number(state.month) + delta;
            if (m < 1) {
                m = 12;
                y -= 1;
            } else if (m > 12) {
                m = 1;
                y += 1;
            }
            selectedDate = null;
            loadMonth(y, m);
        }

        gridEl.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-cal-date]');
            if (!btn || btn.disabled) return;
            var date = btn.getAttribute('data-cal-date');
            if (!date) return;
            selectedDate = date;
            renderGrid();
            renderDayPanel(date);
            highlightTimeline(date);
            scrollTimelineTo(date);
            scrollToDayPanelIfMobile();
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                shiftMonth(-1);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                shiftMonth(1);
            });
        }

        var initial = parseInitial();
        if (initial && initial.year && initial.month) {
            applyData(initial);
        } else {
            var now = new Date();
            loadMonth(now.getFullYear(), now.getMonth() + 1);
        }
    }

    function initDescriptionSlice() {
        var nodes = document.querySelectorAll('.slice-this');
        if (!nodes.length) return;

        var collapsedMax = window.matchMedia('(max-width: 640px)').matches ? 88 : 110;

        nodes.forEach(function (el) {
            if (el.getAttribute('data-slice-ready') === '1') return;
            el.setAttribute('data-slice-ready', '1');

            var fullHeight = el.scrollHeight;
            if (fullHeight <= collapsedMax + 24) return;

            el.classList.add('slice', 'slice-masked');
            el.style.height = collapsedMax + 'px';

            var btn = document.createElement('div');
            btn.className = 'slice-btn slice-btn--compact';
            var label = document.createElement('span');
            label.setAttribute('role', 'button');
            label.setAttribute('tabindex', '0');
            label.textContent = 'показать полностью';
            btn.appendChild(label);
            el.insertAdjacentElement('afterend', btn);

            var expanded = false;

            function toggle() {
                expanded = !expanded;
                if (expanded) {
                    el.classList.remove('slice-masked');
                    el.style.height = el.scrollHeight + 'px';
                    label.textContent = 'свернуть';
                    window.setTimeout(function () {
                        if (expanded) el.style.height = 'auto';
                    }, 220);
                } else {
                    var current = el.scrollHeight;
                    el.style.height = current + 'px';
                    // force reflow before collapsing
                    void el.offsetHeight;
                    el.classList.add('slice-masked');
                    el.style.height = collapsedMax + 'px';
                    label.textContent = 'показать полностью';
                }
            }

            label.addEventListener('click', toggle);
            label.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle();
                }
            });
        });
    }

    function initDetailsCollapse() {
        var rows = document.querySelector('[data-details-collapse]');
        if (!rows) return;

        var mq = window.matchMedia('(max-width: 1024px)');
        var visibleCount = 3;
        var details = rows.querySelectorAll('.serial-detail');
        if (details.length <= visibleCount) return;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dontusebuttonclass serial-details__toggle';
        btn.setAttribute('aria-expanded', 'false');
        rows.insertAdjacentElement('afterend', btn);

        function isExpanded() {
            return rows.classList.contains('is-expanded');
        }

        function update() {
            var compact = mq.matches;
            if (!compact) {
                rows.classList.remove('is-collapsed', 'is-expanded');
                btn.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
                return;
            }

            btn.hidden = false;
            if (isExpanded()) {
                rows.classList.remove('is-collapsed');
                btn.textContent = 'Скрыть информацию';
                btn.setAttribute('aria-expanded', 'true');
            } else {
                rows.classList.add('is-collapsed');
                rows.classList.remove('is-expanded');
                btn.textContent = 'Показать всю информацию';
                btn.setAttribute('aria-expanded', 'false');
            }
        }

        btn.addEventListener('click', function () {
            if (!mq.matches) return;
            if (isExpanded()) {
                rows.classList.remove('is-expanded');
            } else {
                rows.classList.add('is-expanded');
            }
            update();
        });

        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', update);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(update);
        }

        update();
    }

    function initScrollToPlayer() {
        var links = document.querySelectorAll('[data-scroll-to-player]');
        if (!links.length) return;

        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                var target = document.getElementById('player')
                    || document.querySelector('[data-trailer-box]');
                if (!target) return;
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                try {
                    history.replaceState(null, '', '#player');
                } catch (err) {}
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initCsrfRefresh();
        initAuthModal();
        initQuickSearch();
        initHeaderSearch();
        initMobileMenu();
        initThemeToggle();
        initHomeCarousel();
        initHomeSectionTabs();
        initHomeBlockTabs();
        initHomeContentTypeTabs();
        initHomeWatchHistory();
        initScheduleCalendar();
        initWatchlistDropdown();
        initSeriesEngagement();
        initSeriesWatchHistory();
        initAnticipationVotes();
        initEngagementTabs();
        initComments();
        initReviews();
        initCommentSpoilers();
        initEpisodesModal();
        initReactionsWidget();
        initPlayerTabs();
        initPlayerLight();
        initPlayerReport();
        initNotifyModal();
        initBookmarkHint();
        initHeaderNotifications();
        initHeaderFavouritesCount();
        initFavouritesPage();
        initProfileNotifications();
        initProfileTabs();
        initProfileForms();
        initProfileWatchlistRemove();
        initCatalogFilters();
        initSeriesPreview();
        initPersonPhotoHints();
        initSeriesGallery();
        initDescriptionSlice();
        initDetailsCollapse();
        initScrollToPlayer();
    });

    function initSeriesGallery() {
        var triggers = document.querySelectorAll('[data-series-gallery]');
        if (!triggers.length) return;

        var cache = {};
        var state = {
            items: [],
            index: 0,
            seriesId: null,
            title: '',
        };
        var root = null;
        var imgEl = null;
        var titleEl = null;
        var counterEl = null;
        var statusEl = null;
        var thumbsEl = null;
        var prevBtn = null;
        var nextBtn = null;
        var loading = false;

        function ensureModal() {
            if (root) return root;
            root = document.createElement('div');
            root.className = 'ls-gallery';
            root.hidden = true;
            root.setAttribute('role', 'dialog');
            root.setAttribute('aria-modal', 'true');
            root.innerHTML =
                '<div class="ls-gallery__backdrop" data-gallery-close></div>' +
                '<div class="ls-gallery__dialog">' +
                '  <div class="ls-gallery__head">' +
                '    <h2 class="ls-gallery__title" data-gallery-title></h2>' +
                '    <span class="ls-gallery__counter" data-gallery-counter></span>' +
                '    <button type="button" class="ls-gallery__close dontusebuttonclass" data-gallery-close aria-label="Закрыть">&times;</button>' +
                '  </div>' +
                '  <div class="ls-gallery__stage">' +
                '    <button type="button" class="ls-gallery__nav ls-gallery__nav--prev dontusebuttonclass" data-gallery-prev aria-label="Назад" hidden>&lsaquo;</button>' +
                '    <img class="ls-gallery__img" data-gallery-img alt="" hidden>' +
                '    <div class="ls-gallery__status" data-gallery-status>Загрузка…</div>' +
                '    <button type="button" class="ls-gallery__nav ls-gallery__nav--next dontusebuttonclass" data-gallery-next aria-label="Вперёд" hidden>&rsaquo;</button>' +
                '  </div>' +
                '  <div class="ls-gallery__thumbs" data-gallery-thumbs></div>' +
                '</div>';
            document.body.appendChild(root);
            imgEl = root.querySelector('[data-gallery-img]');
            titleEl = root.querySelector('[data-gallery-title]');
            counterEl = root.querySelector('[data-gallery-counter]');
            statusEl = root.querySelector('[data-gallery-status]');
            thumbsEl = root.querySelector('[data-gallery-thumbs]');
            prevBtn = root.querySelector('[data-gallery-prev]');
            nextBtn = root.querySelector('[data-gallery-next]');

            root.addEventListener('click', function(e) {
                var t = e.target;
                if (t.closest('[data-gallery-close]')) {
                    close();
                    return;
                }
                if (t.closest('[data-gallery-prev]')) {
                    showIndex(state.index - 1);
                    return;
                }
                if (t.closest('[data-gallery-next]')) {
                    showIndex(state.index + 1);
                    return;
                }
                var thumb = t.closest('[data-gallery-thumb]');
                if (thumb) {
                    var idx = parseInt(thumb.getAttribute('data-gallery-thumb'), 10);
                    if (!isNaN(idx)) showIndex(idx);
                }
            });

            document.addEventListener('keydown', function(e) {
                if (root.hidden) return;
                if (e.key === 'Escape') close();
                if (e.key === 'ArrowLeft') showIndex(state.index - 1);
                if (e.key === 'ArrowRight') showIndex(state.index + 1);
            });

            return root;
        }

        function setStatus(text) {
            if (!statusEl) return;
            if (text) {
                statusEl.textContent = text;
                statusEl.hidden = false;
            } else {
                statusEl.hidden = true;
            }
        }

        function showIndex(index) {
            if (!state.items.length) return;
            var len = state.items.length;
            state.index = ((index % len) + len) % len;
            var item = state.items[state.index];
            if (imgEl) {
                imgEl.hidden = false;
                imgEl.src = item.url;
                imgEl.alt = state.title || 'Галерея';
            }
            if (counterEl) {
                counterEl.textContent = (state.index + 1) + ' / ' + len;
            }
            if (prevBtn) prevBtn.hidden = len < 2;
            if (nextBtn) nextBtn.hidden = len < 2;
            if (thumbsEl) {
                var thumbs = thumbsEl.querySelectorAll('[data-gallery-thumb]');
                for (var i = 0; i < thumbs.length; i++) {
                    thumbs[i].classList.toggle('is-active', i === state.index);
                }
            }
            setStatus('');
        }

        function renderThumbs() {
            if (!thumbsEl) return;
            thumbsEl.innerHTML = '';
            state.items.forEach(function(item, i) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ls-gallery__thumb dontusebuttonclass' + (i === 0 ? ' is-active' : '');
                btn.setAttribute('data-gallery-thumb', String(i));
                btn.setAttribute('aria-label', 'Фото ' + (i + 1));
                var img = document.createElement('img');
                img.src = item.url;
                img.alt = '';
                img.loading = 'lazy';
                btn.appendChild(img);
                thumbsEl.appendChild(btn);
            });
        }

        function applyPayload(data) {
            var items = Array.isArray(data.items) ? data.items.filter(function(it) {
                return it && it.url;
            }) : [];
            state.title = data.title || 'Галерея';
            state.items = items;
            if (titleEl) titleEl.textContent = state.title;
            if (!items.length) {
                setStatus('Галерея пуста');
                return;
            }
            renderThumbs();
            showIndex(0);
        }

        function open(seriesId) {
            ensureModal();
            root.hidden = false;
            document.body.style.overflow = 'hidden';
            state.seriesId = seriesId;
            state.items = [];
            state.index = 0;
            if (imgEl) {
                imgEl.hidden = true;
                imgEl.removeAttribute('src');
            }
            if (thumbsEl) thumbsEl.innerHTML = '';
            if (counterEl) counterEl.textContent = '';
            if (prevBtn) prevBtn.hidden = true;
            if (nextBtn) nextBtn.hidden = true;
            if (titleEl) titleEl.textContent = 'Галерея';
            setStatus('Загрузка…');

            if (cache[seriesId]) {
                applyPayload(cache[seriesId]);
                return;
            }
            if (loading) return;
            loading = true;
            fetchJson(seriesApiPath(seriesId) + '/gallery')
                .then(function(data) {
                    loading = false;
                    if (!data || !data.ok) {
                        setStatus((data && data.message) || 'Не удалось загрузить галерею');
                        return;
                    }
                    cache[seriesId] = data;
                    if (String(state.seriesId) !== String(seriesId) || root.hidden) return;
                    applyPayload(data);
                })
                .catch(function() {
                    loading = false;
                    setStatus('Не удалось загрузить галерею');
                });
        }

        function close() {
            if (!root || root.hidden) return;
            root.hidden = true;
            document.body.style.overflow = '';
            if (imgEl) {
                imgEl.hidden = true;
                imgEl.removeAttribute('src');
            }
        }

        Array.prototype.forEach.call(triggers, function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var id = btn.getAttribute('data-series-id');
                if (id) open(id);
            });
        });
    }

    function initHeaderFavouritesCount() {
        ensureCsrfToken().then(function() {
            refreshHeaderFavouritesCount();
        });
        document.addEventListener('lordserial:auth-login', function() {
            refreshHeaderFavouritesCount();
        });
    }

    function initFavouritesPage() {
        var root = document.querySelector('[data-favourites-page]');
        if (!root || !cfgBool('favourites_enabled', true)) return;

        var grid = root.querySelector('[data-favourites-grid]');
        var empty = root.querySelector('[data-favourites-empty]');
        var countWrap = root.querySelector('[data-favourites-count]');
        var countNum = root.querySelector('[data-favourites-count-num]');
        var countWord = root.querySelector('[data-favourites-count-word]');
        if (!grid || !empty) return;

        function renderFavourites(data) {
            var html = (data && data.html) || '';
            var count = data && typeof data.count === 'number' ? data.count : 0;
            var word = (data && data.total_word) || '';

            if (count > 0 && html) {
                grid.innerHTML = html;
                grid.hidden = false;
                empty.hidden = true;
                if (countWrap) countWrap.hidden = false;
                if (countNum) countNum.textContent = String(count);
                if (countWord && word) countWord.textContent = word;
                if (Array.isArray(data.items)) {
                    setLocalFavourites(data.items.map(function(item) {
                        return parseInt(item.id, 10);
                    }).filter(function(id) { return id > 0; }));
                }
                updateHeaderFavouritesCount(count);
                return;
            }

            grid.innerHTML = '';
            grid.hidden = true;
            empty.hidden = false;
            if (countWrap) countWrap.hidden = true;
            updateHeaderFavouritesCount(0);
        }

        // Logged-in users already get SSR content; guests need client key/ids.
        if (isSiteLoggedIn() && grid.children.length > 0) return;

        var params = [];
        if (!isSiteLoggedIn()) {
            var guestKey = getGuestLibKey();
            if (guestKey) params.push('guest_key=' + encodeURIComponent(guestKey));
            getLocalFavourites().forEach(function(id) {
                params.push('ids[]=' + encodeURIComponent(String(id)));
            });
            params.push('sync=1');
        }

        var url = '/api/favourites' + (params.length ? ('?' + params.join('&')) : '');
        fetchJson(url)
            .then(function(data) {
                renderFavourites(data || {});
            })
            .catch(function() {
                if (!isSiteLoggedIn() && getLocalFavourites().length === 0) return;
                flashNotice('Не удалось загрузить избранное.', true);
            });
    }
})();