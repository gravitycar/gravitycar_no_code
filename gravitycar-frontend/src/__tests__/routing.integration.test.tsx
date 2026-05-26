/**
 * Integration tests for routing behavior — verifies full routing flows per the
 * "Public Home Page / Projects List View" specification (Epic 48).
 *
 * Strategy:
 * - Render a TestRoutes component inside a MemoryRouter with controlled initialEntries
 * - Mock useAuth to control authentication state
 * - Mock NavigationSidebar and ProjectsListView to avoid internal API calls
 * - Mock apiService to prevent real HTTP calls
 * - Focus on routing outcomes, not component rendering details
 *
 * Note: We mirror the App.tsx route table in TestRoutes rather than importing
 * AppRoutes because App.tsx does not export AppRoutes. The mirror uses only
 * the routes needed for the ACs under test.
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route, Navigate, useSearchParams } from 'react-router-dom';
import { getRedirectPath } from '../utils/redirectPath';

// ---------------------------------------------------------------------------
// Mocks — all vi.mock calls must be at module level (before any imports)
// ---------------------------------------------------------------------------

// Auth state controlled per test via authState variable
const authState = {
  isAuthenticated: false,
  isLoading: false,
  user: null as null | { id: string; username: string; email: string },
  login: vi.fn(),
  loginWithGoogle: vi.fn(),
  logout: vi.fn(),
  checkAuth: vi.fn(),
};

vi.mock('../hooks/useAuth', () => ({
  useAuth: () => authState,
  AuthProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Stub NavigationSidebar — avoids its internal API calls
vi.mock('../components/navigation/NavigationSidebar', () => ({
  default: () => <nav data-testid="navigation-sidebar">Navigation Sidebar</nav>,
}));

// Stub ProjectsListView — avoids its API calls
vi.mock('../components/projects/ProjectsListView', () => ({
  ProjectsListView: () => <div data-testid="projects-list-view">Projects Grid</div>,
}));

// Stub DynamicModelRoute — avoids its API calls
vi.mock('../components/routing/DynamicModelRoute', () => ({
  default: () => <div data-testid="dynamic-model-route">Dynamic Model Route</div>,
}));

// Stub GenericCrudPage
vi.mock('../components/crud/GenericCrudPage', () => ({
  default: ({ title }: { title: string }) => (
    <div data-testid="generic-crud-page">{title}</div>
  ),
}));

// Stub page components
vi.mock('../pages/TriviaPage', () => ({
  default: () => <div data-testid="trivia-page">Trivia Page</div>,
}));
vi.mock('../pages/DnDChatPage', () => ({
  default: () => <div data-testid="dnd-chat-page">DnD Chat Page</div>,
}));
vi.mock('../pages/ChartOfGoodness', () => ({
  default: () => <div data-testid="chart-of-goodness">Chart of Goodness</div>,
}));
vi.mock('../pages/BatchProposeDates', () => ({
  default: () => <div data-testid="batch-propose-dates">Batch Propose Dates</div>,
}));

// Stub apiService to prevent real HTTP calls
vi.mock('../services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    getCurrentUser: vi.fn(),
    login: vi.fn(),
    logout: vi.fn(),
  },
  apiService: {
    get: vi.fn(),
    post: vi.fn(),
    getCurrentUser: vi.fn(),
    login: vi.fn(),
    logout: vi.fn(),
  },
}));

// Stub ErrorBoundary (transparent wrapper)
vi.mock('../components/error/ErrorBoundary', () => ({
  ErrorBoundary: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Stub NotificationContext
vi.mock('../contexts/NotificationContext', () => ({
  NotificationProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Stub NavigatorSetter (calls useNavigate internally — not needed for routing tests)
vi.mock('../utils/navigate', () => ({
  NavigatorSetter: () => null,
  setNavigator: vi.fn(),
  imperativeNavigate: vi.fn(),
}));

// Stub Login component (avoids rendering the real login form)
vi.mock('../components/auth/Login', () => ({
  default: () => <div data-testid="login-page">Login Page</div>,
}));

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function setUnauthenticated() {
  authState.isAuthenticated = false;
  authState.isLoading = false;
  authState.user = null;
}

function setAuthenticated() {
  authState.isAuthenticated = true;
  authState.isLoading = false;
  authState.user = { id: '1', username: 'testuser', email: 'test@example.com' };
}

// ---------------------------------------------------------------------------
// Components used by TestRoutes
// ---------------------------------------------------------------------------

// Lazy-imported page components (assigned in beforeEach after mocks are in place)
let ProjectsPage: React.ComponentType;
let UnauthorizedPage: React.ComponentType;
let Login: React.ComponentType;
let DynamicModelRoute: React.ComponentType;
let Layout: React.ComponentType<{ children: React.ReactNode }>;

// PublicRoute mirrors the one in App.tsx
// Redirects authenticated users away from /login; lets guests through.
const PublicRoute = ({ children }: { children: React.ReactNode }) => {
  const [searchParams] = useSearchParams();

  if (authState.isLoading) {
    return <div data-testid="loading">Loading...</div>;
  }

  return !authState.isAuthenticated ? (
    <>{children}</>
  ) : (
    <Navigate to={getRedirectPath(searchParams)} replace />
  );
};

// TestRoutes mirrors the route table from App.tsx
// Uses lazy-imported (mocked) page components assigned in beforeEach.
const TestRoutes = () => (
  <Routes>
    {/* Login — public only (redirect authenticated users away) */}
    <Route
      path="/login"
      element={
        <PublicRoute>
          <_Login />
        </PublicRoute>
      }
    />

    {/* Root — public home page (no auth requirement) */}
    <Route
      path="/"
      element={
        <_Layout>
          <_ProjectsPage />
        </_Layout>
      }
    />

    {/* Projects Showcase alias — kept for backwards compatibility */}
    <Route
      path="/projects_showcase"
      element={
        <_Layout>
          <_ProjectsPage />
        </_Layout>
      }
    />

    {/* Unauthorized — public (403 interceptor navigates here) */}
    <Route
      path="/unauthorized"
      element={
        <_Layout>
          <_UnauthorizedPage />
        </_Layout>
      }
    />

    {/* Dynamic Model Routes — catch-all for any model */}
    <Route
      path="/:modelName"
      element={
        <_Layout>
          <_DynamicModelRoute />
        </_Layout>
      }
    />

    {/* 404 — catch-all */}
    <Route
      path="*"
      element={
        <div data-testid="not-found">
          <h1>404</h1>
          <p>Page not found</p>
          <a href="/">Go to Home Page</a>
        </div>
      }
    />
  </Routes>
);

