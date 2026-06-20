export interface RadioProps {
  modelValue?: string | number | boolean;
  value: string | number | boolean;
  label: string;
  name?: string;
  description?: string;
  disabled?: boolean;
}

export interface RadioGroupProps {
  modelValue: string | number | boolean;
  name: string;
  label?: string;
  description?: string;
  error?: string;
  disabled?: boolean;
  required?: boolean;
  inline?: boolean;
}
