<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\GraphQL\Resolver\UserResolver;
use App\GraphQL\Resolver\EventResolver;
use App\GraphQL\Resolver\ParticipantResolver;
use App\GraphQL\Resolver\ScalarResolver;
use Doctrine\ORM\EntityManagerInterface;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Schema;
use GraphQL\Error\DebugFlag;

class GraphQLHandler
{
    private Schema $schema;
    private UserResolver $userResolver;
    private EventResolver $eventResolver;
    private ParticipantResolver $participantResolver;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->userResolver = new UserResolver($entityManager);
        $this->eventResolver = new EventResolver($entityManager);
        $this->participantResolver = new ParticipantResolver($entityManager);

        $this->schema = $this->createSchema();
    }

    public function handle(string $query, ?array $variables = null, ?array $context = null): array
    {
        try {
            $result = GraphQL::executeQuery(
                $this->schema,
                $query,
                null,
                $context ?? [],
                $variables ?? []
            );

            return $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);
        } catch (\Throwable $e) {
            return [
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]
                ]
            ];
        }
    }

    private function createSchema(): Schema
    {
        // Create scalar types first
        $jsonType = ScalarResolver::createJSONType();

        // Create enums
        $eventStatusEnum = $this->createEventStatusEnum();
        $eventPriorityEnum = $this->createEventPriorityEnum();
        $participantRoleEnum = $this->createParticipantRoleEnum();
        $participantStatusEnum = $this->createParticipantStatusEnum();

        // Create the types with forward references to avoid circular dependencies
        $userType = new ObjectType([
            'name' => 'User',
            'fields' => function() use (&$eventParticipantType) {
                return [
                    'id' => ['type' => Type::nonNull(Type::id())],
                    'email' => ['type' => Type::nonNull(Type::string())],
                    'firstName' => ['type' => Type::nonNull(Type::string())],
                    'lastName' => ['type' => Type::nonNull(Type::string())],
                    'fullName' => ['type' => Type::nonNull(Type::string())],
                    'profilePicture' => ['type' => Type::string()],
                    'isActive' => ['type' => Type::nonNull(Type::boolean())],
                    'createdAt' => ['type' => Type::nonNull(Type::string())],
                    'updatedAt' => ['type' => Type::nonNull(Type::string())],
                    'lastLoginAt' => ['type' => Type::string()],
                    'eventParticipations' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($eventParticipantType))),
                        'resolve' => fn($user) => $user->getEventParticipants()->toArray()
                    ]
                ];
            }
        ]);

        $eventType = new ObjectType([
            'name' => 'Event',
            'fields' => function() use (&$eventParticipantType, $eventStatusEnum, $eventPriorityEnum, $jsonType) {
                return [
                    'id' => ['type' => Type::nonNull(Type::id())],
                    'title' => ['type' => Type::nonNull(Type::string())],
                    'description' => ['type' => Type::string()],
                    'startTime' => ['type' => Type::nonNull(Type::string())],
                    'endTime' => ['type' => Type::string()],
                    'actualStartTime' => ['type' => Type::string()],
                    'actualEndTime' => ['type' => Type::string()],
                    'estimatedDurationMinutes' => ['type' => Type::int()],
                    'scheduledDurationMinutes' => ['type' => Type::int()],
                    'actualDurationMinutes' => ['type' => Type::int()],
                    'workingDurationMinutes' => ['type' => Type::int()],
                    'status' => ['type' => Type::nonNull($eventStatusEnum)],
                    'priority' => ['type' => Type::nonNull($eventPriorityEnum)],
                    'location' => ['type' => Type::string()],
                    'lunchProvided' => ['type' => Type::nonNull(Type::boolean())],
                    'attendanceNotes' => ['type' => Type::listOf(Type::nonNull(Type::string()))],
                    'metadata' => ['type' => $jsonType],
                    'isRecurring' => ['type' => Type::nonNull(Type::boolean())],
                    'recurringPattern' => ['type' => Type::string()],
                    'parentEventId' => ['type' => Type::int()],
                    'createdAt' => ['type' => Type::nonNull(Type::string())],
                    'updatedAt' => ['type' => Type::nonNull(Type::string())],
                    'participants' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($eventParticipantType))),
                        'resolve' => fn($event) => $event->getParticipants()->toArray()
                    ],
                    'participantsCount' => [
                        'type' => Type::nonNull(Type::int()),
                        'resolve' => fn($event) => $event->getParticipants()->count()
                    ],
                    'isCompleted' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => fn($event) => $event->isCompleted()
                    ],
                    'isCancelled' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => fn($event) => $event->isCancelled()
                    ],
                    'isInProgress' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => fn($event) => $event->isInProgress()
                    ],
                    'isPending' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => fn($event) => $event->isPending()
                    ]
                ];
            }
        ]);

        $eventParticipantType = new ObjectType([
            'name' => 'EventParticipant',
            'fields' => function() use ($userType, $eventType, $participantRoleEnum, $participantStatusEnum) {
                return [
                    'id' => ['type' => Type::nonNull(Type::id())],
                    'user' => ['type' => Type::nonNull($userType)],
                    'event' => ['type' => Type::nonNull($eventType)],
                    'role' => ['type' => Type::nonNull($participantRoleEnum)],
                    'status' => ['type' => Type::nonNull($participantStatusEnum)],
                    'notes' => ['type' => Type::string()],
                    'respondedAt' => ['type' => Type::string()],
                    'checkInTime' => ['type' => Type::string()],
                    'checkOutTime' => ['type' => Type::string()],
                    'wasPresent' => ['type' => Type::nonNull(Type::boolean())],
                    'workedMinutes' => ['type' => Type::int()],
                    'isCurrentlyWorking' => ['type' => Type::nonNull(Type::boolean())],
                    'createdAt' => ['type' => Type::nonNull(Type::string())],
                    'updatedAt' => ['type' => Type::nonNull(Type::string())],
                    'canCreateEvents' => ['type' => Type::nonNull(Type::boolean())],
                    'canAssignWorkers' => ['type' => Type::nonNull(Type::boolean())],
                    'canTrackAttendance' => ['type' => Type::nonNull(Type::boolean())],
                    'canSignUpForWork' => ['type' => Type::nonNull(Type::boolean())]
                ];
            }
        ]);

        // Create input types
        $createEventInput = $this->createEventInput($eventPriorityEnum, $participantRoleEnum, $jsonType);
        $updateEventInput = $this->createUpdateEventInput($eventStatusEnum, $eventPriorityEnum, $jsonType);
        $updateEventTimesInput = $this->createUpdateEventTimesInput();
        $assignParticipantInput = $this->createAssignParticipantInput($participantRoleEnum);
        $updateProfileInput = $this->createUpdateProfileInput();

        // Create Query type
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'me' => [
                    'type' => $userType,
                    'resolve' => fn($root, $args, $context) => $this->userResolver->getMe($context)
                ],
                'users' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($userType))),
                    'resolve' => fn($root, $args, $context) => $this->userResolver->getUsers()
                ],
                'user' => [
                    'type' => $userType,
                    'args' => ['id' => Type::nonNull(Type::id())],
                    'resolve' => fn($root, $args, $context) => $this->userResolver->getUser($args)
                ],
                'events' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($eventType))),
                    'args' => [
                        'status' => $eventStatusEnum,
                        'priority' => $eventPriorityEnum,
                        'startDate' => Type::string(),
                        'endDate' => Type::string(),
                        'limit' => Type::int(),
                        'offset' => Type::int()
                    ],
                    'resolve' => fn($root, $args, $context) => $this->eventResolver->getEvents($args)
                ],
                'event' => [
                    'type' => $eventType,
                    'args' => ['id' => Type::nonNull(Type::id())],
                    'resolve' => fn($root, $args, $context) => $this->eventResolver->getEvent($args)
                ],
                'myEvents' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($eventType))),
                    'resolve' => fn($root, $args, $context) => $this->eventResolver->getMyEvents($context)
                ],
                'upcomingEvents' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($eventType))),
                    'resolve' => fn($root, $args, $context) => $this->eventResolver->getUpcomingEvents()
                ],
                'eventParticipants' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($eventParticipantType))),
                    'args' => ['eventId' => Type::nonNull(Type::id())],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->getEventParticipants($args)
                ],
                'myParticipations' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($eventParticipantType))),
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->getMyParticipations($context)
                ]
            ]
        ]);

        // Create Mutation type
        $mutationType = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'updateProfile' => [
                    'type' => Type::nonNull($userType),
                    'args' => ['input' => Type::nonNull($updateProfileInput)],
                    'resolve' => fn($root, $args, $context) => $this->userResolver->updateProfile($args, $context)
                ],
                'createEvent' => [
                    'type' => Type::nonNull($eventType),
                    'args' => ['input' => Type::nonNull($createEventInput)],
                    'resolve' => fn($root, $args, $context) => $this->eventResolver->createEvent($args, $context)
                ],
                'updateEvent' => [
                    'type' => Type::nonNull($eventType),
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                        'input' => Type::nonNull($updateEventInput)
                    ],
                    'resolve' => fn($root, $args, $context) => $this->eventResolver->updateEvent($args, $context)
                ],
                'deleteEvent' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'args' => ['id' => Type::nonNull(Type::id())],
                    'resolve' => fn($root, $args, $context) => $this->eventResolver->deleteEvent($args, $context)
                ],
                'assignParticipant' => [
                    'type' => Type::nonNull($eventParticipantType),
                    'args' => ['input' => Type::nonNull($assignParticipantInput)],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->assignParticipant($args, $context)
                ],
                'updateParticipantRole' => [
                    'type' => Type::nonNull($eventParticipantType),
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                        'role' => Type::nonNull($participantRoleEnum)
                    ],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->updateParticipantRole($args, $context)
                ],
                'updateParticipantStatus' => [
                    'type' => Type::nonNull($eventParticipantType),
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                        'status' => Type::nonNull($participantStatusEnum)
                    ],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->updateParticipantStatus($args, $context)
                ],
                'removeParticipant' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'args' => ['id' => Type::nonNull(Type::id())],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->removeParticipant($args, $context)
                ],
                'joinEvent' => [
                    'type' => Type::nonNull($eventParticipantType),
                    'args' => ['eventId' => Type::nonNull(Type::id())],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->joinEvent($args, $context)
                ],
                'leaveEvent' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'args' => ['eventId' => Type::nonNull(Type::id())],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->leaveEvent($args, $context)
                ],
                'respondToInvitation' => [
                    'type' => Type::nonNull($eventParticipantType),
                    'args' => [
                        'participantId' => Type::nonNull(Type::id()),
                        'status' => Type::nonNull($participantStatusEnum)
                    ],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->respondToInvitation($args, $context)
                ],
                'checkIn' => [
                    'type' => Type::nonNull($eventParticipantType),
                    'args' => ['eventId' => Type::nonNull(Type::id())],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->checkIn($args, $context)
                ],
                'checkOut' => [
                    'type' => Type::nonNull($eventParticipantType),
                    'args' => ['eventId' => Type::nonNull(Type::id())],
                    'resolve' => fn($root, $args, $context) => $this->participantResolver->checkOut($args, $context)
                ],
                'updateEventTimes' => [
                    'type' => Type::nonNull($eventType),
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                        'input' => Type::nonNull($updateEventTimesInput)
                    ],
                    'resolve' => fn($root, $args, $context) => $this->eventResolver->updateEventTimes($args, $context)
                ]
            ]
        ]);

        return new Schema([
            'query' => $queryType,
            'mutation' => $mutationType,
            'types' => [$userType, $eventType, $eventParticipantType, $jsonType]
        ]);
    }

    private function createEventStatusEnum(): EnumType
    {
        return new EnumType([
            'name' => 'EventStatus',
            'values' => [
                'PENDING' => ['value' => 'pending'],
                'IN_PROGRESS' => ['value' => 'in_progress'],
                'COMPLETED' => ['value' => 'completed'],
                'CANCELLED' => ['value' => 'cancelled'],
            ]
        ]);
    }

    private function createEventPriorityEnum(): EnumType
    {
        return new EnumType([
            'name' => 'EventPriority',
            'values' => [
                'LOW' => ['value' => 'low'],
                'MEDIUM' => ['value' => 'medium'],
                'HIGH' => ['value' => 'high'],
                'URGENT' => ['value' => 'urgent'],
            ]
        ]);
    }

    private function createParticipantRoleEnum(): EnumType
    {
        return new EnumType([
            'name' => 'ParticipantRole',
            'values' => [
                'ORGANIZER' => ['value' => 'organizer'],
                'COORDINATOR' => ['value' => 'coordinator'],
                'SUPERVISOR' => ['value' => 'supervisor'],
                'WORKER' => ['value' => 'worker'],
            ]
        ]);
    }

    private function createParticipantStatusEnum(): EnumType
    {
        return new EnumType([
            'name' => 'ParticipantStatus',
            'values' => [
                'INVITED' => ['value' => 'invited'],
                'ACCEPTED' => ['value' => 'accepted'],
                'DECLINED' => ['value' => 'declined'],
                'TENTATIVE' => ['value' => 'tentative'],
            ]
        ]);
    }

    private function createEventInput(EnumType $priorityEnum, EnumType $roleEnum, $jsonType): InputObjectType
    {
        return new InputObjectType([
            'name' => 'CreateEventInput',
            'fields' => [
                'title' => Type::nonNull(Type::string()),
                'description' => Type::string(),
                'startTime' => Type::nonNull(Type::string()),
                'endTime' => Type::string(),
                'estimatedDurationMinutes' => Type::int(),
                'priority' => ['type' => $priorityEnum, 'defaultValue' => 'medium'],
                'location' => Type::string(),
                'lunchProvided' => ['type' => Type::boolean(), 'defaultValue' => false],
                'metadata' => $jsonType,
                'isRecurring' => ['type' => Type::boolean(), 'defaultValue' => false],
                'recurringPattern' => Type::string(),
                'participantInputs' => Type::listOf($this->createParticipantInput($roleEnum))
            ]
        ]);
    }

    private function createUpdateEventInput(EnumType $statusEnum, EnumType $priorityEnum, $jsonType): InputObjectType
    {
        return new InputObjectType([
            'name' => 'UpdateEventInput',
            'fields' => [
                'title' => Type::string(),
                'description' => Type::string(),
                'startTime' => Type::string(),
                'endTime' => Type::string(),
                'estimatedDurationMinutes' => Type::int(),
                'status' => $statusEnum,
                'priority' => $priorityEnum,
                'location' => Type::string(),
                'lunchProvided' => Type::boolean(),
                'attendanceNotes' => Type::listOf(Type::nonNull(Type::string())),
                'metadata' => $jsonType,
                'isRecurring' => Type::boolean(),
                'recurringPattern' => Type::string()
            ]
        ]);
    }

    private function createUpdateEventTimesInput(): InputObjectType
    {
        return new InputObjectType([
            'name' => 'UpdateEventTimesInput',
            'fields' => [
                'actualStartTime' => Type::string(),
                'actualEndTime' => Type::string(),
                'lunchProvided' => Type::boolean(),
                'attendanceNotes' => Type::listOf(Type::nonNull(Type::string()))
            ]
        ]);
    }

    private function createAssignParticipantInput(EnumType $roleEnum): InputObjectType
    {
        return new InputObjectType([
            'name' => 'AssignParticipantInput',
            'fields' => [
                'eventId' => Type::nonNull(Type::id()),
                'userId' => Type::nonNull(Type::id()),
                'role' => Type::nonNull($roleEnum),
                'notes' => Type::string()
            ]
        ]);
    }

    private function createParticipantInput(EnumType $roleEnum): InputObjectType
    {
        return new InputObjectType([
            'name' => 'ParticipantInput',
            'fields' => [
                'userId' => Type::nonNull(Type::id()),
                'role' => Type::nonNull($roleEnum),
                'notes' => Type::string()
            ]
        ]);
    }

    private function createUpdateProfileInput(): InputObjectType
    {
        return new InputObjectType([
            'name' => 'UpdateProfileInput',
            'fields' => [
                'firstName' => Type::string(),
                'lastName' => Type::string(),
                'profilePicture' => Type::string()
            ]
        ]);
    }
}