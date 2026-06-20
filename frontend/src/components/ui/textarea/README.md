# Textarea Component

## Purpose
A multi-line text input field designed for paragraph entries. It supports optional automatic height resizing, labels, custom helper text, error validations, and animated focus indicator styling.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `modelValue` | `string` | *Required* | Current input value |
| `label` | `string` | *Required* | Uppercase form label |
| `description` | `string` | `undefined` | Secondary guidance helper text |
| `error` | `string` | `undefined` | Error warning text which colors the border red |
| `placeholder` | `string` | `''` | Input placeholder text |
| `disabled` | `boolean` | `false` | Disables keyboard entry inputs |
| `required` | `boolean` | `false` | Displays asterisk indicator |
| `rows` | `number` | `4` | Default text lines count height |
| `autoResize` | `boolean` | `false` | Toggles automatic textarea expansion based on content height |

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `update:modelValue` | `string` | Emitted on text entry modifications |
| `focus` | `FocusEvent` | Emitted when text area gains focus |
| `blur` | `FocusEvent` | Emitted when text area loses focus |

## Examples

### Textarea with Auto Resize
```vue
<Textarea 
  v-model="feedback" 
  label="Your Message" 
  placeholder="Type here..." 
  autoResize 
/>
```
