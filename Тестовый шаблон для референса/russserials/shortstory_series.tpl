<div class="poster has-overlay grid-item d-flex fd-column">
    <div class="poster__desc order-last">
        <a href="{series-link}" class="poster__link">
            <h3 class="poster__title line-clamp">{series-title}</h3>
        </a>
        <div class="poster__meta">
            <a href="{news-link}">{news-title}</a> - Сезон {series-season}, Серия {series-episode}
        </div>
    </div>
    <div class="poster__img img-responsive img-responsive--portrait img-fit-cover anim">
        [series-kadr]<img src="{series-kadr}" loading="lazy" alt="{series-title}">[/series-kadr]
        [not-series-kadr]<img src="{THEME}/images/nokadr.jpg" loading="lazy" alt="{series-title}">[/not-series-kadr]
        [series-future]<div class="poster__label poster__label--white">Скоро</div>[/series-future]
        <div class="has-overlay__icon anim">
        </div>
    </div>
    <div class="poster__btn-info js-show-info"><span class="fal fa-info-circle"></span>Описание</div>
    
    [series-rating-average]
    <div class="poster__rating">Рейтинг: {series-rating-average}</div>
    [/series-rating-average]
</div>

