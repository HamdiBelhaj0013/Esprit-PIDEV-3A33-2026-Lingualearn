<?php

namespace App\Module\UserManagement\Service;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// NOTE: UserService is injected via setter to avoid a circular dependency
// (UserService → StripeService → UserService). See setUserService() below.

class StripeService
{
    public function __construct(
        private readonly StripeClient           $stripe,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface  $router,
        private readonly string                 $priceMonthly,
        private readonly string                 $priceYearly,
    ) {}

    /**
     * Setter injection to avoid a circular dependency:
     * UserService depends on StripeService, so StripeService cannot
     * require UserService in its constructor.
     * Call $stripeService->setUserService($userService) from UserService::__construct(),
     * or wire it via a Symfony service configurator.
     */
    private ?UserService $userService = null;

    public function setUserService(UserService $userService): void
    {
        $this->userService = $userService;
    }

    // =========================================================
    // CUSTOMER
    // =========================================================

    /**
     * Get existing Stripe Customer or create a new one.
     * Stores the customer ID on the User entity for reuse.
     */
    public function getOrCreateCustomer(User $user): Customer
    {
        if ($user->getStripeCustomerId()) {
            return $this->stripe->customers->retrieve($user->getStripeCustomerId());
        }

        $customer = $this->stripe->customers->create([
            'email'    => $user->getEmail(),
            'name'     => $user->getFullName(),
            'metadata' => ['user_id' => $user->getId()],
        ]);

        $user->setStripeCustomerId($customer->id);
        $this->em->flush();

        return $customer;
    }

    // =========================================================
    // CHECKOUT
    // =========================================================

