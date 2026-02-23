<?php

namespace App\Module\UserManagement\Service;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripeService
{
    public function __construct(
        private readonly StripeClient           $stripe,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface  $router,
        private readonly string                 $priceMonthly,
        private readonly string                 $priceYearly,
    ) {}

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
