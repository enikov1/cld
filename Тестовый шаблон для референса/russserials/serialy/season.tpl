[metatags]
<title>{title} {season} сезон все серии подряд смотреть онлайн бесплатно в хорошем качестве</title>
<description>Смотрите {season} сезон {title} все серии подряд онлайн: HD качество, быстрый плеер, удобный просмотр на телефоне и ПК бесплатно.</description>
<og:description>Смотрите {season} сезон {title} все серии подряд онлайн: HD качество, быстрый плеер, удобный просмотр на телефоне и ПК бесплатно.</og:description>
[/metatags]

<article class="season-page" itemscope itemtype="http://schema.org/Article">
    <div class="season__header">
        <div class="season__header-content">
            <h1 class="season__title" itemprop="headline">{title} {season} сезон все серии</h1>
            <div class="season__meta">
                <span class="season__meta-item season__meta-season">{season} сезон</span>
            </div>
        </div>
    </div>

    <div class="season__rating">
        <div class="season__rating-label">Рейтинг сезона</div>
        <div class="season__rating-stars serialy-rating-block">
            {season-rating}
        </div>
    </div>

    <div class="season__navigation">
        <div class="season__navigation-header">
            <h2 class="season__navigation-title">Выберите сезон</h2>
        </div>
        <div class="season__navigation-list">
            {season=[xfvalue_last_season]}
        </div>
    </div>

    <div class="season__episodes">
        <div class="season__episodes-header">
            <h2 class="season__episodes-title">Серии {season} сезона</h2>
        </div>
        <div class="season__episodes-grid">
            {serialy news-id="{news-id}" season="{season}"}
        </div>
    </div>

    <div class="page__comments" data-season="{season}">
        <div class="page__comments-psform">
            <div class="page__comments-psform-title">Комментарии</div>
            <div class="page__comments-psform-form">Написать комментарий</div>
            <div class="d-none page__comments-form">{addcomments}</div>
        </div>
        <div class="page__comments-filters">
            <div class="comments-filter">
                <label>Фильтр по дате:</label>
                <select id="filter_date" onchange="applyCommentFilters()">
                    <option value="">Все</option>
                    <option value="today">Сегодня</option>
                    <option value="week">Неделя</option>
                    <option value="month">Месяц</option>
                    <option value="year">Год</option>
                </select>
            </div>
            <div class="comments-filter">
                <label>Фильтр по рейтингу:</label>
                <select id="filter_rating" onchange="applyCommentFilters()">
                    <option value="">Все</option>
                    <option value="positive">Положительные</option>
                    <option value="negative">Отрицательные</option>
                    <option value="neutral">Нейтральные</option>
                </select>
            </div>
            <div class="comments-filter">
                <label>Сортировка:</label>
                <select id="sort_by" onchange="applyCommentFilters()">
                    <option value="">По умолчанию</option>
                    <option value="date">По дате</option>
                    <option value="rating">По рейтингу</option>
                </select>
            </div>
        </div>
        <div class="page__comments-title">Всего комментариев <span>{comments-num}</span></div>
        <div class="page__comments-list [not-comments]page__comments-list--not-comments[/not-comments]" id="page__comments-list">
            [not-comments]
            <div class="message-info">Комментариев еще нет. Вы можете стать первым!</div>
            [/not-comments] {comments}{navigation}
        </div>
    </div>
</article>