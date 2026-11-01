[aviable=lastcomments]<div class="last-comm-link">{news_title}</div>[/aviable]
<div class="comm-item clearfix [pinned]comment-item--pinned[/pinned] [best-comment]comment-item--best[/best-comment]">

	<div class="comm-left img-box">
		<img src="{foto}" alt="{login}"/>
	</div>
	<div class="comm-right">
		<div class="comm-one clearfix">
			[not-fullname]<span class="comm-author">{login}</span>[/not-fullname]
            [fullname]<span class="comm-author">{fullname}</span>[/fullname]
			<span> Добавлен: {date=d F Y H:i}</span>
			[pinned]📌 [/pinned][best-comment]🏆 [/best-comment]
			[rating-type-3]
			<div class="rate3-comm clearfix">
				[rating-minus]<span class="fa fa-minus"></span>[/rating-minus]
				{rating}
				[rating-plus]<span class="fa fa-plus"></span>[/rating-plus]
			</div> 
			[/rating-type-3]
		</div>
		<div class="comm-two clearfix">
			{comment}
		</div>
		<ul class="comm-three icon-l clearfix">
			<li><span class="fa fa-reply"></span>[reply]Ответить[/reply]</li>
		[not-group=5]
		    <li[not-treecomments] class="reply"[/not-treecomments]>[complaint]Жалоба[/complaint]</li>
			<li>[spam]Спам[/spam]</li>
		[/not-group]
		[group=1]
			<li>[com-edit]Редактировать[/com-edit]</li>
			<li>[com-del]Удалить[/com-del]</li>
			<li>{mass-action}</li>
		[/group]
		[pin-btn]📌[/pin-btn] [spoiler-btn]⚠️[/spoiler-btn]
		</ul>
	</div>
</div>