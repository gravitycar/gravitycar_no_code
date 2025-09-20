/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactNode } from 'react';
import { ApiError, getErrorMessage } from '../../utils/errors';

interface DataWrapperProps {
  loading: boolean;
  error: unknown;
  data: any;
  children: (data: any) => ReactNode;
  fallback?: ReactNode;
  retry?: () => void;
  emptyMessage?: string;
}

/**
 * Wrapper component for handling data loading states, errors, and empty states
 * Provides consistent UI patterns across the application
 */
export function DataWrapper({
  loading,
  error,
  data,
  children,
  fallback,
  retry,
  emptyMessage = 'No data available',
}: DataWrapperProps) {
  if (loading) {
    return <LoadingState />;
  }

  if (error) {
    return <ErrorState error={error} retry={retry} />;
  }

  if (!data || (Array.isArray(data) && data.length === 0)) {
    return fallback || <EmptyState message={emptyMessage} />;
  }

  return <>{children(data)}</>;
}

function LoadingState() {
  return (
    <div className="flex items-center justify-center p-8">
      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <span className="ml-3 text-gray-600">Loading...</span>
    </div>
  );
}

interface ErrorStateProps {
  error: unknown;
  retry?: () => void;
}

function ErrorState({ error, retry }: ErrorStateProps) {
  const apiError = error instanceof ApiError ? error : null;
  const message = getErrorMessage(error);
  const isRetryable = apiError?.isRetryable ?? true;

  // Show validation errors differently
  if (apiError?.status === 422) {
    const validationErrors = apiError.getValidationErrors();
    if (validationErrors) {
      return <ValidationErrorState errors={validationErrors} />;
    }
  }

  return (
    <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
      <div className="text-red-600 text-4xl mb-3">❌</div>
      <h3 className="text-red-800 font-medium mb-2">Failed to Load Data</h3>
      <p className="text-red-600 mb-4 text-sm">{message}</p>
      
      {apiError && (
        <div className="text-xs text-red-500 mb-4">
          Error {apiError.status}: {apiError.type}
        </div>
      )}
      
      <div className="space-x-2">
        {retry && isRetryable && (
          <button 
            onClick={retry}
            className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm"
          >
            Try Again
          </button>
        )}
        <button 
          onClick={() => window.location.reload()}
          className="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm"
        >
          Refresh Page
        </button>
      </div>
    </div>
  );
}

interface ValidationErrorStateProps {
  errors: Record<string, string[]>;
}

function ValidationErrorState({ errors }: ValidationErrorStateProps) {
  return (
    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
      <div className="text-yellow-600 text-2xl mb-2">⚠️</div>
      <h3 className="text-yellow-800 font-medium mb-2">Validation Errors</h3>
      <div className="text-sm text-yellow-700">
        <p className="mb-2">Please correct the following errors:</p>
        <ul className="list-disc list-inside space-y-1">
          {Object.entries(errors).map(([field, fieldErrors]) =>
            fieldErrors.map((error, index) => (
              <li key={`${field}-${index}`}>
                <strong>{field}:</strong> {error}
              </li>
            ))
          )}
        </ul>
      </div>
    </div>
  );
}

interface EmptyStateProps {
  message: string;
  icon?: string;
  action?: {
    label: string;
    onClick: () => void;
  };
}

function EmptyState({ message, icon = '📂', action }: EmptyStateProps) {
  return (
    <div className="text-center text-gray-500 p-8">
      <div className="text-4xl mb-3">{icon}</div>
      <p className="text-sm mb-4">{message}</p>
      {action && (
        <button
          onClick={action.onClick}
          className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"
        >
          {action.label}
        </button>
      )}
    </div>
  );
}

// Loading skeleton components for better perceived performance
export function TableSkeleton({ rows = 5, columns = 4 }: { rows?: number; columns?: number }) {
  return (
    <div className="space-y-2">
      {Array.from({ length: rows }, (_, i) => (
        <div key={i} className="flex space-x-4">
          {Array.from({ length: columns }, (_, j) => (
            <div
              key={j}
              className="h-4 bg-gray-200 rounded animate-pulse flex-1"
            />
          ))}
        </div>
      ))}
    </div>
  );
}

export function FormSkeleton() {
  return (
    <div className="space-y-4">
      {Array.from({ length: 6 }, (_, i) => (
        <div key={i} className="space-y-2">
          <div className="h-4 bg-gray-200 rounded animate-pulse w-1/4" />
          <div className="h-10 bg-gray-200 rounded animate-pulse" />
        </div>
      ))}
    </div>
  );
}
