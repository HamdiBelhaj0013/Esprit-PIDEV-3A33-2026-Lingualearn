<?php

namespace App\Tests\Module\UserManagement\Service;

use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Service\UserManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserManager — covers all business rules of the
 * UserManagement module without touching the database or HTTP layer.
 *
 * Run:  php bin/phpunit tests/Module/UserManagement/Service/UserManagerTest.php --testdox
 */
class UserManagerTest extends TestCase
{
    private UserManager $manager;

    // ----------------------------------------------------------------
    // Setup
    // ----------------------------------------------------------------

    protected function setUp(): void
    {
        $this->manager = new UserManager();
    }

    // ----------------------------------------------------------------
    // Helper: build a fully valid User
    // ----------------------------------------------------------------

    private function makeUser(
        string $firstName    = 'Ali',
        string $lastName     = 'Ben Salah',
        string $email        = 'ali@lingualearn.tn',
        array  $roles        = ['ROLE_USER', 'ROLE_STUDENT'],
        string $plan         = 'FREE',
    ): User {
        $user = new User();
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setSubscriptionPlan($plan);
        return $user;
    }

    // ================================================================
    // PART 1 — validate()
    // ================================================================

    // ✅ Valid student
    public function testValidStudent(): void
    {
        $user = $this->makeUser();
        $this->assertTrue($this->manager->validate($user));
    }

    // ✅ Valid teacher
    public function testValidTeacher(): void
    {
        $user = $this->makeUser(roles: ['ROLE_USER', 'ROLE_TEACHER'], plan: 'MONTHLY');
        $this->assertTrue($this->manager->validate($user));
    }

    // ✅ Valid admin with yearly plan
    public function testValidAdminYearly(): void
    {
        $user = $this->makeUser(roles: ['ROLE_USER', 'ROLE_ADMIN'], plan: 'YEARLY');
        $this->assertTrue($this->manager->validate($user));
    }

    // ❌ Empty first name
    public function testEmptyFirstNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name is required.');

        $user = $this->makeUser(firstName: '   ');
        $this->manager->validate($user);
    }

    // ❌ Empty last name
    public function testEmptyLastNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name is required.');

        $user = $this->makeUser(lastName: '');
        $this->manager->validate($user);
    }

    // ❌ Invalid email format
    public function testInvalidEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address.');

        $user = $this->makeUser(email: 'not-an-email');
        $this->manager->validate($user);
    }

    // ❌ Email without domain
    public function testEmailWithoutDomainThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = $this->makeUser(email: 'ali@');
        $this->manager->validate($user);
    }

    // ❌ Completely unknown role
    public function testInvalidRoleThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role: ROLE_SUPERUSER');

        $user = $this->makeUser(roles: ['ROLE_SUPERUSER']);
        $this->manager->validate($user);
    }

    // ❌ Invalid subscription plan
    public function testInvalidSubscriptionPlanThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid subscription plan: PREMIUM_PLUS');

        $user = $this->makeUser(plan: 'PREMIUM_PLUS');
        $this->manager->validate($user);
    }

    // ================================================================
    // PART 2 — isPremiumActive() + remainingDays()
    // ================================================================

    // ✅ Premium user with future expiry → active
    public function testPremiumActiveWhenExpiryIsFuture(): void
    {
        $user = $this->makeUser(plan: 'MONTHLY');
        $user->setPremium(true);
        $user->setSubscriptionExpiry(new \DateTime('+30 days'));

        $this->assertTrue($this->manager->isPremiumActive($user));
    }

    // ❌ Premium flag true but expiry in the past → not active
    public function testPremiumNotActiveWhenExpired(): void
    {
        $user = $this->makeUser(plan: 'MONTHLY');
        $user->setPremium(true);
        $user->setSubscriptionExpiry(new \DateTime('-1 day'));

        $this->assertFalse($this->manager->isPremiumActive($user));
    }

    // ❌ isPremium flag false → not active regardless of expiry
    public function testPremiumNotActiveWhenFlagFalse(): void
    {
        $user = $this->makeUser();
        $user->setPremium(false);
        $user->setSubscriptionExpiry(new \DateTime('+30 days'));

        $this->assertFalse($this->manager->isPremiumActive($user));
    }

    // ❌ isPremium true but no expiry date → not active
    public function testPremiumNotActiveWithNullExpiry(): void
    {
        $user = $this->makeUser(plan: 'MONTHLY');
        $user->setPremium(true);
        $user->setSubscriptionExpiry(null);

        $this->assertFalse($this->manager->isPremiumActive($user));
    }

    // ✅ Remaining days > 0 for active premium
    public function testRemainingDaysPositiveForActivePremium(): void
    {
        $user = $this->makeUser(plan: 'YEARLY');
        $user->setPremium(true);
        $user->setSubscriptionExpiry(new \DateTime('+60 days'));

        $this->assertGreaterThan(0, $this->manager->remainingDays($user));
    }

    // ✅ Remaining days = 0 for non-premium user
    public function testRemainingDaysZeroForFreeUser(): void
    {
        $user = $this->makeUser(); // FREE plan, not premium
        $user->setPremium(false);

        $this->assertSame(0, $this->manager->remainingDays($user));
    }

    // ================================================================
    // PART 3 — validatePassword()
    // ================================================================

    // ✅ Strong password passes
    public function testStrongPasswordPasses(): void
    {
        $this->assertTrue($this->manager->validatePassword('LinguaLearn2026'));
    }

    // ❌ Too short
    public function testShortPasswordThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 8 characters');

        $this->manager->validatePassword('Ab1');
    }

    // ❌ No uppercase letter
    public function testPasswordWithoutUppercaseThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('uppercase');

        $this->manager->validatePassword('lingualearn2026');
    }

    // ❌ No digit
    public function testPasswordWithoutDigitThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('digit');

        $this->manager->validatePassword('LinguaLearn');
    }

    // ================================================================
    // PART 4 — clampTrialDays()
    // ================================================================

    // ✅ Normal value stays unchanged
    public function testNormalTrialDaysUnchanged(): void
    {
        $this->assertSame(7, $this->manager->clampTrialDays(7));
        $this->assertSame(30, $this->manager->clampTrialDays(30));
    }

    // ✅ Zero → clamped to 1
    public function testZeroTrialDaysClamped(): void
    {
        $this->assertSame(1, $this->manager->clampTrialDays(0));
    }

    // ✅ Negative → clamped to 1
    public function testNegativeTrialDaysClamped(): void
    {
        $this->assertSame(1, $this->manager->clampTrialDays(-99));
    }

    // ✅ Over 365 → clamped to 365
    public function testExcessiveTrialDaysClamped(): void
    {
        $this->assertSame(365, $this->manager->clampTrialDays(999));
    }
}
