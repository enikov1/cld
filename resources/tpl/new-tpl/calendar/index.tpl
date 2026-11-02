[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main expected-page calendar-page">
    <div class="expected-hero">
        <div class="expected-hero__eyebrow"><span class="fa fa-calendar"></span> Календарь</div>
        <h1>{page.heading}</h1>
        [page.lead]
            <p>{page.lead}</p>
        [/page.lead]
    </div>

    {schedule_calendar_block|raw}

    <section class="schedule-timeline" aria-label="Серии по дням">
        <div class="schedule-timeline__toolbar">
            <div class="schedule-timeline__toolbar-title">
                <span class="fa fa-list"></span> Серии за {schedule_calendar.month_label}
            </div>
            [schedule_episode_count]
                <div class="schedule-timeline__toolbar-count">{schedule_episode_count}</div>
            [/schedule_episode_count]
        </div>
        <div data-cal-timeline>
            {schedule_timeline_block|raw}
        </div>
    </section>

    [calendar_seo_html]
        <div class="desc-text clearfix home-seo">{calendar_seo_html|raw}</div>
    [/calendar_seo_html]
</main>
