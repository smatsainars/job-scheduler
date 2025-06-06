import { gql } from '@apollo/client';

// Event Queries
export const GET_UPCOMING_EVENTS = gql`
  query GetUpcomingEvents {
    upcomingEvents {
      id
      title
      description
      startTime
      endTime
      status
      priority
      location
      lunchProvided
      participantsCount
      isCompleted
      isInProgress
      isPending
      participants {
        id
        role
        status
        user {
          id
          fullName
          email
        }
      }
    }
  }
`;

export const GET_ALL_EVENTS = gql`
  query GetAllEvents($status: EventStatus, $priority: EventPriority, $limit: Int, $offset: Int) {
    events(status: $status, priority: $priority, limit: $limit, offset: $offset) {
      id
      title
      description
      startTime
      endTime
      status
      priority
      location
      lunchProvided
      participantsCount
    }
  }
`;

export const GET_USERS = gql`
  query GetUsers {
    users {
      id
      fullName
      email
      firstName
      lastName
      isActive
      createdAt
    }
  }
`;

export const GET_ME = gql`
  query GetMe {
    me {
      id
      fullName
      email
      firstName
      lastName
      isActive
    }
  }
`;