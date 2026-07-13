    <div class="episodes-modal" data-episodes-modal hidden>
        <div class="episodes-modal__overlay" data-episodes-close></div>
        <div class="episodes-modal__window" role="dialog" aria-modal="true" aria-labelledby="episodesModalTitle">
            <div class="episodes-modal__head">
                <div>
                    <div class="episodes-modal__label">Расписание</div>
                    <h3 id="episodesModalTitle">{series.title}</h3>
                </div>
                <button class="episodes-modal__close dontusebuttonclass" type="button" data-episodes-close aria-label="Закрыть">×</button>
            </div>
            <div class="episodes-modal__body">
                [loop schedule]
                    <div class="episodes-season is-open">
                        <button class="episodes-season__toggle dontusebuttonclass" type="button">
                            <span class="episodes-season__num">{item.season_number}</span>
                            <span class="episodes-season__title">{item.title}</span>
                            <span class="episodes-season__arrow"></span>
                        </button>
                        <div class="episodes-season__content">
                            <div class="episodes-list">
                                <div class="episodes-list__head">
                                    <span>№</span>
                                    <span>Название</span>
                                    <span>Дата</span>
                                    <span></span>
                                </div>
                                [loop item.episodes]
                                    <div class="episodes-item">
                                        <div class="episodes-item__num">{item.episode_number} серия</div>
                                        <div class="episodes-item__title">{item.title}</div>
                                        <div class="episodes-item__date">{item.release_at}</div>
                                        [item.is_released]
                                            <div class="episodes-item__status is-released" title="Вышла">✓</div>
                                        [/item.is_released]
                                        [not-item.is_released]
                                            <div class="episodes-item__status" title="Ожидается">…</div>
                                        [/not-item.is_released]
                                    </div>
                                [/loop]
                            </div>
                        </div>
                    </div>
                [/loop]
            </div>
        </div>
    </div>
