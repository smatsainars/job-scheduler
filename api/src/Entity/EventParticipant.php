<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use JsonSerializable;

#[ORM\Entity]
#[ORM\Table(name: 'event_participants')]
#[ORM\UniqueConstraint(name: 'unique_user_event', columns: ['user_id', 'event_id'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_id')]
#[ORM\Index(columns: ['event_id'], name: 'idx_event_id')]
#[ORM\Index(columns: ['role'], name: 'idx_role')]
class EventParticipant implements JsonSerializable
{
    public const ROLE_ORGANIZER = 'organizer';        // Tier 1: Creates events, times, locations
    public const ROLE_COORDINATOR = 'coordinator';    // Tier 2: Assigns workers to jobs
    public const ROLE_SUPERVISOR = 'supervisor';      // Tier 3: Tracks time, attendance, lunch
    public const ROLE_WORKER = 'worker';              // Tier 4: Signs up for work

    public const STATUS_INVITED = 'invited';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_TENTATIVE = 'tentative';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'eventParticipants')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: null)]
    private string $role = self::ROLE_WORKER;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: null)]
    private string $status = self::STATUS_INVITED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'responded_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(name: 'check_in_time', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkInTime = null;

    #[ORM\Column(name: 'check_out_time', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkOutTime = null;

    #[ORM\Column(name: 'was_present', type: Types::BOOLEAN)]
    private bool $wasPresent = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function getCheckInTime(): ?\DateTimeImmutable
    {
        return $this->checkInTime;
    }

    public function getCheckOutTime(): ?\DateTimeImmutable
    {
        return $this->checkOutTime;
    }

    public function wasPresent(): bool
    {
        return $this->wasPresent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Setters
    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->updateTimestamp();
        return $this;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;
        $this->updateTimestamp();
        return $this;
    }

    public function setRole(string $role): self
    {
        if (!in_array($role, [self::ROLE_ORGANIZER, self::ROLE_COORDINATOR, self::ROLE_SUPERVISOR, self::ROLE_WORKER])) {
            throw new \InvalidArgumentException('Invalid role provided');
        }
        $this->role = $role;
        $this->updateTimestamp();
        return $this;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, [self::STATUS_INVITED, self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_TENTATIVE])) {
            throw new \InvalidArgumentException('Invalid status provided');
        }
        $this->status = $status;
        $this->respondedAt = new \DateTimeImmutable();
        $this->updateTimestamp();
        return $this;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        $this->updateTimestamp();
        return $this;
    }

    public function setCheckInTime(?\DateTimeImmutable $checkInTime): self
    {
        $this->checkInTime = $checkInTime;
        $this->updateTimestamp();
        return $this;
    }

    public function setCheckOutTime(?\DateTimeImmutable $checkOutTime): self
    {
        $this->checkOutTime = $checkOutTime;
        $this->updateTimestamp();
        return $this;
    }

    public function setWasPresent(bool $wasPresent): self
    {
        $this->wasPresent = $wasPresent;
        $this->updateTimestamp();
        return $this;
    }

    // Helper methods - Role checks
    public function isOrganizer(): bool
    {
        return $this->role === self::ROLE_ORGANIZER;
    }

    public function isCoordinator(): bool
    {
        return $this->role === self::ROLE_COORDINATOR;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function isWorker(): bool
    {
        return $this->role === self::ROLE_WORKER;
    }

    // Permission helpers
    public function canCreateEvents(): bool
    {
        return $this->role === self::ROLE_ORGANIZER;
    }

    public function canAssignWorkers(): bool
    {
        return in_array($this->role, [self::ROLE_ORGANIZER, self::ROLE_COORDINATOR]);
    }

    public function canTrackAttendance(): bool
    {
        return in_array($this->role, [self::ROLE_ORGANIZER, self::ROLE_COORDINATOR, self::ROLE_SUPERVISOR]);
    }

    public function canSignUpForWork(): bool
    {
        return true; // All roles can sign up for work
    }

    // Status helpers
    public function hasAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function hasDeclined(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function isTentative(): bool
    {
        return $this->status === self::STATUS_TENTATIVE;
    }

    public function isInvited(): bool
    {
        return $this->status === self::STATUS_INVITED;
    }

    // Quick status changes
    public function accept(): self
    {
        return $this->setStatus(self::STATUS_ACCEPTED);
    }

    public function decline(): self
    {
        return $this->setStatus(self::STATUS_DECLINED);
    }

    public function markTentative(): self
    {
        return $this->setStatus(self::STATUS_TENTATIVE);
    }

    // Attendance tracking
    public function checkIn(): self
    {
        $this->checkInTime = new \DateTimeImmutable();
        $this->wasPresent = true;
        $this->updateTimestamp();
        return $this;
    }

    public function checkOut(): self
    {
        $this->checkOutTime = new \DateTimeImmutable();
        $this->updateTimestamp();
        return $this;
    }

    public function getWorkedMinutes(): ?int
    {
        if ($this->checkInTime && $this->checkOutTime) {
            return (int) (($this->checkOutTime->getTimestamp() - $this->checkInTime->getTimestamp()) / 60);
        }
        return null;
    }

    public function isCurrentlyWorking(): bool
    {
        return $this->checkInTime !== null && $this->checkOutTime === null;
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->getId(),
                'email' => $this->user->getEmail(),
                'fullName' => $this->user->getFullName(),
                'profilePicture' => $this->user->getProfilePicture(),
            ],
            'event' => [
                'id' => $this->event->getId(),
                'title' => $this->event->getTitle(),
                'startTime' => $this->event->getStartTime()->format('Y-m-d H:i:s'),
            ],
            'role' => $this->role,
            'status' => $this->status,
            'notes' => $this->notes,
            'respondedAt' => $this->respondedAt?->format('Y-m-d H:i:s'),
            'checkInTime' => $this->checkInTime?->format('Y-m-d H:i:s'),
            'checkOutTime' => $this->checkOutTime?->format('Y-m-d H:i:s'),
            'wasPresent' => $this->wasPresent,
            'workedMinutes' => $this->getWorkedMinutes(),
            'isCurrentlyWorking' => $this->isCurrentlyWorking(),
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}