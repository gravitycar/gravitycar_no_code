/* eslint-disable react-refresh/only-export-components */
import { useState, useEffect, createContext, useContext } from 'react';
import type { ReactNode } from 'react';
import type { User, LoginCredentials, AuthResponse } from '../types';
import { apiService } from '../services/api';

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentials: LoginCredentials) => Promise<AuthResponse>;
  loginWithGoogle: (googleToken: string) => Promise<AuthResponse>;
  logout: () => Promise<void>;
  checkAuth: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider = ({ children }: AuthProviderProps) => {
  const [user, setUser] = useState<User | null>(() => {
    // Initialize user from localStorage if available (for immediate display)
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      try {
        return JSON.parse(storedUser) as User;
      } catch {
        return null;
      }
    }
    return null;
  });
  const [isLoading, setIsLoading] = useState(true);

  const checkAuth = async () => {
    try {
      const token = localStorage.getItem('auth_token');
      if (!token) {
        setUser(null);
        setIsLoading(false);
        return;
      }

      // Verify token is still valid and get fresh user data from backend
      const currentUser = await apiService.getCurrentUser();
      if (currentUser) {
        setUser(currentUser);
        // Update localStorage with fresh user data
        localStorage.setItem('user', JSON.stringify(currentUser));
      } else {
        // Token is invalid, clear it
        setUser(null);
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
      }
    } catch (error) {
      console.error('Auth check failed:', error);
      setUser(null);
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
    } finally {
      setIsLoading(false);
    }
  };

  const login = async (credentials: LoginCredentials): Promise<AuthResponse> => {
    setIsLoading(true);
    try {
      const response = await apiService.login(credentials);
      if (response.success && response.user) {
        setUser(response.user);
        // Ensure user is stored in localStorage (apiService.login already does this, but be explicit)
        localStorage.setItem('user', JSON.stringify(response.user));
      }
      return response;
    } finally {
      setIsLoading(false);
    }
  };

  const loginWithGoogle = async (googleToken: string): Promise<AuthResponse> => {
    setIsLoading(true);
    try {
      const response = await apiService.loginWithGoogle(googleToken);
      if (response.success && response.user) {
        setUser(response.user);
        // Ensure user is stored in localStorage (apiService.loginWithGoogle already does this, but be explicit)
        localStorage.setItem('user', JSON.stringify(response.user));
      }
      return response;
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    setIsLoading(true);
    try {
      await apiService.logout();
    } finally {
      setUser(null);
      setIsLoading(false);
    }
  };

  useEffect(() => {
    checkAuth();
  }, []);

  const value: AuthContextType = {
    user,
    isAuthenticated: !!user,
    isLoading,
    login,
    loginWithGoogle,
    logout,
    checkAuth
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export default useAuth;
