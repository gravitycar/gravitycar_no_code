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

// Metadata-Driven Architecture Types
export interface ModelMetadata {
  name: string;
  table: string;
  description: string;
  fields: Record<string, FieldMetadata>;
  relationships: RelationshipMetadata[];
  react_form_schema: FormSchema;
  api_endpoints: ApiEndpoint[];
}

export interface FieldMetadata {
  name: string;
  type: string; // FieldBase subclass name (TextField, EmailField, etc.)
  react_component: string; // React component name (TextInput, EmailInput, etc.)
  label: string;
  required: boolean;
  validation_rules?: ValidationRule[];
  validationRules?: string[]; // Backend format
  default_value?: any;
  defaultValue?: any; // Backend format
  placeholder?: string;
  help_text?: string;
  max_length?: number;
  min_length?: number;
  readOnly?: boolean;
  unique?: boolean;
  // Field-specific properties
  options?: Record<string, string> | EnumOption[]; // Backend uses objects, frontend uses arrays
  related_model?: string; // For RelatedRecordField
  display_field?: string; // For RelatedRecordField
  searchable?: boolean;
  sortable?: boolean;
  filterable?: boolean;
  // Component props from backend
  component_props?: any;
  react_validation?: any;
}

export interface ValidationRule {
  type: string;
  message: string;
  parameters?: Record<string, any>;
}

export interface EnumOption {
  value: string | number;
  label: string;
  description?: string;
}

export interface RelationshipMetadata {
  name: string;
  type: 'hasOne' | 'hasMany' | 'belongsTo' | 'belongsToMany';
  related_model: string;
  foreign_key?: string;
  local_key?: string;
  pivot_table?: string;
}

export interface FormSchema {
  model: string;
  layout: 'vertical' | 'horizontal' | 'grid';
  sections?: FormSection[];
  fields: Record<string, FormFieldSchema>;
}

export interface FormSection {
  title: string;
  description?: string;
  fields: string[];
  collapsible?: boolean;
}

export interface FormFieldSchema {
  component: string;
  props: Record<string, any>;
  validation: ValidationRule[];
  label: string;
  required: boolean;
  section?: string;
  order?: number;
}

export interface ApiEndpoint {
  method: string;
  path: string;
  description: string;
  parameters?: string[];
}

// Field Component Props Interface
export interface FieldComponentProps {
  value: any;
  onChange: (value: any) => void;
  error?: string;
  disabled?: boolean;
  readOnly?: boolean;
  required?: boolean;
  fieldMetadata: FieldMetadata;
  placeholder?: string;
  label?: string;
}

// Specialized component props
export interface RelatedRecordSelectProps extends FieldComponentProps {
  relatedModel: string;
  displayField: string;
  searchable?: boolean;
  createNew?: boolean;
}
