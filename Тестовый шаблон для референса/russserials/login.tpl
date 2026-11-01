<!--noindex-->
[not-group=5]
	<div class="login-box" id="login-box" title="{login}">
		<div class="lb-user">
			<div class="lb-ava img-box" id="lb-ava"><img src="{foto}" title="{login}" alt="{login}" /></div>
			<div class="lb-name">
				<div>{login}</div>
				[group=1]<a href="{admin-link}" target="_blank">Админпанель</a>[/group]
			</div>
		</div>
		<ul class="lb-menu icon-l">
			<li><a href="{profile-link}"><span class="fa fa-cog"></span>Мой профиль</a></li>
			<li><a href="{favorites-link}"><span class="fa fa-heart-o"></span>Мои закладки ({favorite-count})</a></li>
			<li><a href="{logout-link}"><span class="fa fa-sign-out"></span>Выйти</a></li>
		</ul>
	</div>
[/not-group]
[group=5]
	<div class="auth_modal">
	<div class="auth_modal__wrapper">
		<div class="auth_modal__close">
			<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M2 2L12 12M2 12L12 2" stroke="#C6C6C6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>
        <div class="auth_modal__content">
            <div class="auth_modal__title">Войти на сайт</div>
			<div class="auth_modal__row">
				<form id="auth_login" method="POST" class="auth_modal__form" action="#">
					<div class="auth_modal__field">
						<label for="auth_log_login">Логин</label>
						<input id="auth_log_login" type="text">
					</div>
					<div class="auth_modal__field">
						<label for="auth_log_password">Пароль</label>
						<input id="auth_log_password" type="password">
					</div>
					<label class="auth_modal__checked">
						<input type="checkbox" id="login_not_save" value="1">
						<span class="check"></span>
						<span class="text">Не запоминать меня</span>
					</label>
					<div id="auth_log_result" class="auth_modal_result">

					</div>
					<div class="auth_modal__submit">
						<button type="submit" class="auth_modal__btn-accent">Войти на сайт</button>
						<button id="auth_reg_open" type="button" class="auth_modal__btn-light">Зарегистрироваться</button>
					</div>
					<div class="auth_modal__bottom">
						<a href="/index.php?do=lostpassword" target="_blank">Забыли пароль?</a>
					</div>
				</form>
				<form id="auth_register" action="#" method="POST" class="auth_modal__form">
					<div class="auth_modal__field">
						<label for="auth_register_login">Логин</label>
						<input id="auth_register_login" type="text">
					</div>
					<div class="auth_modal__field">
						<label for="auth_register_password">Пароль</label>
						<input id="auth_register_password" type="password">
					</div>
					<div class="auth_modal__field">
						<label for="auth_register_password2">Повторите пароль</label>
						<input id="auth_register_password2" type="password">
					</div>
					<div class="auth_modal__field">
						<label for="auth_register_email">E-mail</label>
						<input id="auth_register_email" type="email" autocomplete="off">
					</div>
					<div class="captcha">
						<div class="captcha__image-reload">
							<img class="captcha__image" src="/engine/ajax/controller.php?mod=auth_capcha" width="132" alt="captcha">
							<span class="captcha__refresh"></span>
						</div>
						<div class="captcha__group">
							<div class="auth_modal__field">
								<label for="captcha">Код, изображенный на картинке</label>
								<input id="captcha" name="captcha" type="text">
							</div>
							<div class="invalid-feedback"></div>
						</div>
					</div>
					<div id="auth_register_result" class="auth_modal_result">

					</div>
					<div class="auth_modal__submit">
						<button type="submit" class="auth_modal__btn-accent">Зарегистрироваться</button>
						<button id="auth_login_open" type="button" class="auth_modal__btn-light">Войти</button>
					</div>
				</form>
			</div>
			
		</div>
	</div>
</div>
[/group]
<!--/noindex-->