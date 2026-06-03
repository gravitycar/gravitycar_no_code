import { imperativeNavigate } from './navigate';

/**
 * Handles 401 (unauthenticated) and 403 (forbidden) HTTP status codes by
 * clearing auth state and navigating to the appropriate error page.
 *
 * Used by:
 * - The axios interceptor in services/api.ts (replaces duplicated inline logic)
 * - ConfirmRebuildModal before reading the SSE stream body
 *
 * For status codes other than 401 and 403, this function is a no-op.
 *
 * 401 behaviour: Shows an alert, removes auth_token and user from localStorage,
 * then performs a hard redirect to /login with a redirect param. A hard reload
 * (window.location.href) is used rather than imperativeNavigate so that React's
 * in-memory auth state (useAuth hook) is fully cleared — soft navigation would
 * leave stale isAuthenticated=true state in memory.
 *
 * 403 behaviour: Uses imperativeNavigate (soft nav) because the user IS
 * authenticated — only their role is insufficient. The React session context
 * is preserved so the user does not need to log in again. Guarded to avoid
 * a redirect loop when already on /unauthorized.
 */
export function handleAuthError(status: number): void {
  const currentPath = window.location.pathname + window.location.search;
  const encodedRedirect = encodeURIComponent(currentPath);

  if (status === 401) {
    alert('Session expired. Please log in again.');
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    // Hard reload via window.location.href ensures React auth state is fully cleared.
    // imperativeNavigate (soft nav) would leave stale in-memory auth state in React context.
    window.location.href = `/login?redirect=${encodedRedirect}`;
    return;
  }

  if (status === 403) {
    if (window.location.pathname !== '/unauthorized') {
      // Soft nav for 403: user IS authenticated, only role is insufficient.
      imperativeNavigate(`/unauthorized?redirect=${encodedRedirect}`, { replace: true });
    }
    return;
  }
}
