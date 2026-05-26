import { BrowserRouter as Router, Routes, Route, Navigate, useSearchParams } from 'react-router-dom';
import { AuthProvider, useAuth } from './hooks/useAuth';
import { NotificationProvider } from './contexts/NotificationContext';
import { ErrorBoundary } from './components/error/ErrorBoundary';
import Layout from './components/layout/Layout';
import Login from './components/auth/Login';
import DynamicModelRoute from './components/routing/DynamicModelRoute';
import GenericCrudPage from './components/crud/GenericCrudPage';
import TriviaPage from './pages/TriviaPage';
import ProjectsPage from './pages/ProjectsPage';
import DnDChatPage from './pages/DnDChatPage';
import ChartOfGoodness from './pages/ChartOfGoodness';
import BatchProposeDates from './pages/BatchProposeDates';
import UnauthorizedPage from './pages/UnauthorizedPage';
import NotFoundPage from './pages/NotFoundPage';
import { NavigatorSetter } from './utils/navigate';
import { getRedirectPath } from './utils/redirectPath';
import './App.css';

// Public Route Component (only accessible when NOT authenticated)
const PublicRoute = ({ children }: { children: React.ReactNode }) => {
  const { isAuthenticated, isLoading } = useAuth();
  const [searchParams] = useSearchParams();

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-lg text-gray-600">Loading...</div>
      </div>
    );
  }

  return !isAuthenticated ? <>{children}</> : <Navigate to={getRedirectPath(searchParams)} replace />;
};

// App Routes Component
const AppRoutes = () => {
  return (
    <Routes>
      {/* Login — public only (redirect authenticated users away) */}
      <Route
        path="/login"
        element={
          <PublicRoute>
            <Login />
          </PublicRoute>
        }
      />

      {/* Root — public home page (no auth requirement) */}
      <Route
        path="/"
        element={
          <Layout>
            <ProjectsPage />
          </Layout>
        }
      />

      {/* Projects Showcase alias — kept for backwards compatibility */}
      <Route
        path="/projects_showcase"
        element={
          <Layout>
            <ProjectsPage />
          </Layout>
        }
      />

      {/* Unauthorized — public (403 interceptor navigates here) */}
      <Route
        path="/unauthorized"
        element={
          <Layout>
            <UnauthorizedPage />
          </Layout>
        }
      />

      {/* Not Found — public (404 interceptor navigates here) */}
      <Route
        path="/not-found"
        element={
          <Layout>
            <NotFoundPage />
          </Layout>
        }
      />

      {/* Trivia Game */}
      <Route
        path="/trivia"
        element={
          <Layout>
            <TriviaPage />
          </Layout>
        }
      />

      {/* D&D RAG Chat */}
      <Route
        path="/dnd-chat"
        element={
          <Layout>
            <DnDChatPage />
          </Layout>
        }
      />

      {/* Events Routes */}
      <Route
        path="/events"
        element={
          <Layout>
            <GenericCrudPage
              modelName="Events"
              title="Events"
              description="Manage events in your system"
            />
          </Layout>
        }
      />
      <Route
        path="/events/:eventId/chart"
        element={
          <Layout>
            <ChartOfGoodness />
          </Layout>
        }
      />
      <Route
        path="/events/:eventId/propose-dates"
        element={
          <Layout>
            <BatchProposeDates />
          </Layout>
        }
      />

      {/* Dynamic Model Routes — catch-all for any model using GenericCrudPage */}
      {/* Must be placed AFTER all specific routes but BEFORE the 404 route */}
      <Route
        path="/:modelName"
        element={
          <Layout>
            <DynamicModelRoute />
          </Layout>
        }
      />

      {/* 404 — catch-all for unknown frontend paths */}
      <Route
        path="*"
        element={
          <Layout>
            <NotFoundPage />
          </Layout>
        }
      />
    </Routes>
  );
};

function App() {
  return (
    <ErrorBoundary>
      <NotificationProvider>
        <AuthProvider>
          <Router>
            <NavigatorSetter />
            <AppRoutes />
          </Router>
        </AuthProvider>
      </NotificationProvider>
    </ErrorBoundary>
  );
}

export default App;
