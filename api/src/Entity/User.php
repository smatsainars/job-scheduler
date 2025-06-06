<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JsonSerializable;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'], name: 'idx_email')]
#[ORM\Index(columns: ['google_id'], name: 'idx_google_id')]
class User implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $email;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 100)]
    private string $firstName;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 100)]
    private string $lastName;

    #[ORM\Column(name: 'google_id', type: Types::STRING, length: 50, unique: true)]
    private string $googleId;

    #[ORM\Column(name: 'profile_picture', type: Types::TEXT, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'last_login_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: EventParticipant::class)]
    private Collection $eventParticipants;

    public function __construct()
    {
        $this->eventParticipants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getGoogleId(): string
    {
        return $this->googleId;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getEventParticipants(): Collection
    {
        return $this->eventParticipants;
    }

    // Setters
    public function setEmail(string $email): self
    {
        $this->email = $email;
        $this->updateTimestamp();
        return $this;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        $this->updateTimestamp();
        return $this;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        $this->updateTimestamp();
        return $this;
    }

    public function setGoogleId(string $googleId): self
    {
        $this->googleId = $googleId;
        $this->updateTimestamp();
        return $this;
    }

    public function setProfilePicture(?string $profilePicture): self
    {
        $this->profilePicture = $profilePicture;
        $this->updateTimestamp();
        return $this;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        $this->updateTimestamp();
        return $this;
    }

    public function updateLastLogin(): self
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        return $this;
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'fullName' => $this->getFullName(),
            'profilePicture' => $this->profilePicture,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
            'lastLoginAt' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
        ];
    }
}