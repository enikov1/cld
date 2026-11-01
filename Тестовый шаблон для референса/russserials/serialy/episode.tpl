[metatags]
<title>{title} {episode} серия смотреть онлайн бесплатно в хорошем качестве</title>
<description>{title} {episode} серия смотреть онлайн, бесплатно в хорошем HD качестве</description>
<keywords></keywords>
<og:description>{title} {episode} серия смотреть онлайн, бесплатно в хорошем HD качестве</og:description>
[/metatags]

<article class="full ignore-select" itemscope itemtype="https://schema.org/Movie">
    <div id="movie_video" style="margin:20px 0;"></div>
    <script type="text/javascript" src="https://vak345.com/s.js?v=3692d5916e70b59898c78838e5ceee95" async></script>
    <div class="fpage">
        [group=1]
        <div class="full-panel-admin">
            [edit]Редактировать новость[/edit]
        </div>
        [/group]
        <div class="ftitle">
            <h1 itemprop="name">{title} {episode} серия смотреть</h1>
            <div class="to-fav">
                [group=5]<span class="fa fa-star fav-guest" title="Добавить в закладки"></span>[/group] [add-favorites]
                <span class="fa fa-star" title="Добавить в закладки"></span>[/add-favorites] [del-favorites]
                <span class="fa fa-star-o" title="Убрать из закладок"></span>[/del-favorites]
            </div>
        </div>

        <!-- END FTITLE -->

        <div class="fcols clearfix">

            <div class="fposter">
                [xfgiven_poster]<img data-src="[xfvalue_poster]" alt="{title}">[/xfgiven_poster] [xfnotgiven_poster]
                <img data-src="[xfvalue_thumb_url_poster2]" alt="{title}">[/xfnotgiven_poster]
                <div class="frating">{series-rating}</div>

                [xfgiven_last_epizode] [catlist=40]
                <div class="badge_tr">{episode} выпуск</div>[/catlist] [not-catlist=40]
                <div class="badge_tr">{episode} серия</div>[/not-catlist] [/xfgiven_last_epizode]
            </div>

            <div class="finfo">
                <div class="sd-line"><span>Страна:</span> Россия</div>
                [xfgiven_year]
                <div class="sd-line"><span>Год выпуска:</span> <span itemprop="copyrightYear">[xfvalue_year limit="255"]<span></div>[/xfgiven_year]
				[xfgiven_premera-v-rf]<div class="sd-line"><span>Дата выхода:</span> [xfvalue_premera-v-rf limit="255"]</div>[/xfgiven_premera-v-rf] [xfgiven_genres]
                <div class="sd-line"><span>Жанр:</span> <span itemprop="genre">[xfvalue_genres limit="255"]</span></div>[/xfgiven_genres] [xfgiven_time]
                <div class="sd-line"><span>Продолжительность:</span> [xfvalue_time]</div>[/xfgiven_time] [story]
                <div class="sd-line"><span>Описание:</span> {story}</div>
                [/story]
            </div>

        </div>

    </div>

    <!-- END FPAGE -->

    [banner_top_player] {banner_top_player} [/banner_top_player]

    <div class="fsocial_full">
        <div class="social-share">
            <span class="social-btn vk" data-social="vk"></span>
            <span class="social-btn ok" data-social="ok"></span>
            <span class="social-btn whatsapp" data-social="whatsapp"></span>
            <span class="social-btn telegram" data-social="telegram"></span>
            <span class="social-btn viber" data-social="viber"></span>
            <span class="social-btn mailru" data-social="mailru"></span>
            <span class="social-btn livejournal" data-social="livejournal"></span>
        </div>
    </div>
    <span style="margin:5px 0;color:#374248;display:block;"><center>Уважаемые зрители, данная серия доступна через плеер правообладателя.</center></span>
    <h2 class="fplayer-title">Смотреть онлайн {title} {episode} серия бесплатно в хорошем качестве HD 1080</h2>

    <div class="fplayer tabs-box">

        <div class="tabs-b video-box">
            <div class="playlists-control">
                [group=5]<span class="player-ctrl-btn" title="Смотреть без рекламы" id="register_1"></span>[/group] [not-group=5]
                <span class="player-ctrl-btn" title="Смотреть позже" id="watch1"></span>[/not-group]
            </div>
            [xfgiven_playlist]
            <div class="playlists-ajax" data-series="{episode}" data-xfname="playlist" data-news_id="{news-id}">
            </div>[/xfgiven_playlist]

        </div>

        <div class="fpseries">
            <table>
                <tr>
                    <th>Номер серии</th>
                    <th>Дата выхода</th>
                    <th>Рейтинг</th>
                    <th></th>
                </tr>
                {serialy news-id="{news-id}" season="[xfvalue_last_season]" sort="descr"}
            </table>

        </div>


    </div>
</article>

<div class="full-comms ignore-select page__comments" id="full-comms" data-season="{season}" data-episode="{episode}">
    <div class="comms-t">Комментарии</div>
    <div class="page__comments-filters">
        <div class="comments-filter">
            <label>По дате:</label>
            <select id="filter_date" onchange="applyCommentFilters()">
                    <option value="">Все</option>
                    <option value="today">Сегодня</option>
                    <option value="week">Неделя</option>
                    <option value="month">Месяц</option>
                    <option value="year">Год</option>
                </select>
        </div>
        <div class="comments-filter">
            <label>По рейтингу:</label>
            <select id="filter_rating" onchange="applyCommentFilters()">
                    <option value="">Все</option>
                    <option value="positive">Положительные</option>
                    <option value="negative">Отрицательные</option>
                    <option value="neutral">Нейтральные</option>
                </select>
        </div>
        <div class="comments-filter">
            <label>Сортировка:</label>
            <select id="sort_by" onchange="applyCommentFilters()">
                    <option value="">По умолчанию</option>
                    <option value="date">По дате</option>
                    <option value="rating">По рейтингу</option>
                </select>
        </div>
    </div>
    {addcomments} {comments} {navigation}
</div>