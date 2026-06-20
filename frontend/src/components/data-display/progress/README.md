# Progress Components

## Purpose
A collection of dashboard metrics and course tracking visuals, including linear bars, circular gauges, and milestone stepper timelines.

## Components & Props

### LmsProgressBar Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `value` | `number` | *Required* | Active percentage level (0 to 100) |
| `color` | `string` | `'bg-primary'` | Tailwind color utility override |
| `showLabel` | `boolean` | `false` | Displays top numeric indicators |

### LmsCircularProgress Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `value` | `number` | *Required* | Active percentage level (0 to 100) |
| `size` | `number` | `60` | Circular SVG width/height size in pixels |
| `strokeWidth` | `number` | `5` | Circular border stroke thickness |
| `color` | `string` | `'text-primary'` | CSS color utility override |

### LmsMilestoneTracker Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `milestones` | `Milestone[]` | *Required* | Array of chronological milestones |

#### Milestone Interface
```typescript
interface Milestone {
  id: string;
  label: string;
  description?: string;
  status: 'locked' | 'active' | 'completed';
}
```

## Examples

### Milestone Stepper
```vue
<LmsMilestoneTracker :milestones="syllabusTimeline" />
```

### Circular Gauge
```vue
<LmsCircularProgress :value="48" :size="70" color="text-secondary" />
```
