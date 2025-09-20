/* eslint-disable @typescript-eslint/no-explicit-any, react-hooks/exhaustive-deps */
import { useState, useEffect } from 'react';
import type { ModelMetadata } from '../types';
import { metadataCache } from '../services/metadataCache';

interface UseModelMetadataReturn {
  metadata: ModelMetadata | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

/**
 * Custom hook to fetch and cache model metadata from the Gravitycar backend
 * 
 * @param modelName - The name of the model to fetch metadata for
 * @returns Object containing metadata, loading state, error state, and refetch function
 */
export const useModelMetadata = (modelName: string): UseModelMetadataReturn => {
  const [metadata, setMetadata] = useState<ModelMetadata | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchMetadata = async () => {
    try {
      setLoading(true);
      setError(null);

      // Check cache first
      const cached = metadataCache.get(modelName);
      if (cached) {
        console.log(`📋 Using cached metadata for ${modelName}`);
        setMetadata(cached);
        setLoading(false);
        return;
      }

      console.log(`🔍 Fetching metadata for ${modelName} from API`);

      // Use environment variable or fallback to localhost for development
      const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8081';

      // Fetch from API using a direct call to the axios instance
      // We'll add a method to apiService for metadata fetching
      const response = await fetch(`${apiBaseUrl}/metadata/models/${modelName}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('auth_token') || ''}`
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      
      if (data.success && data.data) {
        const modelData = data.data as ModelMetadata;
        
        // Cache the result
        metadataCache.set(modelName, modelData);
        setMetadata(modelData);
        
        console.log(`✅ Successfully fetched and cached metadata for ${modelName}`, modelData);
      } else {
        throw new Error(data.message || `Failed to fetch metadata for ${modelName}`);
      }
    } catch (err: any) {
      console.error(`❌ Failed to fetch metadata for ${modelName}:`, err);
      setError(err.response?.data?.message || err.message || `Failed to fetch metadata for ${modelName}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (modelName) {
      fetchMetadata();
    }
  }, [modelName]);

  return {
    metadata,
    loading,
    error,
    refetch: fetchMetadata
  };
};

export default useModelMetadata;
