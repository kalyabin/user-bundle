<?php

namespace UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormBuilderInterface;
use UserBundle\Validator\Constraints\UserEmail;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Форма восстановления пароля (указание e-mail)
 */
class RememberPasswordType extends AbstractType
{
    /**
     * @Assert\NotBlank()
     * @Assert\Email()
     * @Assert\Length(max=100)
     * @UserEmail(needExists=true, message="Пользователь с таким e-mail не найден")
     *
     * @var string E-mail пользователя, на который будет отправлено сообщение
     */
    public $email;

    /**
     * @inheritdoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class);
    }

    /**
     * @inheritdoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => self::class,
            'cascade_validation' => true,
        ]);
    }
}
