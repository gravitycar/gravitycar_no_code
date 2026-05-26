import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import type { NavigateFunction, NavigateOptions } from 'react-router-dom';

let navigator: NavigateFunction | null = null;

export function setNavigator(fn: NavigateFunction): void {
  navigator = fn;
}

export function imperativeNavigate(path: string, options?: NavigateOptions): void {
  if (navigator) {
    navigator(path, options);
    return;
  }
  window.location.href = path;
}

export function NavigatorSetter(): null {
  const navigate = useNavigate();

  useEffect(() => {
    setNavigator(navigate);
  }, [navigate]);

  return null;
}
