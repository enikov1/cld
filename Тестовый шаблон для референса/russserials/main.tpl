<!DOCTYPE html>
<html lang="ru">
<head>
	{headers}
	 <meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link rel="shortcut icon" href="{THEME}/images/favicon.ico" />
	<link rel="icon" href="/favicon.ico" type="image/x-icon">

	  <link rel="preload" href="{THEME}/fonts/inter-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{THEME}/fonts/inter-500.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{THEME}/fonts/inter-600.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{THEME}/fonts/inter-700.woff2" as="font" type="font/woff2" crossorigin>
	<link rel="preload" href="{THEME}/style/styles.css?v=4.24" as="style">
    <link rel="preload" href="{THEME}/style/engine.css?v=0.6" as="style">
	<link href="{THEME}/style/styles.css?v=4.28" type="text/css" rel="stylesheet" />
	<link href="{THEME}/style/engine.css?v=0.6" type="text/css" rel="stylesheet" />

	<link rel="manifest" href="/manifest.json">
	<meta name="theme-color" content="#1a1a1a">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="apple-mobile-web-app-title" content="Сериалы">
	<link rel="apple-touch-icon" href="/icons/icon-192.png">

	<style>
            @font-face {
                font-family: 'FontAwesome';
                font-style: normal;
                font-weight: 400;
                font-display: swap;
                src:url({THEME}/fonts/fontawesome-webfont.woff2?v=4.5.0) format("woff2")
            }
        </style>
</head>

<body>
<div class="wrap">

	<div class="block center fx-col">

		<div class="cols clearfix" id="cols">

			<main class="main clearfix fx-col grid-list" id="grid">
				[available=showfull|feedback|cat|search|actors|actorlist|dle_filter]<div class="speedbar nowrap">{speedbar}</div>[/available]
				[available=dle_collections]<div class="speedbar nowrap">{speedbar}</div>[/available]
				{info}
				{include file="main-seo.tpl"}
				[available=cat]
				<div class="site-desc clearfix fx-last">
					<h1>{category-seo-title}</h1>
					<p>{category-description}</p>
				</div>
				[/available]

				[available=main|cat|dle_collections|dle_filter]
				<div class="site-social">
            		<span>Спасибо, что делитесь с друзьями</span>
                    <script async src="https://usocial.pro/usocial/usocial.js?uid=1d8cab37e6f67426&v=6.1.5" data-script="usocial" charset="utf-8"></script>
                    <div class="uSocial-Share" data-pid="eca8556bdeae01fab4661480ea815dbb" data-type="share" data-options="rect,style1,default,absolute,horizontal,size32,eachCounter0,counter1,counter-after,nomobile,mobile_position_right" data-social="vk,ok,lj,mail,telegram,wa,vi,bookmarks"></div>
				</div>
				<div class="shorts-header flex-row">
					[not-available=dle_collections|dle_filter]
					<div class="grid-select clearfix" id="grid-select" data-name="Переключить вид">
						<div data-type="grid-list" class="current"><span class="fa fa-reorder"></span></div>
						<div data-type="grid-thumb"><span class="fa fa-th"></span></div>
					</div>
					[/not-available]
					[available=main]<h2>Русские сериалы и шоу</h2>[/available]
					[available=cat]<h2>{category-title}</h2>[/available]
                    [dle-collection main]<h1>Подборки русских сериалов и фильмов</h1>[/dle-collection]

					[dle-collection collections]{include file="engine/lazydev/dle_collections/index.php?action=template&show=title"}[/dle-collection]

					[available=dle_filter]
					<h1>{dle-filter h1}</h1>
					[/available]
				</div>
				[/available]


				[available=main|cat|dle_filter]
				<div class="catalog-links">
					<ul class="catalog-links__list">
						<li><a href="/luchie-melodrami-2026/">Мелодрамы 2026</a></li>
						<li><a href="/luchshie-detektivy-2026-goda/">Детективы 2026</a></li>
						<li><a href="/luchshie-dramy-2026/">Драмы 2026</a></li>
						<li><a href="/luchshie-komedii-2026/">Комедии 2026</a></li>
						<li><a href="/luchshie-kriminalnye-serialy-2026/">Криминал 2026</a></li>
						<li><a href="/luchshie-serialy-boeviki-2026/">Боевики 2026</a></li>
						<li><a href="/luchie-melodrami-2025/">Мелодрамы 2025</a></li>
						<li><a href="/luchshie-detektivy-2025-goda/">Детективы 2025</a></li>
						<li><a href="/luchshie-serialy-dramy-2025/">Драмы 2025</a></li>
						<li><a href="/luchshie-komedii-2025/">Комедии 2025</a></li>
						<li><a href="/luchshie-kriminalnye-serialy-2025/">Криминал 2025</a></li>
					</ul>
				</div>
				[/available]

				<div class="items clearfix">{content}</div>
			</main>

			<!-- END MAIN -->

			<aside class="side">

				<div class="side-box">
					<div class="side-bt">Категории</div>
					<div class="side-bc" id="side-nav">
						<div class="nav-title">По жанрам</div>
						<ul class="nav-menu flex-row">
							<li><a href="/melodrama/">Мелодрама</a><a href="/detectiv/">Детектив</a><a href="/drama/">Драма</a><a
									href="/comedy/">Комедия</a></li>
							<li><a href="/kriminal/">Криминал</a><a href="/boevik/">Боевик</a><a href="/triller/">Триллер</a><a
									href="/mistika/">Мистика</a></li>
							<li><a href="/istoricheskij/">Исторический</a><a href="/fantastika/">Фантастика</a><a href="/voenie/">Военный</a><a
									href="/shou/">ТВ-передачи</a></li>
						</ul>
						<div class="nav-title">По каналу</div>
						<ul class="nav-menu flex-row">
							<li><a href="/1-tv-kanal/">Первый канал</a>
