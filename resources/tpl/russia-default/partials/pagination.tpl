[pagination.has_pages]
<div class="bottom-nav clr ignore-select" id="bottom-nav">
    <div class="pagi-nav clearfix">
        <span class="navigation">
            [pagination.prev_url]<a href="{pagination.prev_url|raw}">«</a> [/pagination.prev_url]
            [loop pagination.pages]
                [item.active]<span>{item.num}</span> [/item.active]
                [not-item.active]<a href="{item.url|raw}">{item.num}</a> [/not-item.active]
            [/loop]
            [pagination.next_url]<a href="{pagination.next_url|raw}">»</a>[/pagination.next_url]
        </span>
    </div>
</div>
[/pagination.has_pages]
