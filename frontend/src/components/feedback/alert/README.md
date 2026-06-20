# Alert Component

## Purpose
An inline notices box representing warning, success, error, or informational alert statuses. Supports custom actions slots, close buttons, and header titles.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `type` | `'success' \| 'error' \| 'warning' \| 'info'` | `'info'` | Alert status theme color |
| `title` | `string` | `undefined` | Header title label text |
| `closable` | `boolean` | `false` | Enables Close dismiss trigger |

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `close` | `void` | Emitted when alert is closed |

## Slots
| Slot Name | Description |
| :--- | :--- |
| `default` | Main alert description body text |
| `actions` | Bottom action button strip |

## Examples

### Success Alert
```vue
<Alert type="success" title="Success Notification">
  Your quiz was graded. Pass score met!
</Alert>
```

### Warning with Action Buttons
```vue
<Alert type="warning" title="Incomplete Profile" closable>
  Please fill out your billing information before requesting reimbursements.
  <template #actions>
    <button class="text-xs font-bold underline">Update Now</button>
  </template>
</Alert>
```
