import { apiService } from './api';
import { NavigationResponse } from '../types/navigation';

class NavigationService {
  private cache: Map<string, NavigationResponse> = new Map();
  private cacheExpiry: Map<string, number> = new Map();
  private readonly CACHE_TTL = 5 * 60 * 1000; // 5 minutes

  /**
   * Get navigation for current user
   */
  async getCurrentUserNavigation(): Promise<NavigationResponse> {
    const cacheKey = 'current_user';
    
    // Check cache first
    if (this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey)!;
    }

    try {
      // Use the apiService's internal axios instance pattern
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const response = await (apiService as any).api.get('/navigation');
      const navigationData = response.data as NavigationResponse;

      // Cache the response
      this.setCache(cacheKey, navigationData);
      
      return navigationData;

    } catch (error) {
      console.error('Failed to fetch navigation:', error);
      throw error;
    }
  }

  /**
   * Get navigation for specific role
   */
  async getNavigationByRole(role: string): Promise<NavigationResponse> {
    const cacheKey = `role_${role}`;
    
    // Check cache first
    if (this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey)!;
    }

    try {
      // Use the apiService's internal axios instance pattern
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const response = await (apiService as any).api.get(`/navigation/${role}`);
      const navigationData = response.data as NavigationResponse;

      // Cache the response
      this.setCache(cacheKey, navigationData);
      
      return navigationData;

    } catch (error) {
      console.error(`Failed to fetch navigation for role ${role}:`, error);
      throw error;
    }
  }

  /**
   * Rebuild navigation cache on the backend
   */
  async rebuildNavigationCache(): Promise<NavigationResponse> {
    try {
      // Use the apiService's internal axios instance pattern
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const response = await (apiService as any).api.post('/navigation/cache/rebuild');
      const result = response.data as NavigationResponse;
      
      // Clear local cache since backend cache was rebuilt
      this.clearCache();
      
      return result;

    } catch (error) {
      console.error('Failed to rebuild navigation cache:', error);
      throw error;
    }
  }

  /**
   * Clear navigation cache
   */
  clearCache(): void {
    this.cache.clear();
    this.cacheExpiry.clear();
  }

  /**
   * Check if cached data is still valid
   */
  private isCacheValid(key: string): boolean {
    const expiry = this.cacheExpiry.get(key);
    return expiry ? Date.now() < expiry : false;
  }

  /**
   * Set cache with TTL
   */
  private setCache(key: string, data: NavigationResponse): void {
    this.cache.set(key, data);
    this.cacheExpiry.set(key, Date.now() + this.CACHE_TTL);
  }
}

export const navigationService = new NavigationService();