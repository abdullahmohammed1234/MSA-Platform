# Sidebar Component

## Purpose
A collapsible layout sidebar used across admin dashboards and Dawah Academy portals. Supports nested item navigation groups, custom icon slots, active route styling highlights, and a footer section.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `items` | `SidebarItem[]` | *Required* | Navigation link tree structures |
| `collapsed` | `boolean` | `false` | Collapse state (w-20 showing icons only vs w-64) |
| `title` | `string` | `'Academy'` | Header branding text |

### SidebarItem Interface
```typescript
interface SidebarItem {
  label: string;
  path: string;
  icon?: string;
  children?: SidebarItem[];
}
```

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `collapse` | `boolean` | Emitted when sidebar collapse state changes |

## Slots
| Slot Name | Description |
| :--- | :--- |
| `[item.icon]` | Renders custom SVG icons for specific list items |
| `default-icon` | Fallback icon representation when no item icon is specified |
| `footer-user` | Replaces user profile information at the sidebar base |

## Examples

```vue
<Sidebar 
  :items="dashboardLinks" 
  :collapsed="isSidebarCollapsed" 
  @collapse="onCollapse" 
/>
```
