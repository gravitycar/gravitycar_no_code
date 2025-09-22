/* eslint-disable @typescript-eslint/no-explicit-any, react-hooks/exhaustive-deps */
import { useEffect, useCallback, useRef, useState } from 'react';
import type { GoogleIdConfiguration, CredentialResponse } from '../types/google';

// Google Client ID from our environment configuration
const GOOGLE_CLIENT_ID = '358948680948-deirlfsmqhbnl27f9qcpjmehtl3golen.apps.googleusercontent.com';

interface UseGoogleOAuthProps {
  onSuccess: (credentialResponse: CredentialResponse) => void;
  onError?: (error: any) => void;
}

export const useGoogleOAuth = ({ onSuccess, onError }: UseGoogleOAuthProps) => {
  const isInitializedRef = useRef(false);
  const onSuccessRef = useRef(onSuccess);
  const onErrorRef = useRef(onError);
  const [isGoogleLoaded, setIsGoogleLoaded] = useState(false);
  const [isGoogleInitialized, setIsGoogleInitialized] = useState(false);
  
  // Update refs when props change
  useEffect(() => {
    onSuccessRef.current = onSuccess;
    onErrorRef.current = onError;
  }, [onSuccess, onError]);
  
  const initializeGoogle = useCallback(() => {
    console.log('🔄 Attempting to initialize Google OAuth...');
    console.log('🔑 Client ID:', GOOGLE_CLIENT_ID);

    if (!window.google || !window.google.accounts || !window.google.accounts.id) {
      console.warn('❌ Google Identity Services not fully loaded');
      setIsGoogleLoaded(false);
      setIsGoogleInitialized(false);
      
      // Retry after a short delay
      setTimeout(() => {
        if (window.google && window.google.accounts && window.google.accounts.id && !isInitializedRef.current) {
          console.log('🔄 Retrying Google OAuth initialization...');
          initializeGoogle();
        }
      }, 500);
      
      return;
    }

    // Set loaded state
    setIsGoogleLoaded(true);

    // Prevent multiple initializations
    if (isInitializedRef.current) {
      console.log('✅ Google OAuth already initialized, skipping...');
      setIsGoogleInitialized(true);
      return;
    }

    console.log('✅ Google Identity Services loaded successfully');
    console.log('🔍 Available Google APIs:', Object.keys(window.google));

    const config: GoogleIdConfiguration = {
      client_id: GOOGLE_CLIENT_ID,
      callback: (response: CredentialResponse) => {
        try {
          console.log('✅ Google OAuth callback received:', response);
          onSuccessRef.current(response);
        } catch (error) {
          console.error('❌ Google OAuth callback error:', error);
          onErrorRef.current?.(error);
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
      isInitializedRef.current = true;
      setIsGoogleInitialized(true);
      console.log('✅ Google OAuth initialized successfully');
    } catch (error) {
      console.error('❌ Failed to initialize Google OAuth:', error);
      setIsGoogleInitialized(false);
      onErrorRef.current?.(error);
    }
  }, []); // No dependencies since we use refs

  const renderButton = useCallback((elementId: string) => {
    console.log('🔄 Attempting to render Google button for element:', elementId);
    
    if (!window.google || !isGoogleInitialized) {
      console.warn('❌ Google Identity Services not loaded or not initialized for button render');
      return;
    }

    const buttonElement = document.getElementById(elementId);
    if (!buttonElement) {
      console.warn(`❌ Element with id "${elementId}" not found`);
      return;
    }

    // Check if button is already rendered
    if (buttonElement.hasChildNodes()) {
      console.log('✅ Google button already rendered, skipping...');
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
      onErrorRef.current?.(error);
    }
  }, [isGoogleInitialized]); // Include isGoogleInitialized as dependency

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
      onErrorRef.current?.(error);
    }
  }, []); // No dependencies since we use refs

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
          // Add a small delay to ensure Google is fully initialized
          setTimeout(() => {
            initializeGoogle();
          }, 100);
        };
        
        script.onerror = (error) => {
          console.error('❌ Failed to load Google Identity Services script:', error);
          setIsGoogleLoaded(false);
          setIsGoogleInitialized(false);
          onErrorRef.current?.(error);
        };
        
        console.log('🔄 Adding script to document head...');
        document.head.appendChild(script);
        
        // Set a timeout for loading
        setTimeout(() => {
          if (!isInitializedRef.current) {
            console.warn('⚠️ Google Identity Services taking longer than expected to load');
            if (!window.google) {
              console.error('❌ Google Identity Services failed to load within timeout');
              setIsGoogleLoaded(false);
              setIsGoogleInitialized(false);
            }
          }
        }, 10000);
        
      } else {
        console.log('✅ Google Identity Services already loaded');
        // Add a small delay to ensure Google is fully ready
        setTimeout(() => {
          initializeGoogle();
        }, 50);
      }
    };

    loadGoogleScript();
    
    // Cleanup function to prevent memory leaks
    return () => {
      // Reset initialization flag when component unmounts
      isInitializedRef.current = false;
      setIsGoogleLoaded(false);
      setIsGoogleInitialized(false);
    };
  }, []); // Empty dependency array - only run once

  return {
    renderButton,
    promptOneTap,
    isGoogleLoaded,
    isGoogleInitialized
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
