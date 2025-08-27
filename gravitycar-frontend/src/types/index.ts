// User model types based on Gravitycar backend
export interface User {
  id: number;
  username: string;
  email: string;
  created_at?: string;
  updated_at?: string;
}

// Movie model types
export interface Movie {
  id: number;
  title: string;
  release_year?: number;
  director?: string;
  created_at?: string;
  updated_at?: string;
}

// Movie Quote types
export interface MovieQuote {
  id: number;
  movie_id: number;
  quote_text: string;
  character_name?: string;
  created_at?: string;
  updated_at?: string;
  movie?: Movie; // Optional populated relationship
}

// Role and Permission types for RBAC
export interface Role {
  id: number;
  name: string;
  description?: string;
  created_at?: string;
  updated_at?: string;
}

export interface Permission {
  id: number;
  name: string;
  description?: string;
  created_at?: string;
  updated_at?: string;
}

// Authentication types
export interface LoginCredentials {
  username: string;
  password: string;
}

export interface AuthResponse {
  success: boolean;
  token?: string;
  user?: User;
  message?: string;
}

// API Response types
export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: string[];
}

export interface PaginatedResponse<T = any> {
  success: boolean;
  data: T[];
  pagination: {
    current_page: number;
    total_pages: number;
    total_items: number;
    per_page: number;
  };
  message?: string;
}

// Form and UI types
export interface FormField {
  name: string;
  label: string;
  type: 'text' | 'email' | 'password' | 'number' | 'textarea' | 'select';
  required?: boolean;
  options?: { value: string | number; label: string }[];
}

// Navigation types
export interface NavItem {
  path: string;
  label: string;
  icon?: string;
  requiresAuth?: boolean;
}
