import type { ReactNode } from 'react';
import { useAuth } from '../../hooks/useAuth';
import NavigationSidebar from '../navigation/NavigationSidebar';

interface LayoutProps {
  children: ReactNode;
}

const Layout = ({ children }: LayoutProps) => {
  const { user, logout, isAuthenticated } = useAuth();

  const handleLogout = async () => {
    await logout();
  };

  // Helper function to get display name for user
  const getUserDisplayName = () => {
    if (!user) return '';
    
    // Prefer first_name + last_name
    if (user.first_name && user.last_name) {
      return `${user.first_name} ${user.last_name}`;
    }
    
    // Fallback to first_name only
    if (user.first_name) {
      return user.first_name;
    }
    
    // Fallback to username or email
    return user.username || user.email;
  };

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Header */}
      <header className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div className="flex items-center">
              <h1 className="text-xl font-semibold text-gray-900">
                Gravitycar Framework
              </h1>
            </div>
            
            {isAuthenticated && (
              <div className="flex items-center space-x-4">
                <span className="text-gray-700">
                  Welcome, {getUserDisplayName()}
                </span>
                <button
                  onClick={handleLogout}
                  className="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
                >
                  Logout
                </button>
              </div>
            )}
          </div>
        </div>
      </header>

      {/* Main Layout with Sidebar */}
      <div className="flex h-screen">
        {/* Dynamic Navigation Sidebar */}
        {isAuthenticated && (
          <NavigationSidebar className="w-64 flex-shrink-0" />
        )}

        {/* Main Content Area */}
        <main className="flex-1 overflow-y-auto">
          <div className="max-w-7xl mx-auto">
            <div className="px-4 sm:px-6 lg:px-8">
              {children}
            </div>
          </div>
        </main>
      </div>

      {/* Footer */}
      <footer className="bg-white border-t">
        <div className="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
          <p className="text-center text-sm text-gray-500">
            © 2025 Gravitycar Framework. Built with React and TypeScript.
          </p>
        </div>
      </footer>
    </div>
  );
};

export default Layout;
