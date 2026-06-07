import React, { useState, useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';
import { NavModelGroup, NavModelItem, NavigationItem, NavigationAction } from '../../types/navigation';
import { useModelActions } from '../../hooks/useModelActions';

interface NavGroupSectionProps {
  group: NavModelGroup;
  location: ReturnType<typeof useLocation>;
  defaultOpen?: boolean;
}

const NavGroupSection: React.FC<NavGroupSectionProps> = ({ group, location, defaultOpen }) => {
  const [isOpen, setIsOpen] = useState<boolean>(defaultOpen ?? false);
  const toggleButtonRef = useRef<HTMLButtonElement>(null);

  const { expandedModel, setExpandedModel, getVisibleActions, handleActionClick } =
    useModelActions();

  const slug = group.label.toLowerCase().replace(/[^a-z0-9]+/g, '-');
  const contentId = `nav-group-${slug}-${slug}`;

  useEffect(() => {
    const isActive = group.items.some((item) => item.url === location.pathname);
    if (isActive) {
      setIsOpen(true);
    }
    // Intentionally no else branch — never force-close on route change
  }, [location.pathname, group.items]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      setIsOpen(false);
      setExpandedModel(null);
      toggleButtonRef.current?.focus();
    }
  };

  const renderSubItem = (subItem: NavModelItem) => {
    const item = subItem as unknown as NavigationItem;
    const visibleActions = getVisibleActions(item);
    const isActive = subItem.url === location.pathname;

    return (
      <li key={subItem.name}>
        <div>
          <div className="flex items-center justify-between px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors">
            <a
              href={subItem.url}
              aria-current={isActive ? 'page' : undefined}
              className="flex items-center flex-1"
            >
              <span className="mr-2">{subItem.icon}</span>
              {subItem.title}
            </a>
            {visibleActions.length > 0 && (
              <button
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  setExpandedModel(expandedModel === subItem.name ? null : subItem.name);
                }}
                className="ml-2 p-1 hover:bg-gray-200 rounded"
                aria-label={`Toggle actions for ${subItem.title}`}
              >
                <svg
                  className={`w-4 h-4 transition-transform ${
                    expandedModel === subItem.name ? 'rotate-180' : ''
                  }`}
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path
                    fillRule="evenodd"
                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                    clipRule="evenodd"
                  />
                </svg>
              </button>
            )}
          </div>

          {expandedModel === subItem.name && visibleActions.length > 0 && (
            <ul className="mt-1 ml-6 space-y-1">
              {visibleActions.map((action: NavigationAction) => (
                <li key={action.key}>
                  {action.action ? (
                    <button
                      onClick={() => handleActionClick(action, item)}
                      className="flex items-center px-3 py-1 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors w-full text-left"
                    >
                      <span className="mr-2">{action.icon}</span>
                      {action.title}
                    </button>
                  ) : (
                    <a
                      href={action.url}
                      className="flex items-center px-3 py-1 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                    >
                      <span className="mr-2">{action.icon}</span>
                      {action.title}
                    </a>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      </li>
    );
  };

  return (
    <li onKeyDown={handleKeyDown}>
      <button
        ref={toggleButtonRef}
        aria-expanded={isOpen}
        aria-controls={contentId}
        onClick={() => setIsOpen((prev) => !prev)}
        className="flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
      >
        <span>{group.label}</span>
        <svg
          className={`w-4 h-4 transition-transform duration-200 ${isOpen ? 'rotate-90' : ''}`}
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fillRule="evenodd"
            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
            clipRule="evenodd"
          />
        </svg>
      </button>

      <ul
        id={contentId}
        role="list"
        className={`overflow-hidden transition-all duration-300 ${
          isOpen ? 'max-h-64' : 'max-h-0'
        }`}
      >
        {group.items.map(renderSubItem)}
      </ul>
    </li>
  );
};

export default NavGroupSection;