<a href="/tv-russia-1/">Россия-1</a>
<a href="/ntv-tv/">НТВ</a>
</li>
							<li><a href="/domashniy-tv/">Домашний</a><a href="/5-kanal-tv/">5 канал</a><a href="/ctc-tv/">СТС</a>
							</li>
							<li><a href="/tvc-tv/">ТВЦ</a></li>
						</ul>

						<div class="nav-title">По году</div>
						<ul class="nav-menu flex-row">
							<li>
								<a href="/god2026/">2026</a>
							</li>
							<li>
								<a href="/god2025/">2025</a>
							</li>
							<li>
								<a href="/god2024/">2024</a>
							</li>
							<li>
								<a href="/god-2023/">2023</a>
							</li>
							<li>
								<a href="/god2022/">2022</a>
							</li>
							<li>
								<a href="/god2021/">2021</a>
							</li>
							<li>
								<a href="/god2020/">2020</a>
							</li>
							<li>
								<a href="/god2019/">2019</a>
							</li>
						</ul>


						<div class="nav-title"><a href="/collections/">Подборки</a></div>

						<ul class="flex-row">
							<li>
								<a href="/collections/pro-ljubov-i-otnoshenija/">Про любовь и отношения</a>
							</li>
							<li>
								<a href="/collections/pro-semju-i-detej/">Про семью и детей</a>
							</li>
							<li>
								<a href="/collections/pro-izmenu-i-predatelstvo/">Про измену и предательство</a>
							</li>
							<li>
								<a href="/collections/pro-rassledovanija/">Про расследования</a>
							</li>
						</ul>

					</div>
				</div>

				<div class="side-box">
					<div class="side-bt">Сериалы</div>
					<div class="side-bc flex-row">
{*
{custom template="custom-side" order="read" days="30" limit="9" cache="yes"}
*}
{custom categoryexclude="38" template="custom-side" limit="9" order="views_top_3" sort="asc"}
					</div>
				</div>

				<div class="side-box">
					<div class="side-bt">Комментарии</div>
					<div class="side-bc">
						{customcomments authorexclude="admin,admin2,admin3,Арман" idexclude="1990" template="lastcomments" order="date" from="0" limit="5" sort="desc" cache="yes"}
					</div>
				</div>
				{*
				<div class="side-box">
					<div class="side-bt">Актуальное</div>
					<div class="side-bc flex-row">
						{custom template="custom-side" limit="9" order="views_top_3"}
					</div>
				</div>
				*}
			</aside>

			<!-- END SIDE -->

		</div>

		<!-- END COLS -->

		<div class="header flex-row">
			<a href="/" class="logotype" title="На главную"><img src="{THEME}/images/logo.svg" alt="название" /></a>
			<div class="search-wrap">
				<form id="quicksearch" method="post">
					<input type="hidden" name="do" value="search" />
					<input type="hidden" name="subaction" value="search" />
					<div class="search-box">
						<input id="story" name="story" placeholder="Название..." type="text" />
						<button type="submit" title="Найти"><span class="fa fa-search"></span></button>
					</div>
				</form>
			</div>
			<div class="login-btns icon-l">
			<button id="theme-toggle" title="Переключить тему">🌙</button>
				[group=5]
					<a class="auth_show_register" href="#">Регистрация</a>
					<div class="button auth_show_login"><span class="fa fa-user"></span><span>Войти</span></div>
				[/group]
				[not-group=5]
					<a href="/?action=logout">Выйти</a>
					<div class="button show-login"><span class="fa fa-cog"></span><span>Мой кабинет</span></div>
				[/not-group]
			</div>
		</div>

		<!-- END HEADER -->
	{*
		<ul class="f-menu clearfix">
			<li><a href="#" class="active">Активная</a></li>
			<li><a href="#" data-label="28">Ссылка</a></li>
			<li><a href="#">Ссылка</a></li>
			<li><a href="#">Ссылка</a></li>
			<li><a href="#">Ссылка</a></li>
			<li><a href="#">Ссылка</a></li>
		</ul>
		*}
		<div class="karusel">
			<div class="owl-carousel" id="owl-top">
                [not-available=cat|showfull]
				{custom template="custom-carou" limit="15" order="rand" cache="yes"}
                [/not-available]
                [available=cat]
                [category={category-id}]{custom categoryexclude="38" category="{category-id}" template="custom-carou-not" limit="15" order="views_top_3" sort="desc" cache="yes"}[/category]
                [/available]

				[available=showfull]
                [category={category-id}]{custom categoryexclude="38" category="{category-id}" template="custom-carou-not" limit="15" order="views_top_7" sort="desc" cache="yes"}[/category]
                [/available]
			</div>
		</div>

		<div class="footer flex-row center">
			<div class="fleft">
				<div>По вопросам нарушения авторских прав обращаться на эл.почту: <a target="_blank" href="mailto:abuse@russerialov.net">abuse@russerialov.net</a> <br>Копирование текстов и видеоматериалов запрещено!</div>
				<a href="/index.php?do=feedback" title="Обратная связь" rel="nofollow">Обратная связь</a>
                <a href="/pravoobladateljam.html" title="Правообладателям" rel="nofollow">Правообладателям</a>
				<a href="/privacy.html" title="Правообладателям" rel="nofollow">Политика конфиденциальности</a>
				<a href="/terms.html" title="Правообладателям" rel="nofollow">Пользовательское соглашение</a>
				<a href="/sitemap.xml">Карта сайта</a>
			</div>
			<div class="fright">

			</div>
		</div>

		<!-- END FOOTER -->

	</div>

	<!-- END BLOCK CENTER -->

