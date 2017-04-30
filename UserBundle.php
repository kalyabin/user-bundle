<?php

namespace UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use UserBundle\DependencyInjection\UserExtension;

/**
 * Модуль пользователей: авторизация, логин и профиль.
 *
 * @package UserBundle
 */
class UserBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new UserExtension();
    }
}
