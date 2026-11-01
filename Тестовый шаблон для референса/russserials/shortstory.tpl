<div class="short clearfix with-mask">
	<div class="short-img img-box">
		[xfgiven_poster]<img data-src="[xfvalue_poster]" alt="{title}">[/xfgiven_poster]
		[xfnotgiven_poster]<img data-src="[xfvalue_thumb_url_poster2]" alt="{title}">[/xfnotgiven_poster]
		<div class="mask flex-col ps-link" data-href="{full-link}">
			<span class="fa fa-play"></span>
		</div>

		[xfgiven_last_epizode]
		[catlist=40]<div class="badge_tr">[xfvalue_last_epizode] выпуск</div>[/catlist]
		[not-catlist=40]<div class="badge_tr">[xfvalue_last_epizode] серия</div>[/not-catlist]
		[/xfgiven_last_epizode]
	</div>
	<div class="short-text">
		<a class="short-title" href="{full-link}">{title}</a>
		<div class="to-fav">
			[group=5]<span class="fa fa-star fav-guest" title="Добавить в закладки"></span>[/group]
			[add-favorites]<span class="fa fa-star" title="Добавить в закладки"></span>[/add-favorites]
			[del-favorites]<span class="fa fa-star-o" title="Убрать из закладок"></span>[/del-favorites]
		</div>
		<div class="short-desc">
			<div class="sd-line"><span>Описание:</span> [xfvalue_description limit="400"]...</div>
		</div>
	</div>
	<div class="short-bottom flex-row">
		{rating}
		<div class="button ps-link" data-href="{full-link}">Смотреть онлайн</div>
	</div>
</div>