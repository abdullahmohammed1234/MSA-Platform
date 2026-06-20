# Button Component

## Purpose
The foundational action trigger. Utilizes spring-scale transitions on mouse hover/click, supports loading states, custom icons, full-width mode, and shiny shimmer sweep styling.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `variant` | `'primary' \| 'secondary' \| 'outline' \| 'ghost' \| 'destructive' \| 'gold' \| 'success' \| 'link'` | `'primary'` | Visual style variant |
| `size` | `'sm' \| 'md' \| 'lg' \| 'icon'` | `'md'` | Sizing scale |
| `isLoading` | `boolean` | `false` | Shows a spinning loader and disables interaction |
| `disabled` | `boolean` | `false` | Disables button triggers |
| `isFullWidth` | `boolean` | `false` | Renders the button as full width (`w-full`) |
| `isShiny` | `boolean` | `false` | Adds a diagonal shimmering glass sheen sweep on hover |
| `type` | `'button' \| 'submit' \| 'reset'` | `'button'` | Native HTML button type |

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `click` | `MouseEvent` | Emitted on button click when not loading or disabled |

## Slots
| Slot Name | Description |
| :--- | :--- |
| `default` | Main label content of the button |
| `left-icon` | Custom icon element positioned before the label |
| `right-icon` | Custom icon element positioned after the label |

## Examples

### Primary Button
```vue
<Button variant="primary">Submit Request</Button>
```

### Prestige Gold with Shimmer
```vue
<Button variant="gold" isShiny>Claim Achievement</Button>
```
