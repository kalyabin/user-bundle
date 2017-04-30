<?php

namespace UserBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Валидатор e-mailа.
 *
 * Если передать needExists = true, то будет проверять, что пользвоатель существует.
 * Иначе будет проверять, что пользователь не существует.
 *
 * @Annotation
 *
 * @Target({"PROPERTY"})
 *
 * @package UserBundle\Validator\Constraints
 */
class UserEmail extends Constraint
{
    /**
     * @var boolean Если true, будет проверять, что пользователь с e-mailом существуе, иначе - не существует
     */
    public $needExists;

    /**
     * @var string|callable Callback-функция, используемая для определения ID пользователя, который нужно пропустить
     */
    public $excludeCallback = 'getId';

    /**
     * @var string Сообщение об ошибке
     */
    public $message;

    /**
     * @return string
     */
    public function validatedBy()
    {
        return UserEmailValidator::class;
    }
}
