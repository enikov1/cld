<nav class="pagination" aria-label="Страницы">
    [pagination.prev_url]
        <a class="pagination__prev" href="{pagination.prev_url|raw}">&laquo; Назад</a>
    [/pagination.prev_url]

    [loop pagination.pages]
        [item.active]
            <span class="pagination__current">{item.num}</span>
        [/item.active]
        [not-item.active]
            <a class="pagination__link" href="{item.url|raw}">{item.num}</a>
        [/not-item.active]
    [/loop]

    [pagination.next_url]
        <a class="pagination__next" href="{pagination.next_url|raw}">Вперёд &raquo;</a>
    [/pagination.next_url]
</nav>
