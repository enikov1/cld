<div class="th-item">
    <a class="th-in with-mask" href="{item.url|raw}">
        <div class="th-img img-resp-vert">
            <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy">
        </div>
        <div class="th-desc">
            <div class="th-title">{item.title}</div>
            [item.episode_progress_label]
                <div class="th-episode">{item.episode_progress_label}</div>
            [/item.episode_progress_label]
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
