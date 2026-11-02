[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main clearfix fx-col grid-list" id="grid">
    [speedbar_block]
    <div class="speedbar nowrap">{speedbar_block|raw}</div>
    [/speedbar_block]

    <div class="shorts-header flex-row">
        <h1>{page.heading}</h1>
        [schedule_episode_count]
            <span class="catalog-results-count" aria-live="polite">
                <span class="catalog-results-count__num">{schedule_episode_count}</span>
                <span class="catalog-results-count__text">серий в этом месяце</span>
            </span>
        [/schedule_episode_count]
    </div>

    [page.lead]
        <div class="desc-text">
            <p>{page.lead}</p>
        </div>
    [/page.lead]

    {schedule_calendar_block|raw}

    <section class="sect schedule-timeline" aria-label="Серии по дням">
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
        <div class="site-desc clearfix fx-last">{calendar_seo_html|raw}</div>
    [/calendar_seo_html]
</main>
