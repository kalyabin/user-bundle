<?php

namespace UserBundle\Controller;

use HttpHelperBundle\Annotation\DisableCsrfProtection;
use HttpHelperBundle\Response\FormValidationJsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\Translator;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Form\Type\ChangeEmailType;
use UserBundle\Form\Type\ChangePasswordType;
use UserBundle\Form\Type\ProfileType;
use UserBundle\Entity\UserEntity;
use UserBundle\Entity\Repository\UserCheckerRepository;
use UserBundle\Utils\UserManager;

/**
 * Контроллер для форм регистрации, авторизации и восстановления пароля
 *
 * @Route(service="user.dashboard_controller")
 *
 * @package UserBundle\Controller
 */
class DashboardController extends Controller
{
    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var CsrfTokenManagerInterface
     */
    protected $csrfManager;

    /**
     * @var string
     */
    protected $restTokenIntention;

    /**
     * @var bool Включено или отключено изменение e-mail
     */
    protected $changeEmailEnabled;

    /**
     * Конструктор
     *
     * @param UserManager $userManager Менеджер пользователей
     * @param CsrfTokenManagerInterface $csrfManager Менеджер токенов CSRF
     * @param string $restTokenIntention Идентификатор токена для перегенерации при логауте
     * @param bool $changeEmailEnabled Включена или отключено изменение e-mail
     */
    public function __construct(UserManager $userManager, CsrfTokenManagerInterface $csrfManager, $restTokenIntention, $changeEmailEnabled = true)
    {
        $this->userManager = $userManager;
        $this->csrfManager = $csrfManager;
        $this->restTokenIntention = $restTokenIntention;
        $this->changeEmailEnabled = $changeEmailEnabled;
    }

    /**
     * Проверка авторизации.
     *
     * В случае, если пользователь авторизован - возвращает его данные,
     * иначе возвращает null.
     *
     * @DisableCsrfProtection()
     *
     * @Method({"POST"})
     *
     * @Route("/check_auth", options={"expose" : true}, name="user.check_auth")
     *
     * @return JsonResponse
     */
    public function checkAuthorizationAction()
    {
        $response = new JsonResponse([
            'auth' => false,
            'user' => null
        ]);

        $user = $this->getUser();

        if ($user instanceof UserEntity) {
            $response->setData([
                'auth' => true,
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'status' => $user->getStatus(),
                ]
            ]);
        }

        return $response;
    }

    /**
     * Изменение e-mail по коду подтверждения
     *
     * @DisableCsrfProtection()
     * @Method({"GET"})
     * @Route("/profile/change_email/confirmation/{checkerId}/{code}", options={"expose" : true}, requirements={
     *     "checkerId" : "\d+",
     *     "code" : "\w+"
     * }, name="user.change_email_confirmation")
     *
     * @param integer $checkerId Идентификатор кода подтверждения
     * @param string $code Код подтверждения
     *
     * @return Response
     */
    public function confirmChangeEmailAction($checkerId, $code)
    {
        if (!$this->changeEmailEnabled) {
            throw $this->createAccessDeniedException();
        }

        /** @var UserCheckerRepository $repository */
        $repository = $this->userManager->getEntityManager()->getRepository(UserCheckerEntity::class);

        $checker = $repository->findOneById($checkerId);

        if (!$checker instanceof UserCheckerEntity) {
            throw $this->createNotFoundException();
        }

        $user = $this->userManager->confirmChecker($checker, UserCheckerEntity::TYPE_CHANGE_EMAIL, $code);

        if (!$user instanceof UserEntity) {
            throw $this->createNotFoundException();
        }

        if ($this->userManager->updateUserEmailFromChecker($user)) {
            return $this->redirect('/#/email-updated/' . $user->getEmail());
        } else {
            return $this->redirect('/#/email-update-error');
        }
    }

    /**
     * Субмит формы изменения e-mail.
     *
     * Ответ в виде JSON.
     *
     * @Method({"POST"})
     * @Route("/profile/change_email", options={"expose" : true}, name="user.change_email")
     * @Security("is_authenticated()")
     *
     * @param Request $request
     *
     * @return FormValidationJsonResponse
     */
    public function changeEmailAction(Request $request)
    {
        if (!$this->changeEmailEnabled) {
            throw $this->createAccessDeniedException();
        }

        $success = false;

        /** @var UserEntity $user */
        $user = $this->getUser();

        $changeEmail = new ChangeEmailType();
        $changeEmail->newEmail = $user->getEmail();
        $changeEmail->setCurrentUserId($user->getId());

        $form = $this->createForm(ChangeEmailType::class, $changeEmail);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $success = $this->userManager->changeUserEmail($user, $changeEmail->newEmail);
        }

        $response = new FormValidationJsonResponse();
        $response->jsonData = [
            'success' => $success,
        ];
        $response->handleForm($form);
        return $response;
    }

    /**
     * Субмит формы изменения пароля.
     *
     * @Method({"POST"})
     * @Route("/profile/change_password", options={"expose" : true}, name="user.change_password")
     * @Security("is_authenticated()")
     *
     * @param Request $request
     *
     * @return FormValidationJsonResponse
     */
    public function changePasswordAction(Request $request)
    {
        $success = false;

        /** @var UserEntity $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->changePassword($user, $user->getPassword());
            $success = true;
        }

        $response = new FormValidationJsonResponse();
        $response->jsonData = [
            'success' => $success,
        ];
        $response->handleForm($form);
        return $response;
    }

    /**
     * Субмит формы изменения профиля.
     *
     * Ответ в виде JSON
     *
     * @Method({"POST"})
     * @Route("/profile/update", options={"expose" : true}, name="user.profile_update")
     * @Security("is_authenticated()")
     *
     * @param Request $request
     *
     * @return FormValidationJsonResponse
     */
    public function profileUpdateAction(Request $request)
    {
        $success = false;

        /** @var UserEntity $user */
        $user = $this->getUser();

        $profileForm = $this->createForm(ProfileType::class, $user);

        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            // субмит формы
            // сохранить пользователя
            $this->userManager->getEntityManager()->persist($user);
            $this->userManager->getEntityManager()->flush();
            $success = true;
        }

        $response = new FormValidationJsonResponse();
        $response->jsonData = [
            'success' => $success
        ];
        $response->handleForm($profileForm);

        return $response;
    }

    /**
     * Логаут пользователя.
     *
     * При логауте пользователя производится сброс сессии, а значит и сброс всех токенов CSRF.
     * Данный метод возвращает обновленный токен CSRF для дальнейшей работы веб-приложения без перезагрузки страницы.
     *
     * @Route("/logout", options={"expose" : true}, name="user.logout")
     * @Security("is_authenticated()")
     */
    public function logoutAction()
    { }

    /**
     * Запрос на генерацию нового CSRF-токена
     *
     * @DisableCsrfProtection()
     * @Method({"HEAD"})
     * @Route("/token", options={"expose" : true}, name="user.token")
     *
     * @return Response
     */
    public function generateTokenAction()
    {
        // удалить старый токен и получить новый
        $this->csrfManager->removeToken($this->restTokenIntention);
        $token = $this->csrfManager->getToken($this->restTokenIntention);

        $response = new Response();
        $response->headers->add([
            'X-CSRF-Token' => $token->getValue()
        ]);

        return $response;
    }
}
