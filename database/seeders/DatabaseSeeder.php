<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use App\Support\SiteConfig;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Только начальные настройки сайта. Контент — через админку и kp:sync.
     */
    public function run(): void
    {
        SiteSetting::set('site_name', 'LordSerial');
        SiteSetting::set('site_tagline', 'Смотреть бесплатно в хорошем качестве');
        SiteSetting::set('footer_text', 'Сериалы онлайн в HD качестве');
        SiteSetting::set('home_heading', 'Сериалы онлайн');
        SiteSetting::set('home_lead', '');
        SiteSetting::set('home_seo_html', '<h1>Только лучшие сериалы онлайн</h1><p>Смотрите зарубежные сериалы, мультсериалы и аниме в хорошем качестве бесплатно.</p>');
        SiteSetting::set('active_theme', 'default');
        SiteSetting::set('admin_path', 'admin');
        SiteSetting::set('comments_auto_approve', '0');
        SiteSetting::set('site_background_header_offset', '200');
        SiteConfig::ensureDefaults();

    }
}
