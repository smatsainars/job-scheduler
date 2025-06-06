<?php

declare(strict_types=1);

namespace App\GraphQL\Resolver;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    // Query resolvers
    public function getUsers(): array
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findBy(['isActive' => true], ['firstName' => 'ASC']);
    }

    public function getUser(array $args): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->find($args['id']);
    }

    public function getMe(array $context): ?User
    {
        // This will be populated by authentication middleware
        return $context['user'] ?? null;
    }

    // Mutation resolvers
    public function updateProfile(array $args, array $context): User
    {
        $user = $context['user'] ?? throw new \Exception('Authentication required');
        $input = $args['input'];

        if (isset($input['firstName'])) {
            $user->setFirstName($input['firstName']);
        }

        if (isset($input['lastName'])) {
            $user->setLastName($input['lastName']);
        }

        if (isset($input['profilePicture'])) {
            $user->setProfilePicture($input['profilePicture']);
        }

        $this->entityManager->flush();

        return $user;
    }

    // Field resolvers
    public function resolveEventParticipations(User $user): array
    {
        return $user->getEventParticipants()->toArray();
    }
}