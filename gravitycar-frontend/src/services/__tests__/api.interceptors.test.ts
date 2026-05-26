/**
 * Unit tests for api.ts request/response interceptors.
 *
 * Strategy: mock axios.create to capture the interceptor callbacks that
 * ApiService registers in its constructor, then invoke them directly.
 * This avoids making real HTTP calls while testing the interceptor logic.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// --- mocks (must be declared before dynamic imports) ---

const mockRequestSuccess = vi.fn((config: Record<string, unknown>) => config);
let capturedResponseErrorHandler: ((error: unknown) => unknown) | null = null;
let capturedRequestHandler: ((config: Record<string, unknown>) => Record<string, unknown>) | null = null;

const mockInterceptorsRequest = {
  use: vi.fn((successFn: (config: Record<string, unknown>) => Record<string, unknown>) => {
    capturedRequestHandler = successFn;
    mockRequestSuccess.mockImplementation(successFn);
  }),
};

const mockInterceptorsResponse = {
  use: vi.fn(
    (_successFn: unknown, errorFn: (error: unknown) => unknown) => {
      capturedResponseErrorHandler = errorFn;
    }
  ),
};

const mockAxiosInstance = {
  interceptors: {
    request: mockInterceptorsRequest,
    response: mockInterceptorsResponse,
  },
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  delete: vi.fn(),
};

vi.mock('axios', () => ({
  default: {
    create: vi.fn(() => mockAxiosInstance),
  },
}));

// Mock the navigate singleton so we can spy on imperativeNavigate
const mockImperativeNavigate = vi.fn();
vi.mock('../../utils/navigate', () => ({
  imperativeNavigate: mockImperativeNavigate,
}));

// ---- helpers ----

function makeBackendError(status: number, message: string, extra: Record<string, unknown> = {}) {
  return {
    response: {
      status,
      data: {
        success: false,
        status,
        error: { message, type: 'error', code: status, ...extra },
        timestamp: '2025-01-01T00:00:00Z',
      },
    },
  };
}

function makePlainHttpError(status: number) {
  return {
    response: {
      status,
      data: {},
    },
  };
}

// ---- tests ----

describe('api.ts interceptors', () => {
  beforeEach(async () => {
    // Clear all mocks before each test
    vi.clearAllMocks();
    capturedResponseErrorHandler = null;
    capturedRequestHandler = null;

    // Reset localStorage
    localStorage.clear();

    // Reset window.location to a safe default
    Object.defineProperty(window, 'location', {
      value: {
        pathname: '/some-page',
        search: '',
        href: '',
      },
      writable: true,
      configurable: true,
    });

    // Re-import ApiService fresh so interceptors are re-registered
    vi.resetModules();
    const mod = await import('../api');
    // Trigger instantiation by accessing the default export
    void mod.default;
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  // -----------------------------------------------------------------------
  // Request interceptor — XDEBUG_TRIGGER
  // -----------------------------------------------------------------------

  describe('request interceptor — XDEBUG_TRIGGER', () => {
    it('adds XDEBUG_TRIGGER when DEV is true and params is undefined', async () => {
      // Arrange
      (import.meta.env as Record<string, unknown>).DEV = true;
      vi.resetModules();
      await import('../api');

      const config: Record<string, unknown> = { headers: {}, params: undefined };

      // Act
      const result = capturedRequestHandler!(config);

      // Assert
      expect((result.params as Record<string, unknown>).XDEBUG_TRIGGER).toBe('mike');
    });

    it('merges XDEBUG_TRIGGER with existing params when DEV is true', async () => {
      // Arrange
      (import.meta.env as Record<string, unknown>).DEV = true;
      vi.resetModules();
      await import('../api');

      const config: Record<string, unknown> = { headers: {}, params: { foo: 'bar' } };

      // Act
      const result = capturedRequestHandler!(config);

      // Assert
      const params = result.params as Record<string, unknown>;
      expect(params.foo).toBe('bar');
      expect(params.XDEBUG_TRIGGER).toBe('mike');
    });

    it('does NOT add XDEBUG_TRIGGER when DEV is false', async () => {
      // Arrange
      (import.meta.env as Record<string, unknown>).DEV = false;
      vi.resetModules();
      await import('../api');

      const config: Record<string, unknown> = { headers: {}, params: undefined };

      // Act
      capturedRequestHandler!(config);

      // Assert — params should remain undefined (XDEBUG_TRIGGER not set)
      expect(config.params).toBeUndefined();
    });

    it('leaves existing params untouched when DEV is false', async () => {
      // Arrange
      (import.meta.env as Record<string, unknown>).DEV = false;
      vi.resetModules();
      await import('../api');

      const config: Record<string, unknown> = { headers: {}, params: { foo: 'bar' } };

      // Act
      capturedRequestHandler!(config);

      // Assert
      const params = config.params as Record<string, unknown>;
      expect(params.foo).toBe('bar');
      expect(params.XDEBUG_TRIGGER).toBeUndefined();
    });
  });

  // -----------------------------------------------------------------------
  // Response interceptor — 401 handling
  // -----------------------------------------------------------------------

  describe('response interceptor — 401 handling', () => {
    it('redirects to /login with encoded path on 401 backend error', async () => {
      // Arrange
      Object.defineProperty(window, 'location', {
        value: { pathname: '/events', search: '', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      const error = makeBackendError(401, 'Unauthorized');
      localStorage.setItem('auth_token', 'tok');
      localStorage.setItem('user', '{}');

      // Act
      await capturedResponseErrorHandler!(error).catch(() => {});

      // Assert
      expect(window.location.href).toBe('/login?redirect=%2Fevents');
      expect(localStorage.getItem('auth_token')).toBeNull();
      expect(localStorage.getItem('user')).toBeNull();
    });

    it('appends path + search to redirect URL on 401 fallback', async () => {
      // Arrange
      Object.defineProperty(window, 'location', {
        value: { pathname: '/events', search: '?page=2', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      const error = makePlainHttpError(401);

      // Act
      await capturedResponseErrorHandler!(error).catch(() => {});

      // Assert
      expect(window.location.href).toBe('/login?redirect=%2Fevents%3Fpage%3D2');
    });

    it('rejects the promise after 401 backend redirect', async () => {
      // Arrange
      Object.defineProperty(window, 'location', {
        value: { pathname: '/some-page', search: '', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      const error = makeBackendError(401, 'Unauthorized');

      // Act & Assert
      await expect(capturedResponseErrorHandler!(error)).rejects.toBeTruthy();
    });
  });

  // -----------------------------------------------------------------------
  // Response interceptor — 403 handling
  // -----------------------------------------------------------------------

  describe('response interceptor — 403 handling', () => {
    it('calls imperativeNavigate to /unauthorized on 403 backend error', async () => {
      // Arrange
      Object.defineProperty(window, 'location', {
        value: { pathname: '/admin', search: '', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      const error = makeBackendError(403, 'Forbidden');

      // Act
      await capturedResponseErrorHandler!(error).catch(() => {});

      // Assert
      expect(mockImperativeNavigate).toHaveBeenCalledWith('/unauthorized?redirect=%2Fadmin', { replace: true });
    });

    it('calls imperativeNavigate to /unauthorized on 403 fallback HTTP error', async () => {
      // Arrange
      Object.defineProperty(window, 'location', {
        value: { pathname: '/admin', search: '', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      const error = makePlainHttpError(403);

      // Act
      await capturedResponseErrorHandler!(error).catch(() => {});

      // Assert
      expect(mockImperativeNavigate).toHaveBeenCalledWith('/unauthorized?redirect=%2Fadmin', { replace: true });
    });

    it('does NOT call imperativeNavigate when already on /unauthorized — 403 backend error (loop guard)', async () => {
      // Arrange — AC-13: loop guard prevents infinite redirect
      Object.defineProperty(window, 'location', {
        value: { pathname: '/unauthorized', search: '', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      const error = makeBackendError(403, 'Forbidden');

      // Act
      await capturedResponseErrorHandler!(error).catch(() => {});

      // Assert
      expect(mockImperativeNavigate).not.toHaveBeenCalled();
    });

    it('does NOT call imperativeNavigate when already on /unauthorized — 403 fallback (loop guard)', async () => {
      // Arrange — AC-13: loop guard applies to fallback branch too
      Object.defineProperty(window, 'location', {
        value: { pathname: '/unauthorized', search: '', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      const error = makePlainHttpError(403);

      // Act
      await capturedResponseErrorHandler!(error).catch(() => {});

      // Assert
      expect(mockImperativeNavigate).not.toHaveBeenCalled();
    });

    it('does NOT clear localStorage on 403 — user remains authenticated', async () => {
      // Arrange — AC-5: localStorage must be preserved on 403
      Object.defineProperty(window, 'location', {
        value: { pathname: '/admin', search: '', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      localStorage.setItem('auth_token', 'valid-token');
      localStorage.setItem('user', '{"id": 1}');
      const error = makeBackendError(403, 'Forbidden');

      // Act
      await capturedResponseErrorHandler!(error).catch(() => {});

      // Assert
      expect(localStorage.getItem('auth_token')).toBe('valid-token');
      expect(localStorage.getItem('user')).toBe('{"id": 1}');
    });

    it('rejects the promise after 403 backend error', async () => {
      // Arrange
      Object.defineProperty(window, 'location', {
        value: { pathname: '/admin', search: '', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      const error = makeBackendError(403, 'Forbidden');

      // Act & Assert
      await expect(capturedResponseErrorHandler!(error)).rejects.toBeTruthy();
    });

    it('does NOT redirect to /login on 403 — only /unauthorized', async () => {
      // Arrange
      Object.defineProperty(window, 'location', {
        value: { pathname: '/admin', search: '', href: '' },
        writable: true,
        configurable: true,
      });
      vi.resetModules();
      await import('../api');

      const error = makeBackendError(403, 'Forbidden');

      // Act
      await capturedResponseErrorHandler!(error).catch(() => {});

      // Assert — href must NOT have been set to /login
      expect(window.location.href).not.toContain('/login');
    });
  });

  // -----------------------------------------------------------------------
  // Response interceptor — network error
  // -----------------------------------------------------------------------

  describe('response interceptor — network error', () => {
    it('rejects with a network error message when there is no response', async () => {
      // Arrange
      vi.resetModules();
      await import('../api');

      const error = { message: 'Network Error', response: undefined };

      // Act & Assert
      await expect(capturedResponseErrorHandler!(error)).rejects.toThrow('Network error');
    });
  });
});
