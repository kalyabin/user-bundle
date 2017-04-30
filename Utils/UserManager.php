<?php

namespace UserBundle\Utils;

use InvalidArgumentException;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;
use UserBundle\Event\UserActivationEvent;
use UserBundle\Event\UserChangedPasswordEvent;
use UserBundle\Event\UserChangeEmailEvent;
use UserBundle\Event\UserRegistrationEvent;
use UserBundle\Event\UserRememberPasswordEvent;

/**
 * Сервис для работы с пользователями
 *
 * @package UserBundle\Utils
 */
class UserManager
{
    /**
     * @var EncoderFactoryInterface Фабрика для энкодера
     */
    protected $encoderFactory;

    /**
     * @var ObjectManager Менеджер для сущностей
     */
    protected $entityManager;

    /**
     * @var EventDispatcherInterface Диспатчер системных событий
     */
    protected $eventDispatcher;

    /**
     * Конструктор
     *
     * @param EncoderFactoryInterface $encoderFactory Фабрика для энкодера
     * @param ObjectManager $entityManager Менеджер сущностей
     * @param EventDispatcherInterface $eventDispatcher Диспатчер системных событий
     */
    public function __construct(EncoderFactoryInterface $encoderFactory, ObjectManager $entityManager, EventDispatcherInterface $eventDispatcher)
    {
        $this->encoderFactory = $encoderFactory;
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Установить пользователю закодированный пароль
     *
     * @param UserEntity $user Модель пользователя
     * @param string $password Незакодированный пароль, который нужно установить
     */
    public function encodeUserPassword(UserEntity $user, $password)
    {
        $encoder = $this->encoderFactory->getEncoder($user);
        if (empty($user->getSalt())) {
            // сгенерировать соль, если еще не сгенерирована
            $user->generateSalt();
        }
        $user->setPassword($encoder->encodePassword($password, $user->getSalt()));
    }

    /**
     * Создает код подтверждения по типу, либо получает уже существующий
     *
     * @param string $type Тип кода подтверждения
     * @param UserEntity $user Пользователь
     *
     * @return UserCheckerEntity
     */
    public function createCheckerByType($type, UserEntity $user)
    {
        $checker = $user->getCheckerByType($type);
        if (empty($checker)) {
            $checker = new UserCheckerEntity();
            $checker
                ->setUser($user)
                ->setType($type)
                ->generateCode();

            $user->addChecker($checker);
        }
        return $checker;
    }

    /**
     * Зарегистрировать пользователя
     *
     * @param UserEntity $user
     *
     * @return UserEntity
     */
    public function registerUser(UserEntity $user)
    {
        if (!empty($user->getId())) {
            throw new InvalidArgumentException('User already registered');
        }

        $user->setStatus(UserEntity::STATUS_NEED_ACTIVATION);

        $checker = $this->createCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE, $user);

        $user->generateSalt();

        // закодировать пароль
        $this->encodeUserPassword($user, $user->getPassword());

        $this->entityManager->persist($user);
        $this->entityManager->persist($checker);

        $this->entityManager->flush();

        // триггер
        $event = new UserRegistrationEvent($user, $checker);
        $this->eventDispatcher->dispatch(UserRegistrationEvent::NAME, $event);

        return $user;
    }

    /**
     * Удалить код проверки по типу и зафиксировать изменения в EntityManager
     *
     * @param UserEntity $user
     * @param string $type
     */
    protected function removeCheckerByType(UserEntity $user, $type)
    {
        $checker = $user->getCheckerByType($type);

        if ($checker) {
            $user->removeCheckerByType($type);
            $this->entityManager->remove($checker);
            $this->entityManager->flush();
        }
    }

    /**
     * Активировать пользователя
     *
     * @param UserEntity $user
     *
     * @return UserEntity
     */
    public function activateUser(UserEntity $user)
    {
        $user->setStatus(UserEntity::STATUS_ACTIVE);
        $this->removeCheckerByType($user, UserCheckerEntity::TYPE_ACTIVATION_CODE);

        $this->entityManager->persist($user);

        $this->entityManager->flush();

        $event = new UserActivationEvent($user);
        $this->eventDispatcher->dispatch(UserActivationEvent::NAME, $event);

        return $user;
    }

