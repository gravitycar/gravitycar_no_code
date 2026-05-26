import { describe, it, expect } from 'vitest';
import { getRedirectPath } from '../redirectPath';

describe('getRedirectPath()', () => {
  describe('valid relative paths', () => {
    it('returns a valid relative path starting with /', () => {
      // Arrange
      const params = new URLSearchParams('redirect=/events/42');

      // Act
      const result = getRedirectPath(params);

      // Assert
      expect(result).toBe('/events/42');
    });

    it('returns the root path when redirect is exactly /', () => {
      // Arrange
      const params = new URLSearchParams('redirect=/');

      // Act
      const result = getRedirectPath(params);

      // Assert
      expect(result).toBe('/');
    });

    it('preserves query strings in the redirect path', () => {
      // Arrange
      const params = new URLSearchParams('redirect=/events/42?tab=details');

      // Act
      const result = getRedirectPath(params);

      // Assert
      expect(result).toBe('/events/42?tab=details');
    });
  });

  describe('missing or absent redirect param', () => {
    it('returns / when redirect param is absent', () => {
      // Arrange
      const params = new URLSearchParams();

      // Act
      const result = getRedirectPath(params);

      // Assert
      expect(result).toBe('/');
    });

    it('returns / when redirect is an empty string', () => {
      // Arrange
      const params = new URLSearchParams('redirect=');

      // Act
      const result = getRedirectPath(params);

      // Assert
      expect(result).toBe('/');
    });
  });

  describe('open redirect prevention', () => {
    it('rejects protocol-relative URLs starting with //', () => {
      // Arrange — an attacker might try //evil.com/steal
      const params = new URLSearchParams('redirect=//evil.com/steal');

      // Act
      const result = getRedirectPath(params);

      // Assert
      expect(result).toBe('/');
    });

    it('rejects absolute URLs starting with https://', () => {
      // Arrange
      const params = new URLSearchParams('redirect=https://evil.com');

      // Act
      const result = getRedirectPath(params);

      // Assert
      expect(result).toBe('/');
    });

    it('rejects relative paths without a leading slash', () => {
      // Arrange
      const params = new URLSearchParams('redirect=events/42');

      // Act
      const result = getRedirectPath(params);

      // Assert
      expect(result).toBe('/');
    });
  });
});
