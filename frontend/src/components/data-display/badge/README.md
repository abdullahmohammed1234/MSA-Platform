# Badge Components

## Purpose
A collection of labeling components including standard status tags, achievements seals, and specialized theological scholar rank credentials.

## Components & Props

### Badge Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `variant` | `'primary' \| 'secondary' \| 'success' \| 'warning' \| 'error' \| 'info' \| 'gold' \| 'outline'` | `'primary'` | Badge status coloring |
| `size` | `'sm' \| 'md' \| 'lg'` | `'md'` | Sizing scale |
| `isShimmer` | `boolean` | `false` | Loops a shimmering light sweep continuously |
| `isPulse` | `boolean` | `false` | Renders a pulsing ping notification dot indicator |

### AchievementBadge Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `title` | `string` | *Required* | Name of the achievement |
| `description` | `string` | *Required* | Description text of the criteria |
| `xp` | `number` | `100` | XP value rewarded |
| `unlockedAt` | `string` | `undefined` | Date string when earned |
| `isLocked` | `boolean` | `false` | Locks state, fading elements into grayscale |

### RoleBadge Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `role` | `'mujtahid' \| 'alim' \| 'murshid' \| 'mutallim'` | *Required* | Theological rank configuration |
| `title` | `string` | *Role Default* | Custom override name |
| `description` | `string` | *Role Default* | Custom override description |

## Examples

### Continuous Shimmering Status Tag
```vue
<Badge variant="gold" isShimmer>Premium Student</Badge>
```

### Locked Achievement Card
```vue
<AchievementBadge 
  title="Quranic Hafidh" 
  description="Memorize the complete Book of Allah." 
  :xp="1500" 
  isLocked 
/>
```

### Alim Scholar Card
```vue
<RoleBadge role="alim" />
```
