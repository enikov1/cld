<div class="poster has-overlay grid-item d-flex fd-column">
    <div class="poster__desc order-last">
        <a href="{season-link}" class="poster__link">
            <h3 class="poster__title line-clamp">{season-title}</h3>
        </a>
        <div class="poster__meta">
            <a href="{news-link}">{news-title}</a> - Сезон {season-season}
        </div>
    </div>
    <div class="poster__img img-responsive img-responsive--portrait img-fit-cover anim">
        [season-kadr]<img src="{season-kadr}" loading="lazy" alt="{season-title}">[/season-kadr]
        [not-season-kadr]<img src="{THEME}/images/nokadr.jpg" loading="lazy" alt="{season-title}">[/not-season-kadr]
        <div class="has-overlay__icon anim">
        </div>
    </div>
    <div class="poster__btn-info js-show-info"><span class="fal fa-info-circle"></span>Описание</div>
    
    [season-rating-average]
    <div class="poster__rating">Рейтинг: {season-rating-average}</div>
    [/season-rating-average]
</div>

