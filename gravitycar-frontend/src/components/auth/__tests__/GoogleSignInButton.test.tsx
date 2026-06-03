import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, waitFor, act } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import type { CredentialResponse } from '../../../types/google';
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

const mockLoginWithGoogle = vi.fn<(token: string) => Promise<AuthResponse>>();
vi.mock('../../../hooks/useAuth', () => ({
  useAuth: () => ({
    login: vi.fn(),
    loginWithGoogle: mockLoginWithGoogle,
    logout: vi.fn(),
    checkAuth: vi.fn(),
    user: null,
    isAuthenticated: false,
    isLoading: false,
  }),
}));

// Capture the onSuccess callback so we can invoke it directly in tests
let capturedOnSuccess: ((cr: CredentialResponse) => void) | null = null;

vi.mock('../../../hooks/useGoogleOAuth', () => ({
  useGoogleOAuth: ({ onSuccess }: { onSuccess: (cr: CredentialResponse) => void }) => {
    capturedOnSuccess = onSuccess;
    return {
      renderButton: vi.fn(),
      isGoogleLoaded: false,
      isGoogleInitialized: false,
    };
  },
  decodeGoogleJWT: vi.fn(() => ({})),
}));

// ---- helpers ----

function renderButton(initialPath = '/login') {
  return render(
    <MemoryRouter initialEntries={[initialPath]}>
      <ButtonComponent />
    </MemoryRouter>
  );
}

let ButtonComponent: React.ComponentType;
beforeEach(async () => {
  vi.clearAllMocks();
  capturedOnSuccess = null;
  const mod = await import('../GoogleSignInButton');
  ButtonComponent = mod.default;
});

function fakeCredentialResponse(credential = 'fake-jwt-token'): CredentialResponse {
  return { credential };
}

// ---- tests ----

describe('GoogleSignInButton — handleGoogleSuccess', () => {
  describe('successful Google login', () => {
    it('navigates to redirect path when ?redirect= is valid and login succeeds', async () => {
      // Arrange
      mockLoginWithGoogle.mockResolvedValueOnce({ success: true, token: 'tok', user: undefined, message: 'OK' });
      renderButton('/login?redirect=%2Fevents');

      // Act — trigger the callback that the Google SDK would call
      await act(async () => { await capturedOnSuccess!(fakeCredentialResponse()); });

      // Assert
      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalledWith('/events');
      });
    });

    it('navigates to / when no ?redirect= param is present', async () => {
      // Arrange
      mockLoginWithGoogle.mockResolvedValueOnce({ success: true, token: 'tok', user: undefined, message: 'OK' });
      renderButton('/login');

      // Act
      await act(async () => { await capturedOnSuccess!(fakeCredentialResponse()); });

      // Assert
      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalledWith('/');
      });
    });
  });

  describe('failed Google login', () => {
    it('does NOT navigate when loginWithGoogle returns success:false', async () => {
      // Arrange
      mockLoginWithGoogle.mockResolvedValueOnce({ success: false, message: 'Google login failed' });
      renderButton('/login');

      // Act
      await act(async () => { await capturedOnSuccess!(fakeCredentialResponse()); });

      // Assert — navigate must NOT be called
      await waitFor(() => {
        expect(mockLoginWithGoogle).toHaveBeenCalled();
      });
      expect(mockNavigate).not.toHaveBeenCalled();
    });

    it('does NOT navigate when the credential is missing', async () => {
      // Arrange — Google sent back a response with no credential
      renderButton('/login');

      // Act
      await act(async () => { await capturedOnSuccess!({ credential: undefined }); });

      // Assert
      await waitFor(() => {
        expect(mockNavigate).not.toHaveBeenCalled();
      });
    });

    it('does NOT navigate when loginWithGoogle throws', async () => {
      // Arrange
      mockLoginWithGoogle.mockRejectedValueOnce(new Error('OAuth failure'));
      renderButton('/login');

      // Act
      await act(async () => { await capturedOnSuccess!(fakeCredentialResponse()); });

      // Assert
      await waitFor(() => {
        expect(mockNavigate).not.toHaveBeenCalled();
      });
    });
  });
});
