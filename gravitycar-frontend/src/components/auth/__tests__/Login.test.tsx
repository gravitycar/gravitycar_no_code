import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import type { AuthResponse } from '../../../types';

// --- mocks ---

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

const mockLogin = vi.fn<() => Promise<AuthResponse>>();
vi.mock('../../../hooks/useAuth', () => ({
  useAuth: () => ({
    login: mockLogin,
    loginWithGoogle: vi.fn(),
    logout: vi.fn(),
    checkAuth: vi.fn(),
    user: null,
    isAuthenticated: false,
    isLoading: false,
  }),
}));

// Stub GoogleSignInButton so it doesn't attempt to load the Google SDK
vi.mock('../GoogleSignInButton', () => ({
  default: () => <div data-testid="google-signin-stub" />,
}));

// ---- helpers ----

function renderLogin(initialPath = '/login') {
  return render(
    <MemoryRouter initialEntries={[initialPath]}>
      <LoginComponent />
    </MemoryRouter>
  );
}

// Lazy import so mocks are in place before import
let LoginComponent: React.ComponentType;
beforeEach(async () => {
  vi.clearAllMocks();
  const mod = await import('../Login');
  LoginComponent = mod.default;
});

async function submitForm(username = 'testuser', password = 'secret') {
  await act(async () => {
    fireEvent.change(screen.getByLabelText(/username/i), { target: { value: username, name: 'username' } });
    fireEvent.change(screen.getByLabelText(/password/i), { target: { value: password, name: 'password' } });
    fireEvent.submit(screen.getByRole('button', { name: /sign in/i }).closest('form')!);
  });
}

// ---- tests ----

describe('Login component — handleSubmit', () => {
  describe('successful login', () => {
    it('navigates to redirect path when ?redirect= is a valid relative path', async () => {
      // Arrange
      mockLogin.mockResolvedValueOnce({ success: true, token: 'tok', user: undefined, message: 'OK' });
      renderLogin('/login?redirect=%2Fevents%2F42');

      // Act
      await submitForm();

      // Assert
      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalledWith('/events/42');
      });
    });

    it('navigates to / when no ?redirect= param is present', async () => {
      // Arrange
      mockLogin.mockResolvedValueOnce({ success: true, token: 'tok', user: undefined, message: 'OK' });
      renderLogin('/login');

      // Act
      await submitForm();

      // Assert
      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalledWith('/');
      });
    });

    it('navigates to / when ?redirect= value is an invalid protocol-relative URL', async () => {
      // Arrange — open redirect prevention
      mockLogin.mockResolvedValueOnce({ success: true, token: 'tok', user: undefined, message: 'OK' });
      renderLogin('/login?redirect=%2F%2Fevil.com%2Fsteal');

      // Act
      await submitForm();

      // Assert
      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalledWith('/');
      });
    });

    it('navigates to / when ?redirect= value is an absolute URL', async () => {
      // Arrange — open redirect prevention
      mockLogin.mockResolvedValueOnce({ success: true, token: 'tok', user: undefined, message: 'OK' });
      renderLogin('/login?redirect=https%3A%2F%2Fevil.com');

      // Act
      await submitForm();

      // Assert
      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalledWith('/');
      });
    });
  });

  describe('failed login', () => {
    it('shows error message and does NOT navigate when login returns success:false', async () => {
      // Arrange
      mockLogin.mockResolvedValueOnce({ success: false, message: 'Bad credentials' });
      renderLogin('/login');

      // Act
      await submitForm();

      // Assert
      await waitFor(() => {
        expect(screen.getByText('Bad credentials')).toBeInTheDocument();
      });
      expect(mockNavigate).not.toHaveBeenCalled();
    });

    it('shows a fallback error message when login returns success:false with no message', async () => {
      // Arrange
      mockLogin.mockResolvedValueOnce({ success: false, message: undefined });
      renderLogin('/login');

      // Act
      await submitForm();

      // Assert
      await waitFor(() => {
        expect(screen.getByText('Login failed')).toBeInTheDocument();
      });
      expect(mockNavigate).not.toHaveBeenCalled();
    });

    it('shows a generic error message and does NOT navigate when login throws', async () => {
      // Arrange
      mockLogin.mockRejectedValueOnce(new Error('Network failure'));
      renderLogin('/login');

      // Act
      await submitForm();

      // Assert
      await waitFor(() => {
        expect(screen.getByText('An unexpected error occurred')).toBeInTheDocument();
      });
      expect(mockNavigate).not.toHaveBeenCalled();
    });
  });
});
