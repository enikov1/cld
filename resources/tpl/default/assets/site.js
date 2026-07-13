(function () {
    'use strict';

    var siteCfg = (function () {
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

    var LS_FAV_KEY = 'ls_favourites';
    var LS_HISTORY_KEY = 'ls_watch_history';

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
        return readLocalJson(LS_FAV_KEY, []).map(function (id) {
            return parseInt(id, 10);
        }).filter(function (id) { return id > 0; });
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
        var ids = getLocalFavourites().filter(function (item) { return item !== id; });
        if (active) ids.unshift(id);
        setLocalFavourites(ids.slice(0, cfgInt('favourites_list_limit', 100)));
    }

    function getLocalHistory() {
        return readLocalJson(LS_HISTORY_KEY, []).map(function (id) {
            return parseInt(id, 10);
        }).filter(function (id) { return id > 0; });
    }

    function pushLocalHistory(seriesId) {
        var id = parseInt(seriesId, 10);
        if (!id) return;
        var ids = getLocalHistory().filter(function (item) { return item !== id; });
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
            label.textContent = isFavourite
                ? cfg('favourites_ui_remove_label', 'В избранном')
                : cfg('favourites_ui_add_label', 'В избранное');
        }
    }

    function mergeGuestLibrary() {
        if (!cfgBool('favourites_enabled', true) && !cfgBool('watch_history_enabled', true)) return;

        postJson('/api/user-library/merge-guest', {
            favourites: getLocalFavourites(),
            history: getLocalHistory(),
        }).catch(function () {});
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function setCsrfToken(token) {
        if (!token) return;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
    }

    var csrfRefreshPromise = null;

    function refreshCsrfToken() {
        if (csrfRefreshPromise) return csrfRefreshPromise;
        csrfRefreshPromise = fetch('/api/csrf', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (r) {
                if (!r.ok) throw new Error('csrf refresh failed');
                return r.json();
            })
            .then(function (data) {
                if (data && data.token) setCsrfToken(data.token);
                return csrfToken();
            })
            .catch(function () {
                return csrfToken();
            })
            .finally(function () {
                csrfRefreshPromise = null;
            });
        return csrfRefreshPromise;
    }

    function csrfHeaders(extra) {
        return Object.assign({
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        }, extra || {});
    }

    function fetchWithCsrf(url, options, retried) {
        options = options || {};
        var headers = csrfHeaders(options.headers);
        if (options.body && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }

        return fetch(url, Object.assign({}, options, {
            credentials: 'same-origin',
            headers: headers,
        })).then(function (response) {
            if (response.status === 419 && !retried) {
                return refreshCsrfToken().then(function () {
                    return fetchWithCsrf(url, options, true);
                });
            }
            return response;
        });
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
        return response.json().then(function (data) {
            return { ok: response.ok, status: response.status, data: data };
        }).catch(function () {
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

        form.querySelectorAll('input, textarea, select').forEach(function (input) {
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

        Object.keys(errors).forEach(function (field) {
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
            Object.keys(data.errors).forEach(function (key) {
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
        form.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    }

    function applyFieldErrors(form, errors) {
        if (!errors) return;
        Object.keys(errors).forEach(function (field) {
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
        fd.forEach(function (value, key) {
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

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var action = form.getAttribute('action');
            if (!action) return;

            var feedback = opts.feedback
                || form.querySelector('[data-form-feedback]')
                || (form.closest('[data-auth-panel]') ? form.closest('[data-auth-panel]').querySelector('.auth-form-feedback') : null);
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
                .then(function (res) {
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
                        window.setTimeout(function () {
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
                .catch(function () {
                    showFeedback(feedback, 'Не удалось отправить форму. Проверьте соединение.', true);
                })
                .finally(function () {
                    if (submitBtn) submitBtn.disabled = false;
                });
        });
    }

    function openAuthPanel(name) {
        var overlay = document.getElementById('loginOverlay');
        if (!overlay) return;
        overlay.classList.add('is-active');
        overlay.querySelectorAll('[data-auth-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-auth-panel') !== name;
        });
    }

    function closeAuthModal() {
        var overlay = document.getElementById('loginOverlay');
        if (overlay) overlay.classList.remove('is-active');
    }

    function markLoggedInRoots() {
        document.querySelectorAll('.fmain[data-series-id], #commentsSection').forEach(function (el) {
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
        ['auth', 'token', 'email'].forEach(function (key) {
            if (!url.searchParams.has(key)) return;
            url.searchParams.delete(key);
            changed = true;
        });
        if (changed) {
            window.history.replaceState({}, '', url.pathname + url.search + url.hash);
        }
        document.body.removeAttribute('data-auth-panel');
    }

    function updateHeaderAuthState() {
        var actions = document.querySelector('.ls-actions');
        if (!actions) return;

        var loginBtn = actions.querySelector('.js-login-open');
        if (loginBtn) loginBtn.remove();

        if (!actions.querySelector('a.ls-action[href="/profile/"]') && cfgBool('auth_profile_enabled', true)) {
            var profileLink = document.createElement('a');
            profileLink.className = 'dontusebuttonclass ls-action';
            profileLink.href = '/profile/';
            profileLink.title = cfg('auth_ui_header_profile', 'Профиль');
            profileLink.innerHTML = '<span class="fa fa-user"></span>';
            var themeBtn = actions.querySelector('.js-theme-toggle');
            actions.insertBefore(profileLink, themeBtn || null);
        }

        if (cfgBool('notifications_enabled', true) && !document.getElementById('headerNotifyBtn')) {
            var bellBtn = document.createElement('button');
            bellBtn.type = 'button';
            bellBtn.className = 'dontusebuttonclass ls-action js-notify-btn js-series-bell';
            bellBtn.id = 'headerNotifyBtn';
            bellBtn.title = 'Уведомления';
            bellBtn.innerHTML = '<span class="fa fa-bell"></span><span class="series-bell-count" id="headerNotifyCount" hidden></span>';
            var themeBtn = actions.querySelector('.js-theme-toggle');
            actions.insertBefore(bellBtn, themeBtn || null);
            initHeaderNotifications();
        }
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
        updateHeaderAuthState();
        upgradeCommentsComposeForm();
        cleanAuthUrlParams();
        document.dispatchEvent(new CustomEvent('lordserial:auth-login', { detail: data || {} }));
        mergeGuestLibrary();
    }

    function initAuthModal() {
        var overlay = document.getElementById('loginOverlay');
        if (!overlay) return;

        document.querySelectorAll('.js-login-open').forEach(function (btn) {
            btn.addEventListener('click', function () { openAuthPanel('login'); });
        });

        document.querySelectorAll('.js-login-close, .login-modal__close').forEach(function (el) {
            el.addEventListener('click', function () {
                overlay.classList.remove('is-active');
            });
        });

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('is-active');
        });

        document.querySelectorAll('.js-auth-switch').forEach(function (btn) {
            btn.addEventListener('click', function () {
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

        overlay.querySelectorAll('.login-form').forEach(function (form) {
            bindAjaxForm(form, {
                feedback: form.parentElement ? form.parentElement.querySelector('.auth-form-feedback') : null,
                onSuccess: function (data) {
                    if (data.logged_in) applyAuthSession(data);
                },
            });
        });
    }

    function initQuickSearch() {
        document.querySelectorAll('.js-quick-search').forEach(function (form) {
            var input = form.querySelector('input[name="q"]');
            var panel = form.querySelector('.ls-search__panel');
            if (!input || !panel) return;

            var timer = null;
            var requestId = 0;
            var minChars = parseInt(cfg('search_suggest_min_chars', 2), 10) || 2;

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
                data.groups.forEach(function (group) {
                    html += '<div class="ls-search__group">';
                    html += '<div class="ls-search__group-title">' + escapeHtml(group.label) + '</div>';
                    html += '<div class="ls-search__group-list">';
                    group.items.forEach(function (item) {
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
                    closePanel();
                    return;
                }

                var current = ++requestId;
                panel.innerHTML = '<div class="ls-search__loading">Ищем...</div>';
                openPanel();

                fetch('/api/search/suggest?q=' + encodeURIComponent(q), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                    .then(readJsonResponse)
                    .then(function (res) {
                        if (current !== requestId) return;
                        if (!res.ok || !res.data) {
                            closePanel();
                            return;
                        }
                        render(res.data);
                    })
                    .catch(function () {
                        if (current === requestId) closePanel();
                    });
            }

            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(fetchSuggest, 280);
            });

            input.addEventListener('focus', function () {
                var q = input.value.trim();
                if (q.length >= minChars) {
                    if (panel.innerHTML) openPanel();
                    else fetchSuggest();
                }
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closePanel();
            });

            document.addEventListener('click', function (e) {
                if (!form.contains(e.target)) closePanel();
            });
        });
    }

    function initMobileMenu() {
        var menu = document.getElementById('lsMobileMenu');
        var overlay = document.querySelector('.ls-mobile-overlay');
        if (!menu) return;

        function closeMobileSections() {
            menu.querySelectorAll('.ls-mobile-section.is-open').forEach(function (section) {
                section.classList.remove('is-open');
                var toggle = section.querySelector('.js-mobile-section-toggle');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        }

        function openMobileMenu() {
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

        document.querySelectorAll('.js-mobile-menu-open').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (document.body.classList.contains('ls-menu-open')) {
                    closeMobileMenu();
                } else {
                    openMobileMenu();
                }
            });
        });

        document.querySelectorAll('.js-mobile-menu-close').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                closeMobileMenu();
            });
        });

        menu.querySelectorAll('.js-mobile-section-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var section = btn.closest('.js-mobile-section');
                if (!section) return;

                var willOpen = !section.classList.contains('is-open');
                menu.querySelectorAll('.js-mobile-section.is-open').forEach(function (openSection) {
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

        menu.querySelectorAll('.ls-mobile-link, .ls-mobile-section__body a').forEach(function (link) {
            link.addEventListener('click', function () {
                closeMobileMenu();
            });
        });

        document.addEventListener('keydown', function (e) {
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

        themeToggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (themeToggle.classList.contains('fa-moon-o')) {
                enableDarkTheme();
            } else {
                disableDarkTheme();
            }
        });
    }

    function initHomeCarousel() {
        document.querySelectorAll('[data-carou]').forEach(function (root) {
            if (root.getAttribute('data-carou-bound') === '1') return;
            root.setAttribute('data-carou-bound', '1');

            var track = root.querySelector('.carou-track');
            if (!track) return;

            var prev = root.querySelector('.carou-nav--prev');
            var next = root.querySelector('.carou-nav--next');
            var step = 280;

            function scrollBy(delta) {
                track.scrollBy({ left: delta, behavior: 'smooth' });
            }

            if (prev) prev.addEventListener('click', function () { scrollBy(-step); });
            if (next) next.addEventListener('click', function () { scrollBy(step); });
        });
    }

    function initHomeSectionTabs() {
        document.querySelectorAll('[data-home-section-type]').forEach(function (sect) {
            var tabs = sect.querySelector('[data-section-tabs]');
            var cards = sect.querySelector('[data-section-cards]');
            if (!tabs || !cards) return;

            var sectionType = sect.getAttribute('data-home-section-type');
            var sectionId = sect.getAttribute('data-home-section-id');
            var activeSort = null;

            tabs.querySelectorAll('[data-sort]').forEach(function (tab) {
                if (tab.classList.contains('is-active')) {
                    activeSort = tab.getAttribute('data-sort');
                }
            });
            if (!activeSort) activeSort = 'latest';

            function setActiveTab(sort) {
                tabs.querySelectorAll('[data-sort]').forEach(function (tab) {
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
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        cards.innerHTML = data && data.html ? data.html : '';
                    })
                    .catch(function () {})
                    .finally(function () {
                        cards.classList.remove('is-loading');
                    });
            }

            tabs.querySelectorAll('[data-sort]').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    loadSort(tab.getAttribute('data-sort') || 'latest');
                });
                tab.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        loadSort(tab.getAttribute('data-sort') || 'latest');
                    }
                });
            });
        });
    }

    function initWatchlistDropdown() {
        document.querySelectorAll('[data-watchlist-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var el = btn.closest('[data-watchlist-root]');
                if (!el) return;
                document.querySelectorAll('[data-watchlist-root].is-open').forEach(function (open) {
                    if (open !== el) open.classList.remove('is-open');
                });
                el.classList.toggle('is-open');
            });
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-watchlist-root]')) return;
            document.querySelectorAll('[data-watchlist-root].is-open').forEach(function (el) {
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

        var active = lists.filter(function (l) {
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

    function renderWatchlistMenu(menu, lists, listIds, loggedIn, slug) {
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
            loginBtn.addEventListener('click', function () {
                if (window.lordSerialOpenAuth) window.lordSerialOpenAuth('login');
            });
            menu.appendChild(hint);
            menu.appendChild(loginBtn);
            return;
        }

        lists.forEach(function (list) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dontusebuttonclass watchlist-btn';
            btn.setAttribute('data-list-id', String(list.id));
            btn.textContent = list.name;
            if (listIds.indexOf(list.id) !== -1) {
                btn.classList.add('active');
            }
            btn.addEventListener('click', function () {
                postJson('/api/series/' + encodeURIComponent(seriesId) + '/watchlist', {
                    list_id: list.id,
                    action: 'toggle',
                })
                    .then(function (r) {
                        if (r.status === 401) {
                            if (window.lordSerialOpenAuth) window.lordSerialOpenAuth('login');
                            return null;
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data) return;
                        renderWatchlistMenu(menu, lists, data.list_ids || [], true, slug);
                        updateWatchlistLabel(lists, data.list_ids || []);
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
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var likes = root.querySelector('[data-likes]');
                    var dislikes = root.querySelector('[data-dislikes]');
                    if (likes) likes.textContent = data.likes;
                    if (dislikes) dislikes.textContent = data.dislikes;
                    updateUserRating(data.user_rating);

                    root.querySelectorAll('.vote-btn').forEach(function (btn) {
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
                .catch(function () {});
        }

        root.querySelectorAll('.vote-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                postJson('/api/series/' + encodeURIComponent(seriesId) + '/vote', {
                    value: parseInt(btn.getAttribute('data-vote'), 10),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            var likes = root.querySelector('[data-likes]');
                            var dislikes = root.querySelector('[data-dislikes]');
                            if (likes) likes.textContent = data.likes;
                            if (dislikes) dislikes.textContent = data.dislikes;
                            updateUserRating(data.user_rating);
                        }
                        refreshEngagement();
                    })
                    .catch(function () {});
            });
        });

        refreshEngagement();
        document.addEventListener('lordserial:auth-login', refreshEngagement);

        var favBtn = document.querySelector('[data-favourite-toggle][data-series-id="' + seriesId + '"]');
        if (favBtn && cfgBool('favourites_enabled', true)) {
            if (isLocalFavourite(seriesId)) {
                updateFavouriteButton(favBtn, true);
            }

            favBtn.addEventListener('click', function () {
                postJson(seriesApiPath(seriesId) + '/favourite', {})
                    .then(readJsonResponse)
                    .then(function (res) {
                        if (!res.ok) return;
                        var isFav = !!(res.data && res.data.is_favourite);
                        updateFavouriteButton(favBtn, isFav);
                        setLocalFavourite(seriesId, isFav);
                    })
                    .catch(function () {});
            });
        }
    }

    function initProfileForms() {
        var flash = document.getElementById('profileFlash');

        document.querySelectorAll(
            '.profile-form, .profile-new-list, .profile-rename-list, .profile-delete-list, .profile-logout'
        ).forEach(function (form) {
            bindAjaxForm(form, {
                feedback: form.querySelector('[data-form-feedback]') || flash,
                onSuccess: function (data, form) {
                    if (data.profile) {
                        document.querySelectorAll('.profile-sidebar__name').forEach(function (el) {
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

        document.querySelectorAll('[data-watchlist-remove]').forEach(function (btn) {
            if (btn.getAttribute('data-bound') === '1') return;
            btn.setAttribute('data-bound', '1');

            btn.addEventListener('click', function () {
                var listId = btn.getAttribute('data-list-id');
                var seriesId = btn.getAttribute('data-series-id');
                if (!listId || !seriesId) return;

                btn.disabled = true;

                postJson('/profile/lists/' + encodeURIComponent(listId) + '/remove-item', {
                    series_id: parseInt(seriesId, 10),
                })
                    .then(readJsonResponse)
                    .then(function (res) {
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
                            window.setTimeout(function () {
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
                    .catch(function () {
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

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var name = tab.getAttribute('data-profile-tab');
                tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
                document.querySelectorAll('[data-profile-panel]').forEach(function (panel) {
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
                .then(function (res) {
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
                .catch(function () {
                    showNotice(cfg('comments_msg_submit_failed', 'Не удалось отправить комментарий.'), true);
                })
                .finally(function () {
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
            section.querySelectorAll('[data-comment-reply-wrap]').forEach(function (el) {
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

            actions.querySelector('.comment-form__cancel').addEventListener('click', function () {
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
            scope.querySelectorAll('[data-comment-vote]').forEach(function (btn) {
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
                c.children.forEach(function (child) {
                    replies.appendChild(renderComment(child, depth + 1));
                });
                article.appendChild(replies);
            }

            return article;
        }

        function bindListEvents() {
            if (!listEl) return;

            listEl.addEventListener('click', function (e) {
                var voteBtn = e.target.closest('[data-comment-vote]');
                if (!voteBtn) return;
                var article = voteBtn.closest('.comment-item');
                if (!article) return;
                var commentId = article.dataset.commentId;
                if (!commentId) return;

                postJson('/api/comments/' + encodeURIComponent(commentId) + '/vote', {
                    value: parseInt(voteBtn.getAttribute('data-comment-vote'), 10),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) updateVoteUi(article, data);
                    })
                    .catch(function () {});
            });
        }

        function setCommentsSort(nextSort) {
            currentSort = nextSort === 'rating' ? 'rating' : 'date';
            if (!sortEl) return;
            sortEl.querySelectorAll('[data-comments-sort-value]').forEach(function (btn) {
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
                .then(function (r) { return r.json(); })
                .then(function (data) {
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
                    items.forEach(function (c) {
                        listEl.appendChild(renderComment(c, 0));
                    });
                })
                .catch(function () {
                    listEl.innerHTML = '<p class="comment-empty">' + cfg('comments_ui_load_error', 'Не удалось загрузить комментарии.') + '</p>';
                });
        }

        if (sortEl) {
            sortEl.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-comments-sort-value]');
                if (!btn || !sortEl.contains(btn)) return;
                var nextSort = btn.getAttribute('data-comments-sort-value');
                if (!nextSort || nextSort === currentSort) return;
                currentSort = nextSort === 'rating' ? 'rating' : 'date';
                setCommentsSort(currentSort);
                loadComments();
            });
        }

        section.addEventListener('submit', function (e) {
            var form = e.target.closest('[data-comment-form]');
            if (!form || !section.contains(form)) return;
            e.preventDefault();
        });

        section.addEventListener('click', function (e) {
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
        document.addEventListener('lordserial:comments-compose-upgrade', function () {
            section.querySelectorAll('[data-comment-form]').forEach(enhanceCommentForm);
        });
        if (!commentsSsr) {
            loadComments();
        }
    }

    function initCommentSpoilers() {
        document.addEventListener('click', function (e) {
            var toggleBtn = e.target.closest('.comment-spoiler__toggle');
            if (!toggleBtn || toggleBtn.closest('#commentsSection')) return;

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

        document.querySelectorAll('[data-episodes-open]').forEach(function (btn) {
            btn.addEventListener('click', openModal);
        });

        modal.querySelectorAll('[data-episodes-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        modal.querySelectorAll('.episodes-season__toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var season = btn.closest('.episodes-season');
                if (season) season.classList.toggle('is-open');
            });
        });

        document.addEventListener('keydown', function (e) {
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

            widget.querySelectorAll('[data-reaction-card]').forEach(function (card) {
                var id = parseInt(card.getAttribute('data-reaction-id'), 10);
                var item = (data.items || []).find(function (x) { return x.id === id; });
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
                .then(function (res) {
                    if (!res.ok) {
                        showFeedback(parseApiErrors(res.data), true);
                        return;
                    }
                    render(res.data);
                })
                .catch(function () {
                    showFeedback('Не удалось сохранить оценку. Попробуйте ещё раз.', true);
                })
                .finally(function () {
                    setLoading(false);
                });
        }

        widget.addEventListener('click', function (e) {
            var card = e.target.closest('[data-reaction-card]');
            if (!card || !widget.contains(card)) return;
            var reactionId = parseInt(card.getAttribute('data-reaction-id'), 10);
            vote(reactionId);
        });

        widget.addEventListener('keydown', function (e) {
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
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.enabled !== false) render(data);
            })
            .catch(function () {});
    }

    function fetchJson(url, options) {
        return fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        }, options || {})).then(function (r) { return r.json(); });
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
            listEl.innerHTML = items.map(function (item) {
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
            return fetchJson('/api/notifications/').then(function (data) {
                renderItems(data.items || []);
                updateCount(data.unread || 0);
            }).catch(function () {});
        }

        function closeDropdown() {
            dropdown.classList.remove('is-active');
        }

        function openDropdown() {
            positionDropdown();
            dropdown.classList.add('is-active');
            loadNotifications().then(function () {
                return postJson('/api/notifications/read', { all: true });
            }).then(function () {
                updateCount(0);
                listEl.querySelectorAll('.series-item.is-unread').forEach(function (el) {
                    el.classList.remove('is-unread');
                });
            });
        }

        bellBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (dropdown.classList.contains('is-active')) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.classList.contains('is-active')) return;
            if (dropdown.contains(e.target) || bellBtn.contains(e.target)) return;
            closeDropdown();
        });

        window.addEventListener('resize', function () {
            if (dropdown.classList.contains('is-active')) positionDropdown();
        });

        listEl.addEventListener('click', function (e) {
            var dismissBtn = e.target.closest('[data-dismiss-notification]');
            if (!dismissBtn) return;
            e.preventDefault();
            e.stopPropagation();
            var id = dismissBtn.getAttribute('data-dismiss-notification');
            var itemEl = dismissBtn.closest('.series-item');
            deleteJson('/api/notifications/' + encodeURIComponent(id))
                .then(function (response) {
                    if (!response.ok) return loadNotifications();
                    if (itemEl) itemEl.remove();
                    var remaining = listEl.querySelectorAll('.series-item').length;
                    if (!remaining) {
                        dropdown.classList.add('is-empty');
                    }
                    return loadNotifications();
                })
                .catch(function () {
                    return loadNotifications();
                });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                postJson('/api/notifications/clear', {}).then(function () {
                    return loadNotifications();
                });
            });
        }

        loadNotifications();
    }

    function initProfileNotifications() {
        var prefsForm = document.getElementById('notificationPrefsForm');
        if (!prefsForm) return;

        prefsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = prefsForm.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            postJson('/api/notifications/preferences', {
                notify_via_email: !!prefsForm.querySelector('[name="notify_via_email"]')?.checked,
                notify_via_site: !!prefsForm.querySelector('[name="notify_via_site"]')?.checked,
            })
                .then(readJsonResponse)
                .then(function (res) {
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
                .catch(function () {
                    var flash = document.getElementById('profileFlash');
                    if (flash) {
                        flash.hidden = false;
                        flash.textContent = 'Не удалось сохранить настройки уведомлений.';
                        flash.className = 'profile-flash profile-flash--error';
                    }
                })
                .finally(function () {
                    if (submitBtn) submitBtn.disabled = false;
                });
        });

        document.querySelectorAll('[data-unsubscribe-series]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var seriesId = btn.getAttribute('data-unsubscribe-series');
                if (!seriesId || !window.confirm('Отключить уведомления для этого сериала?')) return;
                deleteJson('/api/notifications/series/' + encodeURIComponent(seriesId)).then(function () {
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
            prevBtn.addEventListener('click', function () {
                scrollTabsBy(-Math.max(180, Math.round(tabsWrap.clientWidth * 0.6)));
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                scrollTabsBy(Math.max(180, Math.round(tabsWrap.clientWidth * 0.6)));
            });
        }

        tabsWrap.addEventListener('scroll', updateNavVisibility, { passive: true });
        window.addEventListener('resize', updateNavVisibility);
        window.requestAnimationFrame(function () {
            updateNavVisibility();
            scrollActiveTabIntoView();
            window.requestAnimationFrame(updateNavVisibility);
        });

        function activate(index) {
            tabs.forEach(function (tab) {
                var active = tab.getAttribute('data-player-index') === String(index);
                tab.classList.toggle('is-active', active);
            });

            panels.forEach(function (panel) {
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

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activate(tab.getAttribute('data-player-index'));
            });
        });
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
            notifyBtn.textContent = subscribed
                ? cfg('notifications_ui_unsubscribe_btn', 'Отписаться')
                : cfg('notifications_ui_subscribe_btn', 'Подписаться');
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
            return fetchJson('/api/series/' + encodeURIComponent(seriesId) + '/notifications').then(function (data) {
                if (requestId !== settingsRequestId) return data;
                if (notifyForm) {
                    var anyInput = notifyForm.querySelector('input[name="notify_any"]');
                    if (anyInput) anyInput.checked = data.notify_any !== false;
                    var voices = data.voices || [];
                    notifyForm.querySelectorAll('input[name="voices[]"]').forEach(function (cb) {
                        cb.checked = voices.indexOf(cb.value) !== -1;
                    });
                }
                if (unsubscribeBtn) unsubscribeBtn.hidden = !data.subscribed;
                updateSubscribeUi(data);
                return data;
            }).catch(function () {
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
                .then(function (res) {
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
                })
                .catch(function () {
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
                .then(function (res) {
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
                .catch(function () {
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

        document.querySelectorAll('[data-notify-close], .notify-close, .notify-cancel').forEach(function (el) {
            el.addEventListener('click', closeNotify);
        });

        if (unsubscribeBtn) {
            unsubscribeBtn.addEventListener('click', function () {
                postJson('/api/series/' + encodeURIComponent(seriesId) + '/notifications', { enabled: false })
                    .then(readJsonResponse)
                    .then(function (res) {
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
            notifyForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var voices = [];
                notifyForm.querySelectorAll('input[name="voices[]"]:checked').forEach(function (cb) {
                    voices.push(cb.value);
                });
                var notifyAnyInput = notifyForm.querySelector('input[name="notify_any"]');
                var notifyAny = notifyAnyInput ? notifyAnyInput.checked : true;

                postJson('/api/series/' + encodeURIComponent(seriesId) + '/notifications', {
                    voices: voices,
                    notify_any: notifyAny,
                })
                    .then(readJsonResponse)
                    .then(function (res) {
                        if (!res.ok) {
                            showFeedback(notifyFeedback, parseApiErrors(res.data), true);
                            return;
                        }
                        settingsRequestId++;
                        showFeedback(notifyFeedback, res.data.message || 'Сохранено', false);
                        updateSubscribeUi({ subscribed: true });
                        showSubscribeFeedback(res.data.message || 'Сохранено', false);
                        if (unsubscribeBtn) unsubscribeBtn.hidden = false;
                        setTimeout(closeNotify, 900);
                    })
                    .catch(function () {
                        showFeedback(notifyFeedback, 'Не удалось сохранить настройки.', true);
                    });
            });
        }

        document.addEventListener('lordserial:auth-login', function () {
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
        var primaryFilterKey = taxonomyType === 'person'
            ? 'actor'
            : (taxonomyType === 'year' ? 'year_from' : taxonomyType);
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
            el.querySelectorAll('[data-filter]').forEach(function (node) {
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
            Object.keys(state).forEach(function (key) {
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
            Object.keys(params).forEach(function (key) {
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
            el.querySelectorAll('[data-filter]').forEach(function (node) {
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
            Object.keys(state).forEach(function (key) {
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
            return Object.keys(state).some(function (key) {
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
            Object.keys(state).forEach(function (key) {
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
                el.querySelectorAll('[data-filter]').forEach(function (node) {
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
                .then(function (res) {
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
                .catch(function () {
                    if (currentRequest !== requestId) return;
                    setLoading(false);
                });
        }

        root.addEventListener('change', function (e) {
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

        root.addEventListener('input', function (e) {
            var slider = e.target.closest('[data-filter-type="range"]');
            if (!slider || !root.contains(slider)) return;
            updateRangeOutput(slider);
            clearTimeout(rangeTimer);
            rangeTimer = setTimeout(function () {
                readFiltersFromDom();
                loadCatalog(1, false);
            }, 350);
        });

        root.addEventListener('click', function (e) {
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
            toastTimer = window.setTimeout(function () {
                toast.classList.remove('is-visible');
                window.setTimeout(function () {
                    toast.hidden = true;
                }, 220);
            }, 4200);
        }

        document.querySelectorAll('[data-bookmark-open]').forEach(function (btn) {
            btn.addEventListener('click', openModal);
        });

        if (modal) {
            modal.querySelectorAll('[data-bookmark-close]').forEach(function (el) {
                el.addEventListener('click', closeModal);
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-active')) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            var modKey = isMac ? e.metaKey : e.ctrlKey;
            if (!modKey || e.altKey || e.shiftKey) return;
            if (e.key !== 'd' && e.key !== 'D' && e.key !== 'в' && e.key !== 'В') return;
            if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable)) {
                return;
            }
            showThanks();
        });
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
            window.setTimeout(function () {
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
            window.requestAnimationFrame(function () {
                hint.classList.add('is-visible');
            });
        }

        links.forEach(function (link) {
            link.addEventListener('mouseenter', function () {
                showHint(link);
            });
            link.addEventListener('mouseleave', hideHint);
            link.addEventListener('focus', function () {
                showHint(link);
            });
            link.addEventListener('blur', hideHint);
        });
    }

    function initAnticipationVotes() {
        function applyPayload(root, data) {
            if (!root || !data) return;

            root.querySelectorAll('[data-anticipation-percent]').forEach(function (el) {
                var isRatingStrong = el.tagName === 'STRONG' && el.closest('.expected-rating');
                el.textContent = isRatingStrong ? String(data.percent) : String(data.percent) + '%';
            });

            root.querySelectorAll('[data-anticipation-votes]').forEach(function (el) {
                el.textContent = data.votes_label || '';
            });

            root.querySelectorAll('[data-anticipation-bar]').forEach(function (el) {
                el.style.width = String(data.percent) + '%';
            });

            root.querySelectorAll('[data-anticipation-vote="1"]').forEach(function (btn) {
                btn.classList.toggle('is-active', !!data.watch_active);
                btn.classList.toggle('success', !!data.wait_active);
            });

            root.querySelectorAll('[data-anticipation-vote="-1"]').forEach(function (btn) {
                btn.classList.toggle('success', !!data.nowait_active);
            });
        }

        function vote(seriesId, value, roots) {
            postJson(seriesApiPath(seriesId) + '/anticipation', { value: value })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    roots.forEach(function (root) { applyPayload(root, data); });
                })
                .catch(function () {});
        }

        var seriesMap = {};

        document.querySelectorAll('[data-anticipation-root], [data-anticipation-card]').forEach(function (root) {
            var seriesId = root.getAttribute('data-series-id');
            if (!seriesId) return;
            if (!seriesMap[seriesId]) seriesMap[seriesId] = [];
            seriesMap[seriesId].push(root);
        });

        Object.keys(seriesMap).forEach(function (seriesId) {
            var roots = seriesMap[seriesId];

            fetch(seriesApiPath(seriesId) + '/anticipation', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    roots.forEach(function (root) { applyPayload(root, data); });
                })
                .catch(function () {});

            roots.forEach(function (root) {
                root.querySelectorAll('[data-anticipation-vote]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
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
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.count || !data.html) return;

                cards.innerHTML = data.html;

                if (cards.children.length > 0) {
                    section.hidden = false;
                    initHomeCarousel();
                }
            })
            .catch(function () {});
    }

    function initSeriesWatchHistory() {
        var root = document.querySelector('.fmain[data-series-id]');
        if (!root || !cfgBool('watch_history_enabled', true)) return;

        var seriesId = root.getAttribute('data-series-id');
        if (!seriesId) return;

        pushLocalHistory(seriesId);
    }

    function initCsrfRefresh() {
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                refreshCsrfToken();
            }
        });

        window.addEventListener('pageshow', function (e) {
            if (e.persisted) refreshCsrfToken();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCsrfRefresh();
        initAuthModal();
        initQuickSearch();
        initMobileMenu();
        initThemeToggle();
        initHomeCarousel();
        initHomeSectionTabs();
        initHomeWatchHistory();
        initWatchlistDropdown();
        initSeriesEngagement();
        initSeriesWatchHistory();
        initAnticipationVotes();
        initComments();
        initCommentSpoilers();
        initEpisodesModal();
        initReactionsWidget();
        initPlayerTabs();
        initNotifyModal();
        initBookmarkHint();
        initHeaderNotifications();
        initProfileNotifications();
        initProfileTabs();
        initProfileForms();
        initProfileWatchlistRemove();
        initCatalogFilters();
        initPersonPhotoHints();
    });
})();
