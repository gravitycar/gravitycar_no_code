import type { ModelMetadata } from '../types';

/**
 * Simple in-memory cache for model metadata
 * Prevents repeated API calls for the same model metadata
 */
class MetadataCache {
  private cache = new Map<string, ModelMetadata>();
  private cacheTimestamps = new Map<string, number>();
  private readonly CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

  /**
   * Get cached metadata for a model
   */
  get(modelName: string): ModelMetadata | null {
    const cached = this.cache.get(modelName);
    const timestamp = this.cacheTimestamps.get(modelName);

    if (!cached || !timestamp) {
      return null;
    }

    // Check if cache is expired
    if (Date.now() - timestamp > this.CACHE_DURATION) {
      this.cache.delete(modelName);
      this.cacheTimestamps.delete(modelName);
      return null;
    }

    return cached;
  }

  /**
   * Set cached metadata for a model
   */
  set(modelName: string, metadata: ModelMetadata): void {
    this.cache.set(modelName, metadata);
    this.cacheTimestamps.set(modelName, Date.now());
  }

  /**
   * Clear cache for a specific model
   */
  clear(modelName?: string): void {
    if (modelName) {
      this.cache.delete(modelName);
      this.cacheTimestamps.delete(modelName);
    } else {
      this.cache.clear();
      this.cacheTimestamps.clear();
    }
  }

  /**
   * Get cache statistics
   */
  getStats() {
    return {
      size: this.cache.size,
      models: Array.from(this.cache.keys()),
      oldestEntry: Math.min(...Array.from(this.cacheTimestamps.values())),
      newestEntry: Math.max(...Array.from(this.cacheTimestamps.values()))
    };
  }
}

// Export singleton instance
export const metadataCache = new MetadataCache();
export default metadataCache;
