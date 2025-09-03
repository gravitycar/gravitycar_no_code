import axios from 'axios';
import type { AxiosInstance, AxiosResponse } from 'axios';
import type { 
  ApiResponse, 
  PaginatedResponse, 
  AuthResponse, 
  LoginCredentials,
  User,
  Movie,
  MovieQuote
} from '../types';
import { ApiError, isBackendErrorResponse } from '../utils/errors';

class ApiService {
  private api: AxiosInstance;
  private baseURL: string;

  constructor() {
    // This will be configurable - for now pointing to your local Gravitycar backend on port 8081
    this.baseURL = 'http://localhost:8081';
    
    this.api = axios.create({
      baseURL: this.baseURL,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    // Add request interceptor to include JWT token
    this.api.interceptors.request.use((config) => {
      const token = localStorage.getItem('auth_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    });

    // Add response interceptor for comprehensive error handling
    this.api.interceptors.response.use(
      (response) => response,
      (error) => {
        // Handle network errors (no response)
        if (!error.response) {
          console.error('Network error:', error.message);
          const networkError = new Error('Network error. Please check your connection and try again.');
          return Promise.reject(networkError);
        }

        // Handle backend error responses
        if (error.response.data && isBackendErrorResponse(error.response.data)) {
          const backendError = new ApiError(error.response.data);
          
          // Handle authentication errors
          if (backendError.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            // Redirect to login page
            window.location.href = '/login';
            return Promise.reject(backendError);
          }

          console.error('Backend error:', backendError.getDebugInfo());
          return Promise.reject(backendError);
        }

        // Handle non-backend HTTP errors (fallback)
        const status = error.response.status;
        let message = `HTTP ${status} error`;
        
        switch (status) {
          case 400:
            message = 'Bad request. Please check your input.';
            break;
          case 401:
            message = 'Authentication required. Please log in.';
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            window.location.href = '/login';
            break;
          case 403:
            message = 'Access denied. You don\'t have permission for this action.';
            break;
          case 404:
            message = 'Resource not found.';
            break;
          case 500:
            message = 'Server error. Please try again later.';
            break;
        }

        console.error('HTTP error:', { status, message, error });
        return Promise.reject(new Error(message));
      }
    );
  }

  // Authentication methods
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      // The backend returns a complex nested structure
      const response = await this.api.post('/auth/login', credentials);
      const backendData = response.data;
      
      // The backend returns: { success: true, data: { user: {...}, access_token: "...", ... } }
      if (backendData.success && backendData.data?.access_token) {
        localStorage.setItem('auth_token', backendData.data.access_token);
        if (backendData.data.user) {
          localStorage.setItem('user', JSON.stringify(backendData.data.user));
        }
        
        // Transform to match expected AuthResponse format
        return {
          success: true,
          token: backendData.data.access_token,
          user: backendData.data.user,
          message: 'Login successful'
        };
      }
      
      return {
        success: false,
        message: backendData.message || backendData.error?.message || 'Login failed'
      };
    } catch (error: any) {
      return {
        success: false,
        message: error.response?.data?.error?.message || error.response?.data?.message || 'Login failed'
      };
    }
  }

  async logout(): Promise<void> {
    try {
      await this.api.post('/auth/logout');
    } catch (error) {
      // Continue with logout even if API call fails
    } finally {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
    }
  }

  async loginWithGoogle(googleToken: string): Promise<AuthResponse> {
    try {
      // Use the generic ApiResponse type since the backend returns a different structure
      const response: AxiosResponse<any> = await this.api.post('/auth/google', {
        google_token: googleToken
      });
      
      console.log('🔍 Full Google login response:', response.data);
      
      if (response.data.success && response.data.data) {
        const authData = response.data.data;
        
        console.log('✅ Auth data received:', authData);
        
        // Store the access token
        if (authData.access_token) {
          localStorage.setItem('auth_token', authData.access_token);
          console.log('✅ Access token stored');
        }
        
        // Store the refresh token if provided
        if (authData.refresh_token) {
          localStorage.setItem('refresh_token', authData.refresh_token);
          console.log('✅ Refresh token stored');
        }
        
        // Store user data
        if (authData.user) {
          localStorage.setItem('user', JSON.stringify(authData.user));
          console.log('✅ User data stored:', authData.user);
        }
        
        return {
          success: true,
          user: authData.user,
          token: authData.access_token,
          message: 'Google login successful'
        };
      }
      
      console.error('❌ Google login failed - invalid response structure:', response.data);
      return {
        success: false,
        message: response.data.error?.message || 'Google login failed'
      };
    } catch (error: any) {
      console.error('❌ Google login API error:', error);
      return {
        success: false,
        message: error.response?.data?.error?.message || error.response?.data?.message || 'Google login failed'
      };
    }
  }

  async getGoogleAuthUrl(): Promise<{ authorization_url: string; state: string } | null> {
    try {
      const response: AxiosResponse<ApiResponse<{ authorization_url: string; state: string }>> = 
        await this.api.get('/auth/google/url');
      return response.data.success ? response.data.data || null : null;
    } catch (error) {
      console.error('Failed to get Google auth URL:', error);
      return null;
    }
  }

  async getCurrentUser(): Promise<User | null> {
    try {
      const response: AxiosResponse<User> = await this.api.get('/auth/me');
      console.log('✅ getCurrentUser response:', response.data);
      
      // The /auth/me endpoint returns user data directly, not wrapped in ApiResponse
      return response.data;
    } catch (error) {
      console.error('❌ getCurrentUser failed:', error);
      return null;
    }
  }

  // Generic CRUD methods for Gravitycar models
  async getList<T>(model: string, page: number = 1, limit: number = 10, filters?: Record<string, any>): Promise<PaginatedResponse<T>> {
    try {
      const params = new URLSearchParams({
        page: page.toString(),
        limit: limit.toString(),
        ...filters
      });
      
      const response: AxiosResponse<any> = await this.api.get(`/${model}?${params}`);
      const responseData = response.data;
      
      // Check if response has pagination structure
      if (responseData.pagination) {
        // Already in paginated format
        return responseData as PaginatedResponse<T>;
      } else {
        // Convert simple array response to paginated format
        const data = responseData.data || [];
        return {
          success: responseData.success ?? true,
          data: data,
          pagination: {
            current_page: 1,
            total_pages: 1,
            total_items: data.length,
            per_page: data.length
          },
          message: responseData.message
        };
      }
    } catch (error: any) {
      return {
        success: false,
        data: [],
        pagination: {
          current_page: 1,
          total_pages: 0,
          total_items: 0,
          per_page: limit
        },
        message: error.response?.data?.message || 'Failed to fetch data'
      };
    }
  }

  async getById<T>(model: string, id: string): Promise<ApiResponse<T>> {
    const response: AxiosResponse<ApiResponse<T>> = await this.api.get(`/${model}/${id}`);
    return response.data;
  }

  async create<T>(model: string, data: Partial<T>): Promise<ApiResponse<T>> {
    const response: AxiosResponse<ApiResponse<T>> = await this.api.post(`/${model}`, data);
    return response.data;
  }

  async update<T>(model: string, id: string, data: Partial<T>): Promise<ApiResponse<T>> {
    const response: AxiosResponse<ApiResponse<T>> = await this.api.put(`/${model}/${id}`, data);
    return response.data;
  }

  async delete(model: string, id: string): Promise<ApiResponse> {
    const response: AxiosResponse<ApiResponse> = await this.api.delete(`/${model}/${id}`);
    return response.data;
  }

  // Model-specific convenience methods
  async getUsers(page?: number, limit?: number) {
    return this.getList<User>('users', page, limit);
  }

  async getMovies(page?: number, limit?: number) {
    return this.getList<Movie>('movies', page, limit);
  }

  async getMovieQuotes(page?: number, limit?: number) {
    return this.getList<MovieQuote>('movie_quotes', page, limit);
  }

  async getUserById(id: string) {
    return this.getById<User>('users', id);
  }

  async getMovieById(id: string) {
    return this.getById<Movie>('movies', id);
  }

  async getMovieQuoteById(id: string) {
    return this.getById<MovieQuote>('movie_quotes', id);
  }

  // Health check
  async healthCheck(): Promise<boolean> {
    try {
      const response = await this.api.get('/health');
      return response.status === 200;
    } catch (error) {
      return false;
    }
  }

  // Relationship management methods
  async getRelatedRecords<T>(
    model: string, 
    id: string, 
    relationship: string,
    options?: { page?: number; limit?: number; search?: string }
  ): Promise<PaginatedResponse<T>> {
    try {
      const params = new URLSearchParams();
      if (options?.page) params.append('page', options.page.toString());
      if (options?.limit) params.append('limit', options.limit.toString());
      if (options?.search) params.append('search', options.search);
      
      const response: AxiosResponse<any> = await this.api.get(
        `/${model}/${id}/relationships/${relationship}?${params}`
      );
      
      const responseData = response.data;
      
      // Convert to paginated format if needed
      if (responseData.pagination) {
        return responseData as PaginatedResponse<T>;
      } else {
        const data = responseData.data || responseData || [];
        return {
          success: responseData.success ?? true,
          data: Array.isArray(data) ? data : [data],
          pagination: {
            current_page: 1,
            total_pages: 1,
            total_items: Array.isArray(data) ? data.length : 1,
            per_page: Array.isArray(data) ? data.length : 1
          },
          message: responseData.message
        };
      }
    } catch (error) {
      console.error(`Failed to get related ${relationship} for ${model}:`, error);
      return {
        success: false,
        data: [],
        pagination: {
          current_page: 1,
          total_pages: 1,
          total_items: 0,
          per_page: 10
        },
        message: `Failed to get related ${relationship}`
      };
    }
  }
  
  async assignRelationship(
    model: string,
    id: string,
    relationship: string,
    targetIds: string[],
    additionalData?: Record<string, any>
  ): Promise<ApiResponse<any>> {
    try {
      const response: AxiosResponse<ApiResponse<any>> = await this.api.post(
        `/${model}/${id}/relationships/${relationship}/assign`,
        { target_ids: targetIds, additional_data: additionalData }
      );
      return response.data;
    } catch (error) {
      console.error(`Failed to assign ${relationship} relationship for ${model}:`, error);
      return {
        success: false,
        data: null,
        message: `Failed to assign ${relationship} relationship`
      };
    }
  }
  
  async removeRelationship(
    model: string,
    id: string,
    relationship: string,
    targetIds: string[]
  ): Promise<ApiResponse<any>> {
    try {
      const response: AxiosResponse<ApiResponse<any>> = await this.api.post(
        `/${model}/${id}/relationships/${relationship}/remove`,
        { target_ids: targetIds }
      );
      return response.data;
    } catch (error) {
      console.error(`Failed to remove ${relationship} relationship for ${model}:`, error);
      return {
        success: false,
        data: null,
        message: `Failed to remove ${relationship} relationship`
      };
    }
  }
  
  async getRelationshipHistory<T>(
    model: string,
    id: string,
    relationship: string,
    options?: { page?: number; limit?: number }
  ): Promise<PaginatedResponse<T>> {
    try {
      const params = new URLSearchParams();
      if (options?.page) params.append('page', options.page.toString());
      if (options?.limit) params.append('limit', options.limit.toString());
      
      const response: AxiosResponse<any> = await this.api.get(
        `/${model}/${id}/relationships/${relationship}/history?${params}`
      );
      
      const responseData = response.data;
      
      // Convert to paginated format if needed
      if (responseData.pagination) {
        return responseData as PaginatedResponse<T>;
      } else {
        const data = responseData.data || [];
        return {
          success: responseData.success ?? true,
          data: data,
          pagination: {
            current_page: 1,
            total_pages: 1,
            total_items: data.length,
            per_page: data.length
          },
          message: responseData.message
        };
      }
    } catch (error) {
      console.error(`Failed to get ${relationship} history for ${model}:`, error);
      return {
        success: false,
        data: [],
        pagination: {
          current_page: 1,
          total_pages: 1,
          total_items: 0,
          per_page: 10
        },
        message: `Failed to get ${relationship} history`
      };
    }
  }
  
  // TMDB integration methods
  async searchTMDB(title: string): Promise<ApiResponse<any>> {
    try {
      const response: AxiosResponse<ApiResponse<any>> = await this.api.get(
        `/movies/tmdb/search?title=${encodeURIComponent(title)}`
      );
      return response.data;
    } catch (error) {
      console.error('TMDB search failed:', error);
      return {
        success: false,
        data: null,
        message: 'Failed to search TMDB'
      };
    }
  }
  
  async enrichMovieWithTMDB(tmdbId: string): Promise<ApiResponse<any>> {
    try {
      const response: AxiosResponse<ApiResponse<any>> = await this.api.get(
        `/movies/tmdb/enrich/${tmdbId}`
      );
      return response.data;
    } catch (error) {
      console.error('TMDB enrichment failed:', error);
      return {
        success: false,
        data: null,
        message: 'Failed to enrich movie with TMDB data'
      };
    }
  }
}

// Export singleton instance
export const apiService = new ApiService();
export default apiService;
