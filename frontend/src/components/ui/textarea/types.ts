export interface TextareaProps {
  modelValue: string;
  label: string;
  description?: string;
  error?: string;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  rows?: number;
  autoResize?: boolean;
}
