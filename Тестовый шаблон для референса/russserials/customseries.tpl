[series-item]
<div class="custom-series-item">
    <div class="series-image">
        [series-kadr]<img src="{series-kadr}" alt="{series-title}">[/series-kadr] [not-series-kadr]
        <div class="no-image">Нет изображения</div>[/not-series-kadr]
    </div>
    <div class="series-info">
        <h3><a href="{series-link}">{series-title}</a></h3>
        <div class="series-meta">
            <span class="series-news"><a href="{news-link}">{news-title}</a></span>
            <span class="series-season">Сезон {series-season}</span>
            <span class="series-episode">Серия {series-episode}</span>
        </div>
        [series-story]
        <div class="series-story">{series-story}</div>[/series-story]
        <div class="series-link"><a href="{series-link}" class="btn-view">Смотреть</a></div>
    </div>
</div>
[/series-item] [not-series]
<div class="no-series">Серии не найдены</div>
[/not-series]