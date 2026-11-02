<div class="catalog-filters__group" data-filter-group="{filter.key|raw}">
    <label class="catalog-filters__label" for="cf-{filter.key|raw}">{filter.label}</label>
    <div class="catalog-filters__select-wrap">
        <select
            class="catalog-filters__select"
            id="cf-{filter.key|raw}"
            data-filter="{filter.key|raw}"
            data-filter-type="select"
        >
            [not-filter.hide_empty]
            <option value="">{filter.empty_label}</option>
            [/not-filter.hide_empty]
            [loop filter.options]
                <option value="{item.value|raw}"[item.selected] selected[/item.selected]>{item.label}</option>
            [/loop]
        </select>
        <span class="catalog-filters__select-icon fa fa-chevron-down" aria-hidden="true"></span>
    </div>
</div>
