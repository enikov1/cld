[has_schedule_calendar]
<section class="schedule-cal sect[is_calendar_page] schedule-cal--page[/is_calendar_page]" data-schedule-calendar data-api-url="/api/home/episode-calendar"[is_calendar_page] data-details="1" data-sync-url="/kalendar/"[/is_calendar_page]>
    <div class="sect-header fx-row fx-middle fx-start">
        [is_calendar_page]
            <div class="sect-title">Календарь месяца</div>
        [/is_calendar_page]
        [not-is_calendar_page]
            <div class="sect-title"><a href="/kalendar/">Календарь выхода серий</a></div>
        [/not-is_calendar_page]
    </div>
    <div class="schedule-cal__layout">
        <div class="schedule-cal__month">
            <div class="schedule-cal__nav">
                <button class="schedule-cal__nav-btn dontusebuttonclass" type="button" data-cal-prev aria-label="Предыдущий месяц">
                    <span class="fa fa-angle-left"></span>
                </button>
                <div class="schedule-cal__month-label" data-cal-month-label>{schedule_calendar.month_label}</div>
                <button class="schedule-cal__nav-btn dontusebuttonclass" type="button" data-cal-next aria-label="Следующий месяц">
                    <span class="fa fa-angle-right"></span>
                </button>
            </div>
            <div class="schedule-cal__weekdays" aria-hidden="true">
                <span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span>
            </div>
            <div class="schedule-cal__grid" data-cal-grid role="grid" aria-label="Календарь выхода серий"></div>
        </div>
        <div class="schedule-cal__day-panel">
            <div class="schedule-cal__day-title" data-cal-day-title>Выберите день</div>
            <div class="schedule-cal__day-list" data-cal-day-list>
                <div class="schedule-cal__empty">Нажмите на день, чтобы увидеть серии</div>
            </div>
        </div>
    </div>
    <script type="application/json" data-cal-initial>{schedule_calendar_json|raw}</script>
</section>
[/has_schedule_calendar]
