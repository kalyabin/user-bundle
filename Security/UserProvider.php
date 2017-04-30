<?php

namespace UserBundle\Security;

use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use UserBundle\Entity\UserEntity;
use UserBundle\Entity\Repository\UserRepository;
use UserBundle\Utils\UserManager;

/**
 * Провайдер пользователей для авторизации в системе
 *
 * @package UserBundle\Security
 */
class UserProvider implements UserProviderInterface
{
    /**
     * @var \UserBundle\Entity\Repository\UserRepository Репозиторий пользователей
     */
    protected $userRepository;

    /**
     * @var UserManager Менеджер пользователей
     */
    protected $userManager;

    /**
     * Конструктор
     *
     * @param UserManager $userManager
     */
    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
        $this->userRepository = $userManager->getEntityManager()->getRepository(UserEntity::class);
    }

    /**
     * Получить пользователя по логину
     *
     * @param string $username
     *
     * @return null|\UserBundle\Entity\UserEntity
     */
    public function loadUserByUsername($username)
    {
        $user = null;

        try {
            $user = $this->userRepository->findOneByEmail($username);
        } catch (DatabaseObjectNotFoundException $e) {
            throw new UsernameNotFoundException('Пользователь не найден');
        }

        if (!$user instanceof UserEntity) {
            throw new UsernameNotFoundException('Пользователь не найден');
        }

        return $user;
    }

    /**
     * Проверяет, поддерживается ли указанный класс данным провайдером
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        return $class === UserEntity::class;
    }

    /**
     * Получить реального пользователя из БД на основе данных из сессии
     *
     * @param UserInterface $user
     *
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof UserEntity) {
            throw new UnsupportedUserException('Данный вид аккаунтов не поддерживается');
        }

        return $this->loadUserByUsername($user->getUsername());
    }
}
