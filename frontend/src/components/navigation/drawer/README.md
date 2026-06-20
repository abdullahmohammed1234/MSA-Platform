# Drawer Component

## Purpose
A sliding sidebar panel animating from viewport edges. It features support for left, right, and bottom directions, integrates blur overlays, supports custom titles, header slots, and footer buttons.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `isOpen` | `boolean` | *Required* | Mount and display state of the drawer |
| `position` | `'left' \| 'right' \| 'bottom'` | `'right'` | Sliding edge direction |
| `title` | `string` | `''` | Default header title text |
| `size` | `string` | `''` | Width (left/right) or height (bottom) configuration (e.g. `w-96`, `h-96`) |

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `close` | `void` | Triggered when overlay click or Close button is pressed |

## Slots
| Slot Name | Description |
| :--- | :--- |
| `default` | Main body panel scroll content |
| `header` | Custom header container replacement |
| `footer` | Bottom panel actions container |

## Examples

```vue
<Drawer :isOpen="isSettingsOpen" position="right" title="User Settings" @close="isSettingsOpen = false">
  <div>Settings Form Here...</div>
</Drawer>
```
