<section class="reactions-widget" data-reactions-widget data-series-id="{series.id}">
    <div class="reactions-widget__head">
        <div class="reactions-widget__intro">
            <span class="reactions-widget__badge">{reactions.badge}</span>
            <h3 class="reactions-widget__title">{reactions.title}</h3>
            <p class="reactions-widget__total" data-reactions-total>{reactions.total_label}</p>
        </div>
    </div>
    <div class="reactions-widget__grid" data-reactions-grid>
        [loop reactions.items]
            <button type="button"
                    class="reactions-card dontusebuttonclass[item.is_selected] is-selected[/item.is_selected]"
                    data-reaction-id="{item.id}"
                    data-reaction-card
                    aria-pressed="[item.is_selected]true[/item.is_selected][not-item.is_selected]false[/not-item.is_selected]">
                <span class="reactions-card__emoji" aria-hidden="true">{item.emoji}</span>
                <span class="reactions-card__label">{item.label}</span>
                <span class="reactions-card__count" data-reaction-count>{item.count_label}</span>
                <span class="reactions-card__bar" aria-hidden="true">
                    <span class="reactions-card__bar-fill" data-reaction-bar style="width:{item.percent}%"></span>
                </span>
                <span class="reactions-card__percent" data-reaction-percent>{item.percent}%</span>
            </button>
        [/loop]
    </div>
    <div class="reactions-widget__feedback" data-reactions-feedback hidden></div>
</section>
