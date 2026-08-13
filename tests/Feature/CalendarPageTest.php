<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_renders_with_admin_seo(): void
    {
        SiteSetting::set('calendar_heading', 'Тестовый график сериалов');
        SiteSetting::set('calendar_meta_title', 'SEO title календаря');
        SiteSetting::set('calendar_meta_description', 'SEO description календаря');

        $response = $this->get('/kalendar/');

        $response->assertOk();
        $response->assertSee('SEO title календаря', false);
        $response->assertSee('SEO description календаря', false);
        $response->assertSee('Тестовый график сериалов', false);
        $response->assertSee('data-schedule-calendar', false);
        $response->assertSee('data-cal-timeline', false);
        $response->assertSee('/kalendar/', false);
    }
}
