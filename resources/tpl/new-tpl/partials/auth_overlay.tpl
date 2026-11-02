<div class="login-overlaynew" id="loginOverlay">
    <div class="login-modal">
        <button class="dontusebuttonclass login-modal__close js-login-close" type="button" aria-label="Закрыть">×</button>

        [auth_notice]
            <div class="auth-notice">{auth_notice|raw}</div>
        [/auth_notice]

        [auth_login_enabled]
        <div class="login-modal__panel" data-auth-panel="login">
            <div class="auth_modal__content">
                <div class="auth_modal__title">{auth_ui_login_title}</div>
                <div class="auth-form-feedback" hidden data-form-feedback></div>
                [auth_errors_list]
                    <div class="auth_modal_result">
                        [loop auth_errors_list]
                            <div>{item.message}</div>
                        [/loop]
                    </div>
                [/auth_errors_list]
                <form action="/login" method="post" class="login-form auth_modal__form">
                    <input type="hidden" name="_token" value="{csrf_token|raw}">
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="email" name="email" placeholder="Email" required value="{auth_email}">
                    </div>
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="password" name="password" placeholder="Пароль" required>
                    </div>
                    <label class="auth_modal__checked">
                        <input type="checkbox" name="remember" value="1">
                        <span class="check"></span>
                        <span class="text">Запомнить меня</span>
                    </label>
                    <div class="auth_modal__submit">
                        <button type="submit" class="dontusebuttonclass login-form__submit auth_modal__btn-accent">Войти на сайт</button>
                    </div>
                    <div class="login-modal__bottom auth_modal__bottom">
                        [auth_password_reset_enabled]
                        <button type="button" class="dontusebuttonclass js-auth-switch" data-auth-open="forgot">Забыли пароль?</button>
                        [/auth_password_reset_enabled]
                        [auth_register_enabled]
                        <button type="button" class="dontusebuttonclass js-auth-switch" data-auth-open="register">Регистрация</button>
                        [/auth_register_enabled]
                    </div>
                </form>
            </div>
        </div>
        [/auth_login_enabled]

        [auth_register_enabled]
        <div class="login-modal__panel" data-auth-panel="register" hidden>
            <div class="auth_modal__content">
                <div class="auth_modal__title">{auth_ui_register_title}</div>
                <div class="auth-form-feedback" hidden data-form-feedback></div>
                [auth_errors_list]
                    <div class="auth_modal_result">
                        [loop auth_errors_list]
                            <div>{item.message}</div>
                        [/loop]
                    </div>
                [/auth_errors_list]
                <form action="/register" method="post" class="login-form auth_modal__form">
                    <input type="hidden" name="_token" value="{csrf_token|raw}">
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="text" name="name" placeholder="Имя" required>
                    </div>
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="password" name="password" placeholder="Пароль" required>
                    </div>
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="password" name="password_confirmation" placeholder="Повтор пароля" required>
                    </div>
                    <div class="auth_modal__submit">
                        <button type="submit" class="dontusebuttonclass login-form__submit auth_modal__btn-accent">Создать аккаунт</button>
                        <button type="button" class="dontusebuttonclass js-auth-switch auth_modal__btn-light" data-auth-open="login">Войти</button>
                    </div>
                </form>
            </div>
        </div>
        [/auth_register_enabled]

        [auth_password_reset_enabled]
        <div class="login-modal__panel" data-auth-panel="forgot" hidden>
            <div class="auth_modal__content">
                <div class="auth_modal__title">{auth_ui_forgot_title}</div>
                <div class="auth-form-feedback" hidden data-form-feedback></div>
                [auth_errors_list]
                    <div class="auth_modal_result">
                        [loop auth_errors_list]
                            <div>{item.message}</div>
                        [/loop]
                    </div>
                [/auth_errors_list]
                <form action="/password/email" method="post" class="login-form auth_modal__form">
                    <input type="hidden" name="_token" value="{csrf_token|raw}">
                    <p class="auth_modal__text">{auth_ui_forgot_hint}</p>
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="email" name="email" placeholder="Email" required value="{auth_email}">
                    </div>
                    <div class="auth_modal__submit">
                        <button type="submit" class="dontusebuttonclass login-form__submit auth_modal__btn-accent">Отправить ссылку</button>
                    </div>
                    <div class="login-modal__bottom auth_modal__bottom">
                        <button type="button" class="dontusebuttonclass js-auth-switch" data-auth-open="login">Вернуться ко входу</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="login-modal__panel" data-auth-panel="reset" hidden>
            <div class="auth_modal__content">
                <div class="auth_modal__title">{auth_ui_reset_title}</div>
                <div class="auth-form-feedback" hidden data-form-feedback></div>
                [auth_errors_list]
                    <div class="auth_modal_result">
                        [loop auth_errors_list]
                            <div>{item.message}</div>
                        [/loop]
                    </div>
                [/auth_errors_list]
                <form action="/password/reset" method="post" class="login-form auth_modal__form" id="passwordResetForm">
                    <input type="hidden" name="_token" value="{csrf_token|raw}">
                    <input type="hidden" name="token" value="{reset_token}" id="resetTokenField">
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="email" name="email" placeholder="Email" required value="{auth_email}" id="resetEmailField">
                    </div>
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="password" name="password" placeholder="Новый пароль" required>
                    </div>
                    <div class="auth_modal__field">
                        <input class="dontuseinputclass" type="password" name="password_confirmation" placeholder="Повтор пароля" required>
                    </div>
                    <div class="auth_modal__submit">
                        <button type="submit" class="dontusebuttonclass login-form__submit auth_modal__btn-accent">Сохранить пароль</button>
                    </div>
                    <div class="login-modal__bottom auth_modal__bottom">
                        <button type="button" class="dontusebuttonclass js-auth-switch" data-auth-open="login">Войти</button>
                    </div>
                </form>
            </div>
        </div>
        [/auth_password_reset_enabled]
    </div>
</div>
