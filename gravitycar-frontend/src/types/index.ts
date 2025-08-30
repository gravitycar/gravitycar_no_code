// User model types based on Gravitycar backend
export interface User {
  id: string; // UUID string
  username?: string;
  email: string;
  first_name?: string;
  last_name?: string;
  google_id?: string;
  auth_provider: 'local' | 'google' | 'hybrid' | ''; // Can be empty string
  last_login_method?: 'local' | 'google' | null;
  email_verified_at?: string | null;
  profile_picture_url?: string | null;
  last_google_sync?: string | null;
  is_active: boolean | string | ''; // Backend returns "1", "", or actual boolean
  last_login?: string | null;
  user_type: 'admin' | 'manager' | 'user';
  user_timezone: string;
  created_at?: string;
  updated_at?: string;
}

// Movie model types
export interface Movie {
  id: string; // UUID string
  name: string; // Movie title/name
  poster?: string; // Image path/filename
  poster_url?: string; // Full URL to poster image
  synopsis?: string; // Movie synopsis/description
  created_at?: string;
  updated_at?: string;
  created_by?: string;
  created_by_name?: string;
  updated_by?: string;
  updated_by_name?: string;
  deleted_at?: string;
  deleted_by?: string;
  deleted_by_name?: string;
}

// Movie Quote types
export interface MovieQuote {
  id: string; // UUID string
  movie_id: string; // UUID string
  quote: string; // The actual quote text
  movie?: string; // Related movie name/title
  movie_poster?: string; // Related movie poster
  created_at?: string;
  updated_at?: string;
  created_by?: string;
  created_by_name?: string;
  updated_by?: string;
  updated_by_name?: string;
  deleted_at?: string;
  deleted_by?: string;
  deleted_by_name?: string;
}

// Role and Permission types for RBAC
export interface Role {
  id: string; // UUID string
  name: string;
  description?: string;
  created_at?: string;
  updated_at?: string;
}

export interface Permission {
  id: string; // UUID string
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
  ui?: UIMetadata; // Optional - some models may not have UI metadata
}

export interface UIMetadata {
  listFields: string[]; // Fields to display in list/table view, in order
  createFields: string[]; // Fields to show in create/edit forms, in order
  editFields?: string[]; // Fields to show in edit forms (optional, defaults to createFields)
  // NEW: Relationship field configurations
  relationshipFields?: Record<string, RelationshipFieldMetadata>;
  // NEW: Related items sections for detail/edit views
  relatedItemsSections?: Record<string, RelatedItemsSectionMetadata>;
}

// NEW: Relationship field metadata for form rendering
export interface RelationshipFieldMetadata {
  type: 'RelationshipSelector';
  relationship: string;
  mode: 'parent_selection' | 'children_management';
  required: boolean;
  label: string;
  relatedModel: string;
  displayField: string;
  allowCreate?: boolean;
  searchable?: boolean;
}

// NEW: Related items section metadata for detail views
export interface RelatedItemsSectionMetadata {
  title: string;
  relationship: string;
  mode: 'children_management';
  relatedModel: string;
  displayColumns: string[];
  actions: string[]; // ['create', 'edit', 'delete']
  allowInlineCreate?: boolean;
  allowInlineEdit?: boolean;
  createFields: string[];
  editFields: string[];
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
