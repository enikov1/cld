/* =========================================================================
   Main frontend script
   Один IIFE, без засорения глобального пространства имён.
   Публичный API (для вызова из HTML/шаблонов) экспортируется в window ниже.
   ========================================================================= */
(function ($, window, document) {
    'use strict';

    /* ============================ THEME INIT ============================
       Выполняется как можно раньше. Для полного устранения FOUC
       вынесите эти строки в <head> в inline <script>. */
    var STORAGE_THEME = 'theme';

    function safeStorageGet(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }
    function safeStorageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) { /* noop */ }
    }

    (function applyInitialTheme() {
        var saved = safeStorageGet(STORAGE_THEME);
        if (saved === 'dark' || saved === 'light') {
            try { document.documentElement.setAttribute('data-theme', saved); } catch (e) { /* noop */ }
        }
    })();

    /* ============================ UTILITIES ============================ */

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    // Ограничитель частоты вызовов через requestAnimationFrame
    function rafThrottle(fn) {
        var scheduled = false;
        return function () {
            if (scheduled) return;
            scheduled = true;
            var args = arguments, ctx = this;
            window.requestAnimationFrame(function () {
                scheduled = false;
                fn.apply(ctx, args);
            });
        };
    }

    function isElementInDom($el) {
        return !!(($el && $el.length) && $.contains(document.documentElement, $el[0]));
    }

    /* ========================= MESSAGE NOTIFICATIONS ========================= */

    var NOTIF_ICONS = {
        success: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
        error:   '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
        warning: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
    };

    /**
     * Отображает уведомление.
     * @param {'error'|'success'|'warning'} type
     * @param {string} message
     * @param {string} [title]
     * @param {number} [duration] 0 = не закрывать автоматически
     */
    function showNotification(type, message, title, duration) {
        type = type || 'error';
        duration = (duration === undefined) ? 5000 : duration;

        var $container = $('#dle-push-container');
        if (!$container.length) {
            $container = $('<div id="dle-push-container" class="DLEPush"></div>').appendTo('body');
        }

        // Безопасная сборка (без конкатенации пользовательского текста в HTML)
        var $notification = $('<div>')
            .addClass('DLEPush-notification wrapper push-' + type)
            .append($('<div class="DLEPush-icon">').html(NOTIF_ICONS[type] || NOTIF_ICONS.error))
            .append($('<div class="DLEPush-header">').text(title || ''))
            .append($('<div class="DLEPush-message">').text(message == null ? '' : String(message)))
            .append($('<button class="DLEPush-close" type="button" aria-label="Закрыть">').html('&times;'));

        $container.append($notification);

        // Запуск анимации появления
        window.setTimeout(function () {
            $notification.css('animation', 'DLEPush-show 1s');
        }, 10);

        var removeNotification = function () {
            $notification.fadeOut(300, function () {
                $(this).remove();
                if ($container.children().length === 0) $container.remove();
            });
        };

        $notification.find('.DLEPush-close').on('click', removeNotification);

        if (duration > 0) {
            window.setTimeout(removeNotification, duration);
        }
    }

    function showError(message, title)   { showNotification('error',   message, title || 'Ошибка'); }
    function showSuccess(message, title) { showNotification('success', message, title || 'Успешно'); }
    function showWarning(message, title) { showNotification('warning', message, title || 'Предупреждение'); }

    /* ========================= SPOILER / PIN (комментарии) ========================= */

    var activeSpoilerRequests = Object.create(null);

    function votesWord(n) {
        return n === 1 ? 'голос' : (n < 5 ? 'голоса' : 'голосов');
    }

    /**
     * Пометить комментарий как спойлер.
     * @param {string|number} commentId
     * @param {HTMLElement} [btnElement]  Кнопка, инициировавшая действие.
     */
    function markCommentSpoiler(commentId, btnElement) {
        if (!commentId) return false;
        commentId = String(commentId);

        if (activeSpoilerRequests[commentId]) return false;

        var $btn;
        if (btnElement) {
            $btn = $(btnElement);
        }
        if (!$btn || !$btn.length || !isElementInDom($btn)) {
            $btn = $('.comment-spoiler-btn[data-comment-id="' + commentId + '"]').first();
        }
        if (!$btn.length) {
            showError('Кнопка спойлера не найдена. Обновите страницу.');
            return false;
        }

        activeSpoilerRequests[commentId] = true;

        var originalHtml  = $btn.html();
        var originalTitle = $btn.attr('title') || 'Пометить как спойлер';

        $btn.css({ 'pointer-events': 'none', 'opacity': '0.5', 'cursor': 'wait' })
            .html('⏳')
            .attr('title', 'Отправка...');

        $.post('/engine/ajax/controller.php?mod=comments_advanced', {
            action: 'mark_spoiler',
            comment_id: commentId
        }, function (data) {
            delete activeSpoilerRequests[commentId];

            if (data && data.success) {
                var count = Number(data.spoiler_count) || 0;
                var word  = votesWord(count);

                showSuccess('Голос учтён! (' + count + ' ' + word + ')');

                $btn.attr('title', 'Пометить как спойлер (' + count + ')')
                    .css({ 'pointer-events': 'auto', 'opacity': '0.8', 'cursor': 'pointer' })
                    .html('⚠️');

                if (data.is_spoiler) {
                    var $wrapper = $('#comm-id-' + commentId);
                    var $text = $wrapper.find('.comment-item__main, .full-text');

                    if ($text.length) {
                        var content = $text.html();
                        // Без inline onclick — используем делегирование .comment-spoiler-show-btn
                        var spoilerHtml =
                            '<div class="comment-spoiler-wrapper" data-comment-id="' + commentId + '">' +
                                '<div class="comment-spoiler-hidden" style="display:none;">' + content + '</div>' +
                                '<div class="comment-spoiler-placeholder">' +
                                    '<div class="comment-spoiler-warning">⚠️ Этот комментарий помечен как спойлер (' + count + ' ' + word + ')</div>' +
                                    '<button type="button" class="comment-spoiler-show-btn" data-comment-id="' + commentId + '">Показать спойлер</button>' +
                                '</div>' +
                            '</div>';
                        $text.html(spoilerHtml);
                    }

                    $btn.css('opacity', '0.5')
                        .attr('title', 'Комментарий помечен как спойлер (' + count + ' ' + word + ')');

                    showSuccess('Комментарий скрыт как спойлер', 'Спойлер активирован');
                } else {
                    $btn.css('opacity', '0.7');
                }
            } else {
                $btn.css({ 'pointer-events': 'auto', 'opacity': '1', 'cursor': 'pointer' })
                    .html(originalHtml)
                    .attr('title', originalTitle);
                showError((data && data.error) || 'Ошибка при пометке комментария');
            }
        }, 'json').fail(function () {
            delete activeSpoilerRequests[commentId];
            $btn.css({ 'pointer-events': 'auto', 'opacity': '1', 'cursor': 'pointer' })
                .html(originalHtml)
                .attr('title', originalTitle);
            showError('Ошибка соединения с сервером');
        });

        return false;
    }

    function toggleCommentPin(commentId, pin) {
        if (!commentId) return false;
        $.post('/engine/ajax/controller.php?mod=comments_advanced', {
            action: 'toggle_pin',
            comment_id: commentId,
            pin: pin ? 1 : 0
        }, function (data) {
            if (data && data.success) {
                window.location.reload();
            } else {
                showError((data && data.error) || 'Ошибка при закреплении комментария');
            }
        }, 'json').fail(function () {
            showError('Ошибка соединения с сервером');
        });
        return false;
    }

    function showCommentSpoiler(commentId) {
        var $wrapper = $('.comment-spoiler-wrapper[data-comment-id="' + commentId + '"]');
        if ($wrapper.length) {
            $wrapper.find('.comment-spoiler-hidden').show();
            $wrapper.find('.comment-spoiler-placeholder').hide();
        }
    }

    /* ========================= COMMENTS: FILTERS ========================= */

    function applyCommentFilters() {
        var filterDate   = $('#filter_date').val();
        var filterRating = $('#filter_rating').val();
        var sortBy       = $('#sort_by').val();

        var $commentsWrapper = $('.page__comments').first();
        var season  = $commentsWrapper.data('season')  || 0;
        var episode = $commentsWrapper.data('episode') || 0;

        var newsId = (typeof window.dle_news_id !== 'undefined' && window.dle_news_id) || null;

        if (!newsId) {
            var urlMatch = window.location.href.match(/newsid=(\d+)/);
            if (urlMatch) newsId = urlMatch[1];
        }
        if (!newsId) {
            var pathMatch = window.location.pathname.match(/\/(\d+)-/);
            if (pathMatch) newsId = pathMatch[1];
        }
        if (!newsId) {
            showError('Не удалось определить ID новости');
            return false;
        }

        var $commentsList = $('#page__comments-list, #dle-comments-list').first();
        var $navigation   = $('.dle-comments-navigation').first();

        if ($commentsList.length) {
            $commentsList.css({ opacity: 0.5, 'pointer-events': 'none' });
        }

        var ajaxData = {
            news_id: newsId,
            skin: (typeof dle_skin !== 'undefined' ? dle_skin : ''),
            massact: 'disable'
        };
        if (season)  ajaxData.season  = season;
        if (episode) ajaxData.episode = episode;
        if (filterDate)   ajaxData.filter_date   = filterDate;
        if (filterRating) ajaxData.filter_rating = filterRating;
        if (sortBy)       ajaxData.sort_by       = sortBy;

        $.get(dle_root + 'engine/ajax/controller.php?mod=comments', ajaxData, function (data) {
            if (!data || !data.comments) {
                if ($commentsList.length) $commentsList.css({ opacity: 1, 'pointer-events': 'auto' });
                showError('Ошибка при загрузке комментариев');
                return;
            }

            if ($commentsList.length) {
                $commentsList.find('.comment-item, .comments-tree-list, .comments-tree-item, .ajax_loaded_comments, .message-info').remove();

                var $commentDiv = $commentsList.find('#comment');
                if ($commentDiv.length && !$commentDiv.closest('form').length) {
                    $commentDiv.remove();
                }

                var $form = $commentsList.closest('form');
                var isInForm = $form.length > 0;

                var $newComments = $(data.comments);

                var $ajaxCommentsDiv = $newComments.find('#dle-ajax-comments');
                if ($ajaxCommentsDiv.length) $ajaxCommentsDiv.remove();

                if ($newComments.find('#dlemasscomments').length) {
                    var $formContent = $newComments.find('#dlemasscomments #dle-comments-list');
                    if ($formContent.length) $newComments = $formContent.contents();
                }

                if (isInForm) {
                    var $formCommentsList = $form.find('#dle-comments-list');
                    if ($formCommentsList.length) {
                        $formCommentsList.find('.comment-item, .comments-tree-list, .comments-tree-item, .ajax_loaded_comments').remove();
                        $formCommentsList.prepend($newComments);
                    } else {
                        $commentsList.prepend($newComments);
                    }
                } else {
                    $commentsList.prepend($newComments);
                }

                if ($ajaxCommentsDiv.length) {
                    var $target = isInForm
                        ? ($form.find('#dle-comments-list').length ? $form.find('#dle-comments-list') : $commentsList)
                        : $commentsList;
                    var $existingAjax = $target.find('#dle-ajax-comments');
                    if ($existingAjax.length) {
                        $existingAjax.replaceWith($ajaxCommentsDiv);
                    } else {
                        $target.append($ajaxCommentsDiv);
                    }
                }

                $commentsList.css({ opacity: 1, 'pointer-events': 'auto' });
            }

            if (data.navigation) {
                if ($navigation.length) {
                    $navigation.html(data.navigation);
                } else if ($commentsList.length) {
                    $commentsList.after('<div class="dle-comments-navigation">' + data.navigation + '</div>');
                }
            }

            initDlePlayers();
        }, 'json').fail(function () {
            if ($commentsList.length) $commentsList.css({ opacity: 1, 'pointer-events': 'auto' });
            showError('Ошибка при загрузке комментариев. Попробуйте ещё раз.');
        });

        return false;
    }

    /* ========================= DLEPlayer (безопасная инициализация) ========================= */

    var initedPlayers = (typeof WeakSet !== 'undefined') ? new WeakSet() : null;

    function initDlePlayers() {
        if (typeof window.DLEPlayer === 'undefined') return;
        var containers = document.querySelectorAll('.dleplyrplayer');
        Array.prototype.forEach.call(containers, function (c) {
            if (initedPlayers) {
                if (initedPlayers.has(c)) return;
                initedPlayers.add(c);
            }
            try { new window.DLEPlayer(c); } catch (e) { /* noop */ }
        });
    }

    /* ========================= SERIALY RATING ========================= */

    function doSerialyRate(type, itemId, rating) {
        rating = Number(rating);
        if (!type || !itemId || isNaN(rating) || rating < 0 || rating > 10) {
            showError('Неверные параметры оценки');
            return false;
        }

        var $block = $('.serialy-rating[data-serialy-rating-type="' + type + '"][data-serialy-rating-id="' + itemId + '"]').first();
        if (!$block.length) $block = $('.serialy-rating-block').first();
        if (!$block.length) {
            showError('Блок оценки не найден');
            return false;
        }

        $block.find('a').css('pointer-events', 'none');

        var userHash =
            (typeof window.dle_login_hash !== 'undefined' && window.dle_login_hash) ||
            (typeof dle_login_hash !== 'undefined' && dle_login_hash) ||
            $('input[name="dle_login_hash"]').val() ||
            '';

        $.post(dle_root + 'engine/ajax/controller.php?mod=rating_serialy', {
            type: type,
            item_id: itemId,
            rating: rating,
            user_hash: userHash
        }, function (response) {
            if (response && response.success) {
                if (response.rating) {
                    $block.replaceWith($(response.rating));
                }
                showSuccess('Спасибо за оценку!', 'Оценка сохранена');
            } else {
                $block.find('a').css('pointer-events', 'auto');
                showError((response && response.errorinfo) || 'Ошибка при сохранении оценки');
            }
        }, 'json').fail(function () {
            $block.find('a').css('pointer-events', 'auto');
            showError('Ошибка соединения с сервером');
        });

        return false;
    }

    /* ========================= EPISODE COUNTDOWN ========================= */

    var countdownTimerId = null;
    var countdownItems   = [];

    function tickCountdowns() {
        var now = Math.floor(Date.now() / 1000);
        var remaining = [];

        for (var i = 0; i < countdownItems.length; i++) {
            var item = countdownItems[i];
            var diff = item.ts - now;

            if (diff <= 0) {
                // Время вышло — скрываем блок и перезагружаем страницу ТОЛЬКО
                // если это только что наступило (а не кривые данные из прошлого).
                if (!item.done) {
                    item.done = true;
                    var $notice = item.$el.closest('.episode__future-notice');
                    if ($notice.length) $notice.fadeOut(500);

                    // Если прошло совсем немного — перезагружаем, иначе просто скрываем
                    // (защита от бесконечного reload при некорректном timestamp).
                    if (diff > -60) {
                        window.setTimeout(function () { window.location.reload(); }, 600);
                    }
                }
                continue;
            }

            remaining.push(item);

            var days    = Math.floor(diff / 86400);
            var hours   = Math.floor((diff % 86400) / 3600);
            var minutes = Math.floor((diff % 3600) / 60);
            var seconds = diff % 60;

            item.$el.find('[data-days]').text(days);
            item.$el.find('[data-hours]').text(pad2(hours));
            item.$el.find('[data-minutes]').text(pad2(minutes));
            item.$el.find('[data-seconds]').text(pad2(seconds));
        }

        countdownItems = remaining;

        if (!countdownItems.length && countdownTimerId) {
            window.clearInterval(countdownTimerId);
            countdownTimerId = null;
        }
    }

    function initEpisodeCountdown() {
        countdownItems = [];
        $('.episode__countdown[data-timestamp]').each(function () {
            var $el = $(this);
            var ts  = parseInt($el.data('timestamp'), 10);
            if (!ts || ts <= 0) return;
            countdownItems.push({ $el: $el, ts: ts, done: false });
        });
        if (!countdownItems.length) return;

        if (countdownTimerId) window.clearInterval(countdownTimerId);
        tickCountdowns();
        if (countdownItems.length) {
            countdownTimerId = window.setInterval(tickCountdowns, 1000);
        }
    }

    /* ========================= LAZY LOAD ========================= */

    function initLazyload($context) {
        $context = $context || $(document);

        $context.find('img[data-src]').each(function () {
            var $img = $(this);
            if (!$img.attr('src') && $img.attr('data-src')) {
                $img.attr('src', $img.attr('data-src'));
            }
            $img.addClass('lazy-loaded').removeClass('lazyload lazy');
        });

        if (window.lazySizes && window.lazySizes.loader) {
            $context.find('.lazyload, img[data-srcset]').each(function () {
                try { window.lazySizes.loader.unveil(this); } catch (e) { /* noop */ }
            });
        }
        if (typeof window.lozad === 'function' && $context[0]) {
            try { window.lozad($context[0]).observe(); } catch (e) { /* noop */ }
        }
        if ($.fn.lazyload) {
            try { $context.find('img.lazy').lazyload(); } catch (e) { /* noop */ }
        }

        $(window).trigger('scroll');
    }

    /* ========================= EXPORT PUBLIC API ========================= */

    window.showNotification      = showNotification;
    window.showError             = showError;
    window.showSuccess           = showSuccess;
    window.showWarning           = showWarning;
    window.markCommentSpoiler    = markCommentSpoiler;
    window.toggleCommentPin      = toggleCommentPin;
    window.showCommentSpoiler    = showCommentSpoiler;
    window.applyCommentFilters   = applyCommentFilters;
    window.initEpisodeCountdown  = initEpisodeCountdown;
    window.doSerialyRate         = doSerialyRate;

    /* ========================= DOM READY ========================= */

    $(function () {

        var html = document.documentElement;

        /* ---------- Theme toggle (делегирование) ---------- */
        $(document).on('click', '#theme-toggle', function () {
            var isDark = html.getAttribute('data-theme') === 'dark';
            var next   = isDark ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            safeStorageSet(STORAGE_THEME, next);
        });

        /* ---------- Misc ---------- */
        $('body').addClass('js');
        $('#owl-scr br').remove();

        /* ---------- Клики по ссылкам-обёрткам ---------- */
        $('body').on('click', '.ps-link', function () {
            var url = $(this).attr('data-href');
            if (url) window.location.href = url;
        });

        /* ---------- Гость не может в закладки ---------- */
        $('body').on('click', '.fav-guest', function () {
            DLEalert(
                'Зарегистрируйтесь, и вы сможете создавать свои списки закладок, ' +
                'получать бонусы за активность на сайте, оценивать и многое другое!',
                'Необходима регистрация'
            );
            return false;
        });

        /* ---------- Owl Carousels ---------- */
        $('#owl-top').owlCarousel({
            items: 7, loop: false, rewind: true, nav: true,
            navText: ['<span class="fa fa-arrow-left"></span>', '<span class="fa fa-arrow-right"></span>'],
            slideBy: 2, dots: false, autoplay: true, autoplayTimeout: 12000,
            autoWidth: true, margin: 15
        });
        $('#owl-side').owlCarousel({
            items: 2, loop: false, rewind: true, nav: false,
            navText: ['<span class="fa fa-chevron-left"></span>', '<span class="fa fa-chevron-right"></span>'],
            slideBy: 2, dots: true, autoplay: true, autoplayTimeout: 12000,
            autoWidth: true, margin: 10
        });
        $('#owl-scr').owlCarousel({
            items: 4, loop: true, rewind: true, nav: false,
            navText: ['<span class="fa fa-chevron-left"></span>', '<span class="fa fa-chevron-right"></span>'],
            slideBy: 2, dots: true, autoplay: true, autoplayTimeout: 12000,
            autoWidth: true, margin: 20
        });
        $('#owl-rels').owlCarousel({
            items: 6, loop: false, rewind: true, nav: true,
            navText: ['<span class="fa fa-chevron-left"></span>', '<span class="fa fa-chevron-right"></span>'],
            slideBy: 2, dots: false, autoplay: true, autoplayTimeout: 12000,
            autoWidth: true, margin: 20
        });
        $('#actor-photos-slider').owlCarousel({
            items: 1, loop: false, margin: 10, nav: true, dots: true,
            autoplay: true, autoplayTimeout: 5000, autoplayHoverPause: true,
            navText: ['<i class="fa fa-chevron-left"></i>', '<i class="fa fa-chevron-right"></i>'],
            responsive: {
                0:    { items: 1 },
                600:  { items: 1 },
                1000: { items: 1 }
            }
        });

        /* ---------- Tabs ---------- */
        $('.tabs-box').each(function () {
            $(this).find('.tabs-sel span:first').addClass('current');
            $(this).find('.tabs-b:first').addClass('visible');
        });
        $(document).on('click', '.tabs-sel span:not(.current)', function () {
            $(this).addClass('current').siblings().removeClass('current')
                .parents('.tabs-box').find('.tabs-b').hide()
                .eq($(this).index()).fadeIn(400);
        });

        /* ---------- Pagination (бесконечная подгрузка) ---------- */
        $('body').on('click', '#pagi-load a', function (e) {
            e.preventDefault();

            var $link   = $(this);
            var urlNext = $link.attr('href');
            if (!urlNext || urlNext === '#' || urlNext.indexOf('javascript:') === 0) return false;
            if ($link.hasClass('loading')) return false;

            var $container = $('.main .items');
            var scrollNext = $link.offset().top - 200;

            $link.addClass('loading');

            $.ajax({
                url: urlNext,
                beforeSend: function () { ShowLoading(''); }
            }).done(function (data) {
                var $data     = $(data);
                var $newCards = $data.find('.items .short, .items .short-grid');
                var $newNav   = $data.find('#bottom-nav').last();

                $('.main #bottom-nav, .main .bottom-nav').remove();

                // Схлопываем возможные дубли #dle-content
                var $allContents = $container.find('#dle-content');
                var $content;

                if ($allContents.length > 1) {
                    $content = $allContents.first();
                    $allContents.slice(1).each(function () {
                        var $extra = $(this);
                        $extra.children('.short, .short-grid').appendTo($content);
                        $extra.remove();
                    });
                } else if ($allContents.length === 1) {
                    $content = $allContents;
                } else {
                    $content = $container;
                }

                $content.append($newCards);
                if ($newNav.length) $container.after($newNav);

                try { window.history.pushState('', '', urlNext); } catch (e) { /* noop */ }
                $('html, body').animate({ scrollTop: scrollNext }, 800);

                initLazyload($container);
                HideLoading('');
            }).fail(function () {
                HideLoading('');
                showError('Не удалось загрузить следующую страницу');
            }).always(function () {
                $link.removeClass('loading');
            });

            return false;
        });

        /* ---------- Mobile menu toggle ---------- */
        $('#cols').before('<div class="show-menu icon-l"><span class="fa fa-bars"></span>Наше меню (нажмите)</div>');
        $(document).on('click', '.show-menu', function () {
            $('#cols').toggleClass('viss');
            $(this).find('.fa').toggleClass('fa-bars fa-times');
        });

        /* ---------- Cloned nav (mobile) ---------- */
        (function setupClonedMenu() {
            if (window.innerWidth >= 1220) return;
            if ($('.nav-cloned').length) return;

            var $list = $('<ul class="nav-menu flex-row nav-cloned"></ul>');
            $('.f-menu > li').each(function () {
                var $clone = $(this).clone();
                // Убираем дублирующиеся id у клонированных узлов
                $clone.find('[id]').removeAttr('id');
                $clone.removeAttr('id');
                $list.append($clone);
            });
            $('#side-nav').prepend($list);
        })();

        /* ---------- Login dialog (старый) ---------- */
        if ($.fn.dialog && $('#login-box').length) {
            $('#login-box').dialog({
                autoOpen: false, modal: true, show: 'fade', hide: 'fade', width: 320
            });
            $(document).on('click', '.show-login', function () {
                $('#login-box').dialog('open');
            });
        }

        /* ---------- Avatar ---------- */
        var $lbAva = $('#lb-ava');
        var $acAva = $('#ac-av');
        if ($lbAva.length && $acAva.length) {
            $acAva.html($lbAva.html());
        }

        /* ---------- Comment form: раскрытие защиты при фокусе ---------- */
        $(document).on('click', '.ac-textarea textarea, .fr-wrapper', function () {
            $('.add-comm-form').addClass('active').find('.ac-protect').slideDown(400);
        });

        /* ---------- Соц. авторизация (попап) ---------- */
        $(document).on('click', '.ac-soc a, .lb-soc a', function (e) {
            e.preventDefault();
            var href = $(this).attr('href');
            if (!href) return false;
            var width  = 820;
            var height = 420;
            var left   = Math.round((window.screen.width  - width) / 2);
            var top    = Math.round((window.screen.height - height) / 2 - 100);
            window.open(
                href, 'auth_window',
                'width=' + width + ',height=' + height +
                ',top=' + top + ',left=' + left +
                ',menubar=no,resizable=no,scrollbars=no,status=no,toolbar=no'
            );
            return false;
        });

        /* ---------- Перенос аякс-комментариев ---------- */
        var $ajaxComments = $('#dle-content > #dle-ajax-comments');
        var $fullComms    = $('#full-comms');
        if ($ajaxComments.length && $fullComms.length) {
            $ajaxComments.appendTo($fullComms);
        }

        /* ---------- Кнопка "наверх" ---------- */
        var $gotop = $('<div id="gotop"><span class="fa fa-chevron-up"></span></div>').appendTo('body');
        $(window).on('scroll', rafThrottle(function () {
            if (window.pageYOffset > 300) $gotop.fadeIn(200);
            else $gotop.fadeOut(200);
        }));
        $gotop.on('click', function () {
            $('html, body').animate({ scrollTop: 0 }, 'slow');
        });

        /* ---------- AUTH: регистрация / логин ---------- */

        var DOC         = $(document);
        var login       = $('#auth_register_login');
        var password    = $('#auth_register_password');
        var password2   = $('#auth_register_password2');
        var email       = $('#auth_register_email');
        var captcha     = $('#captcha');

        var classError   = 'error';
        var classSuccess = 'success';

        var mess_login_min_max = 'Логин не может быть менее 3 символов и более 40 символов';
        var mess_email         = 'Введён неверный e-mail адрес';
        var mess_password      = 'Оба введённых пароля должны быть идентичны.';
        var mess_password2     = 'Длина пароля должна быть не менее 6 символов и не более 72 символов.';

        function clearFieldError($field, cls) {
            $field.closest('.auth_modal__field').find(cls || '.auth_field_text, .auth_field_text2').remove();
        }
        function setFieldError($field, cls, message) {
            var $wrap = $field.closest('.auth_modal__field');
            $wrap.find('.' + cls.replace(/^\./, '')).remove();
            $wrap.append($('<div>').addClass('auth_field_text ' + cls.replace(/^\./, '')).text(message));
        }

        function validLogin() {
            if (!login.length) return;
            var ok = login.val().length >= 3 && login.val().length <= 40;
            login.toggleClass(classSuccess, ok).toggleClass(classError, !ok);
            clearFieldError(login);
            if (!ok) setFieldError(login, 'error', mess_login_min_max);
        }

        function validEmail() {
            if (!email.length) return;
            var val = String(email.val() || '').trim();
            // Простая, но надёжная проверка: что-то @ что-то . что-то, без пробелов
            var ok = val.length <= 40 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
            email.toggleClass(classSuccess, ok).toggleClass(classError, !ok);
            clearFieldError(email);
            if (!ok) setFieldError(email, 'error', mess_email);
        }

        function validPassword() {
            if (!password.length || !password2.length) return;

            // Пароли совпадают
            var same = password.val() === password2.val();
            password2.toggleClass(classSuccess, same).toggleClass(classError, !same);
            clearFieldError(password2);
            if (!same) setFieldError(password2, 'error', mess_password);

            // Длина: 6 ≤ len ≤ 72 (включительно)
            var len = password.val().length;
            var lenOk = len >= 6 && len <= 72;
            password.toggleClass(classSuccess, lenOk).toggleClass(classError, !lenOk);
            if (!lenOk) {
                var $wrap = password2.closest('.auth_modal__field');
                $wrap.find('.auth_field_text2').remove();
                $wrap.append($('<div class="auth_field_text2 error">').text(mess_password2));
            } else {
                password2.closest('.auth_modal__field').find('.auth_field_text2').remove();
            }
        }

        function registerOnClick() {
            ShowLoading('');
            $.post(dle_root + 'engine/ajax/controller.php?mod=authrize&method=register', {
                name: login.val(),
                password1: password.val(),
                password2: password2.val(),
                email: email.val(),
                user_hash: dle_login_hash,
                captcha: captcha.val()
            }, function (data) {
                HideLoading('');
                if (data) {
                    $('#auth_register_result').empty().append(data);
                    if ($('#auth_register .auth_block_success').length) {
                        window.setTimeout(function () { window.location.reload(); }, 1000);
                    }
                }
            }, 'html').fail(function () {
                HideLoading('');
                showError('Ошибка соединения с сервером');
            });
        }

        // Уникальность логина на blur
        if (login.length) {
            login.on('blur', function () {
                $.post(dle_root + 'engine/ajax/controller.php?mod=authrize&method=checklogin', {
                    name: login.val(),
                    user_hash: dle_login_hash
                }, function (data) {
                    HideLoading('');
                    if (data) {
                        clearFieldError(login);
                        login.closest('.auth_modal__field').append(data);
                        var $text = login.closest('.auth_modal__field').find('.auth_field_text');
                        if ($text.hasClass('success')) {
                            login.removeClass('success error').addClass(classSuccess);
                        } else {
                            login.addClass(classError).removeClass(classSuccess);
                        }
                    }
                }, 'html');
            });
        }

        var loginName     = $('#auth_log_login');
        var loginPassword = $('#auth_log_password');
        var loginNotSave  = $('#login_not_save');

        function validLoginLog() {
            if (!loginName.length) return;
            var ok = loginName.val().length >= 3 && loginName.val().length <= 40;
            loginName.toggleClass(classSuccess, ok).toggleClass(classError, !ok);
            clearFieldError(loginName);
            if (!ok) setFieldError(loginName, 'error', mess_login_min_max);
        }
        function validPasswordLog() {
            if (!loginPassword.length) return;
            var len = loginPassword.val().length;
            var ok = len >= 6 && len <= 72;
            loginPassword.toggleClass(classSuccess, ok).toggleClass(classError, !ok);
            clearFieldError(loginPassword);
            if (!ok) setFieldError(loginPassword, 'error', mess_password2);
        }

        if (loginName.length)     loginName.on('input', validLoginLog);
        if (loginPassword.length) loginPassword.on('input', validPasswordLog);

        function loginOnClick() {
            var loginSave = loginNotSave.is(':checked') ? 1 : 0;
            ShowLoading('');
            $.post(dle_root + 'engine/ajax/controller.php?mod=authrize&method=login', {
                name: loginName.val(),
                password: loginPassword.val(),
                login_not_save: loginSave,
                user_hash: dle_login_hash
            }, function (data) {
                HideLoading('');
                if (data) {
                    $('#auth_log_result').empty().append(data);
                    if ($('#auth_login .auth_block_success').length) {
                        window.setTimeout(function () { window.location.reload(); }, 1000);
                    }
                }
            }, 'html').fail(function () {
                HideLoading('');
                showError('Ошибка соединения с сервером');
            });
        }

        if (login.length)     login.on('input', validLogin);
        if (email.length)     email.on('input', validEmail);
        if (password.length)  password.on('input', validPassword);
        if (password2.length) password2.on('input', validPassword);

        DOC.on('submit', '#auth_register', function (e) {
            e.preventDefault();
            registerOnClick();
        });
        DOC.on('submit', '#auth_login', function (e) {
            e.preventDefault();
            loginOnClick();
        });

        /* ---------- AUTH modal ---------- */
        DOC.on('click', '.auth_modal__close', function () {
            $('.auth_modal__row').removeClass('slow');
            $('.auth_modal').fadeOut(300);
        });
        DOC.on('click', '#auth_reg_open', function (e) {
            e.preventDefault();
            $('.auth_modal__row').addClass('slow');
            $('.auth_modal__title').text('Регистрация');
        });
        DOC.on('click', '#auth_login_open', function (e) {
            e.preventDefault();
            $('.auth_modal__row').removeClass('slow');
            $('.auth_modal__title').text('Войти на сайт');
        });
        DOC.on('click', '.auth_show_login', function (e) {
            e.preventDefault();
            $('.auth_modal').fadeIn(200);
            $('.auth_modal__title').text('Войти на сайт');
        });
        DOC.on('click', '.auth_show_register', function (e) {
            e.preventDefault();
            $('.auth_modal__row').addClass('slow');
            $('.auth_modal').fadeIn(200);
            $('.auth_modal__title').text('Регистрация');
        });

        DOC.on('focus', '.auth_modal__content input', function () {
            $(this).closest('.auth_modal__field').addClass('focus');
        });
        DOC.on('blur', '.auth_modal__content input', function () {
            if ($(this).val().length === 0) {
                $(this).closest('.auth_modal__field').removeClass('focus');
            }
        });
        DOC.on('input', '.auth_modal__content input', function () {
            $(this).closest('.auth_modal__field').toggleClass('focus', $(this).val().length > 0);
        });

        /* ---------- Captcha refresh ---------- */
        function refreshCaptcha(el) {
            if (!el || !el.closest) return;
            var wrap = el.closest('.captcha__image-reload');
            if (!wrap) return;
            var img = wrap.querySelector('.captcha__image');
            if (!img) return;
            img.src = '/engine/ajax/controller.php?mod=auth_capcha&r=' + Date.now();
        }
        var captchaBtn = document.querySelector('.captcha__refresh');
        if (captchaBtn) {
            captchaBtn.addEventListener('click', function (e) {
                refreshCaptcha(e.currentTarget);
            });
        }

        /* ---------- Делегирование клика по спойлеру ---------- */
        DOC.on('click', '.comment-spoiler-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);

            // Защита от двойного вызова inline onclick + делегирования
            if ($btn.data('processing')) return false;
            $btn.data('processing', true);

            var commentId = $btn.attr('data-comment-id');
            if (!commentId) {
                var m = ($btn.attr('onclick') || '').match(/markCommentSpoiler\(['"]?(\d+)['"]?/);
                if (m) commentId = m[1];
            }
            if (!commentId) {
                var $container = $btn.closest('.comment-item, .comments-tree-item');
                var idMatch = ($container.attr('id') || '').match(/(\d+)/);
                if (idMatch) commentId = idMatch[1];
            }

            if (commentId) {
                $btn.removeAttr('onclick');
                markCommentSpoiler(commentId, this);
            } else {
                $btn.data('processing', false);
                showError('Не удалось определить ID комментария');
            }
            return false;
        });

        // Делегирование «Показать спойлер» (динамически созданные кнопки)
        DOC.on('click', '.comment-spoiler-show-btn', function (e) {
            e.preventDefault();
            var id = $(this).attr('data-comment-id');
            if (id) showCommentSpoiler(id);
        });

        /* ---------- Countdown ---------- */
        initEpisodeCountdown();

        /* ---------- Lazy load ---------- */
        initLazyload();

        /* ---------- DLE players ---------- */
        initDlePlayers();
    });

    /* ========================= GRID VIEW (второй ready) ========================= */

    $(function () {
        var savedView = null;
        try { savedView = $.cookie('grid-view'); } catch (e) { /* noop */ }

        var $grid = $('#grid');

        if (savedView === 'grid-list' && $grid.length) {
            $grid.removeClass('grid-thumb').addClass('grid-list');
            $('#grid-select div:first-child').addClass('current').siblings('div').removeClass('current');
        } else if (savedView === 'grid-thumb' && $grid.length) {
            $grid.removeClass('grid-list').addClass('grid-thumb');
            $('#grid-select div:last-child').addClass('current').siblings('div').removeClass('current');
        }

        $(document).on('click', '#grid-select div:not(.current)', function () {
            var viewType = $(this).attr('data-type');
            var $gr = $('#grid');
            if (!$gr.length) return;

            $(this).addClass('current').siblings('div').removeClass('current');

            $gr.stop().fadeOut(100, function () {
                $gr.removeClass('grid-list grid-thumb').addClass(viewType).fadeIn();
            });

            try { $.cookie('grid-view', viewType, { path: '/', expires: 7 }); } catch (e) { /* noop */ }
        });
    });

})(jQuery, window, document);


