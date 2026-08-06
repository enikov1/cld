<div class="login-overlaynew" id="loginOverlay">
    <div class="login-modal">
        <button class="dontusebuttonclass login-modal__close js-login-close" type="button" aria-label="Закрыть">×</button>

        [auth_notice]
            <div class="auth-notice">{auth_notice|raw}</div>
        [/auth_notice]

        [auth_login_enabled]
        <div class="login-modal__panel" data-auth-panel="login">
            <div class="login-modal__head">
                <div class="login-modal__icon"><span class="fa fa-user"></span></div>
                <div class="login-modal__title">{auth_ui_login_title}</div>
            </div>
            <div class="auth-form-feedback" hidden data-form-feedback></div>
            [auth_errors_list]
                <div class="auth-errors">
                    [loop auth_errors_list]
                        <div>{item.message}</div>
                    [/loop]
                </div>
            [/auth_errors_list]
            <form action="/login" method="post" class="login-form">
                <input type="hidden" name="_token" value="{csrf_token|raw}">
                <div class="login-form__field">
                    <span class="fa fa-envelope"></span>
                    <input class="dontuseinputclass" type="email" name="email" placeholder="Email" required value="{auth_email}">
                </div>
                <div class="login-form__field">
                    <span class="fa fa-lock"></span>
                    <input class="dontuseinputclass" type="password" name="password" placeholder="Пароль" required>
                </div>
                <div class="login-form__row">
                    <label class="login-remember">
                        <input type="checkbox" name="remember" value="1">
                        <span></span>
                        Запомнить меня
                    </label>
                    [auth_password_reset_enabled]
                    <button type="button" class="dontusebuttonclass js-auth-switch login-forgot" data-auth-open="forgot">Забыли пароль?</button>
                    [/auth_password_reset_enabled]
                </div>
                <button type="submit" class="dontusebuttonclass login-form__submit">Войти на сайт</button>
            </form>
            <div class="login-modal__bottom">
                Нет аккаунта?
                [auth_register_enabled]
                <button type="button" class="dontusebuttonclass js-auth-switch" data-auth-open="register">Регистрация</button>
                [/auth_register_enabled]
            </div>
        </div>
        [/auth_login_enabled]

        [auth_register_enabled]
        <div class="login-modal__panel" data-auth-panel="register" hidden>
            <div class="login-modal__head">
                <div class="login-modal__icon"><span class="fa fa-user-plus"></span></div>
                <div class="login-modal__title">{auth_ui_register_title}</div>
            </div>
            <div class="auth-form-feedback" hidden data-form-feedback></div>
            [auth_errors_list]
                <div class="auth-errors">
                    [loop auth_errors_list]
                        <div>{item.message}</div>
                    [/loop]
                </div>
            [/auth_errors_list]
            <form action="/register" method="post" class="login-form">
                <input type="hidden" name="_token" value="{csrf_token|raw}">
                <div class="login-form__field">
                    <span class="fa fa-user"></span>
                    <input class="dontuseinputclass" type="text" name="name" placeholder="Имя" required>
                </div>
                <div class="login-form__field">
                    <span class="fa fa-envelope"></span>
                    <input class="dontuseinputclass" type="email" name="email" placeholder="Email" required>
                </div>
                <div class="login-form__field">
                    <span class="fa fa-lock"></span>
                    <input class="dontuseinputclass" type="password" name="password" placeholder="Пароль" required>
                </div>
                <div class="login-form__field">
                    <span class="fa fa-lock"></span>
                    <input class="dontuseinputclass" type="password" name="password_confirmation" placeholder="Повтор пароля" required>
                </div>
                <button type="submit" class="dontusebuttonclass login-form__submit">Создать аккаунт</button>
            </form>
            <div class="login-modal__bottom">
                Уже есть аккаунт?
                <button type="button" class="dontusebuttonclass js-auth-switch" data-auth-open="login">Войти</button>
            </div>
        </div>
        [/auth_register_enabled]

        [auth_password_reset_enabled]
        <div class="login-modal__panel" data-auth-panel="forgot" hidden>
            <div class="login-modal__head">
                <div class="login-modal__icon"><span class="fa fa-envelope"></span></div>
                <div class="login-modal__title">{auth_ui_forgot_title}</div>
            </div>
            <div class="auth-form-feedback" hidden data-form-feedback></div>
            [auth_errors_list]
                <div class="auth-errors">
                    [loop auth_errors_list]
                        <div>{item.message}</div>
                    [/loop]
                </div>
            [/auth_errors_list]
            <p class="login-modal__hint">{auth_ui_forgot_hint}</p>
            <form action="/password/email" method="post" class="login-form">
                <input type="hidden" name="_token" value="{csrf_token|raw}">
                <div class="login-form__field">
                    <span class="fa fa-envelope"></span>
                    <input class="dontuseinputclass" type="email" name="email" placeholder="Email" required value="{auth_email}">
                </div>
                <button type="submit" class="dontusebuttonclass login-form__submit">Отправить ссылку</button>
            </form>
            <div class="login-modal__bottom">
                <button type="button" class="dontusebuttonclass js-auth-switch" data-auth-open="login">Вернуться ко входу</button>
            </div>
        </div>

        <div class="login-modal__panel" data-auth-panel="reset" hidden>
            <div class="login-modal__head">
                <div class="login-modal__icon"><span class="fa fa-lock"></span></div>
                <div class="login-modal__title">{auth_ui_reset_title}</div>
            </div>
            <div class="auth-form-feedback" hidden data-form-feedback></div>
            [auth_errors_list]
                <div class="auth-errors">
                    [loop auth_errors_list]
                        <div>{item.message}</div>
                    [/loop]
                </div>
            [/auth_errors_list]
            <form action="/password/reset" method="post" class="login-form" id="passwordResetForm">
                <input type="hidden" name="_token" value="{csrf_token|raw}">
                <input type="hidden" name="token" value="{reset_token}" id="resetTokenField">
                <div class="login-form__field">
                    <span class="fa fa-envelope"></span>
                    <input class="dontuseinputclass" type="email" name="email" placeholder="Email" required value="{auth_email}" id="resetEmailField">
                </div>
                <div class="login-form__field">
                    <span class="fa fa-lock"></span>
                    <input class="dontuseinputclass" type="password" name="password" placeholder="Новый пароль" required>
                </div>
                <div class="login-form__field">
                    <span class="fa fa-lock"></span>
                    <input class="dontuseinputclass" type="password" name="password_confirmation" placeholder="Повтор пароля" required>
                </div>
                <button type="submit" class="dontusebuttonclass login-form__submit">Сохранить пароль</button>
            </form>
            <div class="login-modal__bottom">
                <button type="button" class="dontusebuttonclass js-auth-switch" data-auth-open="login">Войти</button>
            </div>
        </div>
        [/auth_password_reset_enabled]
    </div>
</div>
