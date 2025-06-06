<?php

declare(strict_types=1);

namespace App\GraphQL\Resolver;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class EventResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    // Query resolvers
    public function getEvents(array $args): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->orderBy('e.startTime', 'DESC');

        // Apply filters
        if (isset($args['status'])) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', strtolower($args['status']));
        }

        if (isset($args['priority'])) {
            $qb->andWhere('e.priority = :priority')
               ->setParameter('priority', strtolower($args['priority']));
        }

        if (isset($args['startDate'])) {
            $qb->andWhere('e.startTime >= :startDate')
               ->setParameter('startDate', new \DateTimeImmutable($args['startDate']));
        }

        if (isset($args['endDate'])) {
            $qb->andWhere('e.startTime <= :endDate')
               ->setParameter('endDate', new \DateTimeImmutable($args['endDate']));
        }

        // Apply pagination
        if (isset($args['limit'])) {
            $qb->setMaxResults($args['limit']);
        }

        if (isset($args['offset'])) {
            $qb->setFirstResult($args['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    public function getEvent(array $args): ?Event
    {
        return $this->entityManager
            ->getRepository(Event::class)
            ->find($args['id']);
    }

    public function getMyEvents(array $context): array
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');

        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->join('e.participants', 'p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getUpcomingEvents(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.startTime > :now')
            ->andWhere('e.status != :cancelled')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('cancelled', Event::STATUS_CANCELLED)
            ->orderBy('e.startTime', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    // Mutation resolvers
    public function createEvent(array $args, array $context): Event
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        $input = $args['input'];

        $event = new Event();
        $event->setTitle($input['title']);
        
        if (isset($input['description'])) {
            $event->setDescription($input['description']);
        }

        $event->setStartTime(new \DateTimeImmutable($input['startTime']));

        if (isset($input['endTime'])) {
            $event->setEndTime(new \DateTimeImmutable($input['endTime']));
        }

        if (isset($input['estimatedDurationMinutes'])) {
            $event->setEstimatedDurationMinutes($input['estimatedDurationMinutes']);
        }

        if (isset($input['priority'])) {
            $event->setPriority(strtolower($input['priority']));
        }

        if (isset($input['location'])) {
            $event->setLocation($input['location']);
        }

        if (isset($input['lunchProvided'])) {
            $event->setLunchProvided($input['lunchProvided']);
        }

        if (isset($input['metadata'])) {
            $event->setMetadata($input['metadata']);
        }

        if (isset($input['isRecurring'])) {
            $event->setIsRecurring($input['isRecurring']);
        }

        if (isset($input['recurringPattern'])) {
            $event->setRecurringPattern($input['recurringPattern']);
        }

        $this->entityManager->persist($event);

        // Add the creator as organizer
        $organizerParticipant = new EventParticipant();
        $organizerParticipant->setUser($user);
        $organizerParticipant->setEvent($event);
        $organizerParticipant->setRole(EventParticipant::ROLE_ORGANIZER);
        $organizerParticipant->setStatus(EventParticipant::STATUS_ACCEPTED);

        $this->entityManager->persist($organizerParticipant);

        // Add additional participants if provided
        if (isset($input['participantInputs'])) {
            foreach ($input['participantInputs'] as $participantInput) {
                $participantUser = $this->entityManager
                    ->getRepository(User::class)
                    ->find($participantInput['userId']);

                if ($participantUser) {
                    $participant = new EventParticipant();
                    $participant->setUser($participantUser);
                    $participant->setEvent($event);
                    $participant->setRole(strtolower($participantInput['role']));
                    
                    if (isset($participantInput['notes'])) {
                        $participant->setNotes($participantInput['notes']);
                    }

                    $this->entityManager->persist($participant);
                }
            }
        }

        $this->entityManager->flush();

        return $event;
    }

    public function updateEvent(array $args, array $context): Event
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        $event = $this->entityManager->getRepository(Event::class)->find($args['id']);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        // Check permissions (only organizers and coordinators can update events)
        $userParticipation = $this->getUserParticipation($event, $user);
        if (!$userParticipation || !$userParticipation->canAssignWorkers()) {
            throw new \Exception('Insufficient permissions to update event');
        }

        $input = $args['input'];

        if (isset($input['title'])) {
            $event->setTitle($input['title']);
        }

        if (isset($input['description'])) {
            $event->setDescription($input['description']);
        }

        if (isset($input['startTime'])) {
            $event->setStartTime(new \DateTimeImmutable($input['startTime']));
        }

        if (isset($input['endTime'])) {
            $event->setEndTime(new \DateTimeImmutable($input['endTime']));
        }

        if (isset($input['estimatedDurationMinutes'])) {
            $event->setEstimatedDurationMinutes($input['estimatedDurationMinutes']);
        }

        if (isset($input['status'])) {
            $event->setStatus(strtolower($input['status']));
        }

        if (isset($input['priority'])) {
            $event->setPriority(strtolower($input['priority']));
        }

        if (isset($input['location'])) {
            $event->setLocation($input['location']);
        }

        if (isset($input['lunchProvided'])) {
            $event->setLunchProvided($input['lunchProvided']);
        }

        if (isset($input['attendanceNotes'])) {
            $event->setAttendanceNotes($input['attendanceNotes']);
        }

        if (isset($input['metadata'])) {
            $event->setMetadata($input['metadata']);
        }

        if (isset($input['isRecurring'])) {
            $event->setIsRecurring($input['isRecurring']);
        }

        if (isset($input['recurringPattern'])) {
            $event->setRecurringPattern($input['recurringPattern']);
        }

        $this->entityManager->flush();

        return $event;
    }

    public function deleteEvent(array $args, array $context): bool
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        $event = $this->entityManager->getRepository(Event::class)->find($args['id']);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        // Check permissions (only organizers can delete events)
        $userParticipation = $this->getUserParticipation($event, $user);
        if (!$userParticipation || !$userParticipation->isOrganizer()) {
            throw new \Exception('Only organizers can delete events');
        }

        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return true;
    }

    public function updateEventTimes(array $args, array $context): Event
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        $event = $this->entityManager->getRepository(Event::class)->find($args['id']);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        // Check permissions (supervisors and above can update times)
        $userParticipation = $this->getUserParticipation($event, $user);
        if (!$userParticipation || !$userParticipation->canTrackAttendance()) {
            throw new \Exception('Insufficient permissions to update event times');
        }

        $input = $args['input'];

        if (isset($input['actualStartTime'])) {
            $event->setActualStartTime(new \DateTimeImmutable($input['actualStartTime']));
        }

        if (isset($input['actualEndTime'])) {
            $event->setActualEndTime(new \DateTimeImmutable($input['actualEndTime']));
        }

        if (isset($input['lunchProvided'])) {
            $event->setLunchProvided($input['lunchProvided']);
        }

        if (isset($input['attendanceNotes'])) {
            $event->setAttendanceNotes($input['attendanceNotes']);
        }

        $this->entityManager->flush();

        return $event;
    }

    // Field resolvers
    public function resolveParticipants(Event $event): array
    {
        return $event->getParticipants()->toArray();
    }

    public function resolveParticipantsCount(Event $event): int
    {
        return $event->getParticipants()->count();
    }

    public function resolveIsCompleted(Event $event): bool
    {
        return $event->isCompleted();
    }

    public function resolveIsCancelled(Event $event): bool
    {
        return $event->isCancelled();
    }

    public function resolveIsInProgress(Event $event): bool
    {
        return $event->isInProgress();
    }

    public function resolveIsPending(Event $event): bool
    {
        return $event->isPending();
    }

    private function getUserParticipation(Event $event, User $user): ?EventParticipant
    {
        foreach ($event->getParticipants() as $participant) {
            if ($participant->getUser()->getId() === $user->getId()) {
                return $participant;
            }
        }
        return null;
    }
}