    /**
     * Create a Stripe Checkout Session for the chosen plan.
     * Returns the hosted Checkout URL to redirect the user to.
     */
    public function createCheckoutSession(User $user, string $plan): string
    {
        $customer = $this->getOrCreateCustomer($user);
        $priceId  = strtoupper($plan) === 'YEARLY' ? $this->priceYearly : $this->priceMonthly;

        $session = $this->stripe->checkout->sessions->create([
            'customer'             => $customer->id,
            'payment_method_types' => ['card'],
            'mode'                 => 'subscription',
            'line_items'           => [[
                'price'    => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => $this->router->generate(
                    'subscription_success',
                    ['plan' => strtoupper($plan)],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ) . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->router->generate(
                'subscription_cancel',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'metadata' => [
                'user_id' => $user->getId(),
                'plan'    => strtoupper($plan),
            ],
        ]);

        return $session->url;
    }

    // =========================================================
    // SUBSCRIPTION MANAGEMENT
    // =========================================================

    /**
     * Cancel subscription at period end.
     * User keeps premium access until the billing period ends,
     * then Stripe fires customer.subscription.deleted webhook.
     */
    public function cancelAtPeriodEnd(User $user): Subscription
    {
        if (!$user->getStripeSubscriptionId()) {
            throw new \LogicException('User has no active Stripe subscription.');
        }

        return $this->stripe->subscriptions->update(
            $user->getStripeSubscriptionId(),
            ['cancel_at_period_end' => true]
        );
    }

    /**
     * Switch a user's plan (MONTHLY ↔ YEARLY) on their existing Stripe subscription.
     * Uses Stripe's proration to charge/credit the difference immediately.
     * No new subscription is created — the existing one is updated in place.
     */
    public function switchPlan(User $user, string $newPlan): void
    {
        if (!$user->getStripeSubscriptionId()) {
            throw new \LogicException('User has no active Stripe subscription to switch.');
        }

        $priceId      = strtoupper($newPlan) === 'YEARLY' ? $this->priceYearly : $this->priceMonthly;
        $subscription = $this->stripe->subscriptions->retrieve($user->getStripeSubscriptionId());

        // Get the current subscription item ID (needed to update it)
        $itemId = $subscription->items->data[0]->id ?? null;
        if (!$itemId) {
            throw new \RuntimeException('Could not find subscription item to update.');
        }

        // Update the subscription with the new price, prorating immediately
        $updated = $this->stripe->subscriptions->update(
            $user->getStripeSubscriptionId(),
            [
                'items'               => [['id' => $itemId, 'price' => $priceId]],
                'proration_behavior'  => 'create_prorations',
                'metadata'            => ['plan' => strtoupper($newPlan)],
            ]
        );

        // Update local DB — Stripe will fire customer.subscription.updated webhook
        // too, but we update immediately so the user sees the change right away.
        $expiry = $this->timestampToDateTime($updated->current_period_end);

        if ($this->userService !== null) {
            $this->userService->upgradeToPremium($user, strtoupper($newPlan), $expiry);
        }
    }

    /**
     * Immediately cancel a subscription (admin use only).
     */
    public function cancelImmediately(User $user): Subscription
    {
        if (!$user->getStripeSubscriptionId()) {
            throw new \LogicException('User has no active Stripe subscription.');
        }

        return $this->stripe->subscriptions->cancel(
            $user->getStripeSubscriptionId()
        );
    }

    /**
     * Retrieve a Checkout Session by ID.
     * Used in the webhook to get full session data.
     */
    public function retrieveSession(string $sessionId): Session
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId, [
            'expand' => ['subscription'],
        ]);
    }

    /**
     * Retrieve a Subscription by ID.
     */
    public function retrieveSubscription(string $subscriptionId): Subscription
    {
        return $this->stripe->subscriptions->retrieve($subscriptionId);
    }

    // =========================================================
    // ADMIN — Grant / Revoke premium outside of self-checkout
    // =========================================================

    /**
     * Grant premium to a user via an admin action.
     *
     * This method ensures Stripe stays in sync with the DB:
     * - If the user has no Stripe customer yet, one is created.
     * - A real Stripe subscription is created (or re-activated).
     * - UserService::upgradeToPremium() is called to update the DB.
     *
     * Why this matters: if an admin only flips isPremium in the DB without
     * touching Stripe, the next webhook event (e.g. invoice.payment_succeeded
     * for a different subscription) could silently overwrite or conflict with
     * the manual grant, leaving the user in an inconsistent state.
     *
     * @param string $plan  'MONTHLY' or 'YEARLY'
     */
    public function adminGrantPremium(User $user, string $plan): void
    {
        if ($this->userService === null) {
            throw new \LogicException('UserService not injected into StripeService. Call setUserService() first.');
        }

        // If the user already has an active Stripe subscription, nothing to do.
        if ($user->getStripeSubscriptionId()) {
            // Just make sure the DB flag is consistent.
            if (!$user->isPremium()) {
                $subscription = $this->stripe->subscriptions->retrieve($user->getStripeSubscriptionId());
                $expiry = $this->timestampToDateTime($subscription->current_period_end);
                $this->userService->upgradeToPremium($user, $plan, $expiry);
            }
            return;
        }

        // Ensure a Stripe Customer record exists.
        $customer = $this->getOrCreateCustomer($user);
        $priceId  = strtoupper($plan) === 'YEARLY' ? $this->priceYearly : $this->priceMonthly;

        // Create a Stripe subscription with payment_behavior='default_incomplete'.
        // This means: create the subscription record on Stripe WITHOUT requiring
        // a payment method to be attached right now. If the user later provides
        // a card via the Customer Portal, the next billing cycle will charge them.
        // This allows admins to grant access manually without touching payments.
        $subscription = $this->stripe->subscriptions->create([
            'customer'         => $customer->id,
            'items'            => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'expand'           => ['latest_invoice.payment_intent'],
            'metadata'         => [
                'user_id'    => $user->getId(),
                'plan'       => strtoupper($plan),
                'granted_by' => 'admin',
            ],
        ]);

        $user->setStripeSubscriptionId($subscription->id);
        $this->em->flush();

        $expiry = $this->timestampToDateTime($subscription->current_period_end);
        $this->userService->upgradeToPremium($user, strtoupper($plan), $expiry);
    }


    /**
     * Grant a FREE TRIAL to a user for N days.
     *
     * Creates a real Stripe subscription with trial_end = now + $days.
     * Stripe sends no invoice and requires no payment method during the trial.
     * When the trial ends, Stripe will attempt to charge the user — if no
     * payment method is on file the subscription moves to "past_due" and the
     * webhook fires customer.subscription.deleted, auto-downgrading the user.
     *
     * @param string $plan  'MONTHLY' or 'YEARLY'
     * @param int    $days  Trial length in days (1–365)
     */
    public function adminGrantTrial(User $user, string $plan, int $days = 7): void
    {
        if ($this->userService === null) {
            throw new \LogicException('UserService not injected into StripeService.');
        }

        if ($user->getStripeSubscriptionId()) {
            throw new \LogicException(sprintf(
                '%s already has a Stripe subscription (%s). Revoke it before granting a trial.',
                $user->getFullName(),
                $user->getStripeSubscriptionId()
            ));
        }

        $days     = max(1, min(365, $days));
        $trialEnd = (new \DateTime("+{$days} days"))->getTimestamp();
        $customer = $this->getOrCreateCustomer($user);
        $priceId  = strtoupper($plan) === 'YEARLY' ? $this->priceYearly : $this->priceMonthly;

        $subscription = $this->stripe->subscriptions->create([
            'customer'  => $customer->id,
            'items'     => [['price' => $priceId]],
            'trial_end' => $trialEnd,
            'metadata'  => [
                'user_id'    => $user->getId(),
                'plan'       => strtoupper($plan),
                'granted_by' => 'admin_trial',
                'trial_days' => $days,
            ],
        ]);

        $user->setStripeSubscriptionId($subscription->id);
        $this->em->flush();

        // Use the trial end as the local expiry so isPremium = true for the duration
        $expiry = $this->timestampToDateTime($trialEnd);
        $this->userService->upgradeToPremium($user, strtoupper($plan), $expiry);
    }

    /**
     * Admin-side plan change (upgrade MONTHLY → YEARLY or downgrade YEARLY → MONTHLY).
     *
     * Delegates to the same switchPlan() logic users invoke from the pricing page.
     * Proration is applied immediately on Stripe.
     *
     * @param string $newPlan 'MONTHLY' or 'YEARLY'
     */
    public function adminChangePlan(User $user, string $newPlan): void
    {
        if ($this->userService === null) {
            throw new \LogicException('UserService not injected into StripeService.');
        }

        if (!$user->getStripeSubscriptionId()) {
            // User has admin-granted premium with no real Stripe subscription.
            // Create a fresh subscription on the requested plan.
            $this->adminGrantPremium($user, $newPlan);
            return;
        }

        // Re-use the same switchPlan logic to update the existing subscription.
        $this->switchPlan($user, $newPlan);
    }

    /**
     * Revoke premium from a user via an admin action.
     *
     * Cancels the Stripe subscription immediately (not at period end) and
     * calls UserService::downgradeToFree(). This prevents the case where
     * an admin sets isPremium = false in the DB but the active Stripe subscription
     * keeps firing invoice.payment_succeeded webhooks that silently re-extend
     * the user's subscriptionExpiry and restore their access.
     */
    public function adminRevokePremium(User $user): void
    {
        if ($this->userService === null) {
            throw new \LogicException('UserService not injected into StripeService. Call setUserService() first.');
        }

        // Cancel on Stripe side if a subscription exists.
        if ($user->getStripeSubscriptionId()) {
            try {
                $this->stripe->subscriptions->cancel($user->getStripeSubscriptionId());
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Subscription may already be cancelled on Stripe — that's fine.
                // We still want to clean up the local DB state below.
            }
            $user->setStripeSubscriptionId(null);
            $this->em->flush();
        }

        // Downgrade in DB regardless (handles the manual-grant-no-stripe case too).
        $this->userService->downgradeToFree($user);
    }

    // =========================================================
    // INTERNAL HELPERS
    // =========================================================

    /**
     * Convert a Stripe Unix timestamp to DateTime safely.
     * Kept private here as a mirror of the same helper in the webhook controller
     * so adminGrantPremium() can compute expiry without depending on the controller.
     */
    private function timestampToDateTime(int|null $timestamp): \DateTime
    {
        if (!$timestamp) {
            return new \DateTime('+1 month');
        }

        return new \DateTime('@' . $timestamp);
    }

    // =========================================================
    // PORTAL (Customer Self-Service)
    // =========================================================

    /**
     * Create a Stripe Customer Portal session.
     * Lets the user manage their subscription directly on Stripe.
     */
    public function createPortalSession(User $user): string
    {
        if (!$user->getStripeCustomerId()) {
            throw new \LogicException('User has no Stripe customer record.');
        }

        $session = $this->stripe->billingPortal->sessions->create([
            'customer'   => $user->getStripeCustomerId(),
            'return_url' => $this->router->generate(
                'user_dashboard',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);

        return $session->url;
    }
}
