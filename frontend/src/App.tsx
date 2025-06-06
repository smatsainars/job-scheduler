// frontend/src/App.tsx
import React from 'react';
import { ApolloProvider } from '@apollo/client';
import { client } from './lib/apollo';
import { AuthProvider, useAuth } from './context/AuthContext';
import { LoginPage } from './components/LoginPage';
import { CustomGoogleButton } from './components/GoogleOAuthButton';
import Dashboard from './components/Dashboard'; // Your existing dashboard
import './App.css';

// Loading component
function LoadingScreen() {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="text-center">
        <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full mb-4">
          <span className="text-2xl font-bold text-white">🚀</span>
        </div>
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p className="text-gray-600">Loading your workspace...</p>
      </div>
    </div>
  );
}

// Error component
function ErrorMessage({ error, onRetry }: { error: string; onRetry: () => void }) {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-6 text-center">
        <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
        </div>
        <h3 className="text-lg font-semibold text-gray-900 mb-2">Authentication Error</h3>
        <p className="text-gray-600 mb-4">{error}</p>
        <button
          onClick={onRetry}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors"
        >
          Try Again
        </button>
      </div>
    </div>
  );
}

// Main app content
function AppContent() {
  const { user, isLoading, isAuthenticated, login, logout, error } = useAuth();

  // Show loading screen while checking authentication
  if (isLoading) {
    return <LoadingScreen />;
  }

  // Show error if there's an authentication error
  if (error) {
    return <ErrorMessage error={error} onRetry={() => window.location.reload()} />;
  }

  // Show login page if not authenticated
  if (!isAuthenticated || !user) {
    const handleGoogleLogin = async (credential: string) => {
      try {
        await login(credential);
      } catch (error) {
        console.error('Login failed:', error);
      }
    };

    const handleGoogleError = (error: string) => {
      console.error('Google OAuth error:', error);
    };

    return (
      <LoginPage
        onGoogleLogin={() => {
          // This will be handled by the GoogleOAuthButton component
        }}
        isLoading={isLoading}
      />
    );
  }

  // Show main dashboard if authenticated
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Top navigation with user info and logout */}
      <nav className="bg-white shadow-sm border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center py-4">
            <div className="flex items-center">
              <div className="inline-flex items-center justify-center w-8 h-8 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full mr-3">
                <span className="text-sm font-bold text-white">🚀</span>
              </div>
              <h1 className="text-xl font-semibold text-gray-900">Job Scheduler</h1>
            </div>
            
            <div className="flex items-center space-x-4">
              <div className="flex items-center space-x-3">
                {user.profilePicture && (
                  <img
                    src={user.profilePicture}
                    alt={user.fullName}
                    className="w-8 h-8 rounded-full"
                  />
                )}
                <div className="text-sm">
                  <p className="font-medium text-gray-900">{user.fullName}</p>
                  <p className="text-gray-500">{user.email}</p>
                </div>
              </div>
              <button
                onClick={logout}
                className="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-full p-1"
                title="Sign out"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </nav>

      {/* Main dashboard content */}
      <Dashboard />
    </div>
  );
}

// Root App component
function App() {
  return (
    <AuthProvider>
      <ApolloProvider client={client}>
        <AppContent />
      </ApolloProvider>
    </AuthProvider>
  );
}

export default App;