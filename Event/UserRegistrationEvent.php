<?php

namespace UserBundle\Event;

/**
 * Событие на регистрацию пользователя.
 *
 * Срабатывает сразу же после сохранения нового пользователя в БД и сразу же после генерации кода подтверждения e-mail.
 *
 * На данном этапе статус пользователя - "Требует активации". Модель checker события имеет код подтверждения e-mailа.
 *
 * @package UserBundle\Event
 */
class UserRegistrationEvent extends UserCheckerEvent
{
    /**
     * Название события
     */
    const NAME = 'user.registration';
}
