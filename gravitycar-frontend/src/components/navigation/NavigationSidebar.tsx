import React, { useState, useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { navigationService } from '../../services/navigationService';
import { NavigationData, NavigationAction } from '../../types/navigation';
import { useAuth } from '../../hooks/useAuth';

interface NavigationSidebarProps {
  className?: string;
}

const NavigationSidebar: React.FC<NavigationSidebarProps> = ({ className = '' }) => {
  const { user } = useAuth();
  const location = useLocation();
  const [navigationData, setNavigationData] = useState<NavigationData | null>(null);
  const [expandedModel, setExpandedModel] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadNavigation();
  }, [user]);

  const loadNavigation = async () => {
    try {
      setIsLoading(true);
      setError(null);
      
      const response = await navigationService.getCurrentUserNavigation();
      setNavigationData(response.data);
      
    } catch (err) {
      console.error('Failed to load navigation:', err);
      setError('Failed to load navigation');
    } finally {
      setIsLoading(false);
    }
  };

  const handleModelClick = (modelKey: string) => {
    setExpandedModel(expandedModel === modelKey ? null : modelKey);
  };

  const handleActionClick = (action: NavigationAction, modelName: string) => {
    if (action.action === 'create') {
      // Check if we're currently on the model's page
      const currentPath = location.pathname;
      const expectedPath = `/${modelName.toLowerCase()}`;
      
      if (currentPath === expectedPath) {
        // We're on the model page, dispatch create event
        const createEvent = new CustomEvent('navigation-create', {
          detail: { modelName }
        });
        window.dispatchEvent(createEvent);
      } else {
        // Navigate to the model page first, then trigger create
        window.location.href = expectedPath + '?action=create';
      }
    } else if (action.url) {
      // Regular URL navigation
      window.location.href = action.url;
    }
  };

  if (isLoading) {
    return (
      <nav className={`bg-gray-50 border-r ${className}`}>
        <div className="p-4">
          <div className="animate-pulse">
            <div className="h-4 bg-gray-200 rounded mb-2"></div>
            <div className="h-4 bg-gray-200 rounded mb-2"></div>
            <div className="h-4 bg-gray-200 rounded"></div>
          </div>
        </div>
      </nav>
    );
  }

  if (error) {
    return (
      <nav className={`bg-gray-50 border-r ${className}`}>
        <div className="p-4 text-red-600">
          <p className="text-sm">{error}</p>
          <button 
            onClick={loadNavigation}
            className="mt-2 text-xs underline hover:no-underline"
          >
            Retry
          </button>
        </div>
      </nav>
    );
  }

  if (!navigationData) {
    return (
      <nav className={`bg-gray-50 border-r ${className}`}>
        <div className="p-4 text-gray-500">
          <p className="text-sm">No navigation data available</p>
        </div>
      </nav>
    );
  }

  return (
    <nav className={`bg-gray-50 border-r overflow-y-auto ${className}`}>
      <div className="p-4">
        {/* Custom Pages Section */}
        {navigationData.custom_pages.length > 0 && (
          <div className="mb-6">
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
              Navigation
            </h3>
            <ul className="space-y-1">
              {navigationData.custom_pages.map((page) => (
                <li key={page.key}>
                  <a
                    href={page.url}
                    className="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
                  >
                    <span className="mr-2">{page.icon}</span>
                    {page.title}
                  </a>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Models Section */}
        {navigationData.models.length > 0 && (
          <div>
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
              Data Management
            </h3>
            <ul className="space-y-1">
              {navigationData.models.map((model) => (
                <li key={model.name}>
                  <div>
                    {/* Model Name - Always clickable to list view */}
                    <div className="flex items-center justify-between px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors">
                      <a
                        href={model.url}
                        className="flex items-center flex-1"
                      >
                        <span className="mr-2">{model.icon}</span>
                        {model.title}
                      </a>
                      {model.actions && model.actions.length > 0 && (
                        <button
                          onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            handleModelClick(model.name);
                          }}
                          className="ml-2 p-1 hover:bg-gray-200 rounded"
                        >
                          <svg 
                            className={`w-4 h-4 transition-transform ${
                              expandedModel === model.name ? 'rotate-180' : ''
                            }`}
                            fill="currentColor" 
                            viewBox="0 0 20 20"
                          >
                            <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                          </svg>
                        </button>
                      )}
                    </div>

                    {/* Expandable Actions */}
                    {expandedModel === model.name && model.actions && model.actions.length > 0 && (
                      <ul className="mt-1 ml-6 space-y-1">
                        {model.actions.map((action) => (
                          <li key={action.key}>
                            {action.action ? (
                              <button
                                onClick={() => handleActionClick(action, model.name)}
                                className="flex items-center px-3 py-1 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors w-full text-left"
                              >
                                <span className="mr-2">{action.icon}</span>
                                {action.title}
                              </button>
                            ) : (
                              <a
                                href={action.url}
                                className="flex items-center px-3 py-1 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                              >
                                <span className="mr-2">{action.icon}</span>
                                {action.title}
                              </a>
                            )}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Debug Info (only in development) */}
        {import.meta.env.DEV && (
          <div className="mt-6 pt-4 border-t border-gray-200">
            <p className="text-xs text-gray-400">
              Role: {navigationData.role} | Generated: {new Date(navigationData.generated_at).toLocaleTimeString()}
            </p>
            {/* Show permissions for debugging */}
            <details className="mt-2">
              <summary className="text-xs text-gray-500 cursor-pointer">Model Permissions</summary>
              <div className="mt-1 text-xs text-gray-400">
                {navigationData.models.map((model) => (
                  <div key={model.name} className="mb-1">
                    <strong>{model.name}:</strong> 
                    {model.permissions && Object.entries(model.permissions)
                      .filter(([, hasPermission]) => hasPermission)
                      .map(([permission]) => permission)
                      .join(', ')}
                  </div>
                ))}
              </div>
            </details>
          </div>
        )}
      </div>
    </nav>
  );
};

export default NavigationSidebar;