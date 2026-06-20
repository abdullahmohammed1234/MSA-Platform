# Card Component

## Purpose
The core structural grid container. Supports multiple stylistic layout versions (glass, premium, flat, feature, interactive) and incorporates Y-axis motion lift animations on hover.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `variant` | `'default' \| 'glass' \| 'premium' \| 'flat' \| 'feature' \| 'interactive'` | `'default'` | Visual container styling variant |
| `hoverable` | `boolean` | `false` | Enables mouse hover scaling/lifting animations |

## Slots
| Slot Name | Description |
| :--- | :--- |
| `default` | Main container body content |
| `header` | Sticky top title divider container |
| `footer` | Bottom action button strip |
| `feature-icon`| Rounded circular icon container (only used when `variant` is `'feature'`) |

## Examples

### Default Card
```vue
<Card>
  <p>General dashboard card content.</p>
</Card>
```

### Premium Hoverable Card
```vue
<Card variant="premium" hoverable>
  <template #header>
    <h3 class="font-bold">Course Title</h3>
  </template>
  <p>curriculum descriptions...</p>
</Card>
```
