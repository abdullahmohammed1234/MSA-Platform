# SFU MSA Platform Typography

Official platform fonts and role tokens. Canonical CSS: `msa-typography.css`.

## Installed (verified)

| Family | Files | License |
|--------|-------|---------|
| **DM Serif Display** | `DMSerifDisplay-Regular.woff2` (+ Latin Ext), Italic variants, TTF fallbacks | SIL OFL 1.1 (`OFL-DM-Serif-Display.txt`) |

## Missing (required — do not substitute)

Place licensed webfont files in **`shared/typography/fonts/`**, then run:

```powershell
powershell -File scripts/sync-fonts.ps1
```

| Family | Role | Expected filenames |
|--------|------|--------------------|
| **ITC New Baskerville** | Body / UI text | `ITCNewBaskerville-Regular.woff2`, `ITCNewBaskerville-Italic.woff2`, `ITCNewBaskerville-Bold.woff2`, `ITCNewBaskerville-BoldItalic.woff2` |
| **The Seasons** | Secondary display | `TheSeasons-Regular.woff2` (+ Italic if licensed) |
| **Above the Beyond Script** | Decorative accents only | `AboveTheBeyondScript-Regular.woff2` |

After adding files, uncomment the matching `@font-face` blocks in `msa-typography.css` and re-sync.

These three families are commercial. Confirm redistribution/webfont embedding rights before committing binaries.

## Sync targets

The sync script copies fonts + CSS into:

- `frontend/public/fonts/`
- `connect/public/fonts/`
- `projects/public/fonts/`
- `backend/public/fonts/`

Each independently deployed SPA loads `/fonts/msa-typography.css` (absolute `/fonts/` paths work with Vite `base: /`).

## Role → usage

| Token | Font | Use for |
|-------|------|---------|
| `--font-heading` | DM Serif Display | Page titles, heroes, major headings (`font-heading`, Tailwind `font-display`) |
| `--font-body` | ITC New Baskerville | Body, forms, nav, tables (`font-sans`, `font-body`) |
| `--font-seasons` / `--font-display` | The Seasons | Secondary / promotional display (`font-serif`, `font-seasons`) |
| `--font-script` | Above the Beyond Script | Short decorative phrases only (`font-script`) — never functional UI |
