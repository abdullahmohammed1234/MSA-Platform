# Brand Design Tokens — SFU MSA & Dawah Academy

This document specifies the core brand assets, CSS variables, and Tailwind tokens audited from the existing Next.js Public Website and Dawah Academy portals.

---

## 1. Colors Palette

A scholastic formula matrix designed for optimal ocular comfort during extensive reading of religious texts, featuring warm ivory backdrops paired with deep burgundy and gold prestige elements.

### Primary Tones
* **Deep Burgundy (Brand Maroon)**
  * **Hex**: `#640c0e`
  * **RGB**: `rgb(100, 12, 14)`
  * **Usage**: Principal brand identity, headings, primary buttons, calligraphic accents, and keyboard ring outlines.
* **Deep Burgundy Scale**:
  * `maroon-50`: `#fdf2f2` / `rgb(253, 242, 242)`
  * `maroon-100`: `#fbe4e5` / `rgb(251, 228, 229)`
  * `maroon-200`: `#f7ced1` / `rgb(247, 206, 209)`
  * `maroon-300`: `#f1abb0` / `rgb(241, 171, 176)`
  * `maroon-400`: `#e57a82` / `rgb(229, 122, 130)`
  * `maroon-500`: `#d54d58` / `rgb(213, 77, 88)`
  * `maroon-600`: `#c0333e` / `rgb(192, 51, 62)`
  * `maroon-700`: `#a1252f` / `rgb(161, 37, 47)`
  * `maroon-800`: `#86222b` / `rgb(134, 34, 43)`
  * `maroon-900`: `#712128` / `rgb(113, 33, 40)`
  * `maroon-950`: `#3f0d11` / `rgb(63, 13, 17)`

### Secondary Tones
* **Academic Red**
  * **Hex**: `#b02e32`
  * **RGB**: `rgb(176, 46, 50)`
  * **Usage**: Hover state highlights for primary buttons, active sidebar links, syllabus checkboxes, and key notifications.
* **Secondary Light**
  * **Hex**: `#c94a4e`
  * **RGB**: `rgb(201, 74, 78)`
  * **Usage**: Interactive links and focus highlights.

### Accent Tones
* **Prestige Gold**
  * **Hex**: `#ffdc83`
  * **RGB**: `rgb(255, 220, 131)`
  * **Usage**: Achievements badges, verification seals, locked course nodes, verified instructor borders, and credentials.
* **Prestige Gold Scale**:
  * `gold-50`: `#fffbeb` / `rgb(255, 251, 235)`
  * `gold-100`: `#fef3c7` / `rgb(254, 243, 199)`
  * `gold-200`: `#fde68a` / `rgb(253, 230, 138)`
  * `gold-300`: `#fcd34d` / `rgb(252, 211, 77)`
  * `gold-400`: `#fbbf24` / `rgb(251, 191, 36)`
  * `gold-500`: `#f59e0b` / `rgb(245, 158, 11)`
  * `gold-600`: `#d97706` / `rgb(217, 119, 6)`
  * `gold-700`: `#b45309` / `rgb(180, 83, 9)`
  * `gold-800`: `#92400e` / `rgb(146, 64, 14)`
  * `gold-900`: `#78350f` / `rgb(120, 53, 15)`
  * `gold-950`: `#451a03` / `rgb(69, 26, 3)`
* **Focus Underline Red**
  * **Hex**: `#ea2128`
  * **RGB**: `rgb(234, 33, 40)`
  * **Usage**: Sliding highlight bar on active text inputs.

### Semantic Tones
* **Success (Emerald)**
  * **Hex**: `#065f46`
  * **RGB**: `rgb(6, 95, 70)`
  * **Usage**: Graded passes, lesson checklist completions, and positive status items.
* **Warning (Amber)**
  * **Hex**: `#d97706`
  * **RGB**: `rgb(217, 119, 6)`
  * **Usage**: In-progress items, pending syllabus locks.
* **Error (Alert Red)**
  * **Hex**: `#b02e32`
  * **RGB**: `rgb(176, 46, 50)`
  * **Usage**: Form validation warnings, deletion actions, and failing results.
* **Info (Sky Blue)**
  * **Hex**: `#0ea5e9`
  * **RGB**: `rgb(14, 165, 233)`
  * **Usage**: Optional reading tracks and audit paths.

### Neutral Scale
* **Warm Cream (Canvas Background)**: `#fffbf4` / `rgb(255, 251, 244)` (Used for main background layout)
* **Soft Ivory (Borders & Grids)**: `#ebe8de` / `rgb(235, 232, 222)` (Card borders, dividers, form guides)
* **Neutral Gray (Scrollbar/Dividers)**: `#c2c4c7` / `rgb(194, 196, 199)`
* **Muted Charcoal (Secondary Text)**: `#5a5d61` / `rgb(90, 93, 97)`
* **Neutral Charcoal (Readable Body)**: `#1e1e1e` / `rgb(30, 30, 30)` (AAA contrast typography)
* **Pure Black**: `#000000` / `rgb(0, 0, 0)`
* **Pure White**: `#ffffff` / `rgb(255, 255, 255)`

---

## 2. Typography System

The interface pairs classic display serif fonts for headers with high-legibility sans-serif fonts for reading content.

### Font Families
* **Display / Heading**: `"Playfair Display"`, Georgia, serif (Public website headers / Academic headers)
* **Traditional Serif**: `"Cormorant Garamond"`, Georgia, serif (Used for core Islamic classical texts)
* **Body / UI Labels (Sans-Serif)**: `"Geist Variable"`, `"Inter"`, sans-serif (Standard layout body and UI inputs)
* **Metadata & Coding (Mono)**: `"JetBrains Mono"`, `"SFMono-Regular"`, Consolas, monospace (Timers, scores, student IDs)

