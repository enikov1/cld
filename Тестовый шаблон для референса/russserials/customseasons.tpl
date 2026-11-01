[season-item]
<div class="custom-season-item">
    <div class="season-image">
        [season-kadr]<img src="{season-kadr}" alt="{season-title}">[/season-kadr] [not-season-kadr]
        <div class="no-image">Нет изображения</div>[/not-season-kadr]
    </div>
    <div class="season-info">
        <h3><a href="{season-link}">{season-title}</a></h3>
        <div class="season-meta">
            <span class="season-news"><a href="{news-link}">{news-title}</a></span>
            <span class="season-number">Сезон {season-number}</span>
            <span class="season-date">{season-date}</span>
        </div>
        [season-story]
        <div class="season-story">{season-story}</div>[/season-story]
        <div class="season-link"><a href="{season-link}" class="btn-view">Смотреть</a></div>
    </div>
</div>
[/season-item] [not-season]
<div class="no-season">Сезоны не найдены</div>
[/not-season]