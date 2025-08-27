import { useEffect, useCallback } from 'react';
import type { GoogleIdConfiguration, CredentialResponse } from '../types/google';

// Google Client ID from our environment configuration
const GOOGLE_CLIENT_ID = '358948680948-deirlfsmqhbnl27f9qcpjmehtl3golen.apps.googleusercontent.com';

interface UseGoogleOAuthProps {
  onSuccess: (credentialResponse: CredentialResponse) => void;
  onError?: (error: any) => void;
}

export const useGoogleOAuth = ({ onSuccess, onError }: UseGoogleOAuthProps) => {
  const initializeGoogle = useCallback(() => {
    console.log('🔄 Attempting to initialize Google OAuth...');
    console.log('🔑 Client ID:', GOOGLE_CLIENT_ID);

    if (!window.google) {
      console.warn('❌ Google Identity Services not loaded');
      return;
    }

    console.log('✅ Google Identity Services loaded successfully');
    console.log('🔍 Available Google APIs:', Object.keys(window.google));

    const config: GoogleIdConfiguration = {
      client_id: GOOGLE_CLIENT_ID,
      callback: (response: CredentialResponse) => {
        try {
          console.log('✅ Google OAuth callback received:', response);
          onSuccess(response);
        } catch (error) {
          console.error('❌ Google OAuth callback error:', error);
          onError?.(error);
        }
      },
      auto_select: false,
      cancel_on_tap_outside: true,
      context: 'signin',
      ux_mode: 'popup'
    };

    try {
      console.log('🔄 Initializing Google OAuth with config:', config);
      window.google.accounts.id.initialize(config);
      console.log('✅ Google OAuth initialized successfully');
    } catch (error) {
      console.error('❌ Failed to initialize Google OAuth:', error);
      onError?.(error);
    }
  }, [onSuccess, onError]);

  const renderButton = useCallback((elementId: string) => {
    console.log('🔄 Attempting to render Google button for element:', elementId);
    
    if (!window.google) {
      console.warn('❌ Google Identity Services not loaded for button render');
      return;
    }

    const buttonElement = document.getElementById(elementId);
    if (!buttonElement) {
      console.warn(`❌ Element with id "${elementId}" not found`);
      return;
    }

    console.log('✅ Button element found, rendering Google button...');

    try {
      window.google.accounts.id.renderButton(buttonElement, {
        type: 'standard',
        theme: 'outline',
        size: 'large',
        text: 'signin_with',
        shape: 'rectangular',
        width: '300'
      });
      console.log('✅ Google button rendered successfully');
    } catch (error) {
      console.error('❌ Failed to render Google sign-in button:', error);
      onError?.(error);
    }
  }, [onError]);

  const promptOneTap = useCallback(() => {
    if (!window.google) {
      console.warn('Google Identity Services not loaded');
      return;
    }

    try {
      window.google.accounts.id.prompt((notification) => {
        if (notification.isNotDisplayed()) {
          console.log('One Tap not displayed:', notification.getNotDisplayedReason());
        }
      });
    } catch (error) {
      console.error('Failed to prompt One Tap:', error);
      onError?.(error);
    }
  }, [onError]);

  useEffect(() => {
    // Load Google Identity Services script dynamically
    const loadGoogleScript = () => {
      console.log('🔄 Loading Google Identity Services script...');
      
      if (!window.google) {
        const script = document.createElement('script');
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
        
        script.onload = () => {
          console.log('✅ Google Identity Services script loaded successfully');
          // Initialize after script loads
          initializeGoogle();
        };
        
        script.onerror = (error) => {
          console.error('❌ Failed to load Google Identity Services script:', error);
        };
        
        console.log('🔄 Adding script to document head...');
        document.head.appendChild(script);
      } else {
        console.log('✅ Google Identity Services already loaded');
        initializeGoogle();
      }
    };

    loadGoogleScript();
  }, [initializeGoogle]);

  return {
    renderButton,
    promptOneTap,
    isGoogleLoaded: !!window.google
  };
};

// Utility function to decode JWT token (for display purposes only)
export const decodeGoogleJWT = (token: string) => {
  try {
    const base64Url = token.split('.')[1];
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const jsonPayload = decodeURIComponent(
      atob(base64)
        .split('')
        .map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
        .join('')
    );
    return JSON.parse(jsonPayload);
  } catch (error) {
    console.error('Failed to decode Google JWT:', error);
    return null;
  }
};
