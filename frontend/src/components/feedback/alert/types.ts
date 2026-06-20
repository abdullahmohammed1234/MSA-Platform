export interface AlertProps {
  type?: 'success' | 'error' | 'warning' | 'info';
  title?: string;
  closable?: boolean;
}
