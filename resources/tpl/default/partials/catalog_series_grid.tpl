<div class="sect-cont sect-items clearfix" data-catalog-grid>
    [loop series_list]
        <div class="th-item">
            <a class="th-in with-mask" href="{item.url|raw}">
                <div class="th-img img-resp-vert">
                    <div class="th-card-badges th-card-badges--status">
                        [item.badge_new_episode]
                            <span class="th-card-badge th-card-badge--new">{item.badge_new_episode_label}</span>
                        [/item.badge_new_episode]
                        [item.badge_popular]
                            <span class="th-card-badge th-card-badge--popular">{item.badge_popular_label}</span>
                        [/item.badge_popular]
                    </div>
                    <div class="th-card-badges th-card-badges--meta">
                        [item.season_badge]
                            <span class="th-card-badge th-card-badge--season">{item.season_badge}</span>
                        [/item.season_badge]
                        [item.episode_badge]
                            <span class="th-card-badge th-card-badge--episode">{item.episode_badge}</span>
                        [/item.episode_badge]
                        [item.top_reaction_emoji]
                            <span class="th-card-emoji" title="Топ-реакция">{item.top_reaction_emoji}</span>
                        [/item.top_reaction_emoji]
                    </div>
                    <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy">
                </div>
                <div class="th-desc">
                    <div class="th-title">{item.title}</div>
                    <div class="th-rates fx-row">
                        [item.kp_rating]
                            <div class="th-rate th-rate-kp" data-text="КП"><span>{item.kp_rating}</span></div>
                        [/item.kp_rating]
                        [item.imdb_rating]
                            <div class="th-rate th-rate-imdb" data-text="imdb"><span>{item.imdb_rating}</span></div>
                        [/item.imdb_rating]
                    </div>
                </div>
                <div class="th-mask fx-col fx-center fx-middle anim">
                    <span class="fa fa-play"></span>
                </div>
            </a>
        </div>
    [/loop]
    [not-series_list]
        <p class="desc-text catalog-empty">По выбранным фильтрам ничего не найдено.</p>
    [/not-series_list]
</div>
