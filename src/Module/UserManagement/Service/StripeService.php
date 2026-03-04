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

    private ?UserService $userService = null;

    public function setUserService(UserService $userService): void
    {
        $this->userService = $userService;
    }

    public function getOrCreateCustomer(User $user): Customer
    {
        if ($user->getStripeCustomerId()) {
            return $this->stripe->customers->retrieve($user->getStripeCustomerId());
        }

        $customer = $this->stripe->customers->create([
            'email'    => $user->getEmail(),
            'name'     => $user->getFullName(),
            'metadata' => ['user_id' => (string) $user->getId()],
        ]);

        $user->setStripeCustomerId($customer->id);
        $this->em->flush();

        return $customer;
    }

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
                'user_id' => (string) $user->getId(),
                'plan'    => strtoupper($plan),
            ],
        ]);

        return $session->url;
    }

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

    public function switchPlan(User $user, string $newPlan): void
    {
        if (!$user->getStripeSubscriptionId()) {
            throw new \LogicException('User has no active Stripe subscription to switch.');
        }

        $priceId      = strtoupper($newPlan) === 'YEARLY' ? $this->priceYearly : $this->priceMonthly;
        $subscription = $this->stripe->subscriptions->retrieve($user->getStripeSubscriptionId());

        $itemId = $subscription->items->data[0]->id ?? null;
        if (!$itemId) {
            throw new \RuntimeException('Could not find subscription item to update.');
        }

        $updated = $this->stripe->subscriptions->update(
            $user->getStripeSubscriptionId(),
            [
                'items'              => [['id' => $itemId, 'price' => $priceId]],
                'proration_behavior' => 'create_prorations',
                'metadata'           => ['plan' => strtoupper($newPlan)],
            ]
        );

        $expiry = $this->timestampToDateTime($updated->current_period_end);

        if ($this->userService !== null) {
            $this->userService->upgradeToPremium($user, strtoupper($newPlan), $expiry);
        }
    }

    public function cancelImmediately(User $user): Subscription
    {
        if (!$user->getStripeSubscriptionId()) {
            throw new \LogicException('User has no active Stripe subscription.');
        }

        return $this->stripe->subscriptions->cancel(
            $user->getStripeSubscriptionId()
        );
    }

    public function retrieveSession(string $sessionId): Session
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId, [
            'expand' => ['subscription'],
        ]);
    }

    public function retrieveSubscription(string $subscriptionId): Subscription
    {
        return $this->stripe->subscriptions->retrieve($subscriptionId);
    }

    public function adminGrantPremium(User $user, string $plan): void
    {
        if ($this->userService === null) {
            throw new \LogicException('UserService not injected into StripeService. Call setUserService() first.');
        }

        if ($user->getStripeSubscriptionId()) {
            if (!$user->isPremium()) {
                $subscription = $this->stripe->subscriptions->retrieve($user->getStripeSubscriptionId());
                $expiry = $this->timestampToDateTime($subscription->current_period_end);
                $this->userService->upgradeToPremium($user, $plan, $expiry);
            }
            return;
        }

        $customer = $this->getOrCreateCustomer($user);
        $priceId  = strtoupper($plan) === 'YEARLY' ? $this->priceYearly : $this->priceMonthly;

        $subscription = $this->stripe->subscriptions->create([
            'customer'         => $customer->id,
            'items'            => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'expand'           => ['latest_invoice.payment_intent'],
            'metadata'         => [
                'user_id'    => (string) $user->getId(),
                'plan'       => strtoupper($plan),
                'granted_by' => 'admin',
            ],
        ]);

        $user->setStripeSubscriptionId($subscription->id);
        $this->em->flush();

        $expiry = $this->timestampToDateTime($subscription->current_period_end);
        $this->userService->upgradeToPremium($user, strtoupper($plan), $expiry);
    }

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
                'user_id'    => (string) $user->getId(),
                'plan'       => strtoupper($plan),
                'granted_by' => 'admin_trial',
                'trial_days' => (string) $days,
            ],
        ]);

        $user->setStripeSubscriptionId($subscription->id);
        $this->em->flush();

        $expiry = $this->timestampToDateTime($trialEnd);
        $this->userService->upgradeToPremium($user, strtoupper($plan), $expiry);
    }

    public function adminChangePlan(User $user, string $newPlan): void
    {
        if ($this->userService === null) {
            throw new \LogicException('UserService not injected into StripeService.');
        }

        if (!$user->getStripeSubscriptionId()) {
            $this->adminGrantPremium($user, $newPlan);
            return;
        }

        $this->switchPlan($user, $newPlan);
    }

    public function adminRevokePremium(User $user): void
    {
        if ($this->userService === null) {
            throw new \LogicException('UserService not injected into StripeService. Call setUserService() first.');
        }

        if ($user->getStripeSubscriptionId()) {
            try {
                $this->stripe->subscriptions->cancel($user->getStripeSubscriptionId());
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Already cancelled on Stripe — clean up DB state below.
            }
            $user->setStripeSubscriptionId(null);
            $this->em->flush();
        }

        $this->userService->downgradeToFree($user);
    }

    private function timestampToDateTime(int|null $timestamp): \DateTime
    {
        if (!$timestamp) {
            return new \DateTime('+1 month');
        }

        return new \DateTime('@' . $timestamp);
    }

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
