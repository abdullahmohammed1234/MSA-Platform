# UI Guidelines & Style Guide — SFU MSA & Dawah Academy

This document outlines the visual standards, layout conventions, typography rules, and accessibility compliance guidelines audited from the existing Next.js and Dawah Academy systems. It serves as a quality guide for the Vue 3 frontend implementation.

---

## 1. Core Layout Guidelines

* **Fluid Container Guard**: Content areas must be wrapped inside a responsive container with a maximum width limit of `max-w-7xl` (1280px) and centered (`mx-auto`). This prevents visual lines from stretching too wide on large screens, protecting reading legibility.
* **Gutter & Spacing Consistency**: Margins and paddings must dynamically adapt to viewports using factors of 4px:
  * Mobile: `px-4 py-4` / `gap-4`
  * Tablet: `px-6 py-6` / `gap-6`
  * Desktop: `px-8 py-8` / `gap-8`
  * Ultrawide: `px-12 py-12` / `gap-12`
* **Adaptive Bento Grid**: Dashboard analytics and status panels use a CSS Grid with responsive column spans:
  ```html
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
  ```

---

## 2. Typographic Rules

* **Typographic Pairings**:
  * **Headings**: Use serif Display typography (`Playfair Display`, Georgia, serif) for branding titles, course headings, and dashboard page headers.
  * **Traditional Text**: Use `Cormorant Garamond` for classical quotations and traditional Arabic texts to denote classical scholarship.
  * **UI Labels & Reading Body**: Use clean sans-serif typography (`Geist`, `Inter`, sans-serif) for labels, forms, inputs, and lesson curriculum paragraphs.
  * **Telemetry (Stats/Timers)**: Use monospaced typography (`JetBrains Mono`) for grades, progress percentages, countdown clocks, and IDs.
* **Reading Width Constraints**: Body copy blocks must never exceed **75 characters** per line to prevent reader eye strain during study. Use `max-w-prose` or explicit width limits on textbook paragraphs.
* **Leading & Sizing Scale**:
  * Titles must use tighter leading ratios (`leading-[1.05]` or `leading-[1.1]`) and tighter letter spacing (`tracking-tight`) to maintain a cohesive print-like appearance.
  * Body copy must use generous line-heights (`leading-relaxed` or `leading-loose`) to keep dense paragraphs readable.

---

## 3. Colors & Theme Alignment

* **Spiritual & Scholarly Harmony**: Maintain the sand-cream paper canvas (`#fffbf4`) as the global layout background. Avoid styling pages with pure white backgrounds (`#ffffff`) as this causes high visual contrast and eye strain.
* **Primary Contrast**: Text body colors use a neutral charcoal (`#1e1e1e`) instead of pure black (`#000000`) to create a softer, more premium reading experience.
* **Semantic Tones**:
  * Use **Success Emerald** (`#065f46`) strictly for passed evaluations, complete lessons, and correct answer feedbacks.
  * Use **Accent Gold** (`#ffdc83`) for prestige tracks, verification signatures, claimed certificates, and streak milestones.
  * Use **Academic Red** (`#b02e32`) for active items, course play indicators, navigation hovers, and quiz countdown timers.

---

## 4. Accessibility (A11y) Compliances

To meet WCAG AAA and AA requirements, the system enforces the following standards:

* **High-Contrast Ratios**: The deep burgundy text (`#640c0e`) over the warm cream background (`#fffbf4`) yields an **11.4:1** contrast ratio, exceeding the WCAG AAA requirement of 7.0:1. Muted descriptions (`#5a5d61`) must yield at least a 4.5:1 ratio.
* **Touch Target Size**: Touch targets for all interactive actions (buttons, links, toggle switches, checklist boxes) must occupy a physical area of at least **44px x 44px** on touchscreen viewports.
* **Keyboard Ingress**:
  * Custom inputs and interactive cards must present clear outlines on tab-focus using `focus-visible:ring-2 focus-visible:ring-[#640c0e]`.
  * Dialog overlays and slide sheets must listen to keyboard `Escape` events to dismiss popups safely.
* **Screen Reader Metadata**: Meaningful elements must declare relevant WAI-ARIA states (such as `aria-busy="true"` on loaders, `role="dialog"` on modals, and `aria-invalid="true"` on invalid forms).

---

## 5. LMS Feature Layout Patterns

* **Split-Screen Course Player**: Renders video/textbook content in the left pane and curriculum outline timeline accordion in the right pane. On screens below 768px, the right pane collapses into a slide-out drawer or responsive tab toggle.
* **Padlock Security States**: Grids must display clear padlock icons (`lock` or `shield` vectors) on locked curriculum nodes, preventing click triggers until prerequisites are complete.
* **State Coordination Boundaries**: Loading paths must present pulse skeleton boxes matching the layout dimensions of the destination components. Empty states must render custom vector frames accompanied by call-to-action buttons.
