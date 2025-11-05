/**
 * D&D RAG Chat API Service
 * 
 * This service handles communication with the Flask-based D&D RAG Chat server.
 * It does NOT use the existing apiService instance because:
 * 1. Different base URL (Flask server, not Gravitycar backend)
 * 2. Different response format (no Gravitycar wrapper)
 * 3. No need for XDEBUG_TRIGGER parameter
 * 4. Separate error handling logic
 */

import type {
  DnDQueryRequest,
  DnDQueryResponse,
  DnDErrorResponse,
  HealthCheckResponse
} from '../types/dndRag';

/**
 * Detects the current environment and returns the appropriate D&D RAG API URL
 * @returns The base URL for the D&D RAG Chat server
 */
function getDnDRagApiUrl(): string {
  const hostname = window.location.hostname;
  
  // Local development
  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return import.meta.env.VITE_DND_RAG_API_URL_LOCAL || 'http://localhost:5000';
  }
  
  // Production
  return import.meta.env.VITE_DND_RAG_API_URL_PRODUCTION || 'https://dndchat.gravitycar.com';
}

/**
 * Retrieves the JWT token from localStorage
 * @returns The JWT token or null if not found
 */
function getAuthToken(): string | null {
  return localStorage.getItem('auth_token');
}

/**
 * Formats an error response into a user-friendly message
 * @param error The error response from the API
 * @param statusCode The HTTP status code
 * @returns A user-friendly error message
 */
function formatErrorMessage(error: DnDErrorResponse, statusCode: number): string {
  switch (statusCode) {
    case 429:
      if (error.rate_info?.retry_after) {
        return `You've asked too many questions too quickly. Please wait ${error.rate_info.retry_after} seconds.`;
      } else {
        return `You've reached your daily limit of 30 questions. Try again tomorrow!`;
      }
    
    case 503:
      return `We've hit our daily budget limit. The service will be back at midnight UTC.`;
    
    case 401:
      return `Your session expired. Please log in again.`;
    
    case 400:
      return `Invalid request: ${error.message || error.error}`;
    
    default:
      return error.message || error.error || `Something went wrong (HTTP ${statusCode})`;
  }
}

/**
 * Performs a health check on the D&D RAG Chat server
 * @returns Promise resolving to health check response
 * @throws Error if health check fails
 */
export async function healthCheck(): Promise<HealthCheckResponse> {
  const baseUrl = getDnDRagApiUrl();
  
  try {
    const response = await fetch(`${baseUrl}/health`, {
      method: 'GET',
    });
    
    if (!response.ok) {
      throw new Error(`Health check failed: HTTP ${response.status}`);
    }
    
    return await response.json() as HealthCheckResponse;
  } catch (error) {
    console.error('Health check error:', error);
    throw new Error('Unable to connect to D&D RAG Chat server');
  }
}

/**
 * Queries the D&D RAG Chat server with a question
 * @param request The query request containing the question and optional parameters
 * @returns Promise resolving to the query response
 * @throws Error with user-friendly message if query fails
 */
export async function query(request: DnDQueryRequest): Promise<DnDQueryResponse> {
  const baseUrl = getDnDRagApiUrl();
  const token = getAuthToken();
  
  if (!token) {
    throw new Error('Authentication token not found. Please log in.');
  }
  
  // Always send debug: true to get diagnostics
  const requestBody: DnDQueryRequest = {
    question: request.question,
    debug: true, // Always true - UI controls visibility
    k: request.k || 15
  };
  
  try {
    const response = await fetch(`${baseUrl}/api/query`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify(requestBody),
      credentials: 'include',
    });
    
    const data = await response.json();
    
    if (!response.ok) {
      // Handle error responses
      const error = data as DnDErrorResponse;
      const errorMessage = formatErrorMessage(error, response.status);
      throw new Error(errorMessage);
    }
    
    return data as DnDQueryResponse;
  } catch (error) {
    // Handle network errors (server down, no connection, etc.)
    if (error instanceof TypeError && error.message.includes('fetch')) {
      throw new Error("We're sorry, the Dungeons and dRAGons chat server is down. Please try again later.");
    }
    
    // Re-throw with context if it's already an Error with a custom message
    if (error instanceof Error && error.message !== 'Failed to fetch') {
      throw error;
    }
    
    // Handle unexpected errors
    console.error('Query error:', error);
    throw new Error("We're sorry, the Dungeons and dRAGons chat server is down. Please try again later.");
  }
}

/**
 * D&D RAG Service
 * Exports the public API for the D&D RAG Chat integration
 */
export const dndRagService = {
  healthCheck,
  query,
  getDnDRagApiUrl, // Exported for debugging/testing
};

export default dndRagService;
