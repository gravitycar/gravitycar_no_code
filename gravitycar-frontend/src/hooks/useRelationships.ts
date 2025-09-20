/* eslint-disable @typescript-eslint/no-explicit-any, react-hooks/exhaustive-deps */
import { useState, useEffect, useCallback } from 'react';
import { apiService } from '../services/api';
import type { PaginatedResponse } from '../types';

export interface RelationshipOptions {
  page?: number;
  limit?: number;
  search?: string;
}

export interface UseRelationshipManagerReturn<T> {
  // Data
  items: T[];
  pagination: PaginatedResponse<T>['pagination'] | null;
  loading: boolean;
  error: string | null;
  
  // Actions
  loadItems: (options?: RelationshipOptions) => Promise<void>;
  assignItems: (targetIds: string[], additionalData?: Record<string, any>) => Promise<boolean>;
  removeItems: (targetIds: string[]) => Promise<boolean>;
  refresh: () => Promise<void>;
  
  // Search and pagination
  setSearch: (search: string) => void;
  setPage: (page: number) => void;
  search: string;
  currentPage: number;
}

/**
 * Hook for managing relationship data between two models
 */
export function useRelationshipManager<T = any>(
  parentModel: string,
  parentId: string,
  relationshipName: string,
  initialOptions: RelationshipOptions = {}
): UseRelationshipManagerReturn<T> {
  const [items, setItems] = useState<T[]>([]);
  const [pagination, setPagination] = useState<PaginatedResponse<T>['pagination'] | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState(initialOptions.search || '');
  const [currentPage, setCurrentPage] = useState(initialOptions.page || 1);
  const [limit] = useState(initialOptions.limit || 10);

  const loadItems = useCallback(async (options: RelationshipOptions = {}) => {
    if (!parentId) return;
    
    setLoading(true);
    setError(null);
    
    try {
      const requestOptions = {
        page: options.page ?? currentPage,
        limit: options.limit ?? limit,
        search: options.search ?? search
      };
      
      const response = await apiService.getRelatedRecords<T>(
        parentModel,
        parentId,
        relationshipName,
        requestOptions
      );
      
      if (response.success) {
        setItems(response.data);
        setPagination(response.pagination);
      } else {
        setError(response.message || 'Failed to load related items');
        setItems([]);
        setPagination(null);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load related items');
      setItems([]);
      setPagination(null);
    } finally {
      setLoading(false);
    }
  }, [parentModel, parentId, relationshipName, currentPage, limit, search]);

  const assignItems = useCallback(async (targetIds: string[], additionalData?: Record<string, any>): Promise<boolean> => {
    if (!parentId || targetIds.length === 0) return false;
    
    setLoading(true);
    setError(null);
    
    try {
      const response = await apiService.assignRelationship(
        parentModel,
        parentId,
        relationshipName,
        targetIds,
        additionalData
      );
      
      if (response.success) {
        // Refresh the list to show new assignments
        await loadItems();
        return true;
      } else {
        setError(response.message || 'Failed to assign items');
        return false;
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to assign items');
      return false;
    } finally {
      setLoading(false);
    }
  }, [parentModel, parentId, relationshipName, loadItems]);

  const removeItems = useCallback(async (targetIds: string[]): Promise<boolean> => {
    if (!parentId || targetIds.length === 0) return false;
    
    setLoading(true);
    setError(null);
    
    try {
      const response = await apiService.removeRelationship(
        parentModel,
        parentId,
        relationshipName,
        targetIds
      );
      
      if (response.success) {
        // Refresh the list to show removals
        await loadItems();
        return true;
      } else {
        setError(response.message || 'Failed to remove items');
        return false;
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to remove items');
      return false;
    } finally {
      setLoading(false);
    }
  }, [parentModel, parentId, relationshipName, loadItems]);

  const refresh = useCallback(async () => {
    await loadItems();
  }, [loadItems]);

  const handleSetSearch = useCallback((newSearch: string) => {
    setSearch(newSearch);
    setCurrentPage(1); // Reset to first page on search
  }, []);

  const handleSetPage = useCallback((page: number) => {
    setCurrentPage(page);
  }, []);

  // Load initial data
  useEffect(() => {
    if (parentId) {
      loadItems();
    }
  }, [loadItems, parentId]);

  // Reload when search or page changes
  useEffect(() => {
    if (parentId) {
      loadItems({ page: currentPage, search, limit });
    }
  }, [currentPage, search, parentId, limit, relationshipName, parentModel]);

  return {
    items,
    pagination,
    loading,
    error,
    loadItems,
    assignItems,
    removeItems,
    refresh,
    setSearch: handleSetSearch,
    setPage: handleSetPage,
    search,
    currentPage
  };
}

export interface UseRelationshipHistoryReturn<T> {
  history: T[];
  pagination: PaginatedResponse<T>['pagination'] | null;
  loading: boolean;
  error: string | null;
  loadHistory: (options?: Omit<RelationshipOptions, 'search'>) => Promise<void>;
  refresh: () => Promise<void>;
  setPage: (page: number) => void;
  currentPage: number;
}

/**
 * Hook for accessing relationship history/audit trail
 */
export function useRelationshipHistory<T = any>(
  parentModel: string,
  parentId: string,
  relationshipName: string,
  initialOptions: Omit<RelationshipOptions, 'search'> = {}
): UseRelationshipHistoryReturn<T> {
  const [history, setHistory] = useState<T[]>([]);
  const [pagination, setPagination] = useState<PaginatedResponse<T>['pagination'] | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(initialOptions.page || 1);
  const [limit] = useState(initialOptions.limit || 10);

  const loadHistory = useCallback(async (options: Omit<RelationshipOptions, 'search'> = {}) => {
    if (!parentId) return;
    
    setLoading(true);
    setError(null);
    
    try {
      const requestOptions = {
        page: options.page ?? currentPage,
        limit: options.limit ?? limit
      };
      
      const response = await apiService.getRelationshipHistory<T>(
        parentModel,
        parentId,
        relationshipName,
        requestOptions
      );
      
      if (response.success) {
        setHistory(response.data);
        setPagination(response.pagination);
      } else {
        setError(response.message || 'Failed to load relationship history');
        setHistory([]);
        setPagination(null);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load relationship history');
      setHistory([]);
      setPagination(null);
    } finally {
      setLoading(false);
    }
  }, [parentModel, parentId, relationshipName, currentPage, limit]);

  const refresh = useCallback(async () => {
    await loadHistory();
  }, [loadHistory]);

  const handleSetPage = useCallback((page: number) => {
    setCurrentPage(page);
  }, []);

  // Load initial data
  useEffect(() => {
    if (parentId) {
      loadHistory();
    }
  }, [loadHistory, parentId]);

  // Reload when page changes
  useEffect(() => {
    if (parentId) {
      loadHistory({ page: currentPage, limit });
    }
  }, [currentPage, parentId, limit, relationshipName, parentModel]);

  return {
    history,
    pagination,
    loading,
    error,
    loadHistory,
    refresh,
    setPage: handleSetPage,
    currentPage
  };
}

/**
 * Hook for managing many-to-many relationships with dual-pane interface
 */
export interface UseManyToManyManagerReturn<T> {
  // Assigned items (left pane)
  assignedItems: T[];
  assignedPagination: PaginatedResponse<T>['pagination'] | null;
  assignedLoading: boolean;
  
  // Available items (right pane)
  availableItems: T[];
  availablePagination: PaginatedResponse<T>['pagination'] | null;
  availableLoading: boolean;
  
  // Common state
  error: string | null;
  
  // Actions
  assignItems: (targetIds: string[], additionalData?: Record<string, any>) => Promise<boolean>;
  removeItems: (targetIds: string[]) => Promise<boolean>;
  refresh: () => Promise<void>;
  
  // Search and pagination for available items
  setAvailableSearch: (search: string) => void;
  setAvailablePage: (page: number) => void;
  availableSearch: string;
  availablePage: number;
  
  // Pagination for assigned items
  setAssignedPage: (page: number) => void;
  assignedPage: number;
}

export function useManyToManyManager<T = any>(
  parentModel: string,
  parentId: string,
  relationshipName: string,
  targetModel: string,
  options: { limit?: number } = {}
): UseManyToManyManagerReturn<T> {
  const limit = options.limit || 10;
  
  // Use the relationship hook for assigned items
  const {
    items: assignedItems,
    pagination: assignedPagination,
    loading: assignedLoading,
    error: assignedError,
    assignItems,
    removeItems,
    refresh: refreshAssigned,
    setPage: setAssignedPage,
    currentPage: assignedPage
  } = useRelationshipManager<T>(parentModel, parentId, relationshipName, { limit });
  
  // Separate state for available items
  const [availableItems, setAvailableItems] = useState<T[]>([]);
  const [availablePagination, setAvailablePagination] = useState<PaginatedResponse<T>['pagination'] | null>(null);
  const [availableLoading, setAvailableLoading] = useState(false);
  const [availableError, setAvailableError] = useState<string | null>(null);
  const [availableSearch, setAvailableSearch] = useState('');
  const [availablePage, setAvailablePage] = useState(1);

  const loadAvailableItems = useCallback(async () => {
    setAvailableLoading(true);
    setAvailableError(null);
    
    try {
      const filters = availableSearch ? { search: availableSearch } : undefined;
      const response = await apiService.getList<T>(
        targetModel,
        availablePage,
        limit,
        filters
      );
      
      if (response.success) {
        setAvailableItems(response.data);
        setAvailablePagination(response.pagination);
      } else {
        setAvailableError(response.message || 'Failed to load available items');
        setAvailableItems([]);
        setAvailablePagination(null);
      }
    } catch (err) {
      setAvailableError(err instanceof Error ? err.message : 'Failed to load available items');
      setAvailableItems([]);
      setAvailablePagination(null);
    } finally {
      setAvailableLoading(false);
    }
  }, [targetModel, availablePage, limit, availableSearch]);

  const handleSetAvailableSearch = useCallback((search: string) => {
    setAvailableSearch(search);
    setAvailablePage(1); // Reset to first page on search
  }, []);

  const refresh = useCallback(async () => {
    await Promise.all([
      refreshAssigned(),
      loadAvailableItems()
    ]);
  }, [refreshAssigned, loadAvailableItems]);

  // Load available items when dependencies change
  useEffect(() => {
    loadAvailableItems();
  }, [loadAvailableItems]);

  return {
    assignedItems,
    assignedPagination,
    assignedLoading,
    availableItems,
    availablePagination,
    availableLoading,
    error: assignedError || availableError,
    assignItems,
    removeItems,
    refresh,
    setAvailableSearch: handleSetAvailableSearch,
    setAvailablePage,
    availableSearch,
    availablePage,
    setAssignedPage,
    assignedPage
  };
}
