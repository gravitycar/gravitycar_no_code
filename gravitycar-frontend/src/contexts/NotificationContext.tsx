/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext, useState, useCallback, type ReactNode } from 'react';

interface Notification {
  id: string;
  message: string;
  type: 'success' | 'error' | 'warning' | 'info';
  timestamp: Date;
  duration?: number;
  action?: {
    label: string;
    onClick: () => void;
  };
}

interface NotificationContextType {
  notifications: Notification[];
  showNotification: (message: string, type: Notification['type'], options?: {
    duration?: number;
    action?: Notification['action'];
  }) => void;
  removeNotification: (id: string) => void;
  clearAllNotifications: () => void;
}

const NotificationContext = createContext<NotificationContextType | undefined>(undefined);

export function useNotifications() {
  const context = useContext(NotificationContext);
  if (!context) {
    throw new Error('useNotifications must be used within a NotificationProvider');
  }
  return context;
}

interface NotificationProviderProps {
  children: ReactNode;
}

export function NotificationProvider({ children }: NotificationProviderProps) {
  const [notifications, setNotifications] = useState<Notification[]>([]);

  const showNotification = useCallback((
    message: string, 
    type: Notification['type'], 
    options?: {
      duration?: number;
      action?: Notification['action'];
    }
  ) => {
    const id = Date.now().toString() + Math.random().toString(36).substr(2, 9);
    const notification: Notification = {
      id,
      message,
      type,
      timestamp: new Date(),
      duration: options?.duration ?? 5000,
      action: options?.action,
    };
    
    setNotifications(prev => [...prev, notification]);
    
    // Auto-remove after duration (unless duration is 0 for persistent notifications)
    if (notification.duration && notification.duration > 0) {
      setTimeout(() => {
        setNotifications(prev => prev.filter(n => n.id !== id));
      }, notification.duration);
    }
  }, []);

  const removeNotification = useCallback((id: string) => {
    setNotifications(prev => prev.filter(n => n.id !== id));
  }, []);

  const clearAllNotifications = useCallback(() => {
    setNotifications([]);
  }, []);

  return (
    <NotificationContext.Provider value={{
      notifications,
      showNotification,
      removeNotification,
      clearAllNotifications,
    }}>
      {children}
      <NotificationContainer 
        notifications={notifications} 
        onRemove={removeNotification}
      />
    </NotificationContext.Provider>
  );
}

interface NotificationContainerProps {
  notifications: Notification[];
  onRemove: (id: string) => void;
}

function NotificationContainer({ notifications, onRemove }: NotificationContainerProps) {
  if (notifications.length === 0) return null;

  // Check if there are any error notifications
  const hasErrors = notifications.some(n => n.type === 'error');

  // Center error notifications, otherwise use top-right
  const containerClasses = hasErrors
    ? "fixed inset-0 z-50 flex items-center justify-center pointer-events-none"
    : "fixed top-4 right-4 z-50 space-y-2 max-w-sm";

  return (
    <div className={containerClasses}>
      <div className={hasErrors ? "space-y-2 max-w-md pointer-events-auto" : "space-y-2"}>
        {notifications.map(notification => (
          <NotificationToast
            key={notification.id}
            notification={notification}
            onRemove={onRemove}
          />
        ))}
      </div>
    </div>
  );
}

interface NotificationToastProps {
  notification: Notification;
  onRemove: (id: string) => void;
}

function NotificationToast({ notification, onRemove }: NotificationToastProps) {
  const getTypeStyles = () => {
    switch (notification.type) {
      case 'success':
        return 'bg-green-50 border-green-200 text-green-800';
      case 'error':
        return 'bg-red-50 border-red-200 text-red-800';
      case 'warning':
        return 'bg-yellow-50 border-yellow-200 text-yellow-800';
      case 'info':
        return 'bg-blue-50 border-blue-200 text-blue-800';
      default:
        return 'bg-gray-50 border-gray-200 text-gray-800';
    }
  };

  const getIcon = () => {
    switch (notification.type) {
      case 'success':
        return '✅';
      case 'error':
        return '❌';
      case 'warning':
        return '⚠️';
      case 'info':
        return 'ℹ️';
      default:
        return '📢';
    }
  };

  return (
    <div className={`border rounded-lg shadow-lg p-4 ${getTypeStyles()} animate-in slide-in-from-right duration-300`}>
      <div className="flex items-start space-x-3">
        <span className="text-lg flex-shrink-0">{getIcon()}</span>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium">{notification.message}</p>
          {notification.action && (
            <button
              onClick={notification.action.onClick}
              className="mt-2 text-sm underline hover:no-underline"
            >
              {notification.action.label}
            </button>
          )}
        </div>
        <button
          onClick={() => onRemove(notification.id)}
          className="flex-shrink-0 text-lg hover:opacity-70"
        >
          ×
        </button>
      </div>
    </div>
  );
}

// Convenience hook for showing specific notification types
export function useNotify() {
  const { showNotification } = useNotifications();

  return {
    success: (message: string, options?: Parameters<typeof showNotification>[2]) => 
      showNotification(message, 'success', options),
    error: (message: string, options?: Parameters<typeof showNotification>[2]) => 
      showNotification(message, 'error', options),
    warning: (message: string, options?: Parameters<typeof showNotification>[2]) => 
      showNotification(message, 'warning', options),
    info: (message: string, options?: Parameters<typeof showNotification>[2]) => 
      showNotification(message, 'info', options),
  };
}
