<?php

namespace UserBundle\Event;

/**
 * Событие на запрос о восстановлении пароля.
 *
 * Срабатывает сразу же, как только пользователь запросил восстановление пароля.
 *
 * Модель checker содержит подтверждение e-mail для изменения пароля.
 *
 * @package UserBundle\Event
 */
class UserRememberPasswordEvent extends UserCheckerEvent
{
    /**
     * Название события
     */
    const NAME = 'user.remember_password';
}