### Font Weights
* `light`: `300`
* `regular`: `400`
* `medium`: `500`
* `semibold`: `600`
* `bold`: `700`

### Font Sizing & Scale
* **H1 (Hero Text)**:
  * Size: `text-4xl` to `text-[5.5rem]` (Mobile: 36px, Tablet: 48px, Desktop: 88px)
  * Weight: `font-medium`
  * Line-height: `1.05` (`leading-[1.05]`)
  * Letter-spacing: `tracking-tight` (`-0.02em`)
* **H2 (Section Header)**:
  * Size: `text-3xl` to `text-6xl` (Mobile: 30px, Tablet: 36px, Desktop: 60px)
  * Weight: `font-medium`
  * Line-height: `1.1`
  * Letter-spacing: `tracking-tight`
* **H3 (Subsection Header)**:
  * Size: `text-xl` to `text-4xl` (Mobile: 20px, Tablet: 24px, Desktop: 36px)
  * Weight: `font-medium`
  * Line-height: `1.2`
* **H4 (Card Header)**:
  * Size: `text-lg` to `text-2xl`
  * Weight: `font-semibold`
* **Body Large (Intro Text)**:
  * Size: `text-base` to `text-lg`
  * Weight: `font-normal`
  * Line-height: `leading-relaxed` (`1.625`)
* **Body (Regular text)**:
  * Size: `text-sm` to `text-base`
  * Weight: `font-normal`
  * Line-height: `leading-relaxed`
* **Caption / Small Text**:
  * Size: `text-[10px]` to `text-xs`
  * Weight: `font-semibold` or `font-bold` (For uppercase labels like `premium-label`)
  * Letter-spacing: `tracking-[0.15em]` (For labels)

---

## 3. Spacing System

Layout spacing is constrained to factors of `4px` to maintain a rigid visual rhythm.

| Token | Pixels | Rem | Tailwind Utility Classes |
| :--- | :--- | :--- | :--- |
| `px` | 1px | 0.0625rem | `gap-px`, `border-px` |
| `1` | 4px | 0.25rem | `p-1`, `m-1`, `gap-1` |
| `2` | 8px | 0.5rem | `p-2`, `m-2`, `gap-2` |
| `3` | 12px | 0.75rem | `p-3`, `m-3`, `gap-3` |
| `4` | 16px | 1.0rem | `p-4`, `m-4`, `gap-4` |
| `6` | 24px | 1.5rem | `p-6`, `m-6`, `gap-6` |
| `8` | 32px | 2.0rem | `p-8`, `m-8`, `gap-8` |
| `12` | 48px | 3.0rem | `p-12`, `m-12`, `gap-12` |
| `18` | 72px | 4.5rem | `p-18`, `m-18`, `gap-18` |
| `22` | 88px | 5.5rem | `p-22`, `m-22`, `gap-22` |
| `30` | 120px | 7.5rem | `p-30`, `m-30`, `gap-30` |

---

## 4. Border Radius

* **Small (`radius-xs` / `radius-sm`)**: `0.25rem` (4px) / `0.5rem` (8px) - Used for check inputs, toggle switch thumbs, and minor icons.
* **Medium (`radius-md` / standard `radius`)**: `0.75rem` (12px) - Standard inputs, badges, select boxes, and action tables.
* **Large (`radius-lg`)**: `1.0rem` (16px) - Standard course grids and bento boxes.
* **XL / 2XL (`radius-xl` / `radius-2xl`)**: `1.5rem` (24px) / `2.0rem` (32px) - Cards, sidebar layouts, and modal panels.
* **3XL (`radius-3xl`)**: `3.0rem` (48px) - Premium cards and large sections.
* **Full (`radius-full`)**: `9999px` - Rounded buttons, avatar frames, and circular sliders.

---

## 5. Shadow System

* **Card Elevation (`shadow-soft` / `shadow-premium`)**:
  * Value: `0 10px 40px -10px rgba(100, 12, 14, 0.3)` or `0 4px 20px rgba(0, 0, 0, 0.08)`
  * Usage: Standard dashboard cards and curriculum modules.
* **Hover Elevation (`shadow-premium-md` / `shadow-premium-lg`)**:
  * Value: `0 20px 50px -12px rgba(0, 0, 0, 0.08)`
  * Usage: Cards hovering state when interactive.
* **Dialog overlay (`shadow-2xl` / `shadow-premium-lg`)**:
  * Value: Standard modal popup shadows.
* **Elevated Brand Glow (`shadow-brand` / `shadow-glow`)**:
  * Value: `0 10px 30px -10px rgba(138, 21, 56, 0.15)`
  * Usage: Active buttons hover, certificate cards, and honors achievements.

---

## 6. Layout System

* **Container Constraint**: `max-w-7xl` (1280px max width) centered with `mx-auto` (Prevents wide stretching).
* **Gutters (Padding)**:
  * Mobile: `px-4` / `px-5`
  * Tablet: `px-6` / `px-8`
  * Desktop: `px-12`
* **Section Padding**: `py-16` (Mobile), `py-24` (Tablet), `py-32` (Desktop).

---

## 7. Breakpoints

* **xs (Mobile portrait)**: `< 640px`
* **sm (Mobile landscape)**: `640px` to `767px`
* **md (Tablet portrait)**: `768px` to `1023px`
* **lg (Tablet landscape / small laptop)**: `1024px` to `1279px`
* **xl (Desktop HD)**: `1280px` to `1535px`
* **2xl (Large Desktop / Ultrawide)**: `>= 1536px`
