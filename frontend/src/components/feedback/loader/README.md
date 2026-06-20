# Loader Component

## Purpose
Visual spinning animation indicators showing backgrounds, screens, or inline action buttons are loading.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `type` | `'spinner' \| 'inline' \| 'fullscreen'` | `'spinner'` | Sizing and layout format type |
| `label` | `string` | `''` | Optional descriptive label text |
| `color` | `string` | `'text-primary'` | Tailwind CSS color class override |

## Examples

### Full Screen Overlay
```vue
<Loader type="fullscreen" label="Authorizing Profile..." />
```

### Inline Button Loading
```vue
<Button disabled>
  <Loader type="inline" color="text-white" label="Saving..." />
</Button>
```
