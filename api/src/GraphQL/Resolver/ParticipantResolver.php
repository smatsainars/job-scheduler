<?php

declare(strict_types=1);

namespace App\GraphQL\Resolver;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ParticipantResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    // Query resolvers
    public function getEventParticipants(array $args): array
    {
        $event = $this->entityManager
            ->getRepository(Event::class)
            ->find($args['eventId']);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        return $event->getParticipants()->toArray();
    }

    public function getMyParticipations(array $context): array
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');

        return $this->entityManager->createQueryBuilder()
            ->select('ep')
            ->from(EventParticipant::class, 'ep')
            ->join('ep.event', 'e')
            ->where('ep.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Mutation resolvers
    public function assignParticipant(array $args, array $context): EventParticipant
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        $input = $args['input'];

        $event = $this->entityManager
            ->getRepository(Event::class)
            ->find($input['eventId']);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        $targetUser = $this->entityManager
            ->getRepository(User::class)
            ->find($input['userId']);

        if (!$targetUser) {
            throw new \Exception('User not found');
        }

        // Check permissions (organizers and coordinators can assign workers)
        $userParticipation = $this->getUserParticipation($event, $user);
        if (!$userParticipation || !$userParticipation->canAssignWorkers()) {
            throw new \Exception('Insufficient permissions to assign participants');
        }

        // Check if user is already a participant
        $existingParticipation = $this->getUserParticipation($event, $targetUser);
        if ($existingParticipation) {
            throw new \Exception('User is already a participant in this event');
        }

        $participant = new EventParticipant();
        $participant->setUser($targetUser);
        $participant->setEvent($event);
        $participant->setRole(strtolower($input['role']));

        if (isset($input['notes'])) {
            $participant->setNotes($input['notes']);
        }

        $this->entityManager->persist($participant);
        $this->entityManager->flush();

        return $participant;
    }

    public function updateParticipantRole(array $args, array $context): EventParticipant
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        
        $participant = $this->entityManager
            ->getRepository(EventParticipant::class)
            ->find($args['id']);

        if (!$participant) {
            throw new \Exception('Participant not found');
        }

        // Check permissions (organizers and coordinators can update roles)
        $userParticipation = $this->getUserParticipation($participant->getEvent(), $user);
        if (!$userParticipation || !$userParticipation->canAssignWorkers()) {
            throw new \Exception('Insufficient permissions to update participant role');
        }

        $participant->setRole(strtolower($args['role']));
        $this->entityManager->flush();

        return $participant;
    }

    public function updateParticipantStatus(array $args, array $context): EventParticipant
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        
        $participant = $this->entityManager
            ->getRepository(EventParticipant::class)
            ->find($args['id']);

        if (!$participant) {
            throw new \Exception('Participant not found');
        }

        // Users can update their own status, or supervisors+ can update others
        $userParticipation = $this->getUserParticipation($participant->getEvent(), $user);
        $isOwnParticipation = $participant->getUser()->getId() === $user->getId();

        if (!$isOwnParticipation && (!$userParticipation || !$userParticipation->canTrackAttendance())) {
            throw new \Exception('Insufficient permissions to update participant status');
        }

        $participant->setStatus(strtolower($args['status']));
        $this->entityManager->flush();

        return $participant;
    }

    public function removeParticipant(array $args, array $context): bool
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        
        $participant = $this->entityManager
            ->getRepository(EventParticipant::class)
            ->find($args['id']);

        if (!$participant) {
            throw new \Exception('Participant not found');
        }

        // Check permissions (organizers and coordinators can remove participants)
        $userParticipation = $this->getUserParticipation($participant->getEvent(), $user);
        if (!$userParticipation || !$userParticipation->canAssignWorkers()) {
            throw new \Exception('Insufficient permissions to remove participant');
        }

        // Don't allow removing the last organizer
        if ($participant->isOrganizer()) {
            $organizerCount = $this->entityManager->createQueryBuilder()
                ->select('COUNT(ep)')
                ->from(EventParticipant::class, 'ep')
                ->where('ep.event = :event')
                ->andWhere('ep.role = :role')
                ->setParameter('event', $participant->getEvent())
                ->setParameter('role', EventParticipant::ROLE_ORGANIZER)
                ->getQuery()
                ->getSingleScalarResult();

            if ($organizerCount <= 1) {
                throw new \Exception('Cannot remove the last organizer from the event');
            }
        }

        $this->entityManager->remove($participant);
        $this->entityManager->flush();

        return true;
    }

    public function joinEvent(array $args, array $context): EventParticipant
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        
        $event = $this->entityManager
            ->getRepository(Event::class)
            ->find($args['eventId']);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        // Check if user is already a participant
        $existingParticipation = $this->getUserParticipation($event, $user);
        if ($existingParticipation) {
            throw new \Exception('You are already a participant in this event');
        }

        $participant = new EventParticipant();
        $participant->setUser($user);
        $participant->setEvent($event);
        $participant->setRole(EventParticipant::ROLE_WORKER); // Default to worker role
        $participant->setStatus(EventParticipant::STATUS_ACCEPTED); // Auto-accept self-joins

        $this->entityManager->persist($participant);
        $this->entityManager->flush();

        return $participant;
    }

    public function leaveEvent(array $args, array $context): bool
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        
        $event = $this->entityManager
            ->getRepository(Event::class)
            ->find($args['eventId']);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        $participation = $this->getUserParticipation($event, $user);
        if (!$participation) {
            throw new \Exception('You are not a participant in this event');
        }

        // Don't allow leaving if you're the last organizer
        if ($participation->isOrganizer()) {
            $organizerCount = $this->entityManager->createQueryBuilder()
                ->select('COUNT(ep)')
                ->from(EventParticipant::class, 'ep')
                ->where('ep.event = :event')
                ->andWhere('ep.role = :role')
                ->setParameter('event', $event)
                ->setParameter('role', EventParticipant::ROLE_ORGANIZER)
                ->getQuery()
                ->getSingleScalarResult();

            if ($organizerCount <= 1) {
                throw new \Exception('Cannot leave event as the last organizer');
            }
        }

        $this->entityManager->remove($participation);
        $this->entityManager->flush();

        return true;
    }

    public function respondToInvitation(array $args, array $context): EventParticipant
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        
        $participant = $this->entityManager
            ->getRepository(EventParticipant::class)
            ->find($args['participantId']);

        if (!$participant) {
            throw new \Exception('Participation not found');
        }

        // Users can only respond to their own invitations
        if ($participant->getUser()->getId() !== $user->getId()) {
            throw new \Exception('You can only respond to your own invitations');
        }

        $participant->setStatus(strtolower($args['status']));
        $this->entityManager->flush();

        return $participant;
    }

    public function checkIn(array $args, array $context): EventParticipant
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        
        $event = $this->entityManager
            ->getRepository(Event::class)
            ->find($args['eventId']);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        $participation = $this->getUserParticipation($event, $user);
        if (!$participation) {
            throw new \Exception('You are not a participant in this event');
        }

        if ($participation->isCurrentlyWorking()) {
            throw new \Exception('You are already checked in');
        }

        $participation->checkIn();
        $this->entityManager->flush();

        return $participation;
    }

    public function checkOut(array $args, array $context): EventParticipant
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        
        $event = $this->entityManager
            ->getRepository(Event::class)
            ->find($args['eventId']);

        if (!$event) {
            throw new \Exception('Event not found');
        }

        $participation = $this->getUserParticipation($event, $user);
        if (!$participation) {
            throw new \Exception('You are not a participant in this event');
        }

        if (!$participation->isCurrentlyWorking()) {
            throw new \Exception('You are not currently checked in');
        }

        $participation->checkOut();
        $this->entityManager->flush();

        return $participation;
    }

    // Field resolvers
    public function resolveUser(EventParticipant $participant): User
    {
        return $participant->getUser();
    }

    public function resolveEvent(EventParticipant $participant): Event
    {
        return $participant->getEvent();
    }

    public function resolveWorkedMinutes(EventParticipant $participant): ?int
    {
        return $participant->getWorkedMinutes();
    }

    public function resolveIsCurrentlyWorking(EventParticipant $participant): bool
    {
        return $participant->isCurrentlyWorking();
    }

    public function resolveCanCreateEvents(EventParticipant $participant): bool
    {
        return $participant->canCreateEvents();
    }

    public function resolveCanAssignWorkers(EventParticipant $participant): bool
    {
        return $participant->canAssignWorkers();
    }

    public function resolveCanTrackAttendance(EventParticipant $participant): bool
    {
        return $participant->canTrackAttendance();
    }

    public function resolveCanSignUpForWork(EventParticipant $participant): bool
    {
        return $participant->canSignUpForWork();
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