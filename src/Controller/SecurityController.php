<?php

namespace App\Controller;

use App\Module\UserManagement\Service\FaceRecognitionService;
use App\Module\UserManagement\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    // ── Standard user login ───────────────────────────────────────────────────

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error        = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by the firewall.');
    }

    // ── Admin standard login ──────────────────────────────────────────────────

    #[Route('/admin/login', name: 'app_admin_login')]
    public function adminLogin(AuthenticationUtils $authenticationUtils): Response
    {
        $error        = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/admin_login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/admin/logout', name: 'app_admin_logout')]
    public function adminLogout(): void
    {
        throw new \LogicException('Intercepted by the firewall.');
    }

    // ── Admin face login ──────────────────────────────────────────────────────

    #[Route('/admin/login/face', name: 'app_admin_face_login')]
    public function adminFaceLogin(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('security/admin_face_login.html.twig');
    }

    /**
     * Public AJAX endpoint — verifies face and logs admin in.
     */
    #[Route('/admin/face/verify', name: 'app_admin_face_verify', methods: ['POST'])]
    public function adminFaceVerify(
        Request                $request,
        FaceRecognitionService $faceService,
        UserRepository         $userRepository,
        Security               $security,
    ): JsonResponse {
        $data     = json_decode($request->getContent(), true);
        $email    = trim($data['email']  ?? '');
        $imageB64 = trim($data['image']  ?? '');

        if (!$email || !$imageB64) {
            return $this->json(['success' => false, 'message' => 'Email and image are required.'], 400);
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->json(['success' => false, 'message' => 'Face not recognised.'], 401);
        }

        try {
            $result = $faceService->verify($user->getId(), $imageB64);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        if (!($result['match'] ?? false)) {
            return $this->json([
                'success'  => false,
                'message'  => 'Face not recognised.',
                'distance' => $result['distance'] ?? null,
            ], 401);
        }

        try {
            $security->login($user, 'security.authenticator.form_login.admin');
        } catch (\Exception $e) {
            // Wrong authenticator name — return it so we can debug
            return $this->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage(),
            ], 500);
        }

        return $this->json([
            'success'  => true,
            'redirect' => $this->generateUrl('admin_dashboard'),
        ]);
    }

    // ── Face enrollment (authenticated admin — from profile) ──────────────────

    #[Route('/admin/profile/enroll-face', name: 'app_admin_enroll_face')]
    public function adminEnrollFacePage(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('security/admin_enroll_face.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/admin/profile/enroll-face/save', name: 'app_admin_enroll_face_save', methods: ['POST'])]
    public function adminEnrollFaceSave(
        Request                $request,
        FaceRecognitionService $faceService,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data     = json_decode($request->getContent(), true);
        $imageB64 = trim($data['image'] ?? '');

        if (!$imageB64) {
            return $this->json(['success' => false, 'message' => 'No image received.'], 400);
        }

        try {
            $faceService->enroll($this->getUser()->getId(), $imageB64);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->json(['success' => true, 'message' => 'Face enrolled successfully.']);
    }

    // ── Setup enrollment (public — called right after first admin creation) ───

    /**
     * Called by the setup wizard after account creation.
     * Protected by a one-time token written to session by the setup controller.
     * No ROLE_ADMIN check — the user is not logged in yet at this point.
     */
    #[Route('/setup/enroll-face', name: 'app_setup_enroll_face', methods: ['POST'])]
    public function setupEnrollFace(
        Request                $request,
        FaceRecognitionService $faceService,
        UserRepository         $userRepository,
    ): JsonResponse {
        $data     = json_decode($request->getContent(), true);
        $userId   = (int) ($data['user_id'] ?? 0);
        $imageB64 = trim($data['image']    ?? '');
        $token    = trim($data['token']    ?? '');

        // Validate one-time token stored in session by the setup controller
        $session     = $request->getSession();
        $validToken  = $session->get('setup_enroll_token');
        $tokenUserId = $session->get('setup_enroll_user_id');

        if (!$validToken || $token !== $validToken || $userId !== (int) $tokenUserId) {
            return $this->json(['success' => false, 'message' => 'Invalid or expired setup token.'], 403);
        }

        $user = $userRepository->find($userId);
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        if (!$imageB64) {
            return $this->json(['success' => false, 'message' => 'No image received.'], 400);
        }

        try {
            $faceService->enroll($userId, $imageB64);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // Consume the token — one use only
        $session->remove('setup_enroll_token');
        $session->remove('setup_enroll_user_id');

        return $this->json(['success' => true, 'message' => 'Face enrolled successfully.']);
    }
}
