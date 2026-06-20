# Chart Component

## Purpose
A completely dependency-free, high-performance, responsive SVG Charting wrapper. Supports Line, Area, Bar, and Pie renderings, displaying tooltip labels on hover and supporting custom color themes.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `data` | `ChartDataPoint[]` | *Required* | Data items array mapping labels to numbers |
| `type` | `'line' \| 'bar' \| 'pie' \| 'area'` | `'line'` | Chart format style |
| `title` | `string` | `''` | Chart widget title |
| `height` | `number` | `260` | Target SVG render height in pixels |
| `color` | `string` | `'#640c0e'` | Color overlay override (Hex format) |

### ChartDataPoint Interface
```typescript
interface ChartDataPoint {
  label: string;
  value: number;
}
```

## Examples

### Line Chart
```vue
<Chart 
  title="Monthly Registration Growth" 
  type="line" 
  :data="chartStats" 
/>
```

### Area Chart
```vue
<Chart 
  title="Dawah Outreach Impact" 
  type="area" 
  :data="impactStats" 
  color="#b02e32" 
/>
```
