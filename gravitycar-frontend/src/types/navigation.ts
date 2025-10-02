export interface NavigationItem {
  name: string;
  title: string;
  url: string;
  icon?: string;
  actions?: NavigationAction[];
  permissions?: {
    list: boolean;
    create: boolean;
    update: boolean;
    delete: boolean;
  };
}

export interface NavigationAction {
  key: string;
  title: string;
  url?: string; // Optional for URL-based navigation
  action?: string; // Optional for action-based triggers (e.g., 'create')
  icon?: string;
}

export interface CustomPage {
  key: string;
  title: string;
  url: string;
  icon?: string;
  roles: string[];
}

export interface NavigationSection {
  key: string;
  title: string;
}

export interface NavigationData {
  role: string;
  sections: NavigationSection[];
  custom_pages: CustomPage[];
  models: NavigationItem[];
  generated_at: string;
}

export interface NavigationResponse {
  success: boolean;
  status: number;
  data: NavigationData;
  cache_hit?: boolean;
  timestamp: string;
  count?: number;
}