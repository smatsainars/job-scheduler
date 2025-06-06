import React from 'react';
import { ApolloProvider, useQuery } from '@apollo/client';
import { client } from './lib/apollo';
import { GET_UPCOMING_EVENTS, GET_USERS } from './graphql/queries';
import './App.css';

// Loading Component
function LoadingSpinner() {
  return (
    <div className="flex items-center justify-center p-8">
      <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      <span className="ml-3 text-gray-600">Loading...</span>
    </div>
  );
}

// Types
interface User {
  id: string;
  fullName: string;
  email: string;
  isActive: boolean;
}

interface Event {
  id: string;
  title: string;
  description?: string;
  startTime: string;
  endTime?: string;
  status: string;
  priority: string;
  location?: string;
  lunchProvided: boolean;
  participantsCount: number;
}

// Error Component
function ErrorMessage({ error }: { error: Error | { message: string } }) {
  return (
    <div className="bg-red-50 border border-red-200 rounded-md p-4 m-4">
      <div className="flex">
        <div className="ml-3">
          <h3 className="text-sm font-medium text-red-800">
            GraphQL Error
          </h3>
          <div className="mt-2 text-sm text-red-700">
            {error.message}
          </div>
        </div>
      </div>
    </div>
  );
}

// Event Card Component
function EventCard({ event }: { event: Event }) {
  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'URGENT':
        return 'bg-red-100 text-red-800 border-red-200';
      case 'HIGH':
        return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'MEDIUM':
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'LOW':
        return 'bg-green-100 text-green-800 border-green-200';
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  return (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between">
        <div className="flex-1">
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            {event.title}
          </h3>
          {event.description && (
            <p className="text-gray-600 text-sm mb-3">
              {event.description}
            </p>
          )}
          <div className="flex items-center text-sm text-gray-500 mb-2">
            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            {formatDate(event.startTime)}
          </div>
          {event.location && (
            <div className="flex items-center text-sm text-gray-500 mb-2">
              <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              {event.location}
            </div>
          )}
          <div className="flex items-center text-sm text-gray-500">
            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 0a3 3 0 01-3-3V5a3 3 0 113 0v4a3 3 0 01-3 3z" />
            </svg>
            {event.participantsCount} participants
          </div>
        </div>
        <div className="ml-4 flex flex-col items-end space-y-2">
          <span className={`px-2 py-1 text-xs font-medium rounded-full border ${getPriorityColor(event.priority)}`}>
            {event.priority}
          </span>
          <span className={`px-2 py-1 text-xs font-medium rounded-full ${
            event.status === 'PENDING' ? 'bg-yellow-100 text-yellow-800' :
            event.status === 'IN_PROGRESS' ? 'bg-blue-100 text-blue-800' :
            event.status === 'COMPLETED' ? 'bg-green-100 text-green-800' :
            'bg-gray-100 text-gray-800'
          }`}>
            {event.status.replace('_', ' ')}
          </span>
          {event.lunchProvided && (
            <span className="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
              🍽️ Lunch
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

// Stats Card Component
function StatsCard({ title, value, icon }: { title: string; value: number; icon: React.ReactNode }) {
  return (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div className="flex items-center">
        <div className="flex-shrink-0">
          <div className="w-8 h-8 bg-primary-100 rounded-md flex items-center justify-center">
            {icon}
          </div>
        </div>
        <div className="ml-4">
          <dt className="text-sm font-medium text-gray-500 truncate">{title}</dt>
          <dd className="text-2xl font-semibold text-gray-900">{value}</dd>
        </div>
      </div>
    </div>
  );
}

// Main Dashboard Component
function Dashboard() {
  const { data: eventsData, loading: eventsLoading, error: eventsError } = useQuery(GET_UPCOMING_EVENTS);
  const { data: usersData, loading: usersLoading, error: usersError } = useQuery(GET_USERS);

  if (eventsLoading || usersLoading) {
    return <LoadingSpinner />;
  }

  if (eventsError) {
    return <ErrorMessage error={eventsError} />;
  }

  if (usersError) {
    return <ErrorMessage error={usersError} />;
  }

  const events: Event[] = eventsData?.upcomingEvents || [];
  const users: User[] = usersData?.users || [];

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white shadow-sm border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="py-6">
            <div className="md:flex md:items-center md:justify-between">
              <div className="flex-1 min-w-0">
                <h1 className="text-3xl font-bold text-gray-900">
                  🚀 Job Scheduler Dashboard
                </h1>
                <p className="mt-1 text-sm text-gray-500">
                  Manage your events, track time, and coordinate teams
                </p>
              </div>
              <div className="mt-4 flex md:mt-0 md:ml-4">
                <button className="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                  <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                  Create Event
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        {/* Stats */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <StatsCard 
            title="Total Events" 
            value={events.length} 
            icon={<svg className="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>}
          />
          <StatsCard 
            title="Active Users" 
            value={users.filter(u => u.isActive).length} 
            icon={<svg className="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 0a3 3 0 01-3-3V5a3 3 0 113 0v4a3 3 0 01-3 3z" /></svg>}
          />
          <StatsCard 
            title="High Priority" 
            value={events.filter(e => e.priority === 'HIGH' || e.priority === 'URGENT').length} 
            icon={<svg className="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>}
          />
          <StatsCard 
            title="With Lunch" 
            value={events.filter(e => e.lunchProvided).length} 
            icon={<svg className="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0A1.5 1.5 0 013 17.196V21h18v-3.804c0-.527-.351-.65-.546-.65z" /></svg>}
          />
        </div>

        {/* Events Section */}
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-semibold text-gray-900">
              📅 Upcoming Events
            </h2>
            <span className="text-sm text-gray-500">
              {events.length} events scheduled
            </span>
          </div>

          {events.length === 0 ? (
            <div className="text-center py-12">
              <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <h3 className="mt-2 text-sm font-medium text-gray-900">No events yet</h3>
              <p className="mt-1 text-sm text-gray-500">
                Get started by creating your first event.
              </p>
              <div className="mt-6">
                <button className="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                  Create Event
                </button>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {events.map((event: Event) => (
                <EventCard key={event.id} event={event} />
              ))}
            </div>
          )}
        </div>

        {/* API Connection Status */}
        <div className="mt-8 bg-green-50 border border-green-200 rounded-md p-4">
          <div className="flex">
            <div className="flex-shrink-0">
              <svg className="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
              </svg>
            </div>
            <div className="ml-3">
              <h3 className="text-sm font-medium text-green-800">
                ✅ GraphQL API Connected
              </h3>
              <div className="mt-2 text-sm text-green-700">
                Successfully connected to your PHP GraphQL API at localhost:8000
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// Main App Component
function App() {
  return (
    <ApolloProvider client={client}>
      <div className="App">
        <Dashboard />
      </div>
    </ApolloProvider>
  );
}

export default App;