// Thin wrapper components that delegate to lazy-imported mocked components.
// These are defined after vi.mock calls so the mocks are in place; the actual
// component references are set in beforeEach via dynamic imports.
const _ProjectsPage = () => <>{ProjectsPage && <ProjectsPage />}</>;
const _UnauthorizedPage = () => <>{UnauthorizedPage && <UnauthorizedPage />}</>;
const _Login = () => <>{Login && <Login />}</>;
const _DynamicModelRoute = () => <>{DynamicModelRoute && <DynamicModelRoute />}</>;
const _Layout = ({ children }: { children: React.ReactNode }) =>
  Layout ? <Layout>{children}</Layout> : <>{children}</>;

/** Render TestRoutes at the given initial path */
function renderRoutes(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <TestRoutes />
    </MemoryRouter>
  );
}

// ---------------------------------------------------------------------------
// Test setup
// ---------------------------------------------------------------------------

beforeEach(async () => {
  vi.clearAllMocks();
  setUnauthenticated();

  // Lazy-import after mocks are registered so we get mocked versions
  const projectsMod = await import('../pages/ProjectsPage');
  ProjectsPage = projectsMod.default;

  const unauthorizedMod = await import('../pages/UnauthorizedPage');
  UnauthorizedPage = unauthorizedMod.default;

  const loginMod = await import('../components/auth/Login');
  Login = loginMod.default;

  const dynamicMod = await import('../components/routing/DynamicModelRoute');
  DynamicModelRoute = dynamicMod.default;

  const layoutMod = await import('../components/layout/Layout');
  Layout = layoutMod.default;
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Routing integration tests', () => {
  // -------------------------------------------------------------------------
  // AC-1: Public Home Page — unauthenticated visitors see ProjectsPage at /
  // -------------------------------------------------------------------------

  describe('AC-1: Public home page at /', () => {
    it('renders ProjectsPage for an unauthenticated user — no redirect to /login', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/');

      // Assert — ProjectsListView (inside ProjectsPage) is rendered
      expect(screen.getByTestId('projects-list-view')).toBeInTheDocument();
      // Assert — no login page redirect
      expect(screen.queryByTestId('login-page')).not.toBeInTheDocument();
    });

    it('renders ProjectsPage for an authenticated user at /', () => {
      // Arrange
      setAuthenticated();

      // Act
      renderRoutes('/');

      // Assert
      expect(screen.getByTestId('projects-list-view')).toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // AC-2: No /dashboard route — navigating there hits DynamicModelRoute
  // -------------------------------------------------------------------------

  describe('AC-2: No /dashboard route — falls through to DynamicModelRoute', () => {
    it('navigating to /dashboard renders DynamicModelRoute (catch-all), not a dedicated dashboard', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/dashboard');

      // Assert — DynamicModelRoute handles /:modelName (dashboard is not a dedicated route)
      expect(screen.getByTestId('dynamic-model-route')).toBeInTheDocument();
    });

    it('navigating to /dashboard does NOT render a "Dashboard" component', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/dashboard');

      // Assert — no dedicated dashboard page component rendered
      expect(screen.queryByTestId('dashboard-page')).not.toBeInTheDocument();
      // The catch-all DynamicModelRoute is rendered instead
      expect(screen.getByTestId('dynamic-model-route')).toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // AC-3: No ProtectedRoute — formerly-protected routes render for unauth users
  // -------------------------------------------------------------------------

  describe('AC-3: No ProtectedRoute — routes render for unauthenticated users', () => {
    it('/unauthorized is accessible to unauthenticated users', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/unauthorized');

      // Assert — UnauthorizedPage renders without redirect to login
      expect(screen.getByText(/Access Denied/i)).toBeInTheDocument();
      expect(screen.queryByTestId('login-page')).not.toBeInTheDocument();
    });

    it('/projects_showcase renders for unauthenticated users (no ProtectedRoute)', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/projects_showcase');

      // Assert — ProjectsPage renders, no redirect
      expect(screen.getByTestId('projects-list-view')).toBeInTheDocument();
      expect(screen.queryByTestId('login-page')).not.toBeInTheDocument();
    });

    it('/:modelName catch-all renders for unauthenticated users (no ProtectedRoute)', () => {
      // Arrange
      setUnauthenticated();

      // Act — /some-model uses the /:modelName catch-all route
      renderRoutes('/some-model');

      // Assert — DynamicModelRoute renders, no redirect to login
      expect(screen.getByTestId('dynamic-model-route')).toBeInTheDocument();
      expect(screen.queryByTestId('login-page')).not.toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // AC-6: /unauthorized page renders with a link back to /
  // -------------------------------------------------------------------------

  describe('AC-6: /unauthorized page', () => {
    it('renders for an unauthenticated user with a permission-denied message', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/unauthorized');

      // Assert — permission-denied message
      expect(screen.getByText(/Access Denied/i)).toBeInTheDocument();
      expect(screen.getByText(/permission/i)).toBeInTheDocument();
    });

    it('renders a Login link for unauthenticated users', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/unauthorized');

      // Assert — unauthenticated users see Login, not Go to Home Page
      const links = screen.getAllByRole('link', { name: /login/i });
      expect(links.length).toBeGreaterThan(0);
      const bodyLoginLink = links.find(l => l.getAttribute('href')?.startsWith('/login'));
      expect(bodyLoginLink).toBeInTheDocument();
    });

    it('renders for an authenticated user with a link to /', () => {
      // Arrange
      setAuthenticated();

      // Act
      renderRoutes('/unauthorized');

      // Assert
      expect(screen.getByText(/Access Denied/i)).toBeInTheDocument();
      const homeLink = screen.getByRole('link', { name: /go to home/i });
      expect(homeLink).toHaveAttribute('href', '/');
    });
  });

  // -------------------------------------------------------------------------
  // AC-7: Authenticated user visiting /login is redirected to /
  // -------------------------------------------------------------------------

  describe('AC-7: PublicRoute redirect for authenticated users', () => {
    it('authenticated user at /login is redirected to / (not /dashboard)', () => {
      // Arrange
      setAuthenticated();

      // Act
      renderRoutes('/login');

      // Assert — login page NOT rendered; ProjectsPage (at /) is shown instead
      expect(screen.queryByTestId('login-page')).not.toBeInTheDocument();
      expect(screen.getByTestId('projects-list-view')).toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // AC-7 + AC-14: Authenticated user at /login?redirect=/events redirected to /events
  // -------------------------------------------------------------------------

  describe('AC-7 + AC-14: PublicRoute preserves ?redirect= for authenticated users', () => {
    it('authenticated user at /login?redirect=%2Fevents is redirected to /events', () => {
      // Arrange
      setAuthenticated();

      // Act
      renderRoutes('/login?redirect=%2Fevents');

      // Assert — should be redirected to /events
      // /events hits /:modelName -> DynamicModelRoute in our test table
      expect(screen.queryByTestId('login-page')).not.toBeInTheDocument();
      expect(screen.getByTestId('dynamic-model-route')).toBeInTheDocument();
    });

    it('authenticated user at /login?redirect=https://evil.com is redirected to / (open redirect prevention)', () => {
      // Arrange
      setAuthenticated();

      // Act — absolute URL in redirect param should be rejected
      renderRoutes('/login?redirect=https%3A%2F%2Fevil.com');

      // Assert — invalid redirect falls back to /, so ProjectsPage is shown
      expect(screen.queryByTestId('login-page')).not.toBeInTheDocument();
      expect(screen.getByTestId('projects-list-view')).toBeInTheDocument();
    });

    it('authenticated user at /login?redirect=//evil.com is redirected to / (protocol-relative rejection)', () => {
      // Arrange
      setAuthenticated();

      // Act — protocol-relative URL should also be rejected
      renderRoutes('/login?redirect=%2F%2Fevil.com');

      // Assert — falls back to /
      expect(screen.queryByTestId('login-page')).not.toBeInTheDocument();
      expect(screen.getByTestId('projects-list-view')).toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // AC-8: NavigationSidebar renders for both authenticated and unauthenticated users
  // -------------------------------------------------------------------------

  describe('AC-8: NavigationSidebar renders for all users', () => {
    it('NavigationSidebar is rendered for an unauthenticated user at /', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/');

      // Assert — sidebar is present for guests
      expect(screen.getByTestId('navigation-sidebar')).toBeInTheDocument();
    });

    it('NavigationSidebar is rendered for an authenticated user at /', () => {
      // Arrange
      setAuthenticated();

      // Act
      renderRoutes('/');

      // Assert — sidebar is present for authenticated users too
      expect(screen.getByTestId('navigation-sidebar')).toBeInTheDocument();
    });

    it('NavigationSidebar is rendered for unauthenticated user at /unauthorized', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/unauthorized');

      // Assert — sidebar renders on the unauthorized page too
      expect(screen.getByTestId('navigation-sidebar')).toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // AC-9: No redirect loop — unauthenticated user at /login stays on /login
  // -------------------------------------------------------------------------

  describe('AC-9: No login redirect loop', () => {
    it('unauthenticated user at /login stays on /login (login page rendered)', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/login');

      // Assert — login page is rendered, no infinite loop
      expect(screen.getByTestId('login-page')).toBeInTheDocument();
    });

    it('unauthenticated user at /login is NOT redirected to / or elsewhere', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/login');

      // Assert — ProjectsPage is NOT rendered (no redirect to /)
      expect(screen.queryByTestId('projects-list-view')).not.toBeInTheDocument();
      // Assert — exactly one login page rendered (no duplication from looping)
      expect(screen.getAllByTestId('login-page')).toHaveLength(1);
    });
  });

  // -------------------------------------------------------------------------
  // AC-12: Deleted routes (/metadata-test, /test-related-record, /movies-quotes-demo)
  //        are NOT present — they hit the catch-all /:modelName route
  // -------------------------------------------------------------------------

  describe('AC-12: Deleted test routes are gone (hit catch-all)', () => {
    it('/metadata-test hits the DynamicModelRoute catch-all (no dedicated page)', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/metadata-test');

      // Assert — no dedicated page; falls to /:modelName catch-all
      expect(screen.getByTestId('dynamic-model-route')).toBeInTheDocument();
    });

    it('/test-related-record hits the DynamicModelRoute catch-all (no dedicated page)', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/test-related-record');

      // Assert
      expect(screen.getByTestId('dynamic-model-route')).toBeInTheDocument();
    });

    it('/movies-quotes-demo hits the DynamicModelRoute catch-all (no dedicated page)', () => {
      // Arrange
      setUnauthenticated();

      // Act
      renderRoutes('/movies-quotes-demo');

      // Assert
      expect(screen.getByTestId('dynamic-model-route')).toBeInTheDocument();
    });
  });
});
