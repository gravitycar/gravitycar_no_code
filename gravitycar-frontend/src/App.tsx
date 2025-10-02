import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './hooks/useAuth';
import { NotificationProvider } from './contexts/NotificationContext';
import { ErrorBoundary } from './components/error/ErrorBoundary';
import Layout from './components/layout/Layout';
import Login from './components/auth/Login';
import Dashboard from './pages/Dashboard';
import MetadataTestPage from './pages/MetadataTestPage';
import TestRelatedRecord from './pages/TestRelatedRecord';
import DynamicModelRoute from './components/routing/DynamicModelRoute';
import MoviesQuotesRelationshipDemo from './pages/MoviesQuotesRelationshipDemo';
import TriviaPage from './pages/TriviaPage';
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
      
      <Route
        path="/movies-quotes-demo"
        element={
          <ProtectedRoute>
            <Layout>
              <MoviesQuotesRelationshipDemo />
            </Layout>
          </ProtectedRoute>
        }
      />
      
      {/* Movie Quote Trivia Game Route */}
      <Route
        path="/trivia"
        element={
          <ProtectedRoute>
            <Layout>
              <TriviaPage />
            </Layout>
          </ProtectedRoute>
        }
      />
      
      {/* Default route */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      
      {/* Dynamic Model Routes - handles any model using GenericCrudPage */}
      {/* This must be placed AFTER all specific routes but BEFORE the 404 route */}
      <Route
        path="/:modelName"
        element={
          <ProtectedRoute>
            <Layout>
              <DynamicModelRoute />
            </Layout>
          </ProtectedRoute>
        }
      />
      
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
