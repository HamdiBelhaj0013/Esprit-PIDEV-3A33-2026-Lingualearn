<?php

namespace App\Module\UserManagement\Service;

use App\Module\UserManagement\Entity\User;

/**
 * Business rules service for the UserManagement module.
 * This thin service layer is what PHPUnit tests target directly
 * (no HTTP, no DB, no Symfony kernel needed).
 */
class UserManager
{
    private const VALID_ROLES  = ['ROLE_USER', 'ROLE_STUDENT', 'ROLE_TEACHER', 'ROLE_ADMIN'];
    private const VALID_PLANS  = ['FREE', 'MONTHLY', 'YEARLY'];
    private const VALID_STATUS = ['active', 'suspended', 'pending'];

    // ----------------------------------------------------------------
    // Validation
    // ----------------------------------------------------------------

    /**
     * Validates all business rules for a User entity.
     *
     * @throws \InvalidArgumentException on the first violated rule
     */
    public function validate(User $user): bool
    {
        if (empty(trim((string) $user->getFirstName()))) {
            throw new \InvalidArgumentException('First name is required.');
        }

        if (empty(trim((string) $user->getLastName()))) {
            throw new \InvalidArgumentException('Last name is required.');
        }

        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        foreach ($user->getRoles() as $role) {
            if (!in_array($role, self::VALID_ROLES, true)) {
                throw new \InvalidArgumentException(sprintf('Invalid role: %s', $role));
            }
        }

        if (!in_array($user->getSubscriptionPlan(), self::VALID_PLANS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid subscription plan: %s', $user->getSubscriptionPlan())
            );
        }

        return true;
    }

    // ----------------------------------------------------------------
    // Premium helpers
    // ----------------------------------------------------------------

    /**
     * Returns true only when the user has an active, non-expired premium subscription.
     */
    public function isPremiumActive(User $user): bool
    {
        if (!$user->isPremium()) {
            return false;
        }

        $expiry = $user->getSubscriptionExpiry();
        if ($expiry === null) {
            return false;
        }

        return $expiry > new \DateTime();
    }

    /**
     * Calculates how many days remain in the current subscription.
     * Returns 0 if the user is not premium or the subscription has expired.
     */
    public function remainingDays(User $user): int
    {
        if (!$this->isPremiumActive($user)) {
            return 0;
        }

        $now    = new \DateTime();
        $expiry = $user->getSubscriptionExpiry();

        return (int) $now->diff($expiry)->days;
    }

    // ----------------------------------------------------------------
    // Password policy
    // ----------------------------------------------------------------

    /**
     * Enforces the platform password policy.
     *
     * Rules:
     *   - At least 8 characters
     *   - At least one uppercase letter
     *   - At least one digit
     *
     * @throws \InvalidArgumentException when the password violates a rule
     */
    public function validatePassword(string $password): bool
    {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new \InvalidArgumentException('Password must contain at least one uppercase letter.');
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new \InvalidArgumentException('Password must contain at least one digit.');
        }

        return true;
    }

    // ----------------------------------------------------------------
    // Trial days
    // ----------------------------------------------------------------

    /**
     * Clamps the requested trial duration to the allowed range [1, 365].
     */
    public function clampTrialDays(int $requested): int
    {
        return max(1, min(365, $requested));
    }
}
