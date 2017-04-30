<?php

namespace UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping\UniqueConstraint;
use UserBundle\UserBundle;

/**
 * Модель для проверки пользователя по различным кодам подтверждения
 * (например, по коду подтверждения e-mail, либо по коду активации).
 *
 * Тип проверки у станавливается в поле type на основе констант.
 * Связь с пользователем - один-ко-многоми.
 *
 * Одна проверка может быть получена не больше MAX_ATTEMPTS раз.
 *
 * @ORM\Entity(repositoryClass="UserBundle\Entity\Repository\UserCheckerRepository")
 * @ORM\Table(name="user_checker", uniqueConstraints={@UniqueConstraint(name="unique_user_type", columns={"user_id", "type"})})
 *
 * @package UserBundle\Entity
 */
class UserCheckerEntity
{
    const TYPE_ACTIVATION_CODE = 'activation_code';
    const TYPE_REMEMBER_PASSWORD = 'remember_password';
    const TYPE_CHANGE_EMAIL = 'change_email';

    /**
     * Максимальное количество получений
     */
    const MAX_ATTEMPTS = 10;

    /**
     * @ORM\Column(type="bigint", nullable=false, unique=true)
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var integer Внутренний идентификатор
     */
    private $id;

    /**
     * @ORM\Column(type="string", nullable=false, length=32, unique=true)
     *
     * @Assert\NotBlank()
     * @Assert\Length(max=32)
     *
     * @var string Уникальный код
     */
    private $code;

    /**
     * @ORM\Column(type="string", nullable=true, length=255, unique=false)
     *
     * @Assert\Length(max=255)
     *
     * @var string JSON с какими-то сериализованными данными для изменения (например, новый e-mail)
     */
    private $data;

    /**
     * @ORM\Column(type="smallint", nullable=false)
     *
     * @Assert\Range(min=0, max=10)
     *
     * @var integer Количество попыток проверить код
     */
    private $attempts = 0;

    /**
     * @ORM\Column(type="string", nullable=false)
     *
     * @Assert\NotBlank()
     * @Assert\Callback(callback="getTypesList")
     *
     * @var string Тип проверки
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity="UserEntity", inversedBy="checker")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @Assert\NotBlank()
     *
     * @var UserEntity Привязанная модель пользователя
     */
    private $user;

    /**
     * Set user
     *
     * @param \UserBundle\Entity\UserEntity $user
     *
     * @return UserCheckerEntity
     */
    public function setUser(\UserBundle\Entity\UserEntity $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \UserBundle\Entity\UserEntity
     */
    public function getUser()
    {
        return $this->user;
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
     * Set code
     *
     * @param string $code
     *
     * @return UserCheckerEntity
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Установить несериализованные данные в JSON строку
     *
     * @param mixed $data
     *
     * @return self
     */
    public function setJsonData($data)
    {
        $encoder = new JsonEncoder();
        $this->setData($encoder->encode($data, JsonEncoder::FORMAT));

        return $this;
    }

    /**
     * Получить данные из сериализованной строки
     *
     * @return mixed
     */
    public function getJsonData()
    {
        $decoder = new JsonDecode(true);
        return $decoder->decode($this->getData(), JsonEncoder::FORMAT);
    }

    /**
     * Установить сопутствующие данные в JSON-формате.
     *
     * @param string $data Строка в JSON
     *
     * @return self
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Получить сериализованные данные в JSON
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set attempts
     *
     * @param integer $attempts
     *
     * @return UserCheckerEntity
     */
    public function setAttempts($attempts)
    {
        $this->attempts = $attempts;

        return $this;
    }

    /**
     * Get attempts
     *
     * @return integer
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * Увеличить попытки
     */
    public function increaseAttempts()
    {
        $this->attempts++;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return UserCheckerEntity
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Получить список типов подтверждений
     *
     * @return array
     */
    public static function getTypesList()
    {
        return [self::TYPE_ACTIVATION_CODE, self::TYPE_REMEMBER_PASSWORD];
    }

    /**
     * Генерация кода
     *
     * @return UserCheckerEntity
     */
    public function generateCode()
    {
        $this->code = md5(uniqid() . time() . $this->getUser()->getEmail() . $this->getType());

        return $this;
    }

    /**
     * Возвращает true, если код был запрошен большое количество раз
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->getAttempts() >= static::MAX_ATTEMPTS;
    }
}
