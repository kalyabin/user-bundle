<?php

namespace UserBunde\Tests\Form\Type;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Tests\FormWebTestCase;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Entity\UserEntity;
use UserBundle\Form\Type\RegistrationType;

/**
 * Тестирование формы регистрации
 *
 * @package UserBundle\Tests\Form\Type
 */
class RegistrationTypeTest extends FormWebTestCase
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
        ])->getReferenceRepository();
    }

    /**
     * @inheritdoc
     */
    protected function getFormData()
    {
        return new UserEntity();
    }

    /**
     * @inheritdoc
     */
    protected function getFormClass()
    {
        return RegistrationType::class;
    }

    /**
     * @inheritdoc
     */
    public function getInvalidData()
    {
        return [
            [
                'data' => [],
                'errorKeys' => [
                    'password[first]', 'password[second]', 'email', 'name',
                ],
            ], [
                'data' => [
                    'name' => 'Tester',
                ],
                'errorKeys' => [
                    'password[first]', 'password[second]', 'email',
                ],
            ], [
                'data' => [
                    'name' => 'Tester',
                    'email' => 'test@test.ru',
                ],
                'errorKeys' => [
                    'password[first]', 'password[second]',
                ],
            ], [
                'data' => [
                    'name' => 'Tester',
                    'email' => 'test@test.ru',
                    'password' => [
                        'first' => 'testpassword',
                    ],
                ],
                'errorKeys' => [
                    'password[first]', 'password[second]',
                ],
            ], [
                'data' => [
                    'name' => 'Tester',
                    'email' => 'test@test.ru',
                    'password' => [
                        'second' => 'testpassword',
                    ]
                ],
                'errorKeys' => [
                    'password[first]', 'password[second]',
                ],
            ], [
                'data' => [
                    'name' => 'Tester',
                    'email' => 'wrong email format',
                    'password' => [
                        'first' => 'testpassword',
                        'second' => 'testpassword',
                    ]
                ],
                'errorKeys' => [
                    'email',
                ],
            ], [
                'data' => [
                    'name' => 'Tester',
                    'email' => 'testing@test.ru',
                    'password' => [
                        'first' => 'testpassword',
                        'second' => 'testpassword',
                    ]
                ],
                'errorKeys' => [
                    'email',
                ],
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getValidData()
    {
        return [
            [
                'data' => [
                    'name' => 'Tester',
                    'email' => 'non-existent@test.ru',
                    'password' => [
                        'first' => 'testpassword',
                        'second' => 'testpassword',
                    ]
                ],
            ]
        ];
    }
}
