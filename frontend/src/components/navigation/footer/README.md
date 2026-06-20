# Footer Component

## Purpose
A responsive site footer supporting multi-column link directories, social media redirects, address coordinates, and copyright labels.

## Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `columns` | `FooterColumn[]` | *Provided Defaults* | Grouped directory lists of paths |
| `socials` | `SocialItem[]` | *Provided Defaults* | Social media anchor link references |
| `copyright` | `string` | *SFU MSA Copyright* | Bottom legal copy label |

### FooterColumn Interface
```typescript
interface FooterColumn {
  title: string;
  links: { label: string; path: string; }[];
}
```

## Slots
| Slot Name | Description |
| :--- | :--- |
| `social-[icon]` | Replacement slot to inject SVG icons for specific social media handles |

## Examples

```vue
<Footer :columns="customColumns" />
```
