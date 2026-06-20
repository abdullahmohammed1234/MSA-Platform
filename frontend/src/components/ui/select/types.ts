export interface SelectOption {
  label: string;
  value: string | number;
  disabled?: boolean;
}

export interface SelectProps {
  modelValue: string | number;
  options: SelectOption[];
  label: string;
  description?: string;
  error?: string;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  searchable?: boolean;
}
