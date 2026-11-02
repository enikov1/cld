<div class="catalog-filters__group" data-filter-group="{filter.key|raw}">
    <div class="catalog-filters__range-head fx-row fx-middle">
        <label class="catalog-filters__label" for="cf-{filter.key|raw}">{filter.label}</label>
        <output class="catalog-filters__range-value" for="cf-{filter.key|raw}" data-range-output="{filter.key|raw}">{filter.display_value}</output>
    </div>
    <div class="catalog-filters__range">
        <input
            type="range"
            class="catalog-filters__slider"
            id="cf-{filter.key|raw}"
            data-filter="{filter.key|raw}"
            data-filter-type="range"
            data-filter-suffix="{filter.suffix|raw}"
            min="{filter.min|raw}"
            max="{filter.max|raw}"
            step="{filter.step|raw}"
            value="[filter.is_active]{filter.value|raw}[/filter.is_active][not-filter.is_active]{filter.min|raw}[/not-filter.is_active]"
        >
    </div>
</div>
