# Mobile Navigation Component

## Purpose
A dedicated full-screen drawer optimized specifically for mobile viewports, rendering navigation routes, call-to-actions, and exit dismiss buttons.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `isOpen` | `boolean` | *Required* | Active visibility toggle |
| `navItems` | `NavItem[]` | *Required* | Navigation routes list |
| `brandName` | `string` | `'SFU MSA'` | Display title logo label |
| `isAuthenticated` | `boolean` | `false` | Auth state tracker to switch sign in vs logout triggers |

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `close` | `void` | Emitted to dismiss drawer overlay |
| `logout` | `void` | Emitted when user logs out |

## Examples

```vue
<MobileNav 
  :isOpen="isMenuOpen" 
  :navItems="menuLinks" 
  :isAuthenticated="isAuthenticated" 
  @close="isMenuOpen = false" 
  @logout="signOut" 
/>
```
