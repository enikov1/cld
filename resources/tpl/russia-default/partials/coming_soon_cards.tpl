[loop coming_soon_items]
<article class="expected-card" data-series-id="{item.id}" data-anticipation-card>
    <a class="expected-card__poster" href="{item.url|raw}">
        [item.poster_url]
            <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy">
        [/item.poster_url]
    </a>
    <div class="expected-card__body">
        <div class="expected-card__head">
            <div class="expected-card__titlebox">
                <div class="expected-card__badges">
                    <span class="expected-rank">#{item.rank}</span>
                    [item.premiere_date_label]
                        <span class="expected-date">{item.premiere_date_label}</span>
                    [/item.premiere_date_label]
                    [item.premiere_countdown_label]
                        <span class="expected-countdown">{item.premiere_countdown_label}</span>
                    [/item.premiere_countdown_label]
                </div>
                <h2><a href="{item.url|raw}">{item.title}</a></h2>
                [item.title_en]
                    <div class="expected-card__original">{item.title_en}</div>
                [/item.title_en]
            </div>
            <div class="expected-rating">
                <strong data-anticipation-percent>{item.percent}</strong>%
                <span data-anticipation-votes>{item.votes_label}</span>
            </div>
        </div>
        [item.genres]
            <div class="expected-card__meta">
                [loop item.genres]
                    <span>{item.name}</span>
                [/loop]
            </div>
        [/item.genres]
        <div class="expected-card__bottom">
            <div class="expected-progress">
                <div class="expected-progress__info">
                    <span>Уровень ожидания</span>
                    <b data-anticipation-percent>{item.percent}%</b>
                </div>
                <div class="expected-progress__line">
                    <i data-anticipation-bar style="width:{item.percent}%"></i>
                </div>
            </div>
            <button type="button" class="expected-watch-btn dontusebuttonclass[item.watch_active] is-active[/item.watch_active]" data-anticipation-vote="1">
                <span class="fa fa-bell"></span> Буду смотреть
            </button>
        </div>
    </div>
</article>
[/loop]