    /**
     * Сгенерировать код подтверждения e-mail и отправить событие о восстановлении пароля
     *
     * @param UserEntity $user
     *
     * @return UserEntity
     */
    public function rememberPassword(UserEntity $user)
    {
        $checker = $this->createCheckerByType(UserCheckerEntity::TYPE_REMEMBER_PASSWORD, $user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($checker);

        $this->entityManager->flush();

        // триггер
        $event = new UserRememberPasswordEvent($user, $checker);
        $this->eventDispatcher->dispatch(UserRememberPasswordEvent::NAME, $event);

        return $user;
    }

    /**
     * Изменить пароль пользвателя.
     *
     * Изменяет пароль на указанный, хеширует его и удаляет модель кода подтверждения изменения пароля, если он есть.
     * Отправляет событие при изменении.
     *
     * @param UserEntity $user Модель пользователя
     * @param string $password Новый пароль
     *
     * @return UserEntity
     */
    public function changePassword(UserEntity $user, $password)
    {
        // закешировать пароль
        $user->generateSalt();
        $this->encodeUserPassword($user, $password);

        // удалить код проверки по паролю, если есть
        $this->removeCheckerByType($user, UserCheckerEntity::TYPE_REMEMBER_PASSWORD);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // триггер
        $event = new UserChangedPasswordEvent($user, $password);
        $this->eventDispatcher->dispatch(UserChangedPasswordEvent::NAME, $event);

        return $user;
    }

    /**
     * Проверить код проверки и получить модель пользователя для кода проверки.
     *
     * Если тип и код проверки совпадают с заданными - возвращает модель пользвоателя.
     *
     * В противном случае - увеличивает счетчик просмотра кода проверки.
     * Если счетчик просмотра превышает допустимые значения - удаляет код проверки.
     *
     * @param UserCheckerEntity $checker Модель кода проверки
     * @param string $type Тип кода проверки
     * @param string $code Текстовое представление проверки
     *
     * @return null|UserEntity
     */
    public function confirmChecker(UserCheckerEntity $checker, $type, $code)
    {
        // если тип кода и сам код совпадает - возвращаем модель пользователя сразу же
        if ($checker->getType() == $type && $checker->getCode() == $code) {
            return $checker->getUser();
        }

        // иначе увеличиваем счетчик просмотра кода
        $checker->increaseAttempts();

        // просроченный код проверки, удаляем и возвращаем null
        if ($checker->isExpired()) {
            $this->entityManager->remove($checker);
        } else {
            $this->entityManager->persist($checker);
        }

        $this->entityManager->flush();

        return null;
    }

    /**
     * Получить ORM-менеджера
     *
     * @return ObjectManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * Изменить пользователский e-mail.
     *
     * Генерирует новый код подтверждения и рассылает событие.
     *
     * Если e-mail пользователя отличается от нового - возвращает true, иначе - false.
     *
     * @param UserEntity $user Модель пользователя
     * @param string $newEmail Новый e-mail
     *
     * @return bool
     */
    public function changeUserEmail(UserEntity $user, $newEmail)
    {
        if ($user->getEmail() != $newEmail) {
            $this->removeCheckerByType($user, UserCheckerEntity::TYPE_CHANGE_EMAIL);
            $this->entityManager->persist($user);

            $checker = new UserCheckerEntity();
            $checker
                ->setUser($user)
                ->setType(UserCheckerEntity::TYPE_CHANGE_EMAIL)
                ->setJsonData([
                    'newEmail' => $newEmail,
                ])
                ->generateCode();

            $user->addChecker($checker);

            $this->entityManager->persist($user);
            $this->entityManager->persist($checker);

            $this->entityManager->flush();

            // триггер
            $event = new UserChangeEmailEvent($user, $checker);
            $event->setNewEmail($newEmail);
            $this->eventDispatcher->dispatch(UserChangeEmailEvent::NAME, $event);

            return true;
        }

        return false;
    }

    /**
     * Обновить e-mail пользователя на основе кода проверки с типом change_password.
     *
     * У пользователя должен быть код проверки change_password с данными newEmail.
     * Иначе метод вернет false.
     *
     * После смены e-mail возвращает true и удаляет код проверки с указанным типом.
     *
     * @param UserEntity $user Модель пользователя, для которого требуется сменить e-mail
     *
     * @return bool
     */
    public function updateUserEmailFromChecker(UserEntity $user)
    {
        $checker = $user->getCheckerByType(UserCheckerEntity::TYPE_CHANGE_EMAIL);

        if (!$checker) {
            return false;
        }

        $jsonData = $checker->getJsonData();

        if (!isset($jsonData['newEmail'])) {
            return false;
        }

        $newEmail = $jsonData['newEmail'];
        $user->setEmail($newEmail);
        $this->removeCheckerByType($user, UserCheckerEntity::TYPE_CHANGE_EMAIL);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return true;
    }
}
