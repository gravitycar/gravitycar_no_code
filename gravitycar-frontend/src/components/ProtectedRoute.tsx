import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredRole?: string;
}

/**
 * ProtectedRoute
 *
 * Guards a route behind authentication and an optional role check.
 * Must be rendered inside AuthProvider.
 *
 * States (evaluated in order):
 *   1. isLoading      → render spinner (auth check in progress, avoids flash-redirect)
 *   2. !isAuthenticated → Navigate to /login?redirect=<current path>
 *   3. requiredRole set and user.user_type !== requiredRole → Navigate to /unauthorized?redirect=<current path>
 *   4. authenticated + role OK → render children
 *
 * All Navigate calls use replace=true to avoid polluting browser history with guard redirects.
 * The redirect param includes location.search so deep links (e.g. /admin?tab=cache) are
 * preserved across login and returned to exactly.
 *
 * Usage:
 *   <ProtectedRoute requiredRole="admin">
 *     <AdminPage />
 *   </ProtectedRoute>
 *
 *   <ProtectedRoute>
 *     <ProfilePage />
 *   </ProtectedRoute>
 */
const ProtectedRoute = ({ children, requiredRole }: ProtectedRouteProps): React.ReactElement => {
  const { user, isAuthenticated, isLoading } = useAuth();
  const location = useLocation();
  const redirectParam = encodeURIComponent(location.pathname + location.search);

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to={`/login?redirect=${redirectParam}`} replace />;
  }

  if (requiredRole !== undefined && user?.user_type !== requiredRole) {
    return <Navigate to={`/unauthorized?redirect=${redirectParam}`} replace />;
  }

  return <>{children}</>;
};

export default ProtectedRoute;
