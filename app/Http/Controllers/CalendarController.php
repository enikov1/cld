<?php

namespace App\Http\Controllers;

use App\Services\HomeEpisodeScheduleService;
use App\Support\SiteConfig;
use App\Support\Speedbar;
use Illuminate\Http\Request;

class CalendarController extends TplController
{
    public function index(Request $request)
    {
        $now = now();
        $year = HomeEpisodeScheduleService::normalizeYear($request->query('year'), $now);
        $month = HomeEpisodeScheduleService::normalizeMonth($request->query('month'), $now);

        $scheduleCalendar = HomeEpisodeScheduleService::calendarMonth($year, $month, true);
        $scheduleCalendarJson = json_encode(
            $scheduleCalendar,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
        );

        $heading = SiteConfig::str('calendar_heading') ?: 'График выхода сериалов';
        $lead = SiteConfig::str('calendar_lead');
        $seoHtml = SiteConfig::str('calendar_seo_html');

        $vars = [
            'page' => [
                'heading' => $heading,
                'lead' => $lead,
            ],
            'is_calendar_page' => true,
            'has_schedule_calendar' => true,
            'schedule_calendar' => $scheduleCalendar,
            'schedule_calendar_json' => $scheduleCalendarJson,
            'schedule_calendar_block' => $this->renderPartial('partials/home_schedule_calendar.tpl', [
                'has_schedule_calendar' => true,
                'is_calendar_page' => true,
                'schedule_calendar' => $scheduleCalendar,
                'schedule_calendar_json' => $scheduleCalendarJson,
            ]),
            'schedule_timeline' => $scheduleCalendar['timeline'],
            'schedule_timeline_block' => $this->renderPartial('partials/schedule_calendar_timeline.tpl', [
                'schedule_timeline' => $scheduleCalendar['timeline'],
            ]),
            'schedule_episode_count' => $scheduleCalendar['episode_count'],
            'calendar_seo_html' => $seoHtml,
        ];

        $this->applySpeedbar(Speedbar::forCalendar(), $vars);

        $hasCustomMonth = $request->filled('year') || $request->filled('month');

        $meta = [
            'title' => SiteConfig::str('calendar_meta_title') ?: $heading,
            'description' => SiteConfig::str('calendar_meta_description') ?: $lead,
            'canonical' => url('/kalendar/'),
            'robots' => $hasCustomMonth ? 'noindex,follow' : '',
        ];

        return $this->renderTplPage('calendar/index.tpl', $vars, $meta);
    }
}
