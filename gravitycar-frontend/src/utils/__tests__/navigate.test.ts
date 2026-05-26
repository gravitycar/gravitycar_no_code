import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setNavigator, imperativeNavigate } from '../navigate';
import type { NavigateFunction } from 'react-router-dom';

// Reset module-level state before each test by passing null
beforeEach(() => {
  setNavigator(null as unknown as NavigateFunction);
});

describe('setNavigator() and imperativeNavigate()', () => {
  describe('when navigator is set', () => {
    it('calls the stored navigator function with the path', () => {
      // Arrange
      const mockNavigate = vi.fn();
      setNavigator(mockNavigate as unknown as NavigateFunction);

      // Act
      imperativeNavigate('/foo');

      // Assert
      expect(mockNavigate).toHaveBeenCalledWith('/foo', undefined);
    });

    it('forwards NavigateOptions to the stored navigator', () => {
      // Arrange
      const mockNavigate = vi.fn();
      setNavigator(mockNavigate as unknown as NavigateFunction);

      // Act
      imperativeNavigate('/bar', { replace: true });

      // Assert
      expect(mockNavigate).toHaveBeenCalledWith('/bar', { replace: true });
    });

    it('uses the most recently registered navigator when overwritten', () => {
      // Arrange
      const fn1 = vi.fn();
      const fn2 = vi.fn();
      setNavigator(fn1 as unknown as NavigateFunction);
      setNavigator(fn2 as unknown as NavigateFunction);

      // Act
      imperativeNavigate('/x');

      // Assert
      expect(fn2).toHaveBeenCalledWith('/x', undefined);
      expect(fn1).not.toHaveBeenCalled();
    });
  });

  describe('when navigator is not set (fallback)', () => {
    it('falls back to window.location.href when navigator is null', () => {
      // Arrange — navigator reset to null by beforeEach
      const hrefSetter = vi.fn();
      Object.defineProperty(window, 'location', {
        value: { ...window.location, set href(v: string) { hrefSetter(v); } },
        writable: true,
        configurable: true,
      });

      // Act
      imperativeNavigate('/baz');

      // Assert
      expect(hrefSetter).toHaveBeenCalledWith('/baz');
    });
  });
});
