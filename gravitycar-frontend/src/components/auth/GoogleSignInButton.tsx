import { useEffect, useRef, useState } from 'react';
import { useAuth } from '../../hooks/useAuth';
import { useGoogleOAuth, decodeGoogleJWT } from '../../hooks/useGoogleOAuth';
import type { CredentialResponse } from '../../types/google';

const GoogleSignInButton = () => {
  const { loginWithGoogle } = useAuth();
  const buttonRef = useRef<HTMLDivElement>(null);
  const [error, setError] = useState<string>('');
  const [isLoading, setIsLoading] = useState(false);
  const [debugInfo, setDebugInfo] = useState<string>('Initializing...');

  const handleGoogleSuccess = async (credentialResponse: CredentialResponse) => {
    console.log('✅ Google sign-in successful:', credentialResponse);
    setIsLoading(true);
    setError('');

    try {
      if (!credentialResponse.credential) {
        throw new Error('No credential received from Google');
      }

      // Decode the JWT to see user info (for debugging)
      const userInfo = decodeGoogleJWT(credentialResponse.credential);
      console.log('👤 User info from Google:', userInfo);

      // Call your backend Google OAuth endpoint
      const result = await loginWithGoogle(credentialResponse.credential);
      
      if (!result.success) {
        setError(result.message || 'Google login failed');
      }
    } catch (error: any) {
      console.error('❌ Google login error:', error);
      setError(error.message || 'An error occurred during Google login');
    } finally {
      setIsLoading(false);
    }
  };

  const handleGoogleError = (error: any) => {
    console.error('❌ Google OAuth error:', error);
    setError('Google OAuth configuration error. Please try again.');
  };

  const { renderButton, isGoogleLoaded } = useGoogleOAuth({
    onSuccess: handleGoogleSuccess,
    onError: handleGoogleError
  });

  useEffect(() => {
    // Update debug info based on Google loading state
    setDebugInfo(isGoogleLoaded ? 'Google loaded, rendering button...' : 'Loading Google services...');
    
    if (isGoogleLoaded && buttonRef.current) {
      console.log('🔄 Google loaded, attempting to render button...');
      // Clear any existing content
      buttonRef.current.innerHTML = '';
      // Render the Google sign-in button
      renderButton('google-signin-button');
    }
  }, [isGoogleLoaded, renderButton]);

  return (
    <div className="google-signin-container">
      {error && (
        <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
          {error}
        </div>
      )}
      
      <div className="flex flex-col items-center space-y-3">
        <div className="text-sm text-gray-600 text-center">
          Or continue with Google
        </div>
        
        <div 
          id="google-signin-button" 
          ref={buttonRef}
          className={`transition-opacity ${isLoading ? 'opacity-50 pointer-events-none' : ''}`}
        >
          {!isGoogleLoaded && (
            <div className="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700">
              <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-900 mr-2"></div>
              {debugInfo}
            </div>
          )}
        </div>
        
        {isLoading && (
          <div className="text-sm text-gray-600">
            Signing you in...
          </div>
        )}
      </div>
      
      <div className="mt-4 text-xs text-gray-500 text-center">
        {isGoogleLoaded ? (
          <>Google OAuth is enabled and ready.</>
        ) : (
          <>
            Setting up Google authentication...
            <br />
            If this takes too long, check the browser console for errors.
          </>
        )}
      </div>
    </div>
  );
};

export default GoogleSignInButton;
