<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JsonSerializable;

#[ORM\Entity]
#[ORM\Table(name: 'events')]
#[ORM\Index(columns: ['start_time'], name: 'idx_start_time')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['priority'], name: 'idx_priority')]
class Event implements JsonSerializable
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'start_time', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(name: 'end_time', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(name: 'estimated_duration_minutes', type: Types::INTEGER, nullable: true)]
    private ?int $estimatedDurationMinutes = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: null)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: null)]
    private string $priority = self::PRIORITY_MEDIUM;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'actual_start_time', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actualStartTime = null;

    #[ORM\Column(name: 'actual_end_time', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actualEndTime = null;

    #[ORM\Column(name: 'lunch_provided', type: Types::BOOLEAN)]
    private bool $lunchProvided = false;

    #[ORM\Column(name: 'attendance_notes', type: Types::JSON, nullable: true)]
    private ?array $attendanceNotes = null;

    #[ORM\Column(name: 'is_recurring', type: Types::BOOLEAN)]
    private bool $isRecurring = false;

    #[ORM\Column(name: 'recurring_pattern', type: Types::STRING, length: 50, nullable: true)]
    private ?string $recurringPattern = null;

    #[ORM\Column(name: 'parent_event_id', type: Types::INTEGER, nullable: true)]
    private ?int $parentEventId = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventParticipant::class, cascade: ['persist', 'remove'])]
    private Collection $participants;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getEstimatedDurationMinutes(): ?int
    {
        return $this->estimatedDurationMinutes;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getActualStartTime(): ?\DateTimeImmutable
    {
        return $this->actualStartTime;
    }

    public function getActualEndTime(): ?\DateTimeImmutable
    {
        return $this->actualEndTime;
    }

    public function isLunchProvided(): bool
    {
        return $this->lunchProvided;
    }

    public function getAttendanceNotes(): ?array
    {
        return $this->attendanceNotes;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function getRecurringPattern(): ?string
    {
        return $this->recurringPattern;
    }

    public function getParentEventId(): ?int
    {
        return $this->parentEventId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    // Setters
    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->updateTimestamp();
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        $this->updateTimestamp();
        return $this;
    }

    public function setStartTime(\DateTimeImmutable $startTime): self
    {
        $this->startTime = $startTime;
        $this->updateTimestamp();
        return $this;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): self
    {
        $this->endTime = $endTime;
        $this->updateTimestamp();
        return $this;
    }

    public function setEstimatedDurationMinutes(?int $estimatedDurationMinutes): self
    {
        $this->estimatedDurationMinutes = $estimatedDurationMinutes;
        $this->updateTimestamp();
        return $this;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            throw new \InvalidArgumentException('Invalid status provided');
        }
        $this->status = $status;
        $this->updateTimestamp();
        return $this;
    }

    public function setPriority(string $priority): self
    {
        if (!in_array($priority, [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT])) {
            throw new \InvalidArgumentException('Invalid priority provided');
        }
        $this->priority = $priority;
        $this->updateTimestamp();
        return $this;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        $this->updateTimestamp();
        return $this;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        $this->updateTimestamp();
        return $this;
    }

    public function setActualStartTime(?\DateTimeImmutable $actualStartTime): self
    {
        $this->actualStartTime = $actualStartTime;
        $this->updateTimestamp();
        return $this;
    }

    public function setActualEndTime(?\DateTimeImmutable $actualEndTime): self
    {
        $this->actualEndTime = $actualEndTime;
        $this->updateTimestamp();
        return $this;
    }

    public function setLunchProvided(bool $lunchProvided): self
    {
        $this->lunchProvided = $lunchProvided;
        $this->updateTimestamp();
        return $this;
    }

    public function setAttendanceNotes(?array $attendanceNotes): self
    {
        $this->attendanceNotes = $attendanceNotes;
        $this->updateTimestamp();
        return $this;
    }

    public function setIsRecurring(bool $isRecurring): self
    {
        $this->isRecurring = $isRecurring;
        $this->updateTimestamp();
        return $this;
    }

    public function setRecurringPattern(?string $recurringPattern): self
    {
        $this->recurringPattern = $recurringPattern;
        $this->updateTimestamp();
        return $this;
    }

    public function setParentEventId(?int $parentEventId): self
    {
        $this->parentEventId = $parentEventId;
        $this->updateTimestamp();
        return $this;
    }

    // Helper methods
    public function addParticipant(EventParticipant $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants[] = $participant;
            $participant->setEvent($this);
        }
        $this->updateTimestamp();
        return $this;
    }

    public function removeParticipant(EventParticipant $participant): self
    {
        if ($this->participants->removeElement($participant)) {
            if ($participant->getEvent() === $this) {
                $participant->setEvent(null);
            }
        }
        $this->updateTimestamp();
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getScheduledDurationMinutes(): ?int
    {
        if ($this->endTime && $this->startTime) {
            return (int) (($this->endTime->getTimestamp() - $this->startTime->getTimestamp()) / 60);
        }
        return $this->estimatedDurationMinutes;
    }

    public function getActualDurationMinutes(): ?int
    {
        if ($this->actualEndTime && $this->actualStartTime) {
            return (int) (($this->actualEndTime->getTimestamp() - $this->actualStartTime->getTimestamp()) / 60);
        }
        return null;
    }

    public function getWorkingDurationMinutes(): ?int
    {
        return $this->getActualDurationMinutes();
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'startTime' => $this->startTime->format('Y-m-d H:i:s'),
            'endTime' => $this->endTime?->format('Y-m-d H:i:s'),
            'actualStartTime' => $this->actualStartTime?->format('Y-m-d H:i:s'),
            'actualEndTime' => $this->actualEndTime?->format('Y-m-d H:i:s'),
            'estimatedDurationMinutes' => $this->estimatedDurationMinutes,
            'scheduledDurationMinutes' => $this->getScheduledDurationMinutes(),
            'actualDurationMinutes' => $this->getActualDurationMinutes(),
            'workingDurationMinutes' => $this->getWorkingDurationMinutes(),
            'status' => $this->status,
            'priority' => $this->priority,
            'location' => $this->location,
            'lunchProvided' => $this->lunchProvided,
            'attendanceNotes' => $this->attendanceNotes,
            'metadata' => $this->metadata,
            'isRecurring' => $this->isRecurring,
            'recurringPattern' => $this->recurringPattern,
            'parentEventId' => $this->parentEventId,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
            'participantsCount' => $this->participants->count(),
        ];
    }
}