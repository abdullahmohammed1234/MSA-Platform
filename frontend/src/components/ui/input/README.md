# Input Component

## Purpose
The standard input text field. It handles different text types, contains helper states (error, disabled), supports prefix/suffix icons, and features the custom scholastic sliding Gold underline indicator upon focus.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `modelValue` | `string \| number` | *Required* | Current input value |
| `label` | `string` | *Required* | Uppercase form label |
| `description` | `string` | `undefined` | Secondary guidance helper text |
| `error` | `string` | `undefined` | Error warning text which colors the border red |
| `type` | `'text' \| 'email' \| 'password' \| 'search' \| 'number'` | `'text'` | Input format type |
| `placeholder` | `string` | `''` | Input placeholder text |
| `disabled` | `boolean` | `false` | Disables user keyboard entries |
| `required` | `boolean` | `false` | Displays asterisk indicator |

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `update:modelValue` | `string` | Emitted on value input changes |
| `focus` | `FocusEvent` | Emitted when input gains focus |
| `blur` | `FocusEvent` | Emitted when input loses focus |

## Slots
| Slot Name | Description |
| :--- | :--- |
| `prefix` | Custom prefix icon/content positioned inside the left edge |
| `suffix` | Custom suffix icon/content positioned inside the right edge |

## Examples

### Text Field
```vue
<Input 
  v-model="email" 
  label="Email Address" 
  type="email" 
  placeholder="student@sfu.ca" 
  required 
/>
```
