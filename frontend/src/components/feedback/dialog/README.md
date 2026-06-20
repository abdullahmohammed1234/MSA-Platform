# Dialog Component

## Purpose
A modal overlay for confirmations, settings, and form submissions. Features focus-locking, backdrop blurring overlays, ESC keys dismiss, scroll background prevention, and ARIA compliant roles.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `isOpen` | `boolean` | *Required* | Dialog mount and display state |
| `title` | `string` | `''` | Default header title text |
| `size` | `'sm' \| 'md' \| 'lg' \| 'xl' \| 'fullscreen'` | `'md'` | Max width scaling container |

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `close` | `void` | Triggered when overlay click or Close icon is clicked |

## Slots
| Slot Name | Description |
| :--- | :--- |
| `default` | Main body scroll content |
| `header` | Custom header container replacement |
| `footer` | Bottom action button strip |

## Examples

```vue
<Dialog :isOpen="showConfirm" title="Delete Course Module" size="sm" @close="showConfirm = false">
  <p>Are you sure you want to delete this module? This cannot be undone.</p>
  <template #footer>
    <Button variant="ghost" @click="showConfirm = false">Cancel</Button>
    <Button variant="destructive" @click="confirmDelete">Delete</Button>
  </template>
</Dialog>
```
