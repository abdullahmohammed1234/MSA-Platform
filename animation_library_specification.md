# Animation & Motion Specification — SFU MSA & Dawah Academy

This document specifies the interactive micro-behaviors, routing transitions, spring physics parameters, and timing easing curves audited from the source applications to enable a pixel-perfect reconstruction of the motion system.

---

## 1. Timing & Physics System

To achieve an ultra-premium SaaS feel, animations utilize spring-physics and precise cubic-bezier configurations rather than generic linear transitions.

### Cubic-Bezier Curves
* **premium**: `[0.16, 1, 0.3, 1]` (Ultra-elegant decelerated ease-out; standard for menus, pages, and dialog exits).
* **natural**: `[0.4, 0, 0.2, 1]` (Symmetric standard transition; standard for exits and layout reflows).
* **sharp**: `[0.4, 0, 0.6, 1]` (Accelerated ease-in; standard for quick close-outs).
* **soft**: `[0.25, 0.1, 0.25, 1]` (Highly cushioned ease; standard for slow text fades).

### Spring Physics Configurations
* **subtle**: `{ stiffness: 150, damping: 25, mass: 0.8 }` (Standard for small tags, badge hovers, and checkbox ticks).
* **gentle**: `{ stiffness: 220, damping: 28, mass: 1 }` (Standard for cards entrance, progress bar slide-ups, and page structures).
* **snappy**: `{ stiffness: 380, damping: 35, mass: 0.9 }` (Standard for dropdown open overlays, quick tabs, and accordion drawers).
* **bouncy**: `{ stiffness: 450, damping: 22, mass: 0.95 }` (Reserved for prestige medals or celebration prompts).

### Durations (Animation Tokens)
* **fast**: `0.15s` to `0.25s` (Click compressions, accordion openings, hover shines).
* **normal**: `0.28s` to `0.38s` (Dashboard panel switches, tab slides, modal open overlays).
* **slow**: `0.5s` to `0.8s` (Global page loads, lazy skeletons fades, statistics counter loops).

---

## 2. Page & Section Transitions

Page entries use a smooth vertical slide-up coupled with an opacity fade. Dashboard panels utilize a tighter scaling factor to maintain high-performance responsiveness.

### Standard Page Transition
* **Trigger**: Mount of router views (`AnimatePresence` mode="wait").
* **States**:
  * **initial**: `{ opacity: 0, y: 10 }`
  * **animate**: `{ opacity: 1, y: 0 }`
  * **exit**: `{ opacity: 0, y: -6 }`
* **Timing**: Duration `0.38s`, Easing `cubic-bezier(0.16, 1, 0.3, 1)`.
* **CSS Helpers**: `will-change-[opacity,transform]`.

### Dashboard Tab / Panel Transition
* **Trigger**: Switching active layout tabs (e.g. Analytics vs Notices).
* **States**:
  * **initial**: `{ opacity: 0, scale: 0.995, y: 6 }`
  * **animate**: `{ opacity: 1, scale: 1, y: 0 }`
  * **exit**: `{ opacity: 0, y: -4 }`
* **Timing**: Duration `0.28s`, Easing `cubic-bezier(0.16, 1, 0.3, 1)`.

---

## 3. Hover & Click Animations

Every interactive surface gives instant tactile feedback upon user hover and tap actions.

### Buttons & UI Primitives
* **Hover**: Subtle scaling and lifting to convey weight.
  * *Scale*: `scale: 1.015` (Main: `scale: 1.02`)
  * *Lift*: `y: -2`
  * *Physics*: Spring transition `{ stiffness: 400, damping: 28 }`
* **Tap (Click)**: Tap compression.
  * *Scale*: `scale: 0.985` (Main: `scale: 0.98`)
  * *Physics*: Spring transition `{ stiffness: 400, damping: 15 }`

### Cards
* **Hover**: Elevates on the Y-axis and applies a premium drop shadow.
  * *Lift*: `y: -4` (Main: `y: -8`)
  * *Border/Shadow*: `borderColor: "var(--color-primary)"`, `boxShadow: var(--shadow-premium-md)`
  * *Timing*: Duration `0.35s` (Main: `0.3s`), Easing `easeOut`.

### Text Inputs
* **Hover**: Outer border highlights: `group-hover:border-primary/20`.
* **Focus**: Input scales up slightly `whileFocus={{ scale: 1.01 }}`. An absolute highlight line scales from 0 to 100% on focus-within:
  ```css
  transition-property: transform;
  transition-duration: 500ms;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  ```

---

## 4. Loading States & Skeletons

Visual cues prevent cognitive friction during network delays.

### Skeletons (`animate-pulse`)
* **Standard pulse**: CSS keyframes animating opacity from 1 to 0.5 and back.
  ```css
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  ```
* **Card Skeleton**: Injects a block pattern simulating cover images, layout titles, text paragraphs, and action links in soft ivory (`bg-[#ebe8de]`).

### Shimmering Glass Reflection (`before:animate-[shimmer_2s_infinite]`)
* Renders a diagonal light flare sweeping across loading badges or card borders.
* **Background Gradient**: `linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.25) 50%, transparent 100%)`
* **Keyframes**:
  ```css
  @keyframes shimmer {
    100% {
      transform: translateX(100%);
    }
  }
  ```

---

## 5. Dashboard & LMS Domain Animations

Specialized components include built-in animations linked to viewport triggers and statistics changes.

### Viewport Entrance (`whileInView`)
* **Curriculum Cards**: Slide up when scrolled into view.
  * *States*: `initial={{ opacity: 0, y: 20 }}` to `whileInView={{ opacity: 1, y: 0 }}`.
  * *Viewport trigger*: `once: true`, `margin: "-50px"` or `margin: "-100px"`.

### Statistics Counter (`AnimatedCounter`)
* Animates counting from 0 to target totals (e.g. `1,420` active scholars).
* **Physics**: `useSpring` with `damping: 30`, `stiffness: 100` mapping values on viewport intersection `useInView(ref, { once: true, margin: "-100px" })`.

### Progress Fill (`LmsProgressBar`)
* Standard progress bar width transitions from `width: 0%` to target percentage when mounted.
* **Timing**: Duration `0.8s`, Easing `easeOut`.

---

## 6. Micro-Interactions

* **Badge Hexagon Spin**: Achievement seals rotate slightly on hover to highlight details:
  ```css
  transition-property: transform;
  transition-duration: 500ms;
  transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
  /* Hover rotation */
  transform: rotate(12deg);
  ```
* **Aura Ring Pulsing**: Verified mentors contain an outer glow pulse:
  ```css
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  box-shadow: 0 0 15px rgba(100, 12, 14, 0.15);
  ```
* **Hover Shimmer Flare**: sweeps a light reflection across card surfaces on mouse entry:
  * *Position*: `left: "-100%"` to `whileHover={{ left: "120%" }}`
  * *Timing*: Duration `0.65s`, Easing `easeInOut`.
