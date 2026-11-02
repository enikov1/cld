[schedule_timeline]
    [loop schedule_timeline]
        <section class="schedule-timeline__day[item.is_today] is-today[/item.is_today]" data-cal-day="{item.date}" id="cal-day-{item.date}">
            <h2 class="schedule-timeline__heading">
                <span class="schedule-timeline__date">{item.date_label}</span>
                [item.weekday]
                    <span class="schedule-timeline__weekday">{item.weekday}</span>
                [/item.weekday]
                <span class="schedule-timeline__count">{item.count}</span>
            </h2>
            <div class="schedule-timeline__list">
                [loop item.episodes]
                    <a class="schedule-timeline__item" href="{item.series_url|raw}">
                        [item.poster_url]
                            <div class="schedule-timeline__poster"><img src="{item.poster_url|raw}" alt="" loading="lazy"></div>
                        [/item.poster_url]
                        [not-item.poster_url]
                            <div class="schedule-timeline__poster schedule-timeline__poster--empty"><span class="fa fa-film"></span></div>
                        [/not-item.poster_url]
                        <div class="schedule-timeline__body">
                            <div class="schedule-timeline__title">{item.series_title}</div>
                            [item.title_original]
                                <div class="schedule-timeline__original">{item.title_original}</div>
                            [/item.title_original]
                            <div class="schedule-timeline__meta">
                                [item.year]{item.year}[/item.year]
                                [item.age_label] · {item.age_label}[/item.age_label]
                                [item.genres_label] · {item.genres_label}[/item.genres_label]
                                [item.countries_label] · {item.countries_label}[/item.countries_label]
                            </div>
                            <div class="schedule-timeline__episode">
                                {item.season_number} сезон / {item.episode_number} эпизод
                                [item.episode_title] · {item.episode_title}[/item.episode_title]
                            </div>
                            <div class="schedule-timeline__foot">
                                <span class="schedule-cal__item-status[item.is_released] is-released[/item.is_released]">[item.is_released]Вышла[/item.is_released][not-item.is_released]Ожидается[/not-item.is_released]</span>
                                [item.channel_name]
                                    <span class="schedule-timeline__channel">{item.channel_name}</span>
                                [/item.channel_name]
                            </div>
                        </div>
                        <div class="schedule-timeline__ratings">
                            [item.kp_rating]
                                <span class="schedule-timeline__rate schedule-timeline__rate--kp" title="Кинопоиск">{item.kp_rating}</span>
                            [/item.kp_rating]
                            [item.imdb_rating]
                                <span class="schedule-timeline__rate schedule-timeline__rate--imdb" title="IMDb">{item.imdb_rating}</span>
                            [/item.imdb_rating]
                        </div>
                    </a>
                [/loop]
            </div>
        </section>
    [/loop]
[/schedule_timeline]
[not-schedule_timeline]
    <div class="schedule-timeline__empty">В этом месяце серий нет</div>
[/not-schedule_timeline]
