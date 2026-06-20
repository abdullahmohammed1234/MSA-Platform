# Switch Component

## Purpose
An iOS-style spring toggle switch. It renders a smooth transition between active and inactive states using Motion for Vue.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `modelValue` | `boolean` | *Required* | Current state (true = On, false = Off) |
| `label` | `string` | `undefined` | Display label next to the toggle |
| `description` | `string` | `undefined` | Secondary guidance helper text |
| `disabled` | `boolean` | `false` | Disables toggle interactions |

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `update:modelValue` | `boolean` | Emitted when toggle state changes |

## Examples

### Simple Toggle Switch
```vue
<Switch 
  v-model="notifications" 
  label="Enable Email notifications" 
  description="Receive alerts for course releases." 
/>
```