/* =========================================================================
   Third-party libraries (не изменялись)
   Owl Carousel v2.1.0 | jQuery Cookie v1.3
   ========================================================================= */

/**
 * Owl Carousel v2.1.0
 * Copyright 2013-2016 David Deutsch
 * Licensed under MIT
 */
!function(a,b,c,d){function e(b,c){this.settings=null,this.options=a.extend({},e.Defaults,c),this.$element=a(b),this._handlers={},this._plugins={},this._supress={},this._current=null,this._speed=null,this._coordinates=[],this._breakpoint=null,this._width=null,this._items=[],this._clones=[],this._mergers=[],this._widths=[],this._invalidated={},this._pipe=[],this._drag={time:null,target:null,pointer:null,stage:{start:null,current:null},direction:null},this._states={current:{},tags:{initializing:["busy"],animating:["busy"],dragging:["interacting"]}},a.each(["onResize","onThrottledResize"],a.proxy(function(b,c){this._handlers[c]=a.proxy(this[c],this)},this)),a.each(e.Plugins,a.proxy(function(a,b){this._plugins[a.charAt(0).toLowerCase()+a.slice(1)]=new b(this)},this)),a.each(e.Workers,a.proxy(function(b,c){this._pipe.push({filter:c.filter,run:a.proxy(c.run,this)})},this)),this.setup(),this.initialize()}e.Defaults={items:3,loop:!1,center:!1,rewind:!1,mouseDrag:!0,touchDrag:!0,pullDrag:!0,freeDrag:!1,margin:0,stagePadding:0,merge:!1,mergeFit:!0,autoWidth:!1,startPosition:0,rtl:!1,smartSpeed:250,fluidSpeed:!1,dragEndSpeed:!1,responsive:{},responsiveRefreshRate:200,responsiveBaseElement:b,fallbackEasing:"swing",info:!1,nestedItemSelector:!1,itemElement:"div",stageElement:"div",refreshClass:"owl-refresh",loadedClass:"owl-loaded",loadingClass:"owl-loading",rtlClass:"owl-rtl",responsiveClass:"owl-responsive",dragClass:"owl-drag",itemClass:"owl-item",stageClass:"owl-stage",stageOuterClass:"owl-stage-outer",grabClass:"owl-grab"},e.Width={Default:"default",Inner:"inner",Outer:"outer"},e.Type={Event:"event",State:"state"},e.Plugins={},e.Workers=[{filter:["width","settings"],run:function(){this._width=this.$element.width()}},{filter:["width","items","settings"],run:function(a){a.current=this._items&&this._items[this.relative(this._current)]}},{filter:["items","settings"],run:function(){this.$stage.children(".cloned").remove()}},{filter:["width","items","settings"],run:function(a){var b=this.settings.margin||"",c=!this.settings.autoWidth,d=this.settings.rtl,e={width:"auto","margin-left":d?b:"","margin-right":d?"":b};!c&&this.$stage.children().css(e),a.css=e}},{filter:["width","items","settings"],run:function(a){var b=(this.width()/this.settings.items).toFixed(3)-this.settings.margin,c=null,d=this._items.length,e=!this.settings.autoWidth,f=[];for(a.items={merge:!1,width:b};d--;)c=this._mergers[d],c=this.settings.mergeFit&&Math.min(c,this.settings.items)||c,a.items.merge=c>1||a.items.merge,f[d]=e?b*c:this._items[d].width();this._widths=f}},{filter:["items","settings"],run:function(){var b=[],c=this._items,d=this.settings,e=Math.max(2*d.items,4),f=2*Math.ceil(c.length/2),g=d.loop&&c.length?d.rewind?e:Math.max(e,f):0,h="",i="";for(g/=2;g--;)b.push(this.normalize(b.length/2,!0)),h+=c[b[b.length-1]][0].outerHTML,b.push(this.normalize(c.length-1-(b.length-1)/2,!0)),i=c[b[b.length-1]][0].outerHTML+i;this._clones=b,a(h).addClass("cloned").appendTo(this.$stage),a(i).addClass("cloned").prependTo(this.$stage)}},{filter:["width","items","settings"],run:function(){for(var a=this.settings.rtl?1:-1,b=this._clones.length+this._items.length,c=-1,d=0,e=0,f=[];++c<b;)d=f[c-1]||0,e=this._widths[this.relative(c)]+this.settings.margin,f.push(d+e*a);this._coordinates=f}},{filter:["width","items","settings"],run:function(){var a=this.settings.stagePadding,b=this._coordinates,c={width:Math.ceil(Math.abs(b[b.length-1]))+2*a,"padding-left":a||"","padding-right":a||""};this.$stage.css(c)}},{filter:["width","items","settings"],run:function(a){var b=this._coordinates.length,c=!this.settings.autoWidth,d=this.$stage.children();if(c&&a.items.merge)for(;b--;)a.css.width=this._widths[this.relative(b)],d.eq(b).css(a.css);else c&&(a.css.width=a.items.width,d.css(a.css))}},{filter:["items"],run:function(){this._coordinates.length<1&&this.$stage.removeAttr("style")}},{filter:["width","items","settings"],run:function(a){a.current=a.current?this.$stage.children().index(a.current):0,a.current=Math.max(this.minimum(),Math.min(this.maximum(),a.current)),this.reset(a.current)}},{filter:["position"],run:function(){this.animate(this.coordinates(this._current))}},{filter:["width","position","items","settings"],run:function(){var a,b,c,d,e=this.settings.rtl?1:-1,f=2*this.settings.stagePadding,g=this.coordinates(this.current())+f,h=g+this.width()*e,i=[];for(c=0,d=this._coordinates.length;d>c;c++)a=this._coordinates[c-1]||0,b=Math.abs(this._coordinates[c])+f*e,(this.op(a,"<=",g)&&this.op(a,">",h)||this.op(b,"<",g)&&this.op(b,">",h))&&i.push(c);this.$stage.children(".active").removeClass("active"),this.$stage.children(":eq("+i.join("), :eq(")+")").addClass("active"),this.settings.center&&(this.$stage.children(".center").removeClass("center"),this.$stage.children().eq(this.current()).addClass("center"))}}],e.prototype.initialize=function(){if(this.enter("initializing"),this.trigger("initialize"),this.$element.toggleClass(this.settings.rtlClass,this.settings.rtl),this.settings.autoWidth&&!this.is("pre-loading")){var b,c,e;b=this.$element.find("img"),c=this.settings.nestedItemSelector?"."+this.settings.nestedItemSelector:d,e=this.$element.children(c).width(),b.length&&0>=e&&this.preloadAutoWidthImages(b)}this.$element.addClass(this.options.loadingClass),this.$stage=a("<"+this.settings.stageElement+' class="'+this.settings.stageClass+'"/>').wrap('<div class="'+this.settings.stageOuterClass+'"/>'),this.$element.append(this.$stage.parent()),this.replace(this.$element.children().not(this.$stage.parent())),this.$element.is(":visible")?this.refresh():this.invalidate("width"),this.$element.removeClass(this.options.loadingClass).addClass(this.options.loadedClass),this.registerEventHandlers(),this.leave("initializing"),this.trigger("initialized")},e.prototype.setup=function(){var b=this.viewport(),c=this.options.responsive,d=-1,e=null;c?(a.each(c,function(a){b>=a&&a>d&&(d=Number(a))}),e=a.extend({},this.options,c[d]),delete e.responsive,e.responsiveClass&&this.$element.attr("class",this.$element.attr("class").replace(new RegExp("("+this.options.responsiveClass+"-)\\S+\\s","g"),"$1"+d))):e=a.extend({},this.options),null!==this.settings&&this._breakpoint===d||(this.trigger("change",{property:{name:"settings",value:e}}),this._breakpoint=d,this.settings=e,this.invalidate("settings"),this.trigger("changed",{property:{name:"settings",value:this.settings}}))},e.prototype.optionsLogic=function(){this.settings.autoWidth&&(this.settings.stagePadding=!1,this.settings.merge=!1)},e.prototype.prepare=function(b){var c=this.trigger("prepare",{content:b});return c.data||(c.data=a("<"+this.settings.itemElement+"/>").addClass(this.options.itemClass).append(b)),this.trigger("prepared",{content:c.data}),c.data},e.prototype.update=function(){for(var b=0,c=this._pipe.length,d=a.proxy(function(a){return this[a]},this._invalidated),e={};c>b;)(this._invalidated.all||a.grep(this._pipe[b].filter,d).length>0)&&this._pipe[b].run(e),b++;this._invalidated={},!this.is("valid")&&this.enter("valid")},e.prototype.width=function(a){switch(a=a||e.Width.Default){case e.Width.Inner:case e.Width.Outer:return this._width;default:return this._width-2*this.settings.stagePadding+this.settings.margin}},e.prototype.refresh=function(){this.enter("refreshing"),this.trigger("refresh"),this.setup(),this.optionsLogic(),this.$element.addClass(this.options.refreshClass),this.update(),this.$element.removeClass(this.options.refreshClass),this.leave("refreshing"),this.trigger("refreshed")},e.prototype.onThrottledResize=function(){b.clearTimeout(this.resizeTimer),this.resizeTimer=b.setTimeout(this._handlers.onResize,this.settings.responsiveRefreshRate)},e.prototype.onResize=function(){return this._items.length?this._width===this.$element.width()?!1:this.$element.is(":visible")?(this.enter("resizing"),this.trigger("resize").isDefaultPrevented()?(this.leave("resizing"),!1):(this.invalidate("width"),this.refresh(),this.leave("resizing"),void this.trigger("resized"))):!1:!1},e.prototype.registerEventHandlers=function(){a.support.transition&&this.$stage.on(a.support.transition.end+".owl.core",a.proxy(this.onTransitionEnd,this)),this.settings.responsive!==!1&&this.on(b,"resize",this._handlers.onThrottledResize),this.settings.mouseDrag&&(this.$element.addClass(this.options.dragClass),this.$stage.on("mousedown.owl.core",a.proxy(this.onDragStart,this)),this.$stage.on("dragstart.owl.core selectstart.owl.core",function(){return!1})),this.settings.touchDrag&&(this.$stage.on("touchstart.owl.core",a.proxy(this.onDragStart,this)),this.$stage.on("touchcancel.owl.core",a.proxy(this.onDragEnd,this)))},e.prototype.onDragStart=function(b){var d=null;3!==b.which&&(a.support.transform?(d=this.$stage.css("transform").replace(/.*\(|\)| /g,"").split(","),d={x:d[16===d.length?12:4],y:d[16===d.length?13:5]}):(d=this.$stage.position(),d={x:this.settings.rtl?d.left+this.$stage.width()-this.width()+this.settings.margin:d.left,y:d.top}),this.is("animating")&&(a.support.transform?this.animate(d.x):this.$stage.stop(),this.invalidate("position")),this.$element.toggleClass(this.options.grabClass,"mousedown"===b.type),this.speed(0),this._drag.time=(new Date).getTime(),this._drag.target=a(b.target),this._drag.stage.start=d,this._drag.stage.current=d,this._drag.pointer=this.pointer(b),a(c).on("mouseup.owl.core touchend.owl.core",a.proxy(this.onDragEnd,this)),a(c).one("mousemove.owl.core touchmove.owl.core",a.proxy(function(b){var d=this.difference(this._drag.pointer,this.pointer(b));a(c).on("mousemove.owl.core touchmove.owl.core",a.proxy(this.onDragMove,this)),Math.abs(d.x)<Math.abs(d.y)&&this.is("valid")||(b.preventDefault(),this.enter("dragging"),this.trigger("drag"))},this)))},e.prototype.onDragMove=function(a){var b=null,c=null,d=null,e=this.difference(this._drag.pointer,this.pointer(a)),f=this.difference(this._drag.stage.start,e);this.is("dragging")&&(a.preventDefault(),this.settings.loop?(b=this.coordinates(this.minimum()),c=this.coordinates(this.maximum()+1)-b,f.x=((f.x-b)%c+c)%c+b):(b=this.settings.rtl?this.coordinates(this.maximum()):this.coordinates(this.minimum()),c=this.settings.rtl?this.coordinates(this.minimum()):this.coordinates(this.maximum()),d=this.settings.pullDrag?-1*e.x/5:0,f.x=Math.max(Math.min(f.x,b+d),c+d)),this._drag.stage.current=f,this.animate(f.x))},e.prototype.onDragEnd=function(b){var d=this.difference(this._drag.pointer,this.pointer(b)),e=this._drag.stage.current,f=d.x>0^this.settings.rtl?"left":"right";a(c).off(".owl.core"),this.$element.removeClass(this.options.grabClass),(0!==d.x&&this.is("dragging")||!this.is("valid"))&&(this.speed(this.settings.dragEndSpeed||this.settings.smartSpeed),this.current(this.closest(e.x,0!==d.x?f:this._drag.direction)),this.invalidate("position"),this.update(),this._drag.direction=f,(Math.abs(d.x)>3||(new Date).getTime()-this._drag.time>300)&&this._drag.target.one("click.owl.core",function(){return!1})),this.is("dragging")&&(this.leave("dragging"),this.trigger("dragged"))},e.prototype.closest=function(b,c){var d=-1,e=30,f=this.width(),g=this.coordinates();return this.settings.freeDrag||a.each(g,a.proxy(function(a,h){return"left"===c&&b>h-e&&h+e>b?d=a:"right"===c&&b>h-f-e&&h-f+e>b?d=a+1:this.op(b,"<",h)&&this.op(b,">",g[a+1]||h-f)&&(d="left"===c?a+1:a),-1===d},this)),this.settings.loop||(this.op(b,">",g[this.minimum()])?d=b=this.minimum():this.op(b,"<",g[this.maximum()])&&(d=b=this.maximum())),d},e.prototype.animate=function(b){var c=this.speed()>0;this.is("animating")&&this.onTransitionEnd(),c&&(this.enter("animating"),this.trigger("translate")),a.support.transform3d&&a.support.transition?this.$stage.css({transform:"translate3d("+b+"px,0px,0px)",transition:this.speed()/1e3+"s"}):c?this.$stage.animate({left:b+"px"},this.speed(),this.settings.fallbackEasing,a.proxy(this.onTransitionEnd,this)):this.$stage.css({left:b+"px"})},e.prototype.is=function(a){return this._states.current[a]&&this._states.current[a]>0},e.prototype.current=function(a){if(a===d)return this._current;if(0===this._items.length)return d;if(a=this.normalize(a),this._current!==a){var b=this.trigger("change",{property:{name:"position",value:a}});b.data!==d&&(a=this.normalize(b.data)),this._current=a,this.invalidate("position"),this.trigger("changed",{property:{name:"position",value:this._current}})}return this._current},e.prototype.invalidate=function(b){return"string"===a.type(b)&&(this._invalidated[b]=!0,this.is("valid")&&this.leave("valid")),a.map(this._invalidated,function(a,b){return b})},e.prototype.reset=function(a){a=this.normalize(a),a!==d&&(this._speed=0,this._current=a,this.suppress(["translate","translated"]),this.animate(this.coordinates(a)),this.release(["translate","translated"]))},e.prototype.normalize=function(a,b){var c=this._items.length,e=b?0:this._clones.length;return!this.isNumeric(a)||1>c?a=d:(0>a||a>=c+e)&&(a=((a-e/2)%c+c)%c+e/2),a},e.prototype.relative=function(a){return a-=this._clones.length/2,this.normalize(a,!0)},e.prototype.maximum=function(a){var b,c=this.settings,d=this._coordinates.length,e=Math.abs(this._coordinates[d-1])-this._width,f=-1;if(c.loop)d=this._clones.length/2+this._items.length-1;else if(c.autoWidth||c.merge)for(;d-f>1;)Math.abs(this._coordinates[b=d+f>>1])<e?f=b:d=b;else d=c.center?this._items.length-1:this._items.length-c.items;return a&&(d-=this._clones.length/2),Math.max(d,0)},e.prototype.minimum=function(a){return a?0:this._clones.length/2},e.prototype.items=function(a){return a===d?this._items.slice():(a=this.normalize(a,!0),this._items[a])},e.prototype.mergers=function(a){return a===d?this._mergers.slice():(a=this.normalize(a,!0),this._mergers[a])},e.prototype.clones=function(b){var c=this._clones.length/2,e=c+this._items.length,f=function(a){return a%2===0?e+a/2:c-(a+1)/2};return b===d?a.map(this._clones,function(a,b){return f(b)}):a.map(this._clones,function(a,c){return a===b?f(c):null})},e.prototype.speed=function(a){return a!==d&&(this._speed=a),this._speed},e.prototype.coordinates=function(b){var c,e=1,f=b-1;return b===d?a.map(this._coordinates,a.proxy(function(a,b){return this.coordinates(b)},this)):(this.settings.center?(this.settings.rtl&&(e=-1,f=b+1),c=this._coordinates[b],c+=(this.width()-c+(this._coordinates[f]||0))/2*e):c=this._coordinates[f]||0,c=Math.ceil(c))},e.prototype.duration=function(a,b,c){return 0===c?0:Math.min(Math.max(Math.abs(b-a),1),6)*Math.abs(c||this.settings.smartSpeed)},e.prototype.to=function(a,b){var c=this.current(),d=null,e=a-this.relative(c),f=(e>0)-(0>e),g=this._items.length,h=this.minimum(),i=this.maximum();this.settings.loop?(!this.settings.rewind&&Math.abs(e)>g/2&&(e+=-1*f*g),a=c+e,d=((a-h)%g+g)%g+h,d!==a&&i>=d-e&&d-e>0&&(c=d-e,a=d,this.reset(c))):this.settings.rewind?(i+=1,a=(a%i+i)%i):a=Math.max(h,Math.min(i,a)),this.speed(this.duration(c,a,b)),this.current(a),this.$element.is(":visible")&&this.update()},e.prototype.next=function(a){a=a||!1,this.to(this.relative(this.current())+1,a)},e.prototype.prev=function(a){a=a||!1,this.to(this.relative(this.current())-1,a)},e.prototype.onTransitionEnd=function(a){return a!==d&&(a.stopPropagation(),(a.target||a.srcElement||a.originalTarget)!==this.$stage.get(0))?!1:(this.leave("animating"),void this.trigger("translated"))},e.prototype.viewport=function(){var d;if(this.options.responsiveBaseElement!==b)d=a(this.options.responsiveBaseElement).width();else if(b.innerWidth)d=b.innerWidth;else{if(!c.documentElement||!c.documentElement.clientWidth)throw"Can not detect viewport width.";d=c.documentElement.clientWidth}return d},e.prototype.replace=function(b){this.$stage.empty(),this._items=[],b&&(b=b instanceof jQuery?b:a(b)),this.settings.nestedItemSelector&&(b=b.find("."+this.settings.nestedItemSelector)),b.filter(function(){return 1===this.nodeType}).each(a.proxy(function(a,b){b=this.prepare(b),this.$stage.append(b),this._items.push(b),this._mergers.push(1*b.find("[data-merge]").andSelf("[data-merge]").attr("data-merge")||1)},this)),this.reset(this.isNumeric(this.settings.startPosition)?this.settings.startPosition:0),this.invalidate("items")},e.prototype.add=function(b,c){var e=this.relative(this._current);c=c===d?this._items.length:this.normalize(c,!0),b=b instanceof jQuery?b:a(b),this.trigger("add",{content:b,position:c}),b=this.prepare(b),0===this._items.length||c===this._items.length?(0===this._items.length&&this.$stage.append(b),0!==this._items.length&&this._items[c-1].after(b),this._items.push(b),this._mergers.push(1*b.find("[data-merge]").andSelf("[data-merge]").attr("data-merge")||1)):(this._items[c].before(b),this._items.splice(c,0,b),this._mergers.splice(c,0,1*b.find("[data-merge]").andSelf("[data-merge]").attr("data-merge")||1)),this._items[e]&&this.reset(this._items[e].index()),this.invalidate("items"),this.trigger("added",{content:b,position:c})},e.prototype.remove=function(a){a=this.normalize(a,!0),a!==d&&(this.trigger("remove",{content:this._items[a],position:a}),this._items[a].remove(),this._items.splice(a,1),this._mergers.splice(a,1),this.invalidate("items"),this.trigger("removed",{content:null,position:a}))},e.prototype.preloadAutoWidthImages=function(b){b.each(a.proxy(function(b,c){this.enter("pre-loading"),c=a(c),a(new Image).one("load",a.proxy(function(a){c.attr("src",a.target.src),c.css("opacity",1),this.leave("pre-loading"),!this.is("pre-loading")&&!this.is("initializing")&&this.refresh()},this)).attr("src",c.attr("src")||c.attr("data-src")||c.attr("data-src-retina"))},this))},e.prototype.destroy=function(){this.$element.off(".owl.core"),this.$stage.off(".owl.core"),a(c).off(".owl.core"),this.settings.responsive!==!1&&(b.clearTimeout(this.resizeTimer),this.off(b,"resize",this._handlers.onThrottledResize));for(var d in this._plugins)this._plugins[d].destroy();this.$stage.children(".cloned").remove(),this.$stage.unwrap(),this.$stage.children().contents().unwrap(),this.$stage.children().unwrap(),this.$element.removeClass(this.options.refreshClass).removeClass(this.options.loadingClass).removeClass(this.options.loadedClass).removeClass(this.options.rtlClass).removeClass(this.options.dragClass).removeClass(this.options.grabClass).attr("class",this.$element.attr("class").replace(new RegExp(this.options.responsiveClass+"-\\S+\\s","g"),"")).removeData("owl.carousel")},e.prototype.op=function(a,b,c){var d=this.settings.rtl;switch(b){case"<":return d?a>c:c>a;case">":return d?c>a:a>c;case">=":return d?c>=a:a>=c;case"<=":return d?a>=c:c>=a}},e.prototype.on=function(a,b,c,d){a.addEventListener?a.addEventListener(b,c,d):a.attachEvent&&a.attachEvent("on"+b,c)},e.prototype.off=function(a,b,c,d){a.removeEventListener?a.removeEventListener(b,c,d):a.detachEvent&&a.detachEvent("on"+b,c)},e.prototype.trigger=function(b,c,d,f,g){var h={item:{count:this._items.length,index:this.current()}},i=a.camelCase(a.grep(["on",b,d],function(a){return a}).join("-").toLowerCase()),j=a.Event([b,"owl",d||"carousel"].join(".").toLowerCase(),a.extend({relatedTarget:this},h,c));return this._supress[b]||(a.each(this._plugins,function(a,b){b.onTrigger&&b.onTrigger(j)}),this.register({type:e.Type.Event,name:b}),this.$element.trigger(j),this.settings&&"function"==typeof this.settings[i]&&this.settings[i].call(this,j)),j},e.prototype.enter=function(b){a.each([b].concat(this._states.tags[b]||[]),a.proxy(function(a,b){this._states.current[b]===d&&(this._states.current[b]=0),this._states.current[b]++},this))},e.prototype.leave=function(b){a.each([b].concat(this._states.tags[b]||[]),a.proxy(function(a,b){this._states.current[b]--},this))},e.prototype.register=function(b){if(b.type===e.Type.Event){if(a.event.special[b.name]||(a.event.special[b.name]={}),!a.event.special[b.name].owl){var c=a.event.special[b.name]._default;a.event.special[b.name]._default=function(a){return!c||!c.apply||a.namespace&&-1!==a.namespace.indexOf("owl")?a.namespace&&a.namespace.indexOf("owl")>-1:c.apply(this,arguments)},a.event.special[b.name].owl=!0}}else b.type===e.Type.State&&(this._states.tags[b.name]?this._states.tags[b.name]=this._states.tags[b.name].concat(b.tags):this._states.tags[b.name]=b.tags,this._states.tags[b.name]=a.grep(this._states.tags[b.name],a.proxy(function(c,d){return a.inArray(c,this._states.tags[b.name])===d},this)))},e.prototype.suppress=function(b){a.each(b,a.proxy(function(a,b){this._supress[b]=!0},this))},e.prototype.release=function(b){a.each(b,a.proxy(function(a,b){delete this._supress[b]},this))},e.prototype.pointer=function(a){var c={x:null,y:null};return a=a.originalEvent||a||b.event,a=a.touches&&a.touches.length?a.touches[0]:a.changedTouches&&a.changedTouches.length?a.changedTouches[0]:a,a.pageX?(c.x=a.pageX,c.y=a.pageY):(c.x=a.clientX,c.y=a.clientY),c},e.prototype.isNumeric=function(a){return!isNaN(parseFloat(a))},e.prototype.difference=function(a,b){return{x:a.x-b.x,y:a.y-b.y}},a.fn.owlCarousel=function(b){var c=Array.prototype.slice.call(arguments,1);return this.each(function(){var d=a(this),f=d.data("owl.carousel");f||(f=new e(this,"object"==typeof b&&b),d.data("owl.carousel",f),a.each(["next","prev","to","destroy","refresh","replace","add","remove"],function(b,c){f.register({type:e.Type.Event,name:c}),f.$element.on(c+".owl.carousel.core",a.proxy(function(a){a.namespace&&a.relatedTarget!==this&&(this.suppress([c]),f[c].apply(this,[].slice.call(arguments,1)),this.release([c]))},f))})),"string"==typeof b&&"_"!==b.charAt(0)&&f[b].apply(f,c)})},a.fn.owlCarousel.Constructor=e}(window.Zepto||window.jQuery,window,document),function(a,b,c,d){var e=function(b){this._core=b,this._interval=null,this._visible=null,this._handlers={"initialized.owl.carousel":a.proxy(function(a){a.namespace&&this._core.settings.autoRefresh&&this.watch()},this)},this._core.options=a.extend({},e.Defaults,this._core.options),this._core.$element.on(this._handlers)};e.Defaults={autoRefresh:!0,autoRefreshInterval:500},e.prototype.watch=function(){this._interval||(this._visible=this._core.$element.is(":visible"),this._interval=b.setInterval(a.proxy(this.refresh,this),this._core.settings.autoRefreshInterval))},e.prototype.refresh=function(){this._core.$element.is(":visible")!==this._visible&&(this._visible=!this._visible,this._core.$element.toggleClass("owl-hidden",!this._visible),this._visible&&this._core.invalidate("width")&&this._core.refresh())},e.prototype.destroy=function(){var a,c;b.clearInterval(this._interval);for(a in this._handlers)this._core.$element.off(a,this._handlers[a]);for(c in Object.getOwnPropertyNames(this))"function"!=typeof this[c]&&(this[c]=null)},a.fn.owlCarousel.Constructor.Plugins.AutoRefresh=e}(window.Zepto||window.jQuery,window,document),function(a,b,c,d){var e=function(b){this._core=b,this._loaded=[],this._handlers={"initialized.owl.carousel change.owl.carousel resized.owl.carousel":a.proxy(function(b){if(b.namespace&&this._core.settings&&this._core.settings.lazyLoad&&(b.property&&"position"==b.property.name||"initialized"==b.type))for(var c=this._core.settings,e=c.center&&Math.ceil(c.items/2)||c.items,f=c.center&&-1*e||0,g=(b.property&&b.property.value!==d?b.property.value:this._core.current())+f,h=this._core.clones().length,i=a.proxy(function(a,b){this.load(b)},this);f++<e;)this.load(h/2+this._core.relative(g)),h&&a.each(this._core.clones(this._core.relative(g)),i),g++},this)},this._core.options=a.extend({},e.Defaults,this._core.options),this._core.$element.on(this._handlers)};e.Defaults={lazyLoad:!1},e.prototype.load=function(c){var d=this._core.$stage.children().eq(c),e=d&&d.find(".owl-lazy");!e||a.inArray(d.get(0),this._loaded)>-1||(e.each(a.proxy(function(c,d){var e,f=a(d),g=b.devicePixelRatio>1&&f.attr("data-src-retina")||f.attr("data-src");this._core.trigger("load",{element:f,url:g},"lazy"),f.is("img")?f.one("load.owl.lazy",a.proxy(function(){f.css("opacity",1),this._core.trigger("loaded",{element:f,url:g},"lazy")},this)).attr("src",g):(e=new Image,e.onload=a.proxy(function(){f.css({"background-image":"url("+g+")",opacity:"1"}),this._core.trigger("loaded",{element:f,url:g},"lazy")},this),e.src=g)},this)),this._loaded.push(d.get(0)))},e.prototype.destroy=function(){var a,b;for(a in this.handlers)this._core.$element.off(a,this.handlers[a]);for(b in Object.getOwnPropertyNames(this))"function"!=typeof this[b]&&(this[b]=null)},a.fn.owlCarousel.Constructor.Plugins.Lazy=e}(window.Zepto||window.jQuery,window,document),function(a,b,c,d){var e=function(b){this._core=b,this._handlers={"initialized.owl.carousel refreshed.owl.carousel":a.proxy(function(a){a.namespace&&this._core.settings.autoHeight&&this.update()},this),"changed.owl.carousel":a.proxy(function(a){a.namespace&&this._core.settings.autoHeight&&"position"==a.property.name&&this.update()},this),"loaded.owl.lazy":a.proxy(function(a){a.namespace&&this._core.settings.autoHeight&&a.element.closest("."+this._core.settings.itemClass).index()===this._core.current()&&this.update()},this)},this._core.options=a.extend({},e.Defaults,this._core.options),this._core.$element.on(this._handlers)};e.Defaults={autoHeight:!1,autoHeightClass:"owl-height"},e.prototype.update=function(){var b=this._core._current,c=b+this._core.settings.items,d=this._core.$stage.children().toArray().slice(b,c),e=[],f=0;a.each(d,function(b,c){e.push(a(c).height())}),f=Math.max.apply(null,e),this._core.$stage.parent().height(f).addClass(this._core.settings.autoHeightClass)},e.prototype.destroy=function(){var a,b;for(a in this._handlers)this._core.$element.off(a,this._handlers[a]);for(b in Object.getOwnPropertyNames(this))"function"!=typeof this[b]&&(this[b]=null)},a.fn.owlCarousel.Constructor.Plugins.AutoHeight=e}(window.Zepto||window.jQuery,window,document),function(a,b,c,d){var e=function(b){this._core=b,this._videos={},this._playing=null,this._handlers={"initialized.owl.carousel":a.proxy(function(a){a.namespace&&this._core.register({type:"state",name:"playing",tags:["interacting"]})},this),"resize.owl.carousel":a.proxy(function(a){a.namespace&&this._core.settings.video&&this.isInFullScreen()&&a.preventDefault()},this),"refreshed.owl.carousel":a.proxy(function(a){a.namespace&&this._core.is("resizing")&&this._core.$stage.find(".cloned .owl-video-frame").remove()},this),"changed.owl.carousel":a.proxy(function(a){a.namespace&&"position"===a.property.name&&this._playing&&this.stop()},this),"prepared.owl.carousel":a.proxy(function(b){if(b.namespace){var c=a(b.content).find(".owl-video");c.length&&(c.css("display","none"),this.fetch(c,a(b.content)))}},this)},this._core.options=a.extend({},e.Defaults,this._core.options),this._core.$element.on(this._handlers),this._core.$element.on("click.owl.video",".owl-video-play-icon",a.proxy(function(a){this.play(a)},this))};e.Defaults={video:!1,videoHeight:!1,videoWidth:!1},e.prototype.fetch=function(a,b){var c=function(){return a.attr("data-vimeo-id")?"vimeo":a.attr("data-vzaar-id")?"vzaar":"youtube"}(),d=a.attr("data-vimeo-id")||a.attr("data-youtube-id")||a.attr("data-vzaar-id"),e=a.attr("data-width")||this._core.settings.videoWidth,f=a.attr("data-height")||this._core.settings.videoHeight,g=a.attr("href");if(!g)throw new Error("Missing video URL.");if(d=g.match(/(http:|https:|)\/\/(player.|www.|app.)?(vimeo\.com|youtu(be\.com|\.be|be\.googleapis\.com)|vzaar\.com)\/(video\/|videos\/|embed\/|channels\/.+\/|groups\/.+\/|watch\?v=|v\/)?([A-Za-z0-9._%-]*)(\&\S+)?/),d[3].indexOf("youtu")>-1)c="youtube";else if(d[3].indexOf("vimeo")>-1)c="vimeo";else{if(!(d[3].indexOf("vzaar")>-1))throw new Error("Video URL not supported.");c="vzaar"}d=d[6],this._videos[g]={type:c,id:d,width:e,height:f},b.attr("data-video",g),this.thumbnail(a,this._videos[g])},e.prototype.thumbnail=function(b,c){var d,e,f,g=c.width&&c.height?'style="width:'+c.width+"px;height:"+c.height+'px;"':"",h=b.find("img"),i="src",j="",k=this._core.settings,l=function(a){e='<div class="owl-video-play-icon"></div>',d=k.lazyLoad?'<div class="owl-video-tn '+j+'" '+i+'="'+a+'"></div>':'<div class="owl-video-tn" style="opacity:1;background-image:url('+a+')"></div>',b.after(d),b.after(e)};return b.wrap('<div class="owl-video-wrapper"'+g+"></div>"),this._core.settings.lazyLoad&&(i="data-src",j="owl-lazy"),h.length?(l(h.attr(i)),h.remove(),!1):void("youtube"===c.type?(f="//img.youtube.com/vi/"+c.id+"/hqdefault.jpg",l(f)):"vimeo"===c.type?a.ajax({type:"GET",url:"//vimeo.com/api/v2/video/"+c.id+".json",jsonp:"callback",dataType:"jsonp",success:function(a){f=a[0].thumbnail_large,l(f)}}):"vzaar"===c.type&&a.ajax({type:"GET",url:"//vzaar.com/api/videos/"+c.id+".json",jsonp:"callback",dataType:"jsonp",success:function(a){f=a.framegrab_url,l(f)}}))},e.prototype.stop=function(){this._core.trigger("stop",null,"video"),this._playing.find(".owl-video-frame").remove(),this._playing.removeClass("owl-video-playing"),this._playing=null,this._core.leave("playing"),this._core.trigger("stopped",null,"video")},e.prototype.play=function(b){var c,d=a(b.target),e=d.closest("."+this._core.settings.itemClass),f=this._videos[e.attr("data-video")],g=f.width||"100%",h=f.height||this._core.$stage.height();this._playing||(this._core.enter("playing"),this._core.trigger("play",null,"video"),e=this._core.items(this._core.relative(e.index())),this._core.reset(e.index()),"youtube"===f.type?c='<iframe width="'+g+'" height="'+h+'" src="//www.youtube.com/embed/'+f.id+"?autoplay=1&v="+f.id+'" frameborder="0" allowfullscreen></iframe>':"vimeo"===f.type?c='<iframe src="//player.vimeo.com/video/'+f.id+'?autoplay=1" width="'+g+'" height="'+h+'" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>':"vzaar"===f.type&&(c='<iframe frameborder="0"height="'+h+'"width="'+g+'" allowfullscreen mozallowfullscreen webkitAllowFullScreen src="//view.vzaar.com/'+f.id+'/player?autoplay=true"></iframe>'),a('<div class="owl-video-frame">'+c+"</div>").insertAfter(e.find(".owl-video")),this._playing=e.addClass("owl-video-playing"))},e.prototype.isInFullScreen=function(){var b=c.fullscreenElement||c.mozFullScreenElement||c.webkitFullscreenElement;return b&&a(b).parent().hasClass("owl-video-frame")},e.prototype.destroy=function(){var a,b;this._core.$element.off("click.owl.video");for(a in this._handlers)this._core.$element.off(a,this._handlers[a]);for(b in Object.getOwnPropertyNames(this))"function"!=typeof this[b]&&(this[b]=null)},a.fn.owlCarousel.Constructor.Plugins.Video=e}(window.Zepto||window.jQuery,window,document),function(a,b,c,d){var e=function(b){this.core=b,this.core.options=a.extend({},e.Defaults,this.core.options),this.swapping=!0,this.previous=d,this.next=d,this.handlers={"change.owl.carousel":a.proxy(function(a){a.namespace&&"position"==a.property.name&&(this.previous=this.core.current(),this.next=a.property.value)},this),"drag.owl.carousel dragged.owl.carousel translated.owl.carousel":a.proxy(function(a){a.namespace&&(this.swapping="translated"==a.type)},this),"translate.owl.carousel":a.proxy(function(a){a.namespace&&this.swapping&&(this.core.options.animateOut||this.core.options.animateIn)&&this.swap()},this)},this.core.$element.on(this.handlers)};e.Defaults={animateOut:!1,animateIn:!1},e.prototype.swap=function(){if(1===this.core.settings.items&&a.support.animation&&a.support.transition){this.core.speed(0);var b,c=a.proxy(this.clear,this),d=this.core.$stage.children().eq(this.previous),e=this.core.$stage.children().eq(this.next),f=this.core.settings.animateIn,g=this.core.settings.animateOut;this.core.current()!==this.previous&&(g&&(b=this.core.coordinates(this.previous)-this.core.coordinates(this.next),d.one(a.support.animation.end,c).css({left:b+"px"}).addClass("animated owl-animated-out").addClass(g)),f&&e.one(a.support.animation.end,c).addClass("animated owl-animated-in").addClass(f))}},e.prototype.clear=function(b){a(b.target).css({left:""}).removeClass("animated owl-animated-out owl-animated-in").removeClass(this.core.settings.animateIn).removeClass(this.core.settings.animateOut),this.core.onTransitionEnd()},e.prototype.destroy=function(){var a,b;for(a in this.handlers)this.core.$element.off(a,this.handlers[a]);for(b in Object.getOwnPropertyNames(this))"function"!=typeof this[b]&&(this[b]=null)},a.fn.owlCarousel.Constructor.Plugins.Animate=e}(window.Zepto||window.jQuery,window,document),function(a,b,c,d){var e=function(b){this._core=b,this._timeout=null,this._paused=!1,this._handlers={"changed.owl.carousel":a.proxy(function(a){a.namespace&&"settings"===a.property.name?this._core.settings.autoplay?this.play():this.stop():a.namespace&&"position"===a.property.name&&this._core.settings.autoplay&&this._setAutoPlayInterval()},this),"initialized.owl.carousel":a.proxy(function(a){a.namespace&&this._core.settings.autoplay&&this.play()},this),"play.owl.autoplay":a.proxy(function(a,b,c){a.namespace&&this.play(b,c)},this),"stop.owl.autoplay":a.proxy(function(a){a.namespace&&this.stop()},this),"mouseover.owl.autoplay":a.proxy(function(){this._core.settings.autoplayHoverPause&&this._core.is("rotating")&&this.pause()},this),"mouseleave.owl.autoplay":a.proxy(function(){this._core.settings.autoplayHoverPause&&this._core.is("rotating")&&this.play()},this),"touchstart.owl.core":a.proxy(function(){this._core.settings.autoplayHoverPause&&this._core.is("rotating")&&this.pause()},this),"touchend.owl.core":a.proxy(function(){this._core.settings.autoplayHoverPause&&this.play()},this)},this._core.$element.on(this._handlers),this._core.options=a.extend({},e.Defaults,this._core.options)};e.Defaults={autoplay:!1,autoplayTimeout:5e3,autoplayHoverPause:!1,autoplaySpeed:!1},e.prototype.play=function(a,b){this._paused=!1,this._core.is("rotating")||(this._core.enter("rotating"),this._setAutoPlayInterval())},e.prototype._getNextTimeout=function(d,e){return this._timeout&&b.clearTimeout(this._timeout),b.setTimeout(a.proxy(function(){this._paused||this._core.is("busy")||this._core.is("interacting")||c.hidden||this._core.next(e||this._core.settings.autoplaySpeed)},this),d||this._core.settings.autoplayTimeout)},e.prototype._setAutoPlayInterval=function(){this._timeout=this._getNextTimeout()},e.prototype.stop=function(){this._core.is("rotating")&&(b.clearTimeout(this._timeout),this._core.leave("rotating"))},e.prototype.pause=function(){this._core.is("rotating")&&(this._paused=!0)},e.prototype.destroy=function(){var a,b;this.stop();for(a in this._handlers)this._core.$element.off(a,this._handlers[a]);for(b in Object.getOwnPropertyNames(this))"function"!=typeof this[b]&&(this[b]=null)},a.fn.owlCarousel.Constructor.Plugins.autoplay=e}(window.Zepto||window.jQuery,window,document),function(a,b,c,d){"use strict";var e=function(b){this._core=b,this._initialized=!1,this._pages=[],this._controls={},this._templates=[],this.$element=this._core.$element,this._overrides={next:this._core.next,prev:this._core.prev,to:this._core.to},this._handlers={"prepared.owl.carousel":a.proxy(function(b){b.namespace&&this._core.settings.dotsData&&this._templates.push('<div class="'+this._core.settings.dotClass+'">'+a(b.content).find("[data-dot]").addBack("[data-dot]").attr("data-dot")+"</div>")},this),"added.owl.carousel":a.proxy(function(a){a.namespace&&this._core.settings.dotsData&&this._templates.splice(a.position,0,this._templates.pop())},this),"remove.owl.carousel":a.proxy(function(a){a.namespace&&this._core.settings.dotsData&&this._templates.splice(a.position,1)},this),"changed.owl.carousel":a.proxy(function(a){a.namespace&&"position"==a.property.name&&this.draw()},this),"initialized.owl.carousel":a.proxy(function(a){a.namespace&&!this._initialized&&(this._core.trigger("initialize",null,"navigation"),this.initialize(),this.update(),this.draw(),this._initialized=!0,this._core.trigger("initialized",null,"navigation"))},this),"refreshed.owl.carousel":a.proxy(function(a){a.namespace&&this._initialized&&(this._core.trigger("refresh",null,"navigation"),this.update(),this.draw(),this._core.trigger("refreshed",null,"navigation"))},this)},this._core.options=a.extend({},e.Defaults,this._core.options),this.$element.on(this._handlers)};e.Defaults={nav:!1,navText:["prev","next"],navSpeed:!1,navElement:"div",navContainer:!1,navContainerClass:"owl-nav",navClass:["owl-prev","owl-next"],slideBy:1,dotClass:"owl-dot",dotsClass:"owl-dots",dots:!0,dotsEach:!1,dotsData:!1,dotsSpeed:!1,dotsContainer:!1},e.prototype.initialize=function(){var b,c=this._core.settings;this._controls.$relative=(c.navContainer?a(c.navContainer):a("<div>").addClass(c.navContainerClass).appendTo(this.$element)).addClass("disabled"),this._controls.$previous=a("<"+c.navElement+">").addClass(c.navClass[0]).html(c.navText[0]).prependTo(this._controls.$relative).on("click",a.proxy(function(a){this.prev(c.navSpeed)},this)),this._controls.$next=a("<"+c.navElement+">").addClass(c.navClass[1]).html(c.navText[1]).appendTo(this._controls.$relative).on("click",a.proxy(function(a){this.next(c.navSpeed)},this)),c.dotsData||(this._templates=[a("<div>").addClass(c.dotClass).append(a("<span>")).prop("outerHTML")]),this._controls.$absolute=(c.dotsContainer?a(c.dotsContainer):a("<div>").addClass(c.dotsClass).appendTo(this.$element)).addClass("disabled"),this._controls.$absolute.on("click","div",a.proxy(function(b){var d=a(b.target).parent().is(this._controls.$absolute)?a(b.target).index():a(b.target).parent().index();b.preventDefault(),this.to(d,c.dotsSpeed)},this));for(b in this._overrides)this._core[b]=a.proxy(this[b],this)},e.prototype.destroy=function(){var a,b,c,d;for(a in this._handlers)this.$element.off(a,this._handlers[a]);for(b in this._controls)this._controls[b].remove();for(d in this.overides)this._core[d]=this._overrides[d];for(c in Object.getOwnPropertyNames(this))"function"!=typeof this[c]&&(this[c]=null)},e.prototype.update=function(){var a,b,c,d=this._core.clones().length/2,e=d+this._core.items().length,f=this._core.maximum(!0),g=this._core.settings,h=g.center||g.autoWidth||g.dotsData?1:g.dotsEach||g.items;if("page"!==g.slideBy&&(g.slideBy=Math.min(g.slideBy,g.items)),g.dots||"page"==g.slideBy)for(this._pages=[],a=d,b=0,c=0;e>a;a++){if(b>=h||0===b){if(this._pages.push({start:Math.min(f,a-d),end:a-d+h-1}),Math.min(f,a-d)===f)break;b=0,++c}b+=this._core.mergers(this._core.relative(a))}},e.prototype.draw=function(){var b,c=this._core.settings,d=this._core.items().length<=c.items,e=this._core.relative(this._core.current()),f=c.loop||c.rewind;this._controls.$relative.toggleClass("disabled",!c.nav||d),c.nav&&(this._controls.$previous.toggleClass("disabled",!f&&e<=this._core.minimum(!0)),this._controls.$next.toggleClass("disabled",!f&&e>=this._core.maximum(!0))),this._controls.$absolute.toggleClass("disabled",!c.dots||d),c.dots&&(b=this._pages.length-this._controls.$absolute.children().length,c.dotsData&&0!==b?this._controls.$absolute.html(this._templates.join("")):b>0?this._controls.$absolute.append(new Array(b+1).join(this._templates[0])):0>b&&this._controls.$absolute.children().slice(b).remove(),this._controls.$absolute.find(".active").removeClass("active"),this._controls.$absolute.children().eq(a.inArray(this.current(),this._pages)).addClass("active"))},e.prototype.onTrigger=function(b){var c=this._core.settings;b.page={index:a.inArray(this.current(),this._pages),count:this._pages.length,size:c&&(c.center||c.autoWidth||c.dotsData?1:c.dotsEach||c.items)}},e.prototype.current=function(){var b=this._core.relative(this._core.current());return a.grep(this._pages,a.proxy(function(a,c){return a.start<=b&&a.end>=b},this)).pop()},e.prototype.getPosition=function(b){var c,d,e=this._core.settings;return"page"==e.slideBy?(c=a.inArray(this.current(),this._pages),d=this._pages.length,b?++c:--c,c=this._pages[(c%d+d)%d].start):(c=this._core.relative(this._core.current()),d=this._core.items().length,b?c+=e.slideBy:c-=e.slideBy),c},e.prototype.next=function(b){a.proxy(this._overrides.to,this._core)(this.getPosition(!0),b)},e.prototype.prev=function(b){a.proxy(this._overrides.to,this._core)(this.getPosition(!1),b)},e.prototype.to=function(b,c,d){var e;!d&&this._pages.length?(e=this._pages.length,a.proxy(this._overrides.to,this._core)(this._pages[(b%e+e)%e].start,c)):a.proxy(this._overrides.to,this._core)(b,c)},a.fn.owlCarousel.Constructor.Plugins.Navigation=e}(window.Zepto||window.jQuery,window,document),function(a,b,c,d){"use strict";var e=function(c){this._core=c,this._hashes={},this.$element=this._core.$element,this._handlers={"initialized.owl.carousel":a.proxy(function(c){c.namespace&&"URLHash"===this._core.settings.startPosition&&a(b).trigger("hashchange.owl.navigation")},this),"prepared.owl.carousel":a.proxy(function(b){if(b.namespace){var c=a(b.content).find("[data-hash]").addBack("[data-hash]").attr("data-hash");if(!c)return;this._hashes[c]=b.content}},this),"changed.owl.carousel":a.proxy(function(c){if(c.namespace&&"position"===c.property.name){var d=this._core.items(this._core.relative(this._core.current())),e=a.map(this._hashes,function(a,b){return a===d?b:null}).join();if(!e||b.location.hash.slice(1)===e)return;b.location.hash=e}},this)},this._core.options=a.extend({},e.Defaults,this._core.options),this.$element.on(this._handlers),a(b).on("hashchange.owl.navigation",a.proxy(function(a){var c=b.location.hash.substring(1),e=this._core.$stage.children(),f=this._hashes[c]&&e.index(this._hashes[c]);f!==d&&f!==this._core.current()&&this._core.to(this._core.relative(f),!1,!0)},this))};e.Defaults={URLhashListener:!1},e.prototype.destroy=function(){var c,d;a(b).off("hashchange.owl.navigation");for(c in this._handlers)this._core.$element.off(c,this._handlers[c]);for(d in Object.getOwnPropertyNames(this))"function"!=typeof this[d]&&(this[d]=null)},a.fn.owlCarousel.Constructor.Plugins.Hash=e}(window.Zepto||window.jQuery,window,document),function(a,b,c,d){function e(b,c){var e=!1,f=b.charAt(0).toUpperCase()+b.slice(1);return a.each((b+" "+h.join(f+" ")+f).split(" "),function(a,b){return g[b]!==d?(e=c?b:!0,!1):void 0}),e}function f(a){return e(a,!0)}var g=a("<support>").get(0).style,h="Webkit Moz O ms".split(" "),i={transition:{end:{WebkitTransition:"webkitTransitionEnd",MozTransition:"transitionend",OTransition:"oTransitionEnd",transition:"transitionend"}},animation:{end:{WebkitAnimation:"webkitAnimationEnd",MozAnimation:"animationend",OAnimation:"oAnimationEnd",animation:"animationend"}}},j={csstransforms:function(){return!!e("transform")},csstransforms3d:function(){return!!e("perspective")},csstransitions:function(){return!!e("transition")},cssanimations:function(){return!!e("animation")}};j.csstransitions()&&(a.support.transition=new String(f("transition")),a.support.transition.end=i.transition.end[a.support.transition]),j.cssanimations()&&(a.support.animation=new String(f("animation")),a.support.animation.end=i.animation.end[a.support.animation]),j.csstransforms()&&(a.support.transform=new String(f("transform")),a.support.transform3d=j.csstransforms3d())}(window.Zepto||window.jQuery,window,document);

