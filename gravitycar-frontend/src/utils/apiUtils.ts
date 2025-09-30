/**
 * Utility functions for API requests that ensure consistent debugging support
 * and authentication handling across the entire frontend application.
 */

/**
 * Enhanced fetch wrapper that automatically adds XDEBUG_TRIGGER and authentication
 * for all API requests, ensuring consistent debugging and auth handling.
 */
export const fetchWithDebug = async (url: string, options: RequestInit = {}): Promise<Response> => {
  // Get the base API URL
  const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8081';
  
  // Create the full URL
  const fullUrl = url.startsWith('http') ? url : `${baseURL}${url}`;
  
  // Parse existing URL parameters
  const urlObj = new URL(fullUrl);
  
  // Add XDEBUG_TRIGGER parameter for debugging
  urlObj.searchParams.set('XDEBUG_TRIGGER', 'mike');
  
  // Set up headers with authentication
  const headers = new Headers(options.headers);
  
  // Add JWT token if available
  const token = localStorage.getItem('auth_token');
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }
  
  // Set default content type if not already set
  if (!headers.has('Content-Type') && (options.method === 'POST' || options.method === 'PUT' || options.method === 'PATCH')) {
    headers.set('Content-Type', 'application/json');
  }
  
  // Create the enhanced options
  const enhancedOptions: RequestInit = {
    ...options,
    headers: headers,
  };
  
  try {
    const response = await fetch(urlObj.toString(), enhancedOptions);
    
    // Handle authentication errors
    if (response.status === 401) {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      window.location.href = '/login';
      throw new Error('Authentication required. Please log in.');
    }
    
    return response;
  } catch (error) {
    console.error('API request failed:', {
      url: urlObj.toString(),
      method: options.method || 'GET',
      error: error instanceof Error ? error.message : 'Unknown error'
    });
    throw error;
  }
};

/**
 * Helper function to build API URLs with consistent base URL handling
 */
export const buildApiUrl = (endpoint: string): string => {
  const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8081';
  return endpoint.startsWith('http') ? endpoint : `${baseURL}${endpoint}`;
};

/**
 * Helper function to add XDEBUG_TRIGGER to existing URLs
 */
export const addDebugTrigger = (url: string): string => {
  const urlObj = new URL(url);
  urlObj.searchParams.set('XDEBUG_TRIGGER', 'mike');
  return urlObj.toString();
};