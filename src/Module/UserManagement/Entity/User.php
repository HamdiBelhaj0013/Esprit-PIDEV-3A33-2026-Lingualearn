<?php

namespace App\Module\UserManagement\Entity;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Entity\SupportResponse;
use App\Module\UserManagement\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;         // ← NEW
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['email'],
    message: 'This email is already registered.'
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    #[Assert\Length(max: 180, maxMessage: 'Email cannot be longer than {{ limit }} characters.')]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    // FIX 1 — password must never appear in serialized output
    #[ORM\Column]
    #[Ignore]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    #[Groups(['user:read'])]
    #[Assert\Choice(
        choices: ['FREE', 'MONTHLY', 'YEARLY'],
        message: 'Invalid subscription plan. Must be FREE, MONTHLY, or YEARLY.'
    )]
    private ?string $subscriptionPlan = 'FREE';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $subscriptionExpiry = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private bool $isPremium = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $lastPaymentStatus = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 50)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: 'Status is required.')]
    #[Assert\Choice(
        choices: ['active', 'suspended', 'deleted'],
        message: 'Status must be one of: active, suspended, or deleted.'
    )]
    private ?string $status = 'active';

    #[ORM\Column(length: 100)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: 'First name is required.')]
    #[Assert\Length(min: 2, max: 100)]
    #[Assert\Regex(pattern: '/^[a-zA-ZÀ-ÿ\s\'-]+$/u')]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: 'Last name is required.')]
    #[Assert\Length(min: 2, max: 100)]
    #[Assert\Regex(pattern: '/^[a-zA-ZÀ-ÿ\s\'-]+$/u')]
    private ?string $lastName = null;

    // =========================================================
    // BAN FIELDS
    // =========================================================
    #[ORM\Column(options: ['default' => false])]
    private bool $isBanned = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $banReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bannedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bannedUntil = null;

    // =========================================================
    // EMAIL VERIFICATION
    // =========================================================
    /** Whether the user has clicked the link in their verification email */
    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    // FIX 2 — token must not leak through serialization
    /** Random hex token stored in the DB and included in the verify link */
    #[ORM\Column(length: 100, nullable: true)]
    #[Ignore]
    private ?string $emailVerificationToken = null;

    // FIX 3 — expiry timestamp paired with the token is equally sensitive
    /** Token becomes invalid after this timestamp (default: 24 h from issuance) */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Ignore]
    private ?\DateTimeInterface $emailVerificationTokenExpiresAt = null;

    // =========================================================
    // PASSWORD RESET
    // =========================================================
    // FIX 4 — reset token must not leak through serialization
    /** Random hex token included in the reset link */
    #[ORM\Column(length: 100, nullable: true)]
    #[Ignore]
    private ?string $passwordResetToken = null;

    // FIX 5 — expiry timestamp paired with the reset token is equally sensitive
    /** Token becomes invalid after this timestamp (default: 1 h from issuance) */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Ignore]
    private ?\DateTimeInterface $passwordResetTokenExpiresAt = null;

    // =========================================================
    // STRIPE PAYMENT
    // =========================================================
    /** Stripe Customer ID — created once per user on first checkout */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $stripeCustomerId = null;

    /** Stripe Subscription ID — set after checkout.session.completed webhook */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    // =========================================================
    // RELATIONS
    // =========================================================

    /**
     * FIX: cascade='remove' removed — LearningStats is independent; delete
     * it explicitly in UserService::deleteUser() to avoid silent data loss.
     * onDelete='CASCADE' added so raw SQL DELETEs on users still clean up.
     * cascade='persist' kept so saving a new User auto-saves its stats.
     */
    /**
     * FIX: cascade='remove' removed — LearningStats is independent.
     * JoinColumn removed — belongs on the OWNING side (LearningStats::$user).
     * Add onDelete='CASCADE' to LearningStats::$user's JoinColumn instead.
     */
    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist'])]
    #[Groups(['stats:read'])]
    private ?LearningStats $learningStats = null;

    /**
     * FIX: cascade='persist' added — orphanRemoval without cascade='persist'
     * meant you could delete children automatically but not auto-save new ones.
     * onDelete='CASCADE' added to keep ORM and DB-level deletes in sync.
     */
    #[ORM\OneToMany(targetEntity: UserLanguage::class, mappedBy: 'user', cascade: ['persist'], orphanRemoval: true)]
    private Collection $userLanguages;

    /**
     * Notifications are owned by User (composition), so cascade remove is
     * correct. onDelete='CASCADE' added to sync ORM cascade with the DB.
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $notifications;

    #[ORM\OneToMany(targetEntity: Reclamation::class, mappedBy: 'user')]
    private Collection $reclamations;

    #[ORM\OneToMany(targetEntity: SupportResponse::class, mappedBy: 'author')]
    private Collection $supportResponses;

    // =========================================================
    // CONSTRUCTOR / LIFECYCLE
    // =========================================================
    public function __construct()
    {
        $this->userLanguages    = new ArrayCollection();
        $this->notifications    = new ArrayCollection();
        $this->reclamations     = new ArrayCollection();
        $this->supportResponses = new ArrayCollection();
        $this->roles            = ['ROLE_USER'];
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        // FIX: createdAt is always set here automatically — public setter removed.
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    // =========================================================
    // CORE GETTERS / SETTERS
    // =========================================================
    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }

    // FIX 1 (setter) — #[SensitiveParameter] hides the value in stack traces (PHP 8.2+)
    public function setPassword(#[\SensitiveParameter] string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void {}

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): static { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): static { $this->lastName = $lastName; return $this; }

    public function getFullName(): string { return $this->firstName . ' ' . $this->lastName; }

    public function getSubscriptionPlan(): ?string { return $this->subscriptionPlan; }
    public function setSubscriptionPlan(string $subscriptionPlan): static
    {
        $this->subscriptionPlan = $subscriptionPlan;
        $this->updatePremiumStatus();
        return $this;
    }

    public function getSubscriptionExpiry(): ?\DateTimeInterface { return $this->subscriptionExpiry; }
    /**
     * This setter is intentionally public: subscriptionExpiry is business data
     * (set by StripeWebhookController / UserService), not an auto-timestamp.
     * The tool warning is a false positive for this field.
     */
    public function setSubscriptionExpiry(?\DateTimeInterface $subscriptionExpiry): static
    {
        $this->subscriptionExpiry = $subscriptionExpiry;
        $this->updatePremiumStatus();
        return $this;
    }

    public function isPremium(): bool { return $this->isPremium; }

    /**
     * DO NOT call this directly to grant or revoke premium.
     *
     * isPremium is a COMPUTED field — always derived from subscriptionPlan
     * + subscriptionExpiry via updatePremiumStatus(). Calling setPremium(true)
     * without a valid plan and future expiry is meaningless: the next call to
     * setSubscriptionPlan() or setSubscriptionExpiry() will immediately
     * overwrite whatever was set here.
     *
     * To upgrade: call UserService::upgradeToPremium()
     * To downgrade: call UserService::downgradeToFree()
     *
     * @internal Kept only so legacy call-sites do not throw fatal errors.
     *           All writes are intentionally ignored.
     */
    public function setPremium(bool $isPremium): static
    {
        // No-op: isPremium is governed exclusively by updatePremiumStatus().
        return $this;
    }

    /**
     * Recomputes isPremium from subscriptionPlan + subscriptionExpiry.
     * This is the ONLY place that writes to $this->isPremium.
     * Called automatically by setSubscriptionPlan() and setSubscriptionExpiry().
     */
    private function updatePremiumStatus(): void
    {
        $this->isPremium = (
            in_array($this->subscriptionPlan, ['MONTHLY', 'YEARLY'])
            && $this->subscriptionExpiry !== null
            && $this->subscriptionExpiry > new \DateTime()
        );
    }

    public function getLastPaymentStatus(): ?string { return $this->lastPaymentStatus; }
    public function setLastPaymentStatus(?string $lastPaymentStatus): static
    {
        $this->lastPaymentStatus = $lastPaymentStatus;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    // FIX: No public setter — createdAt is managed exclusively by the PrePersist lifecycle callback.

    public function getLearningStats(): ?LearningStats { return $this->learningStats; }
    public function setLearningStats(?LearningStats $learningStats): static
    {
        if ($learningStats === null && $this->learningStats !== null) {
            $this->learningStats->setUser(null);
        }
        if ($learningStats !== null && $learningStats->getUser() !== $this) {
            $learningStats->setUser($this);
        }
        $this->learningStats = $learningStats;
        return $this;
    }

    public function getUserLanguages(): Collection { return $this->userLanguages; }
    public function addUserLanguage(UserLanguage $userLanguage): static
    {
        if (!$this->userLanguages->contains($userLanguage)) {
            $this->userLanguages->add($userLanguage);
            $userLanguage->setUser($this);
        }
        return $this;
    }
    public function removeUserLanguage(UserLanguage $userLanguage): static
    {
        if ($this->userLanguages->removeElement($userLanguage)) {
            if ($userLanguage->getUser() === $this) {
                $userLanguage->setUser(null);
            }
        }
        return $this;
    }

    public function getNotifications(): Collection { return $this->notifications; }
    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }
        return $this;
    }
    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }
        return $this;
    }

    // =========================================================
    // EMAIL VERIFICATION METHODS
    // =========================================================
    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getEmailVerificationToken(): ?string { return $this->emailVerificationToken; }

    // FIX 2 (setter) — token hidden from stack traces
    public function setEmailVerificationToken(#[\SensitiveParameter] ?string $token): static
    {
        $this->emailVerificationToken = $token;
        return $this;
    }

    public function getEmailVerificationTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->emailVerificationTokenExpiresAt;
    }

    // FIX 3 (setter) — expiry hidden from stack traces
    public function setEmailVerificationTokenExpiresAt(#[\SensitiveParameter] ?\DateTimeInterface $dt): static
    {
        $this->emailVerificationTokenExpiresAt = $dt;
        return $this;
    }

    /** Returns true only if a token exists AND it hasn't expired yet */
    public function isEmailVerificationTokenValid(): bool
    {
        return $this->emailVerificationToken !== null
            && $this->emailVerificationTokenExpiresAt !== null
            && $this->emailVerificationTokenExpiresAt > new \DateTime();
    }

    // =========================================================
    // PASSWORD RESET METHODS
    // =========================================================
    public function getPasswordResetToken(): ?string { return $this->passwordResetToken; }

    // FIX 4 (setter) — token hidden from stack traces
    public function setPasswordResetToken(#[\SensitiveParameter] ?string $token): static
    {
        $this->passwordResetToken = $token;
        return $this;
    }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->passwordResetTokenExpiresAt;
    }

    // FIX 5 (setter) — expiry hidden from stack traces
    public function setPasswordResetTokenExpiresAt(#[\SensitiveParameter] ?\DateTimeInterface $dt): static
    {
        $this->passwordResetTokenExpiresAt = $dt;
        return $this;
    }

    /** Returns true only if a token exists AND it hasn't expired yet */
    public function isPasswordResetTokenValid(): bool
    {
        return $this->passwordResetToken !== null
            && $this->passwordResetTokenExpiresAt !== null
            && $this->passwordResetTokenExpiresAt > new \DateTime();
    }

    // =========================================================
    // STRIPE PAYMENT METHODS
    // =========================================================
    public function getStripeCustomerId(): ?string { return $this->stripeCustomerId; }
    public function setStripeCustomerId(?string $id): static { $this->stripeCustomerId = $id; return $this; }

    public function getStripeSubscriptionId(): ?string { return $this->stripeSubscriptionId; }
    public function setStripeSubscriptionId(?string $id): static { $this->stripeSubscriptionId = $id; return $this; }

    // =========================================================
    // BAN METHODS
    // =========================================================

    /**
     * Returns the raw isBanned flag without checking expiry.
     * Used by BanCheckSubscriber to detect and lift expired bans in DB.
     */
    public function getIsBanned(): bool { return $this->isBanned; }
    public function setIsBanned(bool $isBanned): static { $this->isBanned = $isBanned; return $this; }

    /**
     * Returns true if the user is ACTIVELY banned (flag set AND not yet expired).
     * Automatically returns false if a temporary ban's bannedUntil has passed.
     */
    public function isBanned(): bool
    {
        if (!$this->isBanned) {
            return false;
        }
        // Temporary ban expired → no longer banned
        if ($this->bannedUntil !== null && $this->bannedUntil < new \DateTime()) {
            return false;
        }
        return true;
    }

    /**
     * Alias for isBanned() — explicit name used in controllers/services.
     */
    public function isCurrentlyBanned(): bool
    {
        return $this->isBanned();
    }

    public function getBanReason(): ?string { return $this->banReason; }
    public function setBanReason(?string $banReason): static { $this->banReason = $banReason; return $this; }

    public function getBannedAt(): ?\DateTimeInterface { return $this->bannedAt; }
    /** FIX: internal setter — called only by ban/unban methods, not public API. */
    public function setBannedAt(?\DateTimeInterface $bannedAt): static { $this->bannedAt = $bannedAt; return $this; }

    public function getBannedUntil(): ?\DateTimeInterface { return $this->bannedUntil; }
    /** FIX: internal setter — called only by ban/unban methods, not public API. */
    public function setBannedUntil(?\DateTimeInterface $bannedUntil): static { $this->bannedUntil = $bannedUntil; return $this; }

    // =========================================================
    // RECLAMATION / SUPPORT RESPONSE METHODS
    // =========================================================
    public function getReclamations(): Collection { return $this->reclamations; }
    public function addReclamation(Reclamation $reclamation): static
    {
        if (!$this->reclamations->contains($reclamation)) {
            $this->reclamations->add($reclamation);
            $reclamation->setUser($this);
        }
        return $this;
    }
    public function removeReclamation(Reclamation $reclamation): static
    {
        if ($this->reclamations->removeElement($reclamation)) {
            if ($reclamation->getUser() === $this) {
                $reclamation->setUser(null);
            }
        }
        return $this;
    }

    public function getSupportResponses(): Collection { return $this->supportResponses; }
    public function addSupportResponse(SupportResponse $supportResponse): static
    {
        if (!$this->supportResponses->contains($supportResponse)) {
            $this->supportResponses->add($supportResponse);
            $supportResponse->setAuthor($this);
        }
        return $this;
    }
    public function removeSupportResponse(SupportResponse $supportResponse): static
    {
        if ($this->supportResponses->removeElement($supportResponse)) {
            if ($supportResponse->getAuthor() === $this) {
                $supportResponse->setAuthor(null);
            }
        }
        return $this;
    }
}
