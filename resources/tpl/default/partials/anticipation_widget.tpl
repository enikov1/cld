<div class="movie-expected" data-anticipation-root data-series-id="{series.id}">
    <div class="movie-expected__title">Рейтинг ожидания</div>
    <div class="movie-expected__bar">
        <div class="movie-expected__green" data-anticipation-bar style="width:{anticipation.percent}%"></div>
    </div>
    <div class="movie-expected__actions">
        <div class="movie-expected__wrapper">
            <div class="movie-expected__percent" data-anticipation-percent>{anticipation.percent}%</div>
            <div class="movie-expected__count" data-anticipation-votes>{anticipation.votes_label}</div>
        </div>
        <div class="movie-expected__btns">
            <button type="button" class="movie-expected__wait dontusebuttonclass[anticipation.wait_active] success[/anticipation.wait_active]" data-anticipation-vote="1">
                <i class="fa fa-thumbs-up"></i> Жду
            </button>
            <button type="button" class="movie-expected__nowait dontusebuttonclass[anticipation.nowait_active] success[/anticipation.nowait_active]" data-anticipation-vote="-1">
                <i class="fa fa-thumbs-down"></i> Не жду
            </button>
        </div>
        <a class="movie-expected__most" href="/skoro/">Самые ожидаемые премьеры</a>
    </div>
</div>
