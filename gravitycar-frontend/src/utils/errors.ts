/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * Backend API Error Response Interface
 * Based on Gravitycar RestApiHandler error response format
 */
export interface BackendErrorResponse {
  success: false;
  status: number;
  error: {
    message: string;
    type: string;
    code: number;
    context?: {
      model?: string;
      id?: string;
      validation_errors?: Record<string, string[]>;
      original_error?: string;
      [key: string]: any;
    };
  };
  timestamp: string;
}

/**
 * Enhanced error class that preserves backend error information
 */
export class ApiError extends Error {
  public readonly status: number;
  public readonly type: string;
  public readonly context?: BackendErrorResponse['error']['context'];
  public readonly timestamp: string;
  public readonly isRetryable: boolean;

  constructor(errorResponse: BackendErrorResponse) {
    super(errorResponse.error.message);
    this.name = 'ApiError';
    this.status = errorResponse.status;
    this.type = errorResponse.error.type.trim(); // Backend adds leading space
    this.context = errorResponse.error.context;
    this.timestamp = errorResponse.timestamp;
    this.isRetryable = this.determineRetryability();
  }

  private determineRetryability(): boolean {
    // 5xx errors are generally retryable (server issues)
    if (this.status >= 500) return true;
    
    // Some 4xx errors might be retryable after user action
    if (this.status === 408 || this.status === 429) return true;
    
    // Client errors (400, 401, 403, 404, 422) are generally not retryable
    return false;
  }

  /**
   * Get a user-friendly error message based on status code and context
   */
  getUserFriendlyMessage(): string {
    // Use validation errors for 422 responses
    if (this.status === 422 && this.context?.validation_errors) {
      const errors = Object.values(this.context.validation_errors).flat();
      if (errors.length === 1) {
        return errors[0];
      } else if (errors.length > 1) {
        return `Please correct the following: ${errors.join(', ')}`;
      }
    }

    // Use context-aware messages when available
    switch (this.status) {
      case 400:
        return this.context?.model 
          ? `Invalid ${this.context.model.toLowerCase()} data provided`
          : 'Invalid request. Please check your input and try again.';
      
      case 401:
        return 'Your session has expired. Please log in again.';
      
      case 403:
        return this.context?.model
          ? `You don't have permission to access ${this.context.model.toLowerCase()} records`
          : 'You don\'t have permission to perform this action.';
      
      case 404:
        if (this.context?.model && this.context?.id) {
          return `${this.context.model.slice(0, -1)} with ID "${this.context.id}" was not found`;
        }
        return 'The requested resource was not found.';
      
      case 409:
        return this.context?.model
          ? `This ${this.context.model.toLowerCase().slice(0, -1)} conflicts with existing data`
          : 'This request conflicts with existing data.';
      
      case 429:
        return 'Too many requests. Please wait a moment and try again.';
      
      case 500:
        return 'A server error occurred. Our team has been notified.';
      
      case 502:
        return 'Service temporarily unavailable. Please try again in a moment.';
      
      case 503:
        return 'Service is temporarily down for maintenance.';
      
      default:
        // Fall back to the original backend message
        return this.message || 'An unexpected error occurred.';
    }
  }

  /**
   * Get detailed error information for debugging
   */
  getDebugInfo(): Record<string, any> {
    return {
      message: this.message,
      status: this.status,
      type: this.type,
      context: this.context,
      timestamp: this.timestamp,
      isRetryable: this.isRetryable,
    };
  }

  /**
   * Get validation errors if this is a validation error (422)
   */
  getValidationErrors(): Record<string, string[]> | null {
    if (this.status === 422 && this.context?.validation_errors) {
      return this.context.validation_errors;
    }
    return null;
  }
}

/**
 * Helper function to determine if an error response is from our backend
 */
export function isBackendErrorResponse(error: any): error is BackendErrorResponse {
  return (
    error &&
    typeof error === 'object' &&
    error.success === false &&
    typeof error.status === 'number' &&
    error.error &&
    typeof error.error.message === 'string' &&
    typeof error.error.type === 'string' &&
    typeof error.error.code === 'number'
  );
}

/**
 * Extract user-friendly error message from various error types
 */
export function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.getUserFriendlyMessage();
  }
  
  if (error && typeof error === 'object' && 'message' in error) {
    return String(error.message);
  }
  
  if (typeof error === 'string') {
    return error;
  }
  
  return 'An unexpected error occurred';
}

/**
 * Extract error details for debugging
 */
export function getErrorDetails(error: unknown): Record<string, any> {
  if (error instanceof ApiError) {
    return error.getDebugInfo();
  }
  
  if (error instanceof Error) {
    return {
      name: error.name,
      message: error.message,
      stack: error.stack,
    };
  }
  
  return { error: String(error) };
}
