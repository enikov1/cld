<div class="catalog-filters" data-catalog-filters>
    <div class="catalog-filters__head fx-row fx-middle">
        <strong>Фильтры</strong>
        [has_active]
            <button type="button" class="catalog-filters__reset" data-catalog-reset>Сбросить</button>
        [/has_active]
    </div>

    <!--
      Настройка фильтров:
      1. Порядок и состав полей — config/catalog_filters.php (layout + fields)
      2. Вёрстка — этот шаблон или partials/catalog_filter_select.tpl / catalog_filter_range.tpl
      3. Отдельное поле: [filters.genre] {filters.genre.html|raw} [/filters.genre]
    -->
    <div class="catalog-filters__grid">
        [loop filter_fields]
            {item.html|raw}
        [/loop]
    </div>
</div>
