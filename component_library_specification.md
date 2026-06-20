# Component Library Specification — SFU MSA & Dawah Academy

This document specifies the behavior, properties, HTML wrappers, Tailwind utility classes, and accessibility requirements for every reusable UI component audited in the source applications.

---

## 1. Buttons

The foundational action triggers. Buttons utilize spring scale transitions on mouse hover/click and support loading indicators.

### Properties & Variants
* **Primary / Default (Deep Burgundy)**: `bg-[#640c0e] text-white hover:bg-[#640c0e]/95`
* **Secondary (Soft Ivory)**: `bg-[#ebe8de] text-[#640c0e] hover:bg-[#ebe8de]/80`
* **Outline**: `border border-[#ebe8de] bg-white text-[#640c0e] hover:bg-[#fffbf4]/60`
* **Ghost**: `text-[#640c0e] hover:bg-[#fffbf4]`
* **Destructive (Academic Red)**: `bg-[#b02e32] text-white hover:bg-[#b02e32]/95`
* **Gold (Prestige)**: `bg-[#ffdc83] text-[#640c0e] hover:bg-[#ffdc83]/90 font-bold border border-[#ffdc83]/30`
* **Success (Emerald)**: `bg-emerald-800 text-white hover:bg-emerald-800/90`
* **Shiny (Shimmer Shifter)**: Adds a diagonal glass glare sweeping across the button surface (`before:absolute before:inset-0 before:bg-linear-to-r before:from-transparent before:via-white/20 before:to-transparent before:-translate-x-full hover:before:animate-[shimmer_1.5s_infinite]`).

### Sizes
* **sm (Compact text)**: `h-8 px-3 text-xs rounded-md`
* **md (Standard UI)**: `h-9 px-4 py-2 text-sm rounded-md`
* **lg (Action hero)**: `h-11 px-8 text-base rounded-lg`
* **icon (Square)**: `h-9 w-9 p-0 flex items-center justify-center`

### Interactive States
* **Hover**: Subtle scaling and lifting `whileHover={{ scale: 1.015 }}` (Main: `scale: 1.02, y: -2`)
* **Active (Tap)**: Spring tap compression `whileTap={{ scale: 0.985 }}` (Main: `scale: 0.98`)
* **Disabled**: `disabled:opacity-50 disabled:pointer-events-none`
* **Loading**: Disables inputs, presents a spinning loader icon (`Loader2` rotating), and fades label text.

### Accessibility (A11y)
* WAI-ARIA roles: `role="button"`.
* For loading states: `aria-busy="true"` and `aria-live="polite"`.

---

## 2. Inputs

Includes FormGroups, text inputs, textareas, selects, checkboxes, and switches.

### Text Inputs (`Input` / `TextArea`)
* **Purpose**: Text, Email, Password, Search, Textarea data collection.
* **Base Classes**: `w-full rounded-lg border border-[#ebe8de] bg-[#fffbf4]/20 px-3 py-2 text-sm transition-all focus:outline-none focus:ring-2 focus:ring-[#640c0e]/15`
* **Focus State**: Scales up slightly `whileFocus={{ scale: 1.01 }}`. An absolute colored accent line transitions from scale 0 to 100% on focus-within:
  ```html
  <div class="absolute inset-x-0 bottom-0 h-0.5 bg-[#ffdc83] scale-x-0 group-focus-within:scale-x-100 transition-transform duration-500 rounded-full" />
  ```
* **Error State**: Red border highlight (`border-red-250`), invalid label triggers, and warning descriptor text below the input field.
* **Disabled State**: Grey background backdrop (`bg-neutral-100`), faded icons, and locked keyboard focus.

### FormGroup Layouts
* Wraps the label, input, description, and error lines.
* Label styling: `text-[10px] font-bold uppercase tracking-[0.15em] text-muted-foreground/60 mb-2 block`
* Error text styling: `text-[10.5px] text-red-600 font-semibold block mt-1`

### Switches & Checkboxes
* **Switch Toggle**: iOS-style spring toggle. Renders a track (`w-9 h-5 bg-[#ebe8de] rounded-full`) and an absolute circle thumb (`w-4 h-4 bg-white rounded-full transition-transform duration-200`) which slides right when enabled (`translate-x-4`).
* **Checkbox**: Custom squared box wrapper (`h-5 w-5 rounded border-[#ebe8de] text-[#640c0e] focus:ring-[#640c0e]/20 transition-all cursor-pointer`).

### Accessibility (A11y)
* Input fields declare `id`, matched to parent label tags via `htmlFor`.
* Errors bind `aria-invalid="true"` and link descriptions via `aria-describedby`.

---

## 3. Cards

Cards form the grid elements of dashboards, bento boards, and reading items.

