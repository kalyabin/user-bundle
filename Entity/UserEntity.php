<?php

namespace UserBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use UserBundle\Validator\Constraints\UserEmail;

/**
 * Модель авторизованного пользователя
 *
 * @package UserBundle\Entity
 *
 * @ORM\Entity(repositoryClass="UserBundle\Entity\Repository\UserRepository")
 * @ORM\Table(name="`user`")
 */
class UserEntity implements UserInterface
{
    /**
     * Статус пользователя - активен
     */
    const STATUS_ACTIVE = 1;

    /**
     * Статус пользователя - требуется активация
     */
    const STATUS_NEED_ACTIVATION = 0;

    /**
     * Статус пользователя - заблокирован
     */
    const STATUS_LOCKED = -1;

    /**
     * @ORM\Column(type="bigint", nullable=false)
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var integer Идентификатор пользователя
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=100, nullable=false)
     *
     * @Assert\NotBlank()
     * @Assert\Length(max=100)
     *
     * @var string Имя пользователя
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=100, nullable=false, unique=true)
     *
     * @Assert\NotBlank()
     * @Assert\Email()
     * @Assert\Length(max=100)
     * @UserEmail(needExists=false, message="Такой e-mail уже занят")
     *
     * @var string E-mail пользователя
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=100, nullable=false)
     *
     * @Assert\NotBlank()
     * @Assert\Length(max=100)
     *
     * @var string Хеш пароля
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=10, nullable=false)
     *
     * @var string Соль для кодирования пароля
     */
    private $salt;

    /**
     * @ORM\Column(type="smallint", nullable=false)
     *
     * @Assert\NotBlank()
     * @Assert\Type("integer")
     * @Assert\Choice(callback="getStatusesList")
     *
     * @var integer Статус пользователя (на основе констант self::STATUS_*)
     */
    private $status = 0;

    /**
     * @ORM\OneToMany(targetEntity="UserCheckerEntity", mappedBy="user", cascade={"persist", "remove"})
     *
     * @var UserCheckerEntity[] Привязка к модели проверки пользователя
     */
    private $checker;

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->checker = new ArrayCollection();
    }

    /**
     * Перечисление статусов
     *
     * @return integer[]
     */
    public static function getStatusesList()
    {
        return [self::STATUS_ACTIVE, self::STATUS_LOCKED, self::STATUS_NEED_ACTIVATION];
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return UserEntity
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return UserEntity
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set password
     *
     * @param string $password
     *
     * @return UserEntity
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get password
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Сгенерировать соль для пароля
     *
     * @return UserEntity
     */
    public function generateSalt()
    {
        if (empty($this->salt)) {
            $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $randStringLen = 10;

            $randString = '';
            for ($i = 0; $i < $randStringLen; $i++) {
                $randString .= $charset[mt_rand(0, strlen($charset) - 1)];
            }

            $this->salt = $randString;
        }

        return $this;
    }

    /**
     * Get salt
     *
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * Set salt
     *
     * @param string $salt
     *
     * @return UserEntity
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;

        return $this;
    }

    /**
     * Set status
     *
     * @param integer $status
     *
     * @return UserEntity
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return integer
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get checker
     *
     * @return ArrayCollection
     */
    public function getChecker()
    {
        return $this->checker;
    }

    /**
     * Получить имя пользователя для логина
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->getEmail();
    }

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }

    /**
     * Роли пользователя
     *
     * @return array
     */
    public function getRoles()
    {
        return [];
    }

    /**
     * Проверка активности
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status == self::STATUS_ACTIVE;
    }

    /**
     * Проверка необходимости активации
     *
     * @return bool
     */
    public function isNeedActivation()
    {
        return $this->status == self::STATUS_NEED_ACTIVATION;
    }

    /**
     * Проверка заблокированности
     *
     * @return bool
     */
    public function isLocked()
    {
        return $this->status == self::STATUS_LOCKED;
    }

    /**
     * Add checker
     *
     * @param \UserBundle\Entity\UserCheckerEntity $checker
     *
     * @return UserEntity
     */
    public function addChecker(\UserBundle\Entity\UserCheckerEntity $checker)
    {
        $this->checker[] = $checker;

        return $this;
    }

    /**
     * Remove checker
     *
     * @param \UserBundle\Entity\UserCheckerEntity $checker
     */
    public function removeChecker(\UserBundle\Entity\UserCheckerEntity $checker)
    {
        $this->checker->removeElement($checker);
    }

    /**
     * Получить код проверки по типу
     *
     * @param string $type
     *
     * @return null|UserCheckerEntity
     */
    public function getCheckerByType($type)
    {
        foreach ($this->checker as $checker) {
            if ($checker->getType() == $type) {
                return $checker;
            }
        }
        return null;
    }

    /**
     * Удалить код проверки по типу
     *
     * @param string $type Тип кода проверки
     */
    public function removeCheckerByType($type)
    {
        foreach ($this->checker as $checker) {
            if ($checker->getType() == $type) {
                $this->removeChecker($checker);
            }
        }
    }
}
