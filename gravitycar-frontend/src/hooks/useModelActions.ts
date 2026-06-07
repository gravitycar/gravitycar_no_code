import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { NavigationItem, NavigationAction } from '../types/navigation';

export interface UseModelActionsReturn {
  expandedModel: string | null;
  setExpandedModel: (name: string | null) => void;
  getVisibleActions: (item: NavigationItem) => NavigationAction[];
  handleActionClick: (action: NavigationAction, item: NavigationItem) => void;
}

export function useModelActions(): UseModelActionsReturn {
  const [expandedModel, setExpandedModel] = useState<string | null>(null);
  const location = useLocation();
  const navigate = useNavigate();

  const getVisibleActions = (item: NavigationItem): NavigationAction[] => {
    if (!item.actions) return [];
    return item.actions.filter((action) => {
      if (action.action === 'create') return item.permissions?.create !== false;
      return true;
    });
  };

  const handleActionClick = (action: NavigationAction, item: NavigationItem): void => {
    if (action.action === 'create') {
      const expectedPath = `/${item.name.toLowerCase()}`;
      if (location.pathname === expectedPath) {
        window.dispatchEvent(new CustomEvent('navigation-create', {
          detail: { modelName: item.name }
        }));
      } else {
        navigate(expectedPath + '?action=create');
      }
    } else if (action.url) {
      navigate(action.url);
    }
    setExpandedModel(null);
  };

  return { expandedModel, setExpandedModel, getVisibleActions, handleActionClick };
}
