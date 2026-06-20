# Skeleton Component

## Purpose
Visual placeholder screens mimicking final component shapes. Helps lower cognitive load when waiting for APIs or asynchronous loading calls.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `type` | `'text' \| 'card' \| 'avatar' \| 'table-row'` | `'text'` | Placement layout format |

## Examples

### Text Loading
```vue
<Skeleton type="text" />
```

### Dashboard Course Card Grid loading
```vue
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
  <Skeleton v-for="i in 3" :key="i" type="card" />
</div>
```
