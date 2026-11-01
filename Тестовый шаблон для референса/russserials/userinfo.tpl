<div class="user-prof">
	<div class="up-first">
		<h1 class="nowrap">Пользователь: {usertitle}</h1>
		<div class="up-group">Группа: {status} [time_limit]&nbsp;В группе до: {time_limit}[/time_limit]</div>
		<div class="up-img img-box"><img src="{foto}" alt=""/></div>
		<div class="up-status">
			[online]<p class="online">В сети</p>[/online]
			[offline]<p class="offline">Не в сети</p>[/offline]
		</div>
	</div>
	<ul class="up-second flex-row">
		<li>{comm-num} <p>Комментариев</p></li>
	</ul>
	<ul class="up-third">
		<li>Регистрация: {registration}</li>
		<li>Заходил(а): {lastdate}</li>
		[comm-num]<li>{comments}</li>[/comm-num]
		[not-group=5]
		[fullname]<li>Полное имя: {fullname}</li>[/fullname]
		[/not-group]
	</ul>
	[not-logged]<div class="up-edit"> {edituser} </div>[/not-logged]
</div>

<div id="options" style="display:none; margin-bottom: 30px" class="form-wrap">
	<h1>Редактирование профиля:</h1>
	<div class="form-item clearfix">
		<label>Ваше Имя:</label>
		<input type="text" name="fullname" value="{fullname}" placeholder="Ваше Имя" />
	</div>
	<div class="form-item clearfix">
		<label>Ваш E-Mail:</label>
		<input type="text" name="email" value="{editmail}" placeholder="Ваш E-Mail: {editmail}" />
	</div>
	<div class="form-checks">
		{hidemail}
		<input style="margin-left: 50px" type="checkbox" id="subscribe" name="subscribe" value="1" /> 
		<label for="subscribe">Отписаться от подписанных новостей</label>
	</div>
	<div class="form-item clearfix">
		<label>Место жительства:</label>
		<input type="text" name="land" value="{land}" placeholder="Место жительства" />
	</div>
	<div class="form-item clearfix">
		<label>Старый пароль:</label>
		<input type="password" name="altpass" placeholder="Старый пароль" />
	</div>
	<div class="form-item clearfix">
		<label>Новый пароль:</label>
		<input type="password" name="password1" placeholder="Новый пароль" />
	</div>
	<div class="form-item clearfix">
		<label>Повторите пароль:</label>
		<input type="password" name="password2" placeholder="Повторите Новый пароль" />
	</div>
	<div class="form-item clearfix">
		<label>Аватар:</label>
		<input type="file" name="image" size="28" />
	</div>
	<div class="form-checks">
		<input type="checkbox" name="del_foto" id="del_foto" value="yes" /> 
		<label for="del_foto">Удалить аватар</label>
	</div>
	<div class="form-item clearfix">
		<label>Часовой пояс:</label>
		{timezones}
	</div>
	<div class="form-textarea">
		<label>О себе:</label>
		<textarea name="info" rows="5">{editinfo}</textarea>
	</div>
	<div class="form-textarea">
		<label>Подпись:</label>
		<textarea name="signature" rows="5">{editsignature}</textarea>
	</div>
	<div class="form-xfield">
		<table class="tableform">{xfields}</table>
	</div>
	
	<div class="form-checks">{comments-reply-subscribe}</div>
	<div class="form-checks">{unsubscribe}</div>

	<div class="form-submit">
		<button name="submit" type="submit">Отправить</button>
		<input name="submit" type="hidden" id="submit" value="submit" />
	</div>
</div>