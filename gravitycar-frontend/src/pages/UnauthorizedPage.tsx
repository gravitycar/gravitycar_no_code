import React from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { getRedirectPath } from '../utils/redirectPath';

/**
 * UnauthorizedPage
 *
 * Rendered at /unauthorized when the axios 403 interceptor fires.
 * Accessible to both authenticated and unauthenticated users.
 * No ProtectedRoute wrapper — the route in App.tsx is public.
 */
const UnauthorizedPage: React.FC = () => {
  const { isAuthenticated } = useAuth();
  const [searchParams] = useSearchParams();
  const redirectPath = getRedirectPath(searchParams);
  const loginTo = redirectPath !== '/' ? `/login?redirect=${encodeURIComponent(redirectPath)}` : '/login';

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center py-12">
      <div className="bg-white rounded-lg shadow-md p-8 max-w-md w-full text-center">

        {/* Lock icon — inline SVG, no external library */}
        <div className="flex justify-center mb-4">
          <svg
            className="w-16 h-16 text-red-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
            />
          </svg>
        </div>

        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          Access Denied
        </h1>

        <p className="text-gray-600 mb-6">
          You don't have permission to view this page.
        </p>

        {isAuthenticated ? (
          <Link
            to="/"
            className="inline-block bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium"
          >
            Go to Home Page
          </Link>
        ) : (
          <Link
            to={loginTo}
            className="inline-block bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium"
          >
            Login
          </Link>
        )}

      </div>
    </div>
  );
};

export default UnauthorizedPage;
