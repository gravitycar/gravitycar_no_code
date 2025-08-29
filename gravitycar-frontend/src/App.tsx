import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './hooks/useAuth';
import { NotificationProvider } from './contexts/NotificationContext';
import { ErrorBoundary } from './components/error/ErrorBoundary';
import Layout from './components/layout/Layout';
import Login from './components/auth/Login';
import Dashboard from './pages/Dashboard';
import MetadataTestPage from './pages/MetadataTestPage';
import TestRelatedRecord from './pages/TestRelatedRecord';
import UsersPage from './pages/UsersPage';
import MoviesPage from './pages/MoviesPage';
import MovieQuotesPage from './pages/MovieQuotesPage';
import './App.css';

// Protected Route Component
const ProtectedRoute = ({ children }: { children: React.ReactNode }) => {
  const { isAuthenticated, isLoading } = useAuth();
  
  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-lg text-gray-600">Loading...</div>
      </div>
    );
  }
  
  return isAuthenticated ? <>{children}</> : <Navigate to="/login" replace />;
};

// Public Route Component (only accessible when NOT authenticated)
const PublicRoute = ({ children }: { children: React.ReactNode }) => {
  const { isAuthenticated, isLoading } = useAuth();
  
  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-lg text-gray-600">Loading...</div>
      </div>
    );
  }
  
  return !isAuthenticated ? <>{children}</> : <Navigate to="/dashboard" replace />;
};

// App Routes Component
const AppRoutes = () => {
  return (
    <Routes>
      {/* Public Routes */}
      <Route 
        path="/login" 
        element={
          <PublicRoute>
            <Login />
          </PublicRoute>
        } 
      />
      
      {/* Protected Routes */}
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        }
      />
      
      <Route
        path="/metadata-test"
        element={
          <ProtectedRoute>
            <Layout>
              <MetadataTestPage />
            </Layout>
          </ProtectedRoute>
        }
      />
      
      <Route
        path="/test-related-record"
        element={
          <ProtectedRoute>
            <Layout>
              <TestRelatedRecord />
            </Layout>
          </ProtectedRoute>
        }
      />
      
      {/* Users Management Route - COMPLETED */}
      <Route
        path="/users"
        element={
          <ProtectedRoute>
            <Layout>
              <UsersPage />
            </Layout>
          </ProtectedRoute>
        }
      />
      
      <Route
        path="/movies"
        element={
          <ProtectedRoute>
            <Layout>
              <MoviesPage />
            </Layout>
          </ProtectedRoute>
        }
      />
      
      <Route
        path="/quotes"
        element={
          <ProtectedRoute>
            <Layout>
              <MovieQuotesPage />
            </Layout>
          </ProtectedRoute>
        }
      />
      
      {/* Default route */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      
      {/* 404 route */}
      <Route 
        path="*" 
        element={
          <div className="min-h-screen flex items-center justify-center">
            <div className="text-center">
              <h1 className="text-4xl font-bold text-gray-900 mb-4">404</h1>
              <p className="text-gray-600 mb-4">Page not found</p>
              <a href="/dashboard" className="text-blue-600 hover:text-blue-800">
                Go to Dashboard
              </a>
            </div>
          </div>
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
            <AppRoutes />
          </Router>
        </AuthProvider>
      </NotificationProvider>
    </ErrorBoundary>
  );
}

export default App;
