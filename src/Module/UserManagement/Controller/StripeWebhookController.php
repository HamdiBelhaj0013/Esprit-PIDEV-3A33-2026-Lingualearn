<?php

namespace App\Module\UserManagement\Controller;

use App\Module\UserManagement\Repository\UserRepository;
use App\Module\UserManagement\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeClient           $stripe,
        private readonly UserRepository         $userRepository,
        private readonly UserService            $userService,
        private readonly EntityManagerInterface $em,
        private readonly string                 $webhookSecret,
    ) {}

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handle(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature', '');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (SignatureVerificationException $e) {
            return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_BAD_REQUEST);
        } catch (\UnexpectedValueException $e) {
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        match ($event->type) {
            'checkout.session.completed'    => $this->onCheckoutCompleted($event),
            'customer.subscription.updated' => $this->onSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->onSubscriptionDeleted($event),
            'invoice.payment_succeeded'     => $this->onPaymentSucceeded($event),
            'invoice.payment_failed'        => $this->onPaymentFailed($event),
            default                         => null,
        };

        return new JsonResponse(['status' => 'ok']);
    }

    private function onCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;

        $userId = $session->metadata->user_id ?? null;
        $plan   = $session->metadata->plan ?? 'MONTHLY';

        if (!$userId) {
            return;
        }

        $user = $this->userRepository->find((int) $userId);
        if (!$user) {
            return;
        }

        $subscriptionId = $session->subscription;
        if (!$subscriptionId) {
            return;
        }

        // Re-fetch the subscription directly from Stripe API.
        // The checkout.session event embeds a LIGHTWEIGHT subscription object
        // where current_period_end is null — causing the crash.
        // A direct retrieve() always returns the full object with all fields.
        $subscription = $this->stripe->subscriptions->retrieve((string) $subscriptionId);

        $user->setStripeSubscriptionId($subscription->id);
        $this->em->flush();

        $expiry = $this->timestampToDateTime($subscription->current_period_end);
        $this->userService->upgradeToPremium($user, $plan, $expiry);
    }

    private function onSubscriptionUpdated(Event $event): void
    {
        $subscription = $event->data->object;

        $user = $this->userRepository->findOneBy([
            'stripeCustomerId' => $subscription->customer,
        ]);

        if (!$user) {
            return;
        }

        // Safety guard: if the subscription ID on this event does not match
        // what we have stored, it means either:
        //   (a) a stale/old subscription event arrived after the user was
        //       admin-revoked and their stripeSubscriptionId was cleared, or
        //   (b) the user now has a different subscription (e.g. re-subscribed).
        // In both cases, ignore this event to avoid corrupting local state.
        if ($user->getStripeSubscriptionId() !== $subscription->id) {
            return;
        }

        $expiry = $this->timestampToDateTime($subscription->current_period_end);
        $user->setSubscriptionExpiry($expiry);
        $this->em->flush();
    }

    private function onSubscriptionDeleted(Event $event): void
    {
        $subscription = $event->data->object;

        $user = $this->userRepository->findOneBy([
            'stripeCustomerId' => $subscription->customer,
        ]);

        if (!$user) {
            return;
        }

        // Only act if this is the subscription we actually have on record.
        // If the IDs differ, an admin already revoked/replaced the subscription
        // and we must not touch the current state.
        if ($user->getStripeSubscriptionId() !== $subscription->id) {
            return;
        }

        $user->setStripeSubscriptionId(null);
        $this->em->flush();

        $this->userService->downgradeToFree($user);
    }

    private function onPaymentSucceeded(Event $event): void
    {
        $invoice = $event->data->object;

        if (!$invoice->subscription) {
            return;
        }

        $user = $this->userRepository->findOneBy([
            'stripeCustomerId' => $invoice->customer,
        ]);

        if (!$user) {
            return;
        }

        // Guard: only process this invoice if it belongs to the subscription
        // we currently have on record. This prevents a stale invoice from a
        // cancelled subscription from silently re-extending the user's expiry
        // and restoring premium access after an admin revoke.
        if ($user->getStripeSubscriptionId() !== (string) $invoice->subscription) {
            return;
        }

        $subscription = $this->stripe->subscriptions->retrieve((string) $invoice->subscription);
        $expiry       = $this->timestampToDateTime($subscription->current_period_end);

        $user->setSubscriptionExpiry($expiry);
        $user->setLastPaymentStatus('success');
        $this->em->flush();
    }

    private function onPaymentFailed(Event $event): void
    {
        $invoice = $event->data->object;

        $user = $this->userRepository->findOneBy([
            'stripeCustomerId' => $invoice->customer,
        ]);

        if (!$user) {
            return;
        }

        $user->setLastPaymentStatus('failed');
        $this->em->flush();
    }

    /**
     * Convert a Stripe Unix timestamp to DateTime safely.
     *
     * Root cause of the original bug: Stripe embeds a LIGHTWEIGHT subscription
     * object inside checkout.session.completed where current_period_end is null.
     * Calling setTimestamp(null) in PHP 8.1+ throws a TypeError / deprecation,
     * which crashed the webhook with 500 and left the user on the free plan.
     *
     * Fix: always cast via new \DateTime('@{int}') which is timezone-safe
     * (always UTC regardless of server config) and guard against null/zero.
     */
    private function timestampToDateTime(int|null $timestamp): \DateTime
    {
        if (!$timestamp) {
            // Fallback — should never happen with a properly retrieved subscription.
            return new \DateTime('+1 year');
        }

        return new \DateTime('@' . $timestamp);
    }
}
