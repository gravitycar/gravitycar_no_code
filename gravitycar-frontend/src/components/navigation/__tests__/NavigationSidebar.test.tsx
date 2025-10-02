import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import NavigationSidebar from '../NavigationSidebar';
import { navigationService } from '../../../services/navigationService';
import { useAuth } from '../../../hooks/useAuth';

// Mock dependencies
jest.mock('../../../services/navigationService');
jest.mock('../../../hooks/useAuth');

const mockNavigationService = navigationService as jest.Mocked<typeof navigationService>;
const mockUseAuth = useAuth as jest.MockedFunction<typeof useAuth>;

const mockNavigationData = {
  role: 'admin',
  sections: [
    { key: 'main', title: 'Main Navigation' },
    { key: 'models', title: 'Data Management' }
  ],
  custom_pages: [
    {
      key: 'dashboard',
      title: 'Dashboard',
      url: '/dashboard',
      icon: '📊',
      roles: ['*']
    },
    {
      key: 'trivia',
      title: 'Movie Trivia',
      url: '/trivia',
      icon: '🎬',
      roles: ['admin', 'user']
    }
  ],
  models: [
    {
      name: 'Users',
      title: 'Users',
      url: '/users',
      icon: '👥',
      actions: [
        {
          key: 'create',
          title: 'Create New',
          url: '/users/create',
          icon: '➕'
        }
      ],
      permissions: {
        list: true,
        create: true,
        update: true,
        delete: false
      }
    },
    {
      name: 'Movies',
      title: 'Movies',
      url: '/movies',
      icon: '🎬',
      actions: [],
      permissions: {
        list: true,
        create: false,
        update: false,
        delete: false
      }
    }
  ],
  generated_at: '2025-01-01T00:00:00Z'
};

const mockNavigationResponse = {
  success: true,
  status: 200,
  data: mockNavigationData,
  cache_hit: true,
  timestamp: '2025-01-01T00:00:00Z'
};

describe('NavigationSidebar', () => {
  beforeEach(() => {
    mockUseAuth.mockReturnValue({
      user: { id: '1', username: 'testuser', user_type: 'admin' },
      isAuthenticated: true,
      isLoading: false,
      login: jest.fn(),
      logout: jest.fn()
    });

    mockNavigationService.getCurrentUserNavigation.mockResolvedValue(mockNavigationResponse);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('renders loading state initially', () => {
    render(<NavigationSidebar />);
    
    // Check for loading skeleton
    expect(document.querySelector('.animate-pulse')).toBeInTheDocument();
  });

  it('renders navigation items after loading', async () => {
    render(<NavigationSidebar />);

    await waitFor(() => {
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
      expect(screen.getByText('Movie Trivia')).toBeInTheDocument();
      expect(screen.getByText('Users')).toBeInTheDocument();
      expect(screen.getByText('Movies')).toBeInTheDocument();
    });

    // Check section headers
    expect(screen.getByText('Navigation')).toBeInTheDocument();
    expect(screen.getByText('Data Management')).toBeInTheDocument();
  });

  it('expands and collapses model actions', async () => {
    const user = userEvent.setup();
    render(<NavigationSidebar />);

    await waitFor(() => {
      expect(screen.getByText('Users')).toBeInTheDocument();
    });

    // Actions should not be visible initially
    expect(screen.queryByText('Create New')).not.toBeInTheDocument();

    // Find the expand button for Users (the one that has actions)
    const usersRow = screen.getByText('Users').closest('div');
    const expandButton = usersRow?.querySelector('button');
    expect(expandButton).toBeInTheDocument();

    // Click the expand button
    if (expandButton) {
      await user.click(expandButton);
    }

    // Actions should now be visible
    await waitFor(() => {
      expect(screen.getByText('Create New')).toBeInTheDocument();
    });

    // Click again to collapse
    if (expandButton) {
      await user.click(expandButton);
    }

    // Actions should be hidden again
    await waitFor(() => {
      expect(screen.queryByText('Create New')).not.toBeInTheDocument();
    });
  });

  it('does not show expand button for models without actions', async () => {
    render(<NavigationSidebar />);

    await waitFor(() => {
      expect(screen.getByText('Movies')).toBeInTheDocument();
    });

    // Movies model has no actions, so should not have expand button
    const moviesRow = screen.getByText('Movies').closest('div');
    const expandButton = moviesRow?.querySelector('button');
    expect(expandButton).not.toBeInTheDocument();
  });

  it('handles navigation service errors', async () => {
    mockNavigationService.getCurrentUserNavigation.mockRejectedValue(
      new Error('Network error')
    );

    render(<NavigationSidebar />);

    await waitFor(() => {
      expect(screen.getByText('Failed to load navigation')).toBeInTheDocument();
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });
  });

  it('retries loading navigation on error', async () => {
    const user = userEvent.setup();
    
    // First call fails
    mockNavigationService.getCurrentUserNavigation
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce(mockNavigationResponse);

    render(<NavigationSidebar />);

    // Should show error state
    await waitFor(() => {
      expect(screen.getByText('Failed to load navigation')).toBeInTheDocument();
    });

    // Click retry
    const retryButton = screen.getByText('Retry');
    await user.click(retryButton);

    // Should now show navigation
    await waitFor(() => {
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
    });
  });

  it('shows debug info in development mode', async () => {
    // Mock development environment
    const originalEnv = import.meta.env.DEV;
    (import.meta.env as any).DEV = true;

    render(<NavigationSidebar />);

    await waitFor(() => {
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
    });

    // Check for debug info
    expect(screen.getByText(/Role: admin/)).toBeInTheDocument();
    expect(screen.getByText('Model Permissions')).toBeInTheDocument();

    // Restore environment
    (import.meta.env as any).DEV = originalEnv;
  });

  it('handles empty navigation data gracefully', async () => {
    const emptyNavData = {
      role: 'guest',
      sections: [],
      custom_pages: [],
      models: [],
      generated_at: '2025-01-01T00:00:00Z'
    };

    mockNavigationService.getCurrentUserNavigation.mockResolvedValue({
      success: true,
      status: 200,
      data: emptyNavData,
      cache_hit: false,
      timestamp: '2025-01-01T00:00:00Z'
    });

    render(<NavigationSidebar />);

    await waitFor(() => {
      // Should not show section headers when there's no content
      expect(screen.queryByText('Navigation')).not.toBeInTheDocument();
      expect(screen.queryByText('Data Management')).not.toBeInTheDocument();
    });
  });

  it('applies custom className prop', () => {
    render(<NavigationSidebar className="custom-class" />);
    
    const nav = document.querySelector('nav');
    expect(nav).toHaveClass('custom-class');
    expect(nav).toHaveClass('bg-gray-50');
    expect(nav).toHaveClass('border-r');
  });

  it('reloads navigation when user changes', async () => {
    const { rerender } = render(<NavigationSidebar />);

    // Wait for initial load
    await waitFor(() => {
      expect(mockNavigationService.getCurrentUserNavigation).toHaveBeenCalledTimes(1);
    });

    // Change user
    mockUseAuth.mockReturnValue({
      user: { id: '2', username: 'newuser', user_type: 'user' },
      isAuthenticated: true,
      isLoading: false,
      login: jest.fn(),
      logout: jest.fn()
    });

    rerender(<NavigationSidebar />);

    // Should call navigation service again
    await waitFor(() => {
      expect(mockNavigationService.getCurrentUserNavigation).toHaveBeenCalledTimes(2);
    });
  });
});