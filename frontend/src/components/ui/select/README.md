# Select Component

## Purpose
A styled dropdown select box. It supports standard native select rendering for lightweight usage and mobile accessibility, as well as an advanced searchable options dropdown trigger when handling extensive lists.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `modelValue` | `string \| number` | *Required* | Current selected value |
| `options` | `SelectOption[]` | *Required* | List of dropdown selection item options |
| `label` | `string` | *Required* | Uppercase form label text |
| `description` | `string` | `undefined` | Secondary guidance helper text |
| `error` | `string` | `undefined` | Error warning text which colors the border red |
| `placeholder` | `string` | `'Select an option'`| Default option placeholder text |
| `disabled` | `boolean` | `false` | Disables dropdown toggle interaction |
| `required` | `boolean` | `false` | Displays asterisk indicator |
| `searchable` | `boolean` | `false` | Toggles the search input filtration list panel |

### SelectOption Interface
```typescript
interface SelectOption {
  label: string;
  value: string | number;
  disabled?: boolean;
}
```

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `update:modelValue` | `string \| number` | Emitted when value selection changes |
| `change` | `string \| number` | Emitted when value selection changes |

## Examples

### Searchable Select
```vue
<Select 
  v-model="course" 
  :options="courseOptions" 
  label="Choose Course" 
  searchable 
/>
```
