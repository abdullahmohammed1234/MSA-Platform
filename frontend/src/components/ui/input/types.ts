export interface InputProps {
  modelValue: string | number;
  label?: string;
  description?: string;
  error?: string;
  type?: 'text' | 'email' | 'password' | 'search' | 'number';
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
}
