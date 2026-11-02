[pagination.has_pages]
<div class="bottom-nav clr ignore-select" id="bottom-nav">
    <div class="pagi-nav clearfix">
        <span class="pprev">[pagination.prev_url]<a href="{pagination.prev_url|raw}"><span class="fa fa-angle-double-left"></span></a>[/pagination.prev_url]</span>
        <span class="navigation">[loop pagination.pages][item.active]<span>{item.num}</span>[/item.active][not-item.active]<a href="{item.url|raw}">{item.num}</a>[/not-item.active][/loop]</span>
        <span class="pnext">[pagination.next_url]<a href="{pagination.next_url|raw}"><span class="fa fa-angle-double-right"></span></a>[/pagination.next_url]</span>
    </div>
</div>
[/pagination.has_pages]
