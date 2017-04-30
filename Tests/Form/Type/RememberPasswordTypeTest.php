<?php

namespace UserBunde\Tests\Form\Type;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Tests\FormWebTestCase;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Form\Type\RememberPasswordType;

/**
 * Тестирование формы напоминания пароля
 *
 * @package UserBundle\Tests\Form\Type
 */
class RememberPasswordTypeTest extends FormWebTestCase
{
    /**
     * @var ReferenceRepository
     */
    protected $fixtures;

    protected function setUp()
    {
        parent::setUp();

        $this->fixtures = $this->loadFixtures([
            UserTestFixture::class,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function getFormClass()
    {
        return RememberPasswordType::class;
    }

    /**
     * @inheritdoc
     */
    protected function getFormData()
    {
        return new RememberPasswordType();
    }

    /**
     * @inheritdoc
     */
    public function getValidData()
    {
        return [
            [
                'data' => [
                    'email' => 'testing@orthoapp.ru',
                ]
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getInvalidData()
    {
        return [
            [
                'data' => [],
                'errorKeys' => ['email'],
            ],
            [
                'data' => [
                    'email' => 'wrong email format',
                ],
                'errorKeys' => ['email'],
            ],
            [
                'data' => [
                    'email' => 'non-existent@test.ru',
                ],
                'errorKeys' => ['email'],
            ],
        ];
    }
}
