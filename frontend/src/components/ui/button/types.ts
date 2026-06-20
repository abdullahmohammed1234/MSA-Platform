export interface ButtonProps {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'destructive' | 'gold' | 'success' | 'link';
  size?: 'sm' | 'md' | 'lg' | 'icon';
  isLoading?: boolean;
  disabled?: boolean;
  isFullWidth?: boolean;
  isShiny?: boolean;
  type?: 'button' | 'submit' | 'reset';
}