/*!
 * jQuery Cookie Plugin v1.3
 * https://github.com/carhartl/jquery-cookie
 *
 * Copyright 2011, Klaus Hartl
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.opensource.org/licenses/GPL-2.0
 */
(function(e,h,j){function k(b){return b}function l(b){return decodeURIComponent(b.replace(m," "))}var m=/\+/g,d=e.cookie=function(b,c,a){if(c!==j){a=e.extend({},d.defaults,a);null===c&&(a.expires=-1);if("number"===typeof a.expires){var f=a.expires,g=a.expires=new Date;g.setDate(g.getDate()+f)}c=d.json?JSON.stringify(c):String(c);return h.cookie=[encodeURIComponent(b),"=",d.raw?c:encodeURIComponent(c),a.expires?"; expires="+a.expires.toUTCString():"",a.path?"; path="+a.path:"",a.domain?"; domain="+a.domain:"",a.secure?"; secure":""].join("")}c=d.raw?k:l;a=h.cookie.split("; ");f=0;for(g=a.length;f<g;f++){var i=a[f].split("=");if(c(i.shift())===b)return b=c(i.join("=")),d.json?JSON.parse(b):b}return null};d.defaults={};e.removeCookie=function(b,c){return null!==e.cookie(b)?(e.cookie(b,null,c),!0):!1}})(jQuery,document);
