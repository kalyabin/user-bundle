<?php

namespace UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Validator\Constraints as Assert;
use UserBundle\Validator\Constraints\UserEmail;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Форма изменения e-mail.
 *
 * Пользователь должен быть уже авторизован, на новый e-mail отправляется ссылка с подтверждением.
 *
 * @package UserBundle\Form\Type
 */
class ChangeEmailType extends AbstractType
{
    /**
     * @Assert\NotBlank()
     * @Assert\Length(max=100)
     * @Assert\Email()
     * @UserEmail(needExists=false, message="Такой e-mail уже занят", excludeCallback="getCurrentUserId")
     *
     * @var string Новый e-mail
     */
    public $newEmail;

    /**
     * @var integer Идентификатор текущего пользователя
     */
    protected $currentUserId;

    /**
     * Установить идентификатор текущего пользователя
     *
     * @param integer $userId
     *
     * @return self
     */
    public function setCurrentUserId($userId)
    {
        $this->currentUserId = $userId;
        return $this;
    }

    /**
     * Получить идентификатор текущего пользователя
     *
     * @return int
     */
    public function getCurrentUserId()
    {
        return $this->currentUserId;
    }

    /**
     * @inheritdoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('newEmail', EmailType::class, [
            'label' => 'Новый e-mail'
        ]);
    }

    /**
     * @inheritdoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data' => self::class
        ]);
    }
}
