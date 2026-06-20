# Checkbox Component

## Purpose
A customized toggle selector styled with local ivory variables. It supports single-choice checks, group arrays, indeterminate ticks, labels, and description text blocks.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `modelValue` | `boolean \| any[]` | *Required* | Current state (single toggle boolean or collection array) |
| `value` | `any` | `undefined` | Checkbox item value identifier |
| `label` | `string` | *Required* | Display label text label |
| `description` | `string` | `undefined` | Supporting descriptor label |
| `error` | `string` | `undefined` | Error message string |
| `disabled` | `boolean` | `false` | Disables state adjustments |
| `required` | `boolean` | `false` | Displays asterisk indicator |
| `indeterminate` | `boolean` | `false` | Displays a dash icon indicating partial selections |

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `update:modelValue` | `boolean \| any[]` | Emitted when selection state changes |

## Examples

### Single Checkbox
```vue
<Checkbox 
  v-model="agree" 
  label="I agree to terms" 
  description="Please read carefully." 
  required 
/>
```
