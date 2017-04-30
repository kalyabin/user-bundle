<?php

namespace UserBundle\Validator\Constraints;

use UserBundle\Entity\UserEntity;
use UserBundle\Entity\Repository\UserRepository;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Validator\Constraint;

/**
 * Валидатор пользователя по e-mail.
 *
 * Если передать needExists = true, то будет проверять, что пользвоатель существует.
 * Иначе будет проверять, что пользователь не существует.
 *
 * @package UserBundle\Validator\Constraints
 */
class UserEmailValidator extends ConstraintValidator
{
    /**
     * @var ObjectManager
     */
    protected $em;

    /**
     * Конструктор для класса
     *
     * @param ObjectManager $em
     */
    public function __construct(ObjectManager $em)
    {
        $this->em = $em;
    }

    /**
     * Возвращает true, если пользователь с указанным e-mail существует
     *
     * @param string $email
     * @param integer $id Идентификатор текущего пользователя или null
     *
     * @return bool
     */
    protected function checkUserIsExists($email, $id = null)
    {
        /** @var \UserBundle\Entity\Repository\UserRepository $repository */
        $repository = $this->em->getRepository(UserEntity::class);
        return $repository->userIsExistsByEmail($email, $id);
    }

    /**
     * @inheritdoc
     */
    public function validate($value, Constraint $constraint)
    {
        $excludedId = null;

        $object = $this->context->getObject();

        /** @var UserEmail $constraint */
        if (is_string($constraint->excludeCallback) && method_exists($object, $constraint->excludeCallback)) {
            $excludedId = $object->{$constraint->excludeCallback}();
        }
        elseif (is_callable($constraint->excludeCallback)) {
            $excludedId = call_user_func($constraint->excludeCallback);
        }

        $userExists = $this->checkUserIsExists($value, $excludedId);

        if ($userExists != $constraint->needExists) {
            $this->context->addViolation($constraint->message, [
                '%string%' => $value
            ]);
        }
    }
}
