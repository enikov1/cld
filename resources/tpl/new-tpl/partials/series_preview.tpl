<div class="movie-tip__inner">
    <div class="movie-tip__content">
        <div class="movie-tip__top">
            <h3 class="movie-tip__title">{preview.title}</h3>
            <button type="button" class="movie-tip__close dontusebuttonclass" data-movie-tip-close aria-label="Закрыть">&times;</button>
        </div>

        [preview.badges]
        <div class="movie-tip__badges">
            [loop preview.badges] [item.mod]
            <span class="movie-tip__badge movie-tip__badge--{item.mod}">{item.text}</span> [/item.mod] [not-item.mod]
            <span class="movie-tip__badge">{item.text}</span> [/not-item.mod] [/loop]
        </div>
        [/preview.badges] [preview.description]
        <p class="movie-tip__desc">{preview.description}</p>
        [/preview.description] [preview.has_meta]
        <div class="movie-tip__meta">
            [preview.genres_text]
            <div class="movie-tip__row">
                <span class="movie-tip__name">Жанр</span>
                <span class="movie-tip__value">{preview.genres_text}</span>
            </div>
            [/preview.genres_text] [preview.directors_text]
            <div class="movie-tip__row">
                <span class="movie-tip__name">Режиссёр</span>
                <span class="movie-tip__value">{preview.directors_text}</span>
            </div>
            [/preview.directors_text] [preview.age_limit_label]
            <div class="movie-tip__row">
                <span class="movie-tip__name">Возраст</span>
                <span class="movie-tip__value">{preview.age_limit_label}</span>
            </div>
            [/preview.age_limit_label] [preview.actors_text]
            <div class="movie-tip__row">
                <span class="movie-tip__name">В ролях</span>
                <span class="movie-tip__value">{preview.actors_text}</span>
            </div>
            [/preview.actors_text]
        </div>
        [/preview.has_meta]

        <div class="movie-tip__actions">
            <a class="movie-tip__btn" href="{preview.url|raw}">Смотреть</a>
        </div>
    </div>
</div>