### Variants
* **Default Card**: Standard container with border (`bg-white border border-[#ebe8de] shadow-soft`)
* **Glass Card**: Translucent blurred panel (`bg-white/70 backdrop-blur-md border border-white/20 shadow-premium-lg`)
* **Premium Card**: Double-bordered container with deeper brand shadows (`shadow-brand rounded-2xl`)
* **Flat Card**: Background border contrast (`bg-[#ebe8de]/40 border border-[#ebe8de]`)
* **Feature Card**: Incorporates a leading circular icon container (`bg-primary/10`) which scales up on card hover.
* **Interactive Card**: Configured with pointer cursors and tap scaling.

### Hover Animations
* Hoverable cards elevate subtly on the Y-axis: `whileHover={{ y: -4, boxShadow: "var(--shadow-premium-md)", borderColor: "var(--color-primary)" }}`.

---

## 4. Tables

Tables represent directories, volunteers databases, and quiz result scores.

### Table Structure
* **Header Bar**: Fixed table rows styled with small uppercase bold labels (`text-[10px] uppercase font-bold tracking-[0.15em] text-muted-foreground/60`).
* **Row Elements**: Alternate row background shading and bottom borders (`border-b border-[#ebe8de]/40 hover:bg-[#fffbf4]/40 transition-colors`).
* **Sorting Columns**: Interactive header text containing inline sorting triggers (Arrow vectors matching active key directions).
* **Pagination Footer**: Renders items count, page info, and navigation links (Previous / Next buttons) centered in a flex row.

---

## 5. Dialogs & Modals

Elevated overlays triggered for settings panels, confirmation dialogues, and notice boards.

### Specifications
* **Sizes**: `sm` (384px), `md` (512px), `lg` (768px), `xl` (1024px), `fullscreen` (100% viewport).
* **Overlay Backdrop**: Dark translucent layer with a subtle blur overlay (`bg-black/40 backdrop-blur-xs`).
* **Scrolling**: Modal triggers apply `overflow-hidden` to the document body to prevent background scrolling.
* **A11y**: Focus trapping, keyboard Escape key listeners, and explicit roles (`role="dialog"`, `aria-modal="true"`).

---

## 6. Dropdowns & Selects

Native browser select options styled with local ivory variables.

### Custom Select
* **Styling**: `w-full rounded-lg border border-[#ebe8de] bg-white px-3 py-2 text-sm text-[#640c0e] focus:outline-none focus:ring-2 focus:ring-[#640c0e]/15`
* **Trigger States**: Default, Hover, Focus. Uses inline SVG chevrons to signal expanding options.

---

## 7. Tabs

Horizontal navigation selectors.

### Specifications
* **Container**: Flat layout with minor borders.
* **Active Indicator**: Uses absolute spring-animated backgrounds or colored border-bottom underlines.
* **Layout**: `flex items-center gap-1 border-b border-[#ebe8de] pb-1`.

---

## 8. Badges

Multi-tiered labels denoting status, roles, achievements, and certificate credentials.

### Base Badge
* **Variants**: `gold` (gradient highlight), `burgundy` (brand primary), `outline` (bordered), `success` (emerald), `warning` (amber), `danger` (red), `info` (sky).
* **Sizes**: `xs` (extra-small), `sm` (compact), `md` (standard), `lg` (large).
* **Animations**:
  * `shimmer`: Linear glass sheen sweep (`before:animate-[shimmer_2s_infinite]`).
  * `pulse`: Expanding halo ring (`animate-ping`).
  * `glow`: Radial blurry glow ring backdrop.

### Achievement Badge (`AchievementBadge`)
* Renders a dashed box seal (`w-11 h-11 border-2 border-dashed`) rotating on hover, an XP badge (+150 XP), a title, a description, and unlocked date metadata. Shows a grayscale opacity filter when locked.

### Role Badge (`RoleBadge`)
* Displays theological user rank credentials (e.g. Mujtahid, Alim, Murshid, Mutallim), showing initials, title, description, and traditional calligraphy-like Arabic taxonomy labels (e.g. `مجتهد`).

---

## 9. Progress Bars & Indicators

Used for lesson tracking, module checkpoints, and student portals.

### Base Progress Bar (`LmsProgressBar`)
* Linear track (`h-2 bg-slate-100 rounded-full border`) containing a colored progress bar (`bg-[#640c0e] rounded-full`) transitioning its width dynamically.

### Circular Progress Gauge (`LmsCircularProgress`)
* SVG circular ring overlay displaying percentage values in the center (stiffness: 150, damping: 25).

### Stepper Timeline (`LmsMilestoneTracker`)
* Sequential list of nodes connected by a central vertical line, indicating locked, active, and completed milestones.

---

## 10. Course Cards (`LmsCourseCard`)
* **Standard layout**: Card wrapper with cover image, category tag, module title, short description, instructor details box, progress tracking bar, and metadata row (Time, level).
* **Compact layout**: Inline rows suited to sidebar checklists.

---

## 11. Certificate Cards
* **Traditional Design**: Double gold and burgundy borders, centered student name in displays typography, verification checksum text (e.g. `ID: ACAD-01092F`), and verified QR/verification links.
