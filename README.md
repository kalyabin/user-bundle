# user-bundle
Модуль для работы с пользователями в symfony

Авторизация, регистрация, профиль, восстановление пароля, изменение e-mail, изменение пароля.

Зависит от:

https://github.com/kalyabin/symfony-test-helper
https://github.com/kalyabin/http-helper-bundle

Конфигурация в config.yml:
```yml
framework:
    form:
        csrf_protection: false
```

Конфигурация в routing.yml:

```yml
user:
    resource: "@UserBundle/Controller/"
    type:     annotation
```

Конфигурация security.yml:
```yml
security:

    # http://symfony.com/doc/current/book/security.html#where-do-users-come-from-user-providers
    providers:
        user_provider:
            id: user.provider

    encoders:
        UserBundle\Entity\UserEntity: sha512

    firewalls:
        # disables authentication for assets and the profiler, adapt it according to your needs
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        main:
            pattern: ^/
            anonymous: ~
            logout:
                path: /logout
                target: /
            simple_form:
                provider: user_provider
                authenticator: user.simple_authenticator
                login_path: login.simple_form
                check_path: login.simple_check
                success_handler: authentication_listener
                failure_handler: authentication_listener
```

Конфигурация для шаблонов писем:
```yml
twig:
    paths:
        '%kernel.root_dir%/../src/UserBundle/Resources/views/Emails': 'user_emails'
```

Конфигурация в config_test.yml:
```yml
parameters:
    # в тестах отключить проверку на CSRF-заголовки
    csrf_protection_listener: HttpHelperBundle\Listener\IgnoreCsrfHeaderProtectionListener
framework:
    test: ~
    session:
        handler_id: ~
        storage_id: session.storage.mock_file
        name: MOCKSESSID
liip_functional_test: ~
```
