// frontend/src/components/GoogleOAuthButton.tsx
import React, { useEffect, useRef, useState } from 'react';

interface GoogleOAuthButtonProps {
  onSuccess: (credential: string) => void;
  onError: (error: string) => void;
  disabled?: boolean;
}

export const GoogleOAuthButton: React.FC<GoogleOAuthButtonProps> = ({
  onSuccess,
  onError,
  disabled = false,
}) => {
  const buttonRef = useRef<HTMLDivElement>(null);
  const [isGoogleLoaded, setIsGoogleLoaded] = useState(false);

  useEffect(() => {
    // Load Google OAuth script
    const loadGoogleScript = () => {
      if (window.google) {
        initializeGoogle();
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;
      script.onload = initializeGoogle;
      script.onerror = () => {
        onError('Failed to load Google OAuth');
      };
      document.head.appendChild(script);
    };

    const initializeGoogle = () => {
      if (!window.google) {
        onError('Google OAuth not available');
        return;
      }

      try {
        window.google.accounts.id.initialize({
          client_id: import.meta.env.VITE_GOOGLE_CLIENT_ID || 'your_google_client_id_here',
          callback: handleCredentialResponse,
          auto_select: false,
        });

        setIsGoogleLoaded(true);

        // Render the button
        if (buttonRef.current) {
          window.google.accounts.id.renderButton(buttonRef.current, {
            theme: 'outline',
            size: 'large',
            width: '100%',
            text: 'signin_with',
            shape: 'rectangular',
          });
        }
      } catch (error) {
        onError('Failed to initialize Google OAuth');
      }
    };

    const handleCredentialResponse = (response: any) => {
      if (response.credential) {
        onSuccess(response.credential);
      } else {
        onError('No credential received from Google');
      }
    };

    loadGoogleScript();
  }, [onSuccess, onError]);

  if (!isGoogleLoaded || disabled) {
    return (
      <button
        disabled
        className="w-full flex items-center justify-center gap-3 bg-gray-100 border-2 border-gray-200 rounded-lg px-6 py-3 text-gray-400 font-medium cursor-not-allowed"
      >
        <div className="w-5 h-5 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin"></div>
        Loading Google OAuth...
      </button>
    );
  }

  return <div ref={buttonRef} className="w-full" />;
};

// Alternative manual button for custom styling
export const CustomGoogleButton: React.FC<GoogleOAuthButtonProps> = ({
  onSuccess,
  onError,
  disabled = false,
}) => {
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    // Load Google OAuth script
    const script = document.createElement('script');
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.defer = true;
    script.onload = () => {
      if (window.google) {
        window.google.accounts.id.initialize({
          client_id: import.meta.env.VITE_GOOGLE_CLIENT_ID || 'your_google_client_id_here',
          callback: handleCredentialResponse,
        });
      }
    };
    document.head.appendChild(script);

    const handleCredentialResponse = (response: any) => {
      if (response.credential) {
        onSuccess(response.credential);
      } else {
        onError('No credential received from Google');
      }
      setIsLoading(false);
    };
  }, [onSuccess, onError]);

  const handleClick = () => {
    if (!window.google) {
      onError('Google OAuth not loaded');
      return;
    }

    setIsLoading(true);
    try {
      window.google.accounts.id.prompt();
    } catch (error) {
      onError('Failed to show Google login');
      setIsLoading(false);
    }
  };

  return (
    <button
      onClick={handleClick}
      disabled={disabled || isLoading}
      className="w-full flex items-center justify-center gap-3 bg-white border-2 border-gray-200 rounded-lg px-6 py-3 text-gray-700 font-medium hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
    >
      {isLoading ? (
        <div className="w-5 h-5 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin"></div>
      ) : (
        <svg className="w-5 h-5" viewBox="0 0 24 24">
          <path
            fill="currentColor"
            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
          />
          <path
            fill="currentColor"
            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
          />
          <path
            fill="currentColor"
            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
          />
          <path
            fill="currentColor"
            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
          />
        </svg>
      )}
      {isLoading ? 'Signing in...' : 'Continue with Google'}
    </button>
  );
};