</div>

<!-- END WRAP -->

{login}
{jsfiles}
<script src="{THEME}/js/libs.js?v=1.97"></script>
{AJAX}
[available=actors]<script src="{THEME}/js/fslightbox.js?v=1.0"></script>[/available]


[available=showfull]
<script type="text/javascript" src="{THEME}/playlists/player.js?v=5.0.20"></script>
[/available]

<!--LiveInternet counter--><a href="https://www.liveinternet.ru/click"
target="_blank"><img id="licntEBBD" width="88" height="31" style="border:0;display:none;"
title="LiveInternet"
src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7"
alt=""/></a><script>(function(d,s){d.getElementById("licntEBBD").src=
"https://counter.yadro.ru/hit?t57.6;r"+escape(d.referrer)+
((typeof(s)=="undefined")?"":";s"+s.width+"*"+s.height+"*"+
(s.colorDepth?s.colorDepth:s.pixelDepth))+";u"+escape(d.URL)+
";h"+escape(d.title.substring(0,150))+";"+Math.random()})
(document,screen)</script><!--/LiveInternet-->

[group=5]
    {*
<!-- Yandex.RTB R-A-3581346-5 -->
<script>
window.yaContextCb.push(()=>{
	Ya.Context.AdvManager.render({
		"blockId": "R-A-3581346-5",
		"type": "fullscreen",
		"platform": "touch"
	})
})
</script>
*}
[/group]


 <!-- Yandex.Metrika counter --> <script type="text/javascript">     (function(m,e,t,r,i,k,a){         m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};         m[i].l=1*new Date();         for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}         k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)     })(window, document,'script','https://mc.yandex.ru/metrika/tag.js', 'ym');      ym(95310223, 'init', {clickmap:true, referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true}); </script> <noscript><div><img src="https://mc.yandex.ru/watch/95310223" style="position:absolute; left:-9999px;" alt="" /></div></noscript> <!-- /Yandex.Metrika counter -->
</body>
</html>
