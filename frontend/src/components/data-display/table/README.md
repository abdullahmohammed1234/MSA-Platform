# Table Component

## Purpose
A responsive data grid. Supports sorting columns, page pagination, loading placeholders, empty states, alternating rows, and slot configurations.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `columns` | `TableColumn[]` | *Required* | Column label titles and sorting configuration |
| `items` | `any[]` | *Required* | Data row objects array |
| `isLoading` | `boolean` | `false` | Displays skeleton loading placeholder blocks |
| `emptyText` | `string` | `'No records available.'`| Empty states descriptor label |
| `currentPage` | `number` | `1` | Pagination state active page |
| `totalPages` | `number` | `1` | Pagination total pages |
| `totalItems` | `number` | `0` | Pagination total records count |

### TableColumn Interface
```typescript
interface TableColumn {
  key: string;
  label: string;
  sortable?: boolean;
}
```

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `sort` | `{ key: string, order: 'asc' \| 'desc' }` | Triggered when sorting headers clicked |
| `page-change` | `number` | Triggered when pagination links clicked |

## Slots
| Slot Name | Description |
| :--- | :--- |
| `[column.key]`| Custom overrides for individual cells, receiving `item` and `value` props |
| `empty` | Custom overlay rendering inside body when items are empty |

## Examples

```vue
<Table 
  :columns="columns" 
  :items="users" 
  :currentPage="page" 
  :totalPages="totalPages" 
  @page-change="onPageChange" 
  @sort="onSort"
>
  <template #status="{ item }">
    <span :class="item.status === 'active' ? 'text-success' : 'text-neutral-muted'">
      {{ item.status }}
    </span>
  </template>
</Table>
```
