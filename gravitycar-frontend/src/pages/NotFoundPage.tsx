import React from 'react';
import { Link } from 'react-router-dom';

/**
 * NotFoundPage
 *
 * Rendered at /not-found when the axios 404 interceptor fires.
 * Also used by the * catch-all route for unknown frontend paths.
 * Accessible to both authenticated and unauthenticated users.
 */
const NotFoundPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center py-12">
      <div className="bg-white rounded-lg shadow-md p-8 max-w-md w-full text-center">

        {/* Search/question icon — inline SVG, no external library */}
        <div className="flex justify-center mb-4">
          <svg
            className="w-16 h-16 text-gray-400"
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
              d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>

        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          Page Not Found
        </h1>

        <p className="text-gray-600 mb-6">
          The page or resource you're looking for doesn't exist.
        </p>

        <Link
          to="/"
          className="inline-block bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium"
        >
          Go to Home Page
        </Link>

      </div>
    </div>
  );
};

export default NotFoundPage;
