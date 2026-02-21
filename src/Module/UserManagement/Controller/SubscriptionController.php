<?php

namespace App\Module\UserManagement\Controller;

use App\Module\UserManagement\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    // =========================================================
    // PRICING PAGE
    // =========================================================

    #[Route('/pricing', name: 'pricing')]
    public function pricing(): Response
    {
        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        return $this->render('payment/pricing.html.twig', [
            'user' => $user,
        ]);
    }

    // =========================================================
    // INITIATE CHECKOUT
    // =========================================================

    #[Route('/subscription/checkout/{plan}', name: 'subscription_checkout', methods: ['POST'])]
    public function checkout(string $plan, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('checkout_' . $plan, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('pricing');
        }

        $plan = strtoupper($plan);

        if (!in_array($plan, ['MONTHLY', 'YEARLY'], true)) {
            $this->addFlash('danger', 'Invalid plan selected.');
            return $this->redirectToRoute('pricing');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        if ($user->isPremium()) {
            $this->addFlash('info', 'You already have an active premium subscription.');
            return $this->redirectToRoute('user_dashboard');
        }

        try {
            $checkoutUrl = $this->stripeService->createCheckoutSession($user, $plan);
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Could not initiate checkout. Please try again.');
            return $this->redirectToRoute('pricing');
        }

        return $this->redirect($checkoutUrl);
    }

    // =========================================================
    // POST-CHECKOUT PAGES
    // =========================================================

    /**
     * Stripe redirects here after successful payment.
     * NOTE: We do NOT upgrade the user here — that happens in the webhook.
     * This page is just a friendly confirmation.
     */
    #[Route('/subscription/success', name: 'subscription_success')]
    public function success(Request $request): Response
    {
        $plan = strtoupper($request->query->get('plan', 'MONTHLY'));

        return $this->render('payment/success.html.twig', [
            'plan' => $plan,
        ]);
    }

    /**
     * Stripe redirects here if user clicks "Back" on the checkout page.
     * No charge was made.
     */
    #[Route('/subscription/cancel', name: 'subscription_cancel')]
    public function cancel(): Response
    {
        return $this->render('payment/cancel.html.twig');
    }

    // =========================================================
    // CANCEL SUBSCRIPTION (from dashboard/pricing)
    // =========================================================

    /**
     * User cancels their subscription.
     * Sets cancel_at_period_end = true on Stripe.
     * User keeps premium until end of billing period.
     */
    #[Route('/subscription/cancel-plan', name: 'subscription_cancel_plan', methods: ['POST'])]
    public function cancelPlan(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cancel_plan', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('user_dashboard');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        if (!$user->isPremium()) {
            $this->addFlash('info', 'You do not have an active subscription.');
            return $this->redirectToRoute('user_dashboard');
        }

        try {
            $this->stripeService->cancelAtPeriodEnd($user);
            $this->addFlash('info', sprintf(
                'Your subscription has been cancelled. You keep premium access until %s.',
                $user->getSubscriptionExpiry()?->format('F j, Y') ?? 'the end of your billing period'
            ));
        } catch (\LogicException $e) {
            $this->addFlash('warning', $e->getMessage());
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Could not cancel subscription. Please try again or contact support.');
        }

        return $this->redirectToRoute('user_dashboard');
    }

    // =========================================================
    // STRIPE CUSTOMER PORTAL
    // =========================================================

    /**
     * Redirect premium user to the Stripe Customer Portal.
     * They can update payment method, view invoices, etc.
     */
    #[Route('/subscription/portal', name: 'subscription_portal', methods: ['POST'])]
    public function portal(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('portal', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('user_dashboard');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        try {
            $portalUrl = $this->stripeService->createPortalSession($user);
        } catch (\LogicException $e) {
            $this->addFlash('warning', 'No billing account found. Please subscribe first.');
            return $this->redirectToRoute('pricing');
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Could not open billing portal. Please try again.');
            return $this->redirectToRoute('user_dashboard');
        }

        return $this->redirect($portalUrl);
    }
}
