# Navbar Component

## Purpose
A responsive header navigation bar matching the scholastic design system. Handles logo elements, navigation routes, public/auth action links, and mobile burger menus.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `brandName` | `string` | `'SFU MSA'` | Visual title for the platform |
| `navItems` | `NavItem[]` | *Required* | List of primary menu route options |
| `isAuthenticated` | `boolean` | `false` | Toggles display of auth profile triggers vs guest CTA buttons |
| `user` | `UserObject \| null` | `null` | Logged-in user information |

### NavItem Interface
```typescript
interface NavItem {
  label: string;
  path: string;
  requiresAuth?: boolean;
  requiresAdmin?: boolean;
}
```

## Events
| Event | Payload | Description |
| :--- | :--- | :--- |
| `login` | `void` | Triggered when login button clicked |
| `logout` | `void` | Triggered when user selects Sign Out |

## Slots
| Slot Name | Description |
| :--- | :--- |
| `logo` | Custom logo/branding replacement |
| `auth-buttons` | Custom guest buttons replacement |
| `user-trigger` | Custom profile dropdown trigger replacement |

## Examples

```vue
<Navbar 
  :navItems="menuLinks" 
  :isAuthenticated="auth.isLoggedIn" 
  :user="auth.user" 
  @logout="auth.signOut" 
/>
```
