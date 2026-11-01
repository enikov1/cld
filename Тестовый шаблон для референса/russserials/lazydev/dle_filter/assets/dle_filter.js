let getNavigation = function(doc) {
    let currentNode;
    let navigation = 'none';

    if (typeof doc === 'object') {
        let nodeIterator = document.createNodeIterator(
            doc,
            NodeFilter.SHOW_COMMENT
        );

        while (currentNode = nodeIterator.nextNode()) {
            if (currentNode.nodeType === 8 && currentNode.nodeValue === 'ENGINE_NAVIGATION' && currentNode.nextSibling.nodeValue !== 'ENGINE_NAVIGATION') {
                navigation = currentNode.nextSibling;
                break;
            }
        }
    }

    return navigation;
};

let navigation = getNavigation(document.body);
let filterNavigation = 'none';

$(function() {
    // Поиск по ENTER
    $('[data-dlefilter*=dle-filter] input, [data-dlefilter*=dle-filter] textarea').keypress(function(e) {
        if (e.which == 13) {
            e.preventDefault();
            filterAjax($(this).closest('form'), true);
        }
    });

    // Получаем ID контента
    let getId = function(elemTag) {
        dleFilter.id = (elemTag !== 'FORM' ? $(elemTag).closest('form[data-dlefilter]').data('dlefilter-id') : $(elemTag).data('dlefilter-id')) || 'dle-content';

        if (!dleFilter.original[dleFilter.id] && dleFilter.id != 'dle-content') {
            dleFilter.original[dleFilter.id] = $('#' + dleFilter.id).html();
        }
    }

    // Задаем данные на странице фильтра.
    let setFilterParam = function() {
        let filterParam = decodeURIComponent(location.href).split('/' + dleFilter.filterUrl + '/');
        if (!filterParam[1]) {
            return;
        }
        if (filterParam[1].slice(-1) === '/') {
            filterParam[1] = filterParam[1].slice(0, -1);
        }

        // Разбивка данных
        filterParam = filterParam[1].split('/');
        let arrayParam = [];
        for (let i = 0; i < filterParam.length; i++) {
            arrayParam[i] = filterParam[i].split('=');
            if (arrayParam[i][1]) {
                arrayParam[i][0] = arrayParam[i][0].replace(/\+/g, ' ');
                arrayParam[i][1] = arrayParam[i][1].replace(/\+/g, ' ');
            }
        }

        // Задаем для input text и textarea, так же для ionRange.Slider
        $('[data-dlefilter*=dle-filter] input[type="text"], [data-dlefilter*=dle-filter] textarea').each(function () {
            let nameElem = $(this).attr('name');
            if (nameElem !== undefined && nameElem.length > 0) {
                for (let i = 0; i < arrayParam.length; i++) {
                    if (nameElem === arrayParam[i][0]) {
                        if (nameElem.indexOf('r.') + 1) {
                            let slider = $(this).data('ionRangeSlider');

                            let sliderData = arrayParam[i][1].split(';');
                            if (slider.options.type == 'single') {
                                slider.update({
                                    from: sliderData[0]
                                });
                            } else if (slider.options.type == 'double') {
                                slider.update({
                                    from: sliderData[0],
                                    to: sliderData[1]
                                });
                            }
                        } else {
                            $(this).val(arrayParam[i][1]);
                        }
                    }
                }
            }
        });

        // Select
        $('[data-dlefilter*=dle-filter] select').each(function (index) {
            let nameElem = $(this).attr('name');
            if (nameElem !== undefined && nameElem.length > 0) {
                for (let i = 0; i < arrayParam.length; i++) {
                    if (nameElem === arrayParam[i][0]) {
                        let selectData = [];
                        if (nameElem.indexOf('c.') + 1) {
                            selectData[0] = arrayParam[i][1];
                        } else {
                            selectData = arrayParam[i][1].split(',');
                        }

                        $(this).find('option').each(function (s, n) {
                            if ($.inArray(n.value, selectData) >= 0) {
                                $(this).attr('selected', true);
                            }
                        });
                    }
                }

                if (dleFilter.jsSelect == 1) {
                    let getTail = $('.tail-select');
                    if (getTail.length > 0) {
                        tail.select('[data-dlefilter*=dle-filter] select[data-select-id="' + index + '"]').reload();
                    }
                }
            }
        });

        if (dleFilter.jsSelect == 2) {
            let getChosen = $('.chosen-results');
            if (getChosen.length > 0) {
                $('[data-dlefilter*=dle-filter] select').trigger('chosen:updated');
            }
        }

        if (dleFilter.jsSelect == 3) {
            $('[data-dlefilter*=dle-filter] select').niceSelect('update');
        }

        // Radio / Checkbox
        $('[data-dlefilter*=dle-filter] input[type="radio"], [data-dlefilter*=dle-filter] input[type="checkbox"]').each(function () {
            let nameElem = $(this).attr('name');
            if (nameElem !== undefined && nameElem.length > 0) {
                for (let i = 0; i < arrayParam.length; i++) {
                    if (nameElem === arrayParam[i][0]) {
                        let selectData = arrayParam[i][1].split(',');

                        $(this).each(function (s, n) {
                            if ($.inArray(n.value, selectData) >= 0) {
                                $(this).attr('checked', 'checked');
                            }
                        });
                    }
                }
            }
        });
    };

    // Очистка фильтра
    let filterClear = function() {
        dleFilter.reset = true;

        let formFilter = $(this).closest('form');
        let formId = $(formFilter).data('dlefilter');
        getId(this);

        if (dleFilter.ajaxUrl == 0) {
            document.title = dleFilter.title;
            history.pushState(null, dleFilter.title, dleFilter.path);
        }

        if (filterNavigation !== 'none' && dleFilter.id == 'dle-content' && dleFilter.navApart == 1) {
            $(getNavigation(document.body)).replaceWith(navigation);
        }

        if (dleFilter.path.indexOf('/' + dleFilter.filterUrl + '/') + 1) {
            $('#dle-content').html(dleFilter.content);
        } else {
            if (dleFilter.id == 'dle-content') {
                $('#dle-content').html(dleFilter.content);
            } else {
                $('#' + dleFilter.id).html(dleFilter.original[dleFilter.id]);
            }
        }

        if (dleFilter.lazy == 1 && typeof lazyLoadXT !== 'undefined' && typeof lazyLoadXT === 'function') {
            $('[data-src]').lazyLoadXT();
        } else if (dleFilter.lazy == 1 && typeof jQuery().lazyLoadXT !== 'undefined' && typeof jQuery().lazyLoadXT === 'function') {
            $('[data-src]').lazyLoadXT();
        }

        if ($('#dle-speedbar').length > 0) {
            $('#dle-speedbar').html(dleFilter.speedbar);
        }

        $(formFilter).find('input[type="text"]').each(function() {
            let nameElem = $(this).prop('name');
            if (nameElem.length > 0) {
                if (nameElem.indexOf('r.') + 1) {
                    let slider = $(this).data('ionRangeSlider');
                    if (slider.options.type == 'single') {
                        slider.update({
                            from: slider.options.min
                        });
                    } else if (slider.options.type == 'double') {
                        slider.update({
                            from: slider.options.min,
                            to: slider.options.max
                        });
                    }
                } else {
                    $(this).val('');
                }
                if ($(this).data('dlefilter-show') !== undefined) {
                    showAndHideFilter(this);
                }
            }
        });

        $(formFilter).find('select').each(function(index) {
            let nameElem = $(this).prop('name');
            if (nameElem.length > 0) {
                $(this).children('option').each(function() {
                    $(this).prop('selected', false);
                });

                if (dleFilter.jsSelect == 1) {
                    let getTail = $('.tail-select');
                    if (getTail.length > 0) {
                        tail.select('[data-dlefilter*="' + formId + '"] select[data-select-id="' + index + '"]').reload();
                    }
                }

                if ($(this).data('dlefilter-show') !== undefined) {
                    showAndHideFilter(this);
                }
            }
        });

        if (dleFilter.jsSelect == 2) {
            let getChosen = $('.chosen-results');
            if (getChosen.length > 0) {
                $('[data-dlefilter*="' + formId + '"] select').trigger('chosen:updated');
            }
        }

        if (dleFilter.jsSelect == 3) {
            $('[data-dlefilter*="' + formId + '"] select').niceSelect('update');
        }

        $(formFilter).find('input[type="radio"], input[type="checkbox"]').each(function() {
            let nameElem = $(this).prop('name');
            if (nameElem.length > 0) {
                $(this).prop('checked', false);
                if ($(this).data('dlefilter-show') !== undefined) {
                    showAndHideFilter(this);
                }
            }
        });

        dleFilter.reset = false;
    };

    // Данные фильтра
    let filterWork = function(data) {
        data = $.parseJSON(data);

        if (data.content == 'redirect') {
            dleFilter.ajax = 1;
        }

        if (dleFilter.ajax == 1) {
            window.location.href = data.url;
        } else {
            if (data.clean == dleFilter.defaultUrl) {
                filterClear();
            }  else {
                if ($('#dle-speedbar').length > 0) {
                    $('#dle-speedbar').html($('#dle-speedbar', data.speedbar).html());
                }

                let idContent = '';
                if (dleFilter.path.indexOf('/' + dleFilter.filterUrl + '/') + 1) {
                    $('#dle-content').html(data.content);
                    idContent = '#dle-content';
                } else {
                    $('#' + dleFilter.id).html(data.content);
                    idContent = '#' + dleFilter.id;
                }

                if (data.player && ($(idContent + " .dleplyrplayer").length || $(idContent + " .dleplyrplayer").length || $(idContent + " .dlevideoplayer").length)) {
                    if (data.player == 'plyr') {
                        let playersV = Array.from(document.querySelectorAll('.dleplyrplayer:not(.dlepl)')).map(player => new DLEPlayer(player));
                        playersV.forEach(function(instance, index) {
                            instance.on('play',function() {
                                playersV.forEach(function(instance1, index1) {
                                    if (instance != instance1) {
                                        instance1.pause();
                                    }
                                });
                            });
                        });
                    } else {
                        $('.dleaudioplayer:not(.alreadyLoaded)').addClass('alreadyLoaded').cleanaudioplayer();
                        $('.dlevideoplayer:not(.alreadyLoaded)').addClass('alreadyLoaded').cleanvideoplayer();
                    }
                }

                if (dleFilterId === 1) {
                    $('pre').removeAttr('class');
                    $('pre').each(function() {
                        if (!$(this).find('code').length) {
                            $(this).wrapInner('<code>');
                        }
                    });

                    hljs.highlightAll();
                } else if (dleFilterId === 0) {
                    $('pre code:not([class])').each(function(i, e) {hljs.highlightBlock(e, null)});
                }

                if (navigation !== 'none' && data.nav && dleFilter.id == 'dle-content' && dleFilter.navApart == 1) {
                    filterNavigation = data.nav;
                    navigation = getNavigation(document.body);
                    $(navigation).replaceWith(data.nav);
                } else if (!data.nav && dleFilter.navApart == 1) {
                    navigation = getNavigation(document.body);
                    navigation.remove();
                }

                if (dleFilter.lazy == 1 && typeof lazyLoadXT !== 'undefined' && typeof lazyLoadXT === 'function') {
                    $('[data-src]').lazyLoadXT();
                } else if (dleFilter.lazy == 1 && typeof jQuery().lazyLoadXT !== 'undefined' && typeof jQuery().lazyLoadXT === 'function') {
                    $('[data-src]').lazyLoadXT();
                }

                if (dleFilter.ajaxUrl == 0) {
                    history.pushState(null, data.title, data.clean);
                    document.title = data.title;
                }
            }
        }
    };

    // Собираем данные
    let filterAjax = function(elem, enter) {
        if (dleFilter.reset) {
            return;
        }

        $('[data-dlefilter*=dle-filter] input[name*="r."]').each(function () {
            let nameElem = $(this).attr('name');
            if (nameElem !== undefined && nameElem.length > 0) {
                let slider = $(this).data('ionRangeSlider');
                let sliderDefault = 0;

                if (slider.options.type == 'single') {
                    sliderDefault = slider.options.min;
                } else if (slider.options.type == 'double') {
                    sliderDefault = slider.options.min + ';' + slider.options.max;
                }

                if (sliderDefault == $(this).val()) {
                    $(this).val('');
                }
            }
        });

        enter = enter || false;
        let data, elemTag, elemType = '';

        if (enter === true) {
            data = $(elem).serialize();
        } else {
            elemTag = elem.target.tagName.toUpperCase();

            if (elem.target.type !== undefined) {
                elemType = elem.target.type.toUpperCase();
            }

            if (elemTag === 'TEXTAREA' || elemTag === 'INPUT' && (elemType === 'TEXT' || elemType === 'RESET')) {
                return;
            }

            data = elemTag !== 'FORM' ? $(this).closest('form').serialize() : $(this).serialize();
        }

        getId(elemTag);

        $.ajax({
            beforeSend: function() {
                if (dleFilter.hideLoading == 0) {
                    ShowLoading('');
                }
            },
            url: dle_root + 'engine/lazydev/dle_filter/ajax.php',
            type: 'POST',
            data: {
                data: data,
                url: dleFilter.path,
                dle_hash: dle_login_hash,
                dleFilterJSData
            },
            success: function(output) {
                if (output.error) {
                    DLEalert(output.text, dle_info);
                } else {
                    filterWork(output);
                }
            },
            error: function (output) {
                filterWork(output.responseText);
            }
        }).always(function() {
            if (dleFilter.hideLoading == 0) {
                HideLoading('');
            }
        });
    };

    // Сохраняем данные спидбара
    if ($('#dle-speedbar').length > 0) {
        dleFilter.speedbar = $('#dle-speedbar').html();
    }

    // Сохраняем данные контента
    if ($('#dle-content').length > 0) {
        dleFilter.content = $('#dle-content').html();
    }

    // Задаем callback для click / change в форме фильтра
    $('body').on('click', '[data-dlefilter=submit]', filterAjax);

    if (dleFilter.button == 0) {
        $('body').on('change', '[data-dlefilter*=dle-filter]', filterAjax);
    }

    // Callback для очистки фильтра
    $('body').on('click', '[data-dlefilter=reset]', filterClear);

    // Работа с датой в слайдере
    let dateToTS = function(date) {
        return date.valueOf();
    };

    let validateDate = function(date) {
        date = date.toString().split(',');
        if (Array.isArray(date) && date.length === 3) {
            date[0] = parseInt(date[0]);
            date[1] = parseInt(date[1]);
            date[2] = parseInt(date[2]);
            if (date[0] > 0 && (date[1] > 0 && date[1] <= 12) && (date[2] > 0 && date[2] <= 31)) {
                return date;
            }
        }

        return false;
    };

    // Метки в слайдере
    function convertToPercent(num, min, max) {
        return (num - min) / (max - min) * 100;
    }

    function addMark($slider, config, mark) {
        let html = '';
        let left = 0;
        let leftPercent = '';

        for (let i = 0; i < mark.length; i++) {
            let tempMark = mark[i].toString().split('|');

            left = convertToPercent(parseInt(tempMark[0]), config.min, config.max);
            leftPercent = left + '%';
            html += '<span class="sliderShowcaseMark" style="left: ' + leftPercent + '">' + tempMark[1] + '</span>';
        }

        $slider.append(html);
    }

    // Работа со слайдером
    $('[data-dlefilter*=dle-filter]').find('input[type="text"][name*="r."]').each(function() {
        let sliderVars = $(this).data('slider-config');
        let sliderType = $(this).data('slider-type');
        let sliderLang = $(this).data('slider-lang');
        let sliderMark = $(this).data('slider-mark');

        let date = '';

        if (sliderVars !== undefined && sliderVars.length > 0) {
            let sliderConfig = {};

            if (sliderVars.slice(-1) === ';') {
                sliderVars = sliderVars.slice(0, -1);
            }

            sliderVars = sliderVars.split(';');
            for (let i = 0; i < sliderVars.length; i++) {
                let attempt = sliderVars[i].split(':');
                attempt[0] = attempt[0].trim();
                switch (attempt[0]) {
                    case 'АвтоПолзунок':
                    case 'АвтоПовзунок':
                    case 'AutoSlider':
                        sliderConfig.slider = true;
                        break;
                    case 'Одиночный слайдер':
                    case 'Один слайдер':
                    case 'Single':
                        sliderConfig.type = 'single';
                        break;
                    case 'Двойной слайдер':
                    case 'Два слайдера':
                    case 'Double':
                        sliderConfig.type = 'double';
                        break;
                    case 'Минимальное значение':
                    case 'Мінімальне значення':
                    case 'Min':
                        if (attempt[1] !== undefined) {
                            if ($.isNumeric(attempt[1])) {
                                sliderConfig.min = attempt[1];
                            } else if (sliderType === 'date') {
                                date = validateDate(attempt[1]);
                                if (date !== false) {
                                    sliderConfig.min = dateToTS(new Date(date[0], date[1] - 1, date[2]));
                                }
                            }
                        }
                        break;
                    case 'Максимальное значение':
                    case 'Максимальне значення':
                    case 'Max':
                        if (attempt[1] !== undefined) {
                            if ($.isNumeric(attempt[1])) {
                                sliderConfig.max = attempt[1];
                            } else if (sliderType === 'date') {
                                date = validateDate(attempt[1]);
                                if (date !== false) {
                                    sliderConfig.max = dateToTS(new Date(date[0], date[1] - 1, date[2]));
                                }
                            }
                        }
                        break;
                    case 'Начало слайдера':
                    case 'Початок слайдера':
                    case 'Start':
                        if (attempt[1] !== undefined) {
                            if ($.isNumeric(attempt[1])) {
                                sliderConfig.from = attempt[1];
                            } else if (sliderType === 'date') {
                                date = validateDate(attempt[1]);
                                if (date !== false) {
                                    sliderConfig.from = dateToTS(new Date(date[0], date[1] - 1, date[2]));
                                }
                            }
                        }
                        break;
                    case 'Конец слайдера':
                    case 'Кінець слайдера':
                    case 'End':
                        if (attempt[1] !== undefined) {
                            if ($.isNumeric(attempt[1])) {
                                sliderConfig.to = attempt[1];
                            } else if (sliderType === 'date') {
                                date = validateDate(attempt[1]);
                                if (date !== false) {
                                    sliderConfig.to = dateToTS(new Date(date[0], date[1] - 1, date[2]));
                                }
                            }
                        }
                        break;
                    case 'Шаг':
                    case 'Крок':
                    case 'Step':
                        if (attempt[1] !== undefined && $.isNumeric(attempt[1])) {
                            sliderConfig.step = attempt[1];
                        }
                        break;
                    case 'Шаблон':
                    case 'Tpl':
                        if (attempt[1] !== undefined && attempt[1] !== '') {
                            sliderConfig.skin = attempt[1];
                        }
                        break;
                    case 'Префикс':
                    case 'Префікс':
                    case 'Prefix':
                        if (attempt[1] !== undefined && attempt[1] !== '') {
                            sliderConfig.prefix = attempt[1];
                        }
                        break;
                    case 'Постфикс':
                    case 'Постфікс':
                    case 'Postfix':
                        if (attempt[1] !== undefined && attempt[1] !== '') {
                            sliderConfig.postfix = attempt[1];
                        }
                        break;
                    case 'Сетка':
                    case 'Сітка':
                    case 'Grid':
                        sliderConfig.grid = true;
                        break;
                    case 'Красивые числа':
                    case 'Красиві числа':
                    case 'Numbers':
                        sliderConfig.prettify_enabled = true;
                        break;
                    case 'Скрыть MinMax':
                    case 'Приховати MinMax':
                    case 'Hide MinMax':
                        sliderConfig.hide_min_max = true;
                        break;
                    case 'Скрыть FromTo':
                    case 'Приховати FromTo':
                    case 'Hide FromTo':
                        sliderConfig.hide_from_to = true;
                        break;
                }
            }

            if (sliderConfig.from === undefined) {
                sliderConfig.from = sliderConfig.min;
            }

            if (sliderConfig.to === undefined) {
                sliderConfig.to = sliderConfig.max;
            }

            if (sliderMark) {
                let mark = sliderMark.toString().split(',');

                sliderConfig.onStart = function (data) {
                    addMark(data.slider, sliderConfig, mark);
                }
            }

            if (sliderType === 'date') {
                sliderConfig.prettify = function(ts) {
                    let d = new Date(ts);

                    return d.toLocaleDateString(sliderLang, {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                };
            }

            if (!sliderConfig.prettify_enabled && sliderType !== 'date') {
                sliderConfig.prettify_enabled = false;
            }

            if (sliderConfig.slider) {
                sliderConfig.onFinish = function (data) {
                    filterAjax($('[name="' + data.input[0].name + '"]').closest('form'), true);
                };
            }

            $(this).ionRangeSlider(sliderConfig);
        }
    });

    // Работа с Tail.Select
    if (dleFilter.jsSelect == 1) {
        $('[data-dlefilter*=dle-filter]').find('select').each(function(index) {
            $(this).attr('data-select-id', index);
            let selectVars = $(this).data('select-config');
            let selectConfig = {};
            if (selectVars !== undefined && selectVars.length > 0) {
                if (selectVars.slice(-1) == ';') {
                    selectVars = selectVars.slice(0, -1);
                }
                selectVars = selectVars.split(';');
                for (let i = 0; i < selectVars.length; i++) {
                    let attempt = selectVars[i].split(':');
                    switch (attempt[0]) {
                        case 'Поиск':
                        case 'Пошук':
                        case 'Search':
                            selectConfig.search = true;
                            break;
                        case 'Скрыть выбранные':
                        case 'Приховати обрані':
                        case 'Hide':
                            selectConfig.hideSelected = true;
                            break;
                        case 'Максимум выбрать':
                        case 'Максимум вибрати':
                        case 'Max':
                            if (attempt[1] !== undefined && $.isNumeric(attempt[1])) {
                                selectConfig.multiLimit = attempt[1];
                            }
                            break;
                        case 'Показать количество выбранных':
                        case 'Показувати кількість вибраних':
                        case 'Num':
                            if (!selectConfig.multiContainer) {
                                selectConfig.multiShowCount = true;
                            }
                            break;
                        case 'Вывод выбранных':
                        case 'Виведення обраних':
                        case 'Show':
                            selectConfig.multiContainer = true;
                            if (selectConfig.multiShowCount) {
                                selectConfig.multiShowCount = false;
                            }
                            break;
                        case 'Подсказка':
                        case 'Підказка':
                        case 'Prompt':
                            if (attempt[1] !== undefined && attempt[1] !== '') {
                                selectConfig.placeholder = attempt[1];
                            }
                            break;
                        case 'Язык':
                        case 'Мова':
                        case 'Lang':
                            if (attempt[1] === undefined || attempt[1].trim() == '') {
                                attempt[1] = 'ru';
                            }

                            selectConfig.locale = attempt[1];
                            break;
                        case 'Сортировать':
                        case 'Сортувати':
                        case 'Sort':
                            if (attempt[1] !== undefined && (attempt[1] === 'ASC' || attempt[1] === 'DESC')) {
                                selectConfig.sortItems = attempt[1];
                            }
                            break;
                    }
                }
            }

            if (selectConfig.locale == undefined) {
                selectConfig.locale = 'ru';
            }

            selectConfig.deselect = true;

            tail.select('[data-dlefilter*=dle-filter] select[data-select-id="' + index + '"]', selectConfig).on('change', function(item, state) {
                let name = item.option.parentElement.tagName.toUpperCase() === 'OPTGROUP' ? item.option.parentElement.parentElement.name : item.option.parentElement.name;

                if (state === 'unselect' && !$('[name="' + name + '"] option:selected')) {
                    $('[name="' + name + '"]').val('');
                }

                if (dleFilter.button == 0) {
                    $('[name="' + name + '"]').trigger('change');
                }
            });
        });
    }

    // Автоматически задаем chosen
    if (dleFilter.jsSelect == 2) {
        $('[data-dlefilter*=dle-filter] select').chosen();
    }

    // Автоматически задаем niceSelect
    if (dleFilter.jsSelect == 3) {
        $('[data-dlefilter*=dle-filter] select').niceSelect();
    }

    // AJAX навигация
    let ajaxNavigation = function (urlPage, pathPage) {
        if (urlPage !== undefined) {
            $.ajax({
                url: urlPage,
                beforeSend: function() {
                    if (dleFilter.hideLoading == 0) {
                        ShowLoading('');
                    }
                },
                success: function(output) {
                    if ($('#dle-speedbar').length > 0) {
                        $('#dle-speedbar').html($('#dle-speedbar', output).html());
                    }

                    if (navigation !== 'none' && dleFilter.navApart == 1) {
                        let parser = new DOMParser();
                        let el = parser.parseFromString(output, 'text/html');
                        let nav = getNavigation(el);
                        let navNow = getNavigation(document.body);

                        filterNavigation = nav;
                        $(navNow).replaceWith(nav);
                    }

                    if (window.location.href.toString().indexOf(pathPage) + 1) {
                        $('#dle-content').html($('#dle-content', output).html());
                    } else {
                        $('#' + dleFilter.id).html($('#dle-content', output).html());
                    }

                    if (dleFilter.lazy == 1 && typeof lazyLoadXT !== 'undefined' && typeof lazyLoadXT === 'function') {
                        $('[data-src]').lazyLoadXT();
                    } else if (dleFilter.lazy == 1 && typeof jQuery().lazyLoadXT !== 'undefined' && typeof jQuery().lazyLoadXT === 'function') {
                        $('[data-src]').lazyLoadXT();
                    }

                    if (dleFilter.ajaxUrl == 0) {
                        let titlePage = $(output).filter('title').text();
                        window.history.pushState(titlePage, '', urlPage);
                        if (titlePage != '') {
                            document.title = $(output).filter('title').text();
                        }
                    }

                    if (dleFilter.ajaxAnim == 0) {
                        $('html, body').animate({
                            scrollTop: $('#' + dleFilter.id).offset().top
                        }, 400);
                    }
                },
                error: function(output) {
                    if (dle_group === 1) {
                        console.log(output.responseText, dle_info);
                    }
                }
            }).always(function() {
                if (dleFilter.hideLoading == 0) {
                    HideLoading('');
                }
            });
        }
    }

    // Ajax навигация для готовых страниц
    if (dleFilter.ajaxPage == 1 && urlFilter != 0) {
        $('body').on('click', 'a[href*="' + urlFilter + '"]', function(e) {
            e.preventDefault();
            dleFilter.id = 'dle-content';
            let urlPage = $(this).attr('href');
            ajaxNavigation(urlPage, urlFilter);

            return false;
        });
    }

    // AJAX навигация для страниц фильтра
    if (dleFilter.ajaxNav == 1) {
        $('body').on('click', 'a[href*="/' + dleFilter.filterUrl + '/"]', function(e) {
            e.preventDefault();
            getId(this);
            let urlPage = $(this).attr('href');
            ajaxNavigation(urlPage, '/' + dleFilter.filterUrl + '/');

            return false;
        });
    }

    // Расхождение массива
    Array.prototype.diff = function(a) {
        return this.filter(function(i) {return a.indexOf(i) < 0;});
    };

    let showList = {};
    let hideList = {};
    let whatHide = {};

    // Скрытие и показ блоков
    let showAndHideFilter = function(e) {
        let elemTag = $(e).prop('tagName').toUpperCase();
        let elemType = $(e).prop('type').toUpperCase();

        let showNode = $(e).data('dlefilter-show');
        let objShow = {};
        if (showNode.slice(-1) == ';') {
            showNode = showNode.slice(0, -1);
        }

        showNode = showNode.split(';');
        $.each(showNode, function(index, value) {
            let tempObj = value.split(':');
            if (tempObj[0] && tempObj[1]) {
                objShow[tempObj[0]] = tempObj[1].split(',');
            }
        });

        if (elemTag === 'SELECT') {
            $(e).find('option').each(function(p, elem) {
                $.each(objShow, function(index, value) {
                    if ($(elem).val().toString().length > 0 && $(elem).prop('selected') && $.inArray($(elem).val(), value) >= 0) {
                        if (dleFilter.jsSelect == 1) {
                            showList[index] = '.dle-filter-select-' + index;
                        } else {
                            showList[index] = '[data-dlefilter-hide="' + index + '"]';
                        }
                    } else {
                        if (dleFilter.jsSelect == 1) {
                            hideList[index] = '.dle-filter-select-' + index;
                        } else {
                            hideList[index] = '[data-dlefilter-hide="' + index + '"]';
                        }
                    }
                });
            });
        } else if (elemTag === 'INPUT') {
            $.each(objShow, function(index, value) {
                if (elemType === 'TEXT') {
                    if ($.inArray($(e).val(), value) >= 0) {
                        showList[index] = '[data-dlefilter-hide="' + index + '"]';
                    } else {
                        hideList[index] = '[data-dlefilter-hide="' + index + '"]';
                    }
                } else if (elemType === 'RADIO' || elemType === 'CHECKBOX') {
                    if ($(e).prop('checked') && $.inArray($(e).val(), value) >= 0) {
                        showList[index] = '[data-dlefilter-hide="' + index + '"]';
                    } else {
                        hideList[index] = '[data-dlefilter-hide="' + index + '"]';
                    }
                }
            });
        } else if (elemTag === 'TEXTAREA') {
            $.each(objShow, function(index, value) {
                if ($.inArray($(e).val(), value) >= 0) {
                    showList[index] = '[data-dlefilter-hide="' + index + '"]';
                } else {
                    hideList[index] = '[data-dlefilter-hide="' + index + '"]';
                }
            });
        }

        let temp_array1 = Object.values(hideList);
        let temp_array2 = Object.values(showList);

        whatHide = temp_array1.filter(x => !temp_array2.includes(x))
        if (whatHide) {
            $.each(whatHide, function(index, value) {
                $(value).find('input[type="text"]').each(function() {
                    let nameElem = $(this).prop('name');
                    if ($(this).data('dlefilter-show') !== undefined) {
                        showAndHideFilter(this);
                    }
                    if (nameElem.length > 0) {
                        if (nameElem.indexOf('r.') + 1) {
                            let slider = $(this).data('ionRangeSlider');
							if (slider.options.type == 'single') {
                                slider.update({
									from: slider.options.min
								});

                            } else if (slider.options.type == 'double') {
                                slider.update({
									from: slider.options.min,
									to: slider.options.max
								});
                            }
                        } else {
                            $(this).val('');
                        }
                    }
                });

                $(value).find('select').each(function(index) {
                    let nameElem = $(this).prop('name');
                    if (nameElem.length > 0) {
                        if ($(this).data('dlefilter-show') !== undefined) {
                            showAndHideFilter(this);
                        }

                        $(this).children('option').each(function() {
                            $(this).attr('selected', false);
                        });

                        if (dleFilter.jsSelect == 1) {
                            let getTail = $('.tail-select');
                            if (getTail.length > 0) {
                                tail.select('[data-dlefilter*=dle-filter] select[data-select-id="' + index + '"]').reload();
                            }
                        }
                    }
                });

                if (dleFilter.jsSelect == 2) {
                    let getChosen = $('.chosen-results');
                    if (getChosen.length > 0) {
                        $('[data-dlefilter*=dle-filter] select').trigger('chosen:updated');
                    }
                }

                if (dleFilter.jsSelect == 3) {
                    $('[data-dlefilter*=dle-filter] select').niceSelect('update');
                }

                $(value).find('input[type="radio"], input[type="checkbox"]').each(function() {
                    let nameElem = $(this).prop('name');
                    if (nameElem.length > 0) {
                        if ($(this).data('dlefilter-show') !== undefined) {
                            showAndHideFilter(this);
                        }
                        $(this).attr('checked', false);
                    }
                });
            });
        }
    };

    // Если текущая страница фильтр - задаем параметры
    if (dleFilter.path.indexOf('/' + dleFilter.filterUrl + '/') + 1) {
        setFilterParam();
    }

    // Скрываем скрываемые блоки
    $('[data-dlefilter-hide]').hide();

    // Показываем блоки если они нужны
    $('body').find('[data-dlefilter-show]').each(function(p, e) {
        hideList = {};
        whatHide = {};
        showList = {};

        showAndHideFilter(e);

        if (whatHide) {
            $(whatHide.join(', ')).hide();
        }

        if (showList) {
            $(Object.values(showList).join(', ')).show();
        }
    });

    // Показываем блоки если они нужны если изменились данные фильтра
    $('body').on('change', '[data-dlefilter-show]', function(e) {
        hideList = {};
        whatHide = {};
        showList = {};

        $('body').find('[data-dlefilter-show]').each(function(p, z) {
            showAndHideFilter(z);
        });

        if (whatHide) {
            $(whatHide.join(', ')).hide();
        }

        if (showList) {
            $(Object.values(showList).join(', ')).show();
        }
    });

});

class XFilter {
	constructor() {
		this.searchController = null; // Для отмены предыдущих запросов
		this.xfilterContent = document.querySelector('.xfilter_content');
		this.selectedContainer = document.querySelector('.js-xf-selected') || this.createSelectedContainer();

		this.init();

		this.originalCheckboxStates = new Map(); // Для хранения исходных состояний checkbox
		this.initSubmitResetHandlers();

		this.storageKey = 'xfilter_final_states';
        this.pendingRestorations = new Map(); // Для отслеживания ожидающих восстановлений
        
        // Инициализация с улучшенным восстановлением
        this.initStateRestoration();
		
		
	}

	// Новая система инициализации восстановления
	initStateRestoration() {
		// Загружаем сохраненные состояния
		const savedStates = localStorage.getItem(this.storageKey);
		if (!savedStates) return;

		try {
			this.savedStates = JSON.parse(savedStates);

			// 1. Восстановление для уже существующих фильтров
			this.restoreExistingFiltersImmediately();

			// 2. Наблюдатель для новых фильтров
			this.setupEnhancedObserver();

			// 3. Периодическая проверка для сложных случаев
			this.startStateVerification();

			this.triggerSubmit();

		} catch (e) {
			console.error('State restoration error:', e);
		}
	}

	// 1. Мгновенное восстановление готовых фильтров
	restoreExistingFiltersImmediately() {
		Object.entries(this.savedStates).forEach(([popupName, checkboxes]) => {
			const popup = document.querySelector(`.js-xf-filter[data-popup-toggler="${popupName}"]`);
			if (popup) {
				this.applyStatesToPopup(popup, checkboxes);
			} else {
				// Сохраняем для последующего восстановления
				this.pendingRestorations.set(popupName, checkboxes);
			}
		});
	}

	// 2. Улучшенный наблюдатель DOM
	setupEnhancedObserver() {
		const observer = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				if (!mutation.addedNodes) return;

				Array.from(mutation.addedNodes).forEach((node) => {
					if (!(node instanceof HTMLElement)) return;

					// Проверяем обычные попапы
					if (node.classList.contains('js-xf-filter')) {
						this.handleNewPopup(node);
					}

					// Проверяем динамически загружаемые контейнеры
					const childPopups = node.querySelectorAll('.js-xf-filter');
					if (childPopups.length > 0) {
						childPopups.forEach(popup => {
							this.handleNewPopup(popup);
						});
					}
				});
			});
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	}

	// 3. Периодическая проверка для сложных случаев
	startStateVerification() {
		const verificationInterval = setInterval(() => {
			if (this.pendingRestorations.size === 0) {
				clearInterval(verificationInterval);
				return;
			}

			this.pendingRestorations.forEach((checkboxes, popupName) => {
				const popup = document.querySelector(`.js-xf-filter[data-popup-toggler="${popupName}"]`);
				if (popup) {
					this.applyStatesToPopup(popup, checkboxes);
					this.pendingRestorations.delete(popupName);
				}
			});
		}, 500);
	}

	// Обработка нового попапа
	handleNewPopup(popup) {
		const popupName = popup.dataset.popupToggler;
		if (!popupName || !this.savedStates[popupName]) return;

		// Применяем состояния с проверкой готовности
		this.applyStatesWithReadyCheck(popup, this.savedStates[popupName]);
	}

	// Применение состояний с проверкой готовности DOM
	applyStatesWithReadyCheck(popup, checkboxes, attempt = 0) {
		const maxAttempts = 5;
		const checkboxesElements = popup.querySelectorAll('input[type="checkbox"]');

		if (checkboxesElements.length === 0 && attempt < maxAttempts) {
			setTimeout(() => {
				this.applyStatesWithReadyCheck(popup, checkboxes, attempt + 1);
			}, 200 * (attempt + 1));
			return;
		}

		if (checkboxesElements.length > 0) {
			this.applyStatesToPopup(popup, checkboxes);
		} else {
			console.warn(`Failed to restore popup ${popup.dataset.popupToggler} after ${maxAttempts} attempts`);
			this.pendingRestorations.set(popup.dataset.popupToggler, checkboxes);
		}
	}

	// Основной метод применения состояний
	applyStatesToPopup(popup, checkboxes) {
		try {
			if (!popup || !checkboxes) return;

			Object.entries(checkboxes).forEach(([value, checked]) => {
				try {
					const checkbox = popup.querySelector(`input[type="checkbox"][value="${CSS.escape(value)}"]`);
					if (checkbox) {
						// Безопасное присвоение значения
						if (checkbox.checked !== checked) {
							checkbox.checked = checked;

							// Асинхронный триггер события
							requestAnimationFrame(() => {
								checkbox.dispatchEvent(new Event('change', {
									bubbles: true,
									cancelable: true
								}));
							});
						}
					}
				} catch (e) {
					console.error(`Error processing checkbox ${value}:`, e);
				}
			});

			// Обновление UI с задержкой
			requestAnimationFrame(() => {
				this.updateClearButton(popup);
				this.updateSelectedItems();
			});

		} catch (error) {
			console.error('Error in applyStatesToPopup:', error);
		}
	}

	// Добавляем новые методы для работы с хранилищем
	saveAllStates() {
		const states = {};

		document.querySelectorAll('.js-xf-filter').forEach(popup => {
			const popupName = popup.dataset.popupToggler;
			if (!popupName) return;

			states[popupName] = {};

			popup.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
				states[popupName][checkbox.value] = checkbox.checked;
			});
		});

		localStorage.setItem(this.storageKey, JSON.stringify(states));
	}

	async restoreAllStates() {
		const savedStates = localStorage.getItem(this.storageKey);
		if (!savedStates) return;

		try {
			this.isRestoring = true;
			const states = JSON.parse(savedStates);

			// Восстановление для уже существующих фильтров
			await this.restoreExistingFilters(states);

			// Наблюдатель для динамически загружаемых фильтров
			this.setupMutationObserver(states);

		} catch (e) {
			console.error('Error restoring filter states:', e);
		} finally {
			setTimeout(() => {
				this.isRestoring = false;
				this.updateSelectedItems();
			}, 300);
		}
	}

	// Восстановление для уже загруженных фильтров
	async restoreExistingFilters(states) {
		const promises = [];

		Object.entries(states).forEach(([popupName, checkboxes]) => {
			const popup = document.querySelector(`.js-xf-filter[data-popup-toggler="${popupName}"]`);
			if (popup) {
				promises.push(this.restorePopupWithRetry(popup, checkboxes));
			}
		});

		await Promise.all(promises);
	}

	// Попытка восстановления с повторными попытками
	async restorePopupWithRetry(popup, checkboxes, attempt = 0) {
		try {
			const checkboxesElements = popup.querySelectorAll('input[type="checkbox"]');

			if (checkboxesElements.length === 0 && attempt < 3) {
				await new Promise(resolve => setTimeout(resolve, 200 * (attempt + 1)));
				return this.restorePopupWithRetry(popup, checkboxes, attempt + 1);
			}

			Object.entries(checkboxes).forEach(([value, checked]) => {
				const checkbox = popup.querySelector(`input[type="checkbox"][value="${value}"]`);
				if (checkbox) {
					checkbox.checked = checked;
					// Асинхронный триггер события для избежания рекурсии
					setTimeout(() => checkbox.dispatchEvent(new Event('change')), 10);
				}
			});

			this.updateClearButton(popup);
		} catch (e) {
			console.error(`Error restoring popup (attempt ${attempt}):`, e);
		}
	}

	// Наблюдатель за изменениями DOM для динамических фильтров
	setupMutationObserver(states) {
		const observer = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				mutation.addedNodes.forEach((node) => {
					if (node.nodeType === 1 && node.classList?.contains('js-xf-filter')) {
						const popupName = node.dataset.popupToggler;
						if (popupName && states[popupName]) {
							this.restorePopupWithRetry(node, states[popupName]);
						}
					}
				});
			});
		});

		observer.observe(this.xfilterContent, {
			childList: true,
			subtree: true
		});
	}

	restorePopupStates(popup, checkboxes) {
		Object.entries(checkboxes).forEach(([value, checked]) => {
			const checkbox = popup.querySelector(`input[type="checkbox"][value="${value}"]`);
			if (checkbox) {
				checkbox.checked = checked;
				// Триггерим событие change для обновления UI
				checkbox.dispatchEvent(new Event('change'));
			}
		});

		this.updateClearButton(popup);
		this.updateSelectedItems();
	}

	initSubmitResetHandlers() {
		// Триггер поиска при клике на "Применить"
		document.addEventListener('click', (e) => {
			if (e.target.closest('.xfilter-button--teal')) {
				this.triggerSubmit();
			}
		});

		// Триггер поиска при клике на выбранный элемент
		document.addEventListener('click', (e) => {
			if (e.target.closest('.xf-selected-item')) {
				this.triggerSubmit();
			}
		});

		// Сохраняем состояния checkbox перед очисткой
		document.addEventListener('click', (e) => {
			if (e.target.closest('.js-xf-clear')) {
				this.saveCheckboxStates(e.target.closest('.js-xf-filter'));
			}
		});

		// Восстанавливаем состояния checkbox при отмене
		document.addEventListener('click', (e) => {
			if (e.target.closest('.js-xf-close-null')) {
				this.restoreCheckboxStates(e.target.closest('.js-xf-filter'));
			}
		});
	}

	triggerSubmit() {
		const submitBtn = document.querySelector('.xfilted_submit[data-dlefilter="submit"]');
		if (submitBtn) {
			// Сохраняем состояния перед отправкой
			this.saveAllStates();
			submitBtn.click();
		}
	}

	// Добавляем метод для обработки динамических фильтров
	handleDynamicFilterLoad(popup) {
		if (!popup || !this.savedStates) return;

		const popupName = popup.dataset.popupToggler;
		if (!popupName || !this.savedStates[popupName]) return;

		// Ждем пока все checkbox появятся в DOM
		const checkInterval = setInterval(() => {
			const checkboxes = popup.querySelectorAll('input[type="checkbox"]');
			if (checkboxes.length > 0) {
				clearInterval(checkInterval);
				this.restorePopupStates(popup, this.savedStates[popupName]);
			}
		}, 100);
	}

	triggerReset() {
		this.isRestoring = true;
		const resetBtn = document.querySelector('.xfilted_reset[data-dlefilter="reset"]');
		if (resetBtn) {
			resetBtn.click();
			localStorage.removeItem(this.storageKey);
			this.selectedContainer.innerHTML = '';
		}
		setTimeout(() => this.isRestoring = false, 500);
	}

	init() {
		this.initEventListeners();
		this.initStaticPopups();
		this.updateSelectedItems();
	}

	createSelectedContainer() {
		const container = document.createElement('div');
		container.className = 'js-xf-selected xfilter-selected';
		document.querySelector('.xfilter_wrapper').prepend(container);
		return container;
	}

	saveCheckboxStates(popup) {
		if (!popup) return;

		const checkboxes = popup.querySelectorAll('input[type="checkbox"]');
		this.originalCheckboxStates = new Map();

		checkboxes.forEach(checkbox => {
			this.originalCheckboxStates.set(checkbox.value, checkbox.checked);
		});
	}

	restoreCheckboxStates(popup) {
		if (!popup) return;

		// Восстанавливаем состояния чекбоксов
		const checkboxes = popup.querySelectorAll('input[type="checkbox"]');
		checkboxes.forEach(checkbox => {
			const originalState = this.originalCheckboxStates.get(checkbox.value);
			if (originalState !== undefined) {
				checkbox.checked = originalState;
				checkbox.dispatchEvent(new Event('change'));
			}
		});

		// Обновляем кнопку очистки
		this.updateClearButton(popup);

		// Обновляем список выбранных элементов
		this.updateSelectedItems();
	}

	// Улучшенный дебаунс с возможностью отмены
	debounce(func, wait, immediate = false) {
		let timeout;
		return function () {
			const context = this;
			const args = arguments;

			const later = function () {
				timeout = null;
				if (!immediate) func.apply(context, args);
			};

			const callNow = immediate && !timeout;
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);

			if (callNow) func.apply(context, args);
		};
	}

	initEventListeners() {
		document.addEventListener('click', (e) => this.handleFilterButtonClick(e));
		document.addEventListener('click', (e) => this.handleOutsideClick(e));
	}

	initStaticPopups() {
		document.querySelectorAll('.js-xf-filter').forEach(popup => {
			this.initPopupHandlers(popup);
		});
	}

	handleFilterButtonClick(e) {
		const filterBtn = e.target.closest('.js-xf-filter-toggle');
		if (!filterBtn) return;

		e.preventDefault();
		e.stopPropagation();

		const popupName = filterBtn.dataset.popupFilter;
		const currentPopup = document.querySelector(`.js-xf-filter[data-popup-toggler="${popupName}"]`);
		const title = filterBtn.getAttribute('title');
		const filterLoad = filterBtn.dataset.filterLoad;

		// Проверяем, был ли клик по уже активной кнопке
		const isSameActiveBtn = filterBtn.classList.contains('is-active') &&
			filterBtn.classList.contains('is-open');

		// Управление классами кнопок
		document.querySelectorAll('.js-xf-filter-toggle').forEach(btn => {
			btn.classList.remove('is-active', 'is-open');
		});

		// Если клик по той же активной кнопке - закрываем всё
		if (isSameActiveBtn) {
			// Закрытие всех попапов
			document.querySelectorAll('.js-xf-filter').forEach(popup => {
				popup.style.display = 'none';
			});
			return;
		}

		// Иначе делаем кнопку активной
		filterBtn.classList.add('is-active', 'is-open');

		// Закрытие всех других попапов
		document.querySelectorAll('.js-xf-filter').forEach(popup => {
			popup.style.display = 'none';
		});

		// Открытие текущего попапа
		if (currentPopup) {
			currentPopup.style.display = 'block';
			this.positionPopup(currentPopup);
			return;
		}

		// Проверка существующего попапа
		const existingPopup = document.querySelector(`.js-xf-filter[data-popup-toggler="${popupName}"]`);

		if (existingPopup) {
			existingPopup.style.display = 'block';
			this.positionPopup(existingPopup);
			this.initPopupHandlers(existingPopup);
		} else if (filterLoad) {
			this.loadFilterPopup(popupName, title, filterLoad);
		}
	}

	async loadFilterPopup(popupName, title, filterLoad) {
		try {
			const formData = new FormData();
			formData.append('query', filterLoad);
			formData.append('user_hash', window.dle_login_hash || '');

			const response = await fetch('/engine/ajax/controller.php?mod=load_xfvalue', {
				method: 'POST',
				body: formData
			});

			if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

			const data = await response.text();
			this.createPopupElement(popupName, title, data);
		} catch (error) {
			console.error('Error loading filter:', error);
		}
	}

	createPopupElement(popupName, title, content) {
		try {
			const popupDiv = document.createElement('div');
			popupDiv.className = 'js-xf-filter xfilter-popup';
			popupDiv.dataset.popupToggler = popupName;
			popupDiv.style.display = 'block';

			const searchClass = ['actors', 'directors'].includes(popupName) ?
				(popupName === 'actors' ? 'js-xfsearch-actor' : 'js-xfsearch-director') : '';

			popupDiv.innerHTML = `
                <div class="xfilter-popup__heading">
                    <div class="xfilter-popup__title">${title}</div>
                    <input type="text" class="js-xf-search xfilter-popup__search ${searchClass} focus" placeholder="поиск...">
                    <button class="js-xf-close xfilter-popup__close" title="Закрыть"></button>
                </div>
                <div class="js-xf-options xfilter-popup__content xsort__scrollable">${content}</div>
                <div class="xfilter-popup__actions">
                    <button type="button" class="js-xf-close xfilter-button xfilter-button--teal color-btn">Применить</button>
                    <button type="button" class="js-xf-close-null xfilter-button color-btn">Отмена</button>
                    <button type="button" class="js-xf-clear xfilter-button xfilter-button--warn" style="display: none;">Очистить (<span class="js-xf-clear-count">0</span>)</button>
                </div>
            `;

			this.xfilterContent.appendChild(popupDiv);
			this.positionPopup(popupDiv);
			this.initPopupHandlers(popupDiv);

			// Гарантированное восстановление с обработкой ошибок
			if (this.savedStates && this.savedStates[popupName]) {
				setTimeout(() => {
					try {
						this.applyStatesWithReadyCheck(popupDiv, this.savedStates[popupName]);
					} catch (e) {
						console.error('Error restoring popup state:', e);
					}
				}, 0);
			}

			return popupDiv;
		} catch (error) {
			console.error('Error creating popup element:', error);
			return null;
		}
	}

	positionPopup(popup) {
		const activeBtn = document.querySelector('.js-xf-filter-toggle.is-active');
		if (!activeBtn) return;

		const btnRect = activeBtn.getBoundingClientRect();
		const contentRect = this.xfilterContent.getBoundingClientRect();

		const viewportWidth = window.innerWidth;
		const viewportHeight = window.innerHeight;
		const mobileBreakpoint = 768; // Брейкпоинт для мобильных устройств

		if (viewportWidth > mobileBreakpoint) {
			popup.style.position = 'absolute';
			popup.style.left = `${btnRect.left - contentRect.left}px`;
			popup.style.top = `${btnRect.bottom - contentRect.top}px`;
			popup.style.zIndex = '1000';
		} else {
			popup.style.position = 'fixed';
			popup.style.left = '10px';
			popup.style.right = '10px';
			popup.style.bottom = '10px';
			popup.style.top = 'auto';
			popup.style.maxHeight = '70vh';
			popup.style.overflowY = 'auto';
			popup.style.transform = 'none';
			popup.style.zIndex = '1000';

			// Анимация появления
			popup.style.opacity = '0';
			popup.style.transition = 'opacity 0.2s ease';
			setTimeout(() => {
				popup.style.opacity = '1';
			}, 10);
		}

		// Добавляем обработчик ресайза
		const resizeHandler = () => {
			if (popup.style.display === 'block') {
				this.positionPopup(popup);
			}
		};

		// Удаляем старый обработчик если был
		if (popup._resizeHandler) {
			window.removeEventListener('resize', popup._resizeHandler);
		}

		popup._resizeHandler = resizeHandler;
		window.addEventListener('resize', resizeHandler);
		
	}

	initPopupHandlers(popup) {
		// Обработчики закрытия
		popup.querySelector('.js-xf-close').addEventListener('click', () => {
			popup.style.display = 'none';
			this.triggerSubmit(); // Добавляем триггер поиска
		});

		popup.querySelector('.js-xf-close-null').addEventListener('click', () => {
			// Сбрасываем все чекбоксы в попапе
			popup.querySelectorAll('input[type="checkbox"]').forEach(cb => {
				cb.checked = false;
			});

			// Обновляем кнопку очистки
			this.updateClearButton(popup);

			// Скрываем попап
			popup.style.display = 'none';

			// Полностью очищаем выбранные элементы
			this.selectedContainer.innerHTML = '';

			// Триггерим сброс фильтров
			this.triggerReset();
		});

		// Очистка выбранных значений
		const clearBtn = popup.querySelector('.js-xf-clear');
		if (clearBtn) {
			clearBtn.addEventListener('click', (e) => {
				e.stopPropagation();
				this.saveCheckboxStates(popup);
				popup.querySelectorAll('input[type="checkbox"]').forEach(cb => {
					cb.checked = false;
					cb.dispatchEvent(new Event('change'));
				});
				this.updateClearButton(popup);
				this.updateSelectedItems();
			});
		}

		// Поиск с улучшенным дебаунсом
		const searchInput = popup.querySelector('.js-xf-search');
		if (searchInput) {
			const debouncedSearch = this.debounce(() => {
				this.handleSearch(searchInput, popup);
			}, 500); // Увеличиваем задержку до 500 мс

			searchInput.addEventListener('input', debouncedSearch);
		}

		// Обновление кнопки очистки при изменении чекбоксов
		popup.querySelectorAll('input[type="checkbox"]').forEach(cb => {
			cb.addEventListener('change', () => {
				this.updateClearButton(popup);
				this.updateSelectedItems();
			});
		});

		// Обработчик кнопки фильтра (переключение видимости)
		document.querySelectorAll('.js-xf-filter-toggle').forEach(btn => {
			btn.addEventListener('click', (e) => {
				const popupName = btn.dataset.popupFilter;
				const popup = document.querySelector(`.js-xf-filter[data-popup-toggler="${popupName}"]`);

				if (popup) {
					const isVisible = popup.style.display === 'block';
					popup.style.display = isVisible ? 'none' : 'block';
					btn.classList.toggle('is-open', !isVisible);

					if (!isVisible) {
						this.positionPopup(popup);
					}
				}
			});
		});

		// Инициализация для актеров/режиссеров
		const isActorSearch = popup.querySelector('.js-xfsearch-actor');
		const isDirectorSearch = popup.querySelector('.js-xfsearch-derector');

		if (isActorSearch || isDirectorSearch) {
			this.initSpecialSearch(popup);
		}
	}

	async handleSearch(input, popup) {
		if (!input || !(input instanceof HTMLElement) || !popup || !(popup instanceof HTMLElement)) {
			return;
		}

		// Отменяем предыдущий запрос, если он есть
		if (this.searchController) {
			this.searchController.abort();
		}
		this.searchController = new AbortController();

		const searchText = input.value?.trim() || '';
		const optionsContainer = popup.querySelector('.js-xf-options');
		if (!optionsContainer) return;

		const isActorSearch = input.classList?.contains('js-xfsearch-actor') || false;
		const isDirectorSearch = input.classList?.contains('js-xfsearch-derector') || false;

		// Сохраняем оригинальные элементы перед первым поиском
		if (!this.originalOptions) {
			this.originalOptions = optionsContainer.innerHTML;
		}

		// Если поиск пустой - восстанавливаем оригинальные элементы
		if (searchText === '') {
			optionsContainer.innerHTML = this.originalOptions;
			return;
		}

		// Regular search (non-actor/non-director)
		if (!isActorSearch && !isDirectorSearch) {
			const term = searchText.toLowerCase();
			const options = optionsContainer.querySelectorAll('.js-xf-option');
			let hasVisibleResults = false;

			options.forEach(option => {
				if (!option) return;
				const text = option.textContent?.toLowerCase() || '';
				const isVisible = text.includes(term);
				option.style.display = isVisible ? 'flex' : 'none';
				if (isVisible) hasVisibleResults = true;
			});

			// Показываем сообщение, если ничего не найдено
			const noResultsMsg = optionsContainer.querySelector('.xf-no-results');
			if (!hasVisibleResults) {
				if (!noResultsMsg) {
					const msg = document.createElement('div');
					msg.className = 'xf-no-results';
					msg.textContent = `Ничего не найдено по запросу "${searchText}"`;
					optionsContainer.appendChild(msg);
				}
			} else if (noResultsMsg) {
				noResultsMsg.remove();
			}
			return;
		}

		// AJAX поиск для актеров/режиссеров
		if (searchText.length < 2) {
			optionsContainer.innerHTML = this.originalOptions;
			return;
		}

		try {
			// Сохраняем текущее состояние перед AJAX-запросом
			const tempContent = optionsContainer.innerHTML;
			optionsContainer.innerHTML = `
			<div class="xf-loading">
				<div class="xf-spinner"></div>
				<div>Идет поиск...</div>
			</div>
			`;

			const formData = new FormData();
			formData.append('query', searchText);
			formData.append('type', isDirectorSearch ? 1 : 2);
			formData.append('user_hash', window.dle_login_hash || '');

			const response = await fetch('/engine/ajax/controller.php?mod=xfvalue_search_ajax', {
				method: 'POST',
				body: formData,
				signal: this.searchController.signal
			});

			if (!response.ok) throw new Error(`HTTP error: ${response.status}`);

			const data = await response.text();

			// Проверяем, не изменился ли поисковый запрос пока шёл запрос
			if (input.value?.trim() === searchText) {
				const tempDiv = document.createElement('div');
				tempDiv.innerHTML = data;

				if (tempDiv.querySelector('.js-xf-option')) {
					optionsContainer.innerHTML = data;
					this.initPopupHandlers(popup);
				} else {
					optionsContainer.innerHTML = tempContent; // Восстанавливаем предыдущее состояние
					const msg = document.createElement('div');
					msg.className = 'xf-no-results';
					msg.textContent = `Ничего не найдено по запросу "${searchText}"`;
					optionsContainer.appendChild(msg);
				}
			}
		} catch (error) {
			if (error.name !== 'AbortError') {
				console.error('Search error:', error);
				optionsContainer.innerHTML = this.originalOptions;
				const msg = document.createElement('div');
				msg.className = 'xf-error';
				msg.textContent = 'Ошибка поиска';
				optionsContainer.appendChild(msg);
			}
		}
	}

	initSpecialSearch(popup) {
		// Дополнительные обработчики, если нужны
	}

	updateClearButton(popup) {
		const clearBtn = popup.querySelector('.js-xf-clear');
		if (!clearBtn) return;

		const checkedCount = popup.querySelectorAll('input[type="checkbox"]:checked').length;
		const countSpan = clearBtn.querySelector('.js-xf-clear-count');

		if (countSpan) countSpan.textContent = checkedCount;
		clearBtn.style.display = checkedCount > 0 ? 'block' : 'none';
	}

	updateSelectedItems() {
		this.selectedContainer.innerHTML = '';

		document.querySelectorAll('.js-xf-filter').forEach(popup => {
			popup.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
				const optionElement = checkbox.closest('.js-xf-option');
				if (!optionElement) return;

				const itemText = optionElement.textContent?.trim() || '';
				const itemId = checkbox.value;

				if (!itemId) return;

				const itemElement = document.createElement('div');
				itemElement.className = 'xf-selected-item';
				itemElement.dataset.popup = popup.dataset.popupToggler;
				itemElement.dataset.value = itemId;
				itemElement.textContent = itemText;

				itemElement.addEventListener('click', () => {
					const targetPopup = document.querySelector(`.js-xf-filter[data-popup-toggler="${itemElement.dataset.popup}"]`);
					if (targetPopup) {
						const checkbox = targetPopup.querySelector(`input[type="checkbox"][value="${itemElement.dataset.value}"]`);
						if (checkbox) {
							checkbox.checked = false;
							checkbox.dispatchEvent(new Event('change'));
						}
					}
					itemElement.remove();
				});

				this.selectedContainer.appendChild(itemElement);
			});
		});

		// Сохраняем состояния после обновления
		this.saveAllStates();
	}

	handleOutsideClick(e) {
		if (!e.target.closest('.js-xf-filter') && !e.target.closest('.js-xf-filter-toggle')) {
			document.querySelectorAll('.js-xf-filter').forEach(popup => {
				popup.style.display = 'none';
			});

			document.querySelectorAll('.js-xf-filter-toggle').forEach(btn => {
				btn.classList.remove('is-active', 'is-open');
			});
		}
	}
}

// Инициализация при загрузке DOM
document.addEventListener('DOMContentLoaded', () => {
	new XFilter();
});