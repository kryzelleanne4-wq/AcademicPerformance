---
name: Academic Excellence System
colors:
  surface: '#f9f9ff'
  surface-dim: '#cfdaf1'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f0f3ff'
  surface-container: '#e7eeff'
  surface-container-high: '#dee8ff'
  surface-container-highest: '#d8e3fa'
  on-surface: '#111c2c'
  on-surface-variant: '#43474f'
  inverse-surface: '#263142'
  inverse-on-surface: '#ebf1ff'
  outline: '#737780'
  outline-variant: '#c3c6d1'
  surface-tint: '#3a5f94'
  primary: '#001e40'
  on-primary: '#ffffff'
  primary-container: '#003366'
  on-primary-container: '#799dd6'
  inverse-primary: '#a7c8ff'
  secondary: '#735c00'
  on-secondary: '#ffffff'
  secondary-container: '#fed65b'
  on-secondary-container: '#745c00'
  tertiary: '#1b1f21'
  on-tertiary: '#ffffff'
  tertiary-container: '#303436'
  on-tertiary-container: '#999c9f'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d5e3ff'
  primary-fixed-dim: '#a7c8ff'
  on-primary-fixed: '#001b3c'
  on-primary-fixed-variant: '#1f477b'
  secondary-fixed: '#ffe088'
  secondary-fixed-dim: '#e9c349'
  on-secondary-fixed: '#241a00'
  on-secondary-fixed-variant: '#574500'
  tertiary-fixed: '#e0e3e6'
  tertiary-fixed-dim: '#c4c7ca'
  on-tertiary-fixed: '#191c1e'
  on-tertiary-fixed-variant: '#44474a'
  background: '#f9f9ff'
  on-background: '#111c2c'
  surface-variant: '#d8e3fa'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 40px
  xl: 64px
  gutter: 24px
  margin-mobile: 16px
  max-width: 1280px
---

## Brand & Style

This design system is built upon a **Corporate / Modern** aesthetic tailored for the academic sector. It prioritizes clarity, organizational efficiency, and a sense of institutional prestige. The brand personality is authoritative yet accessible, designed to evoke feelings of trust, focus, and scholarly achievement. 

The style utilizes a disciplined approach to whitespace and information density, ensuring that complex data remains legible and actionable. It avoids unnecessary ornamentation, instead using color and structure to guide the user through performance metrics and administrative tasks.

## Colors

The palette is anchored by **Academic Blue** (#003366), representing stability and intelligence, and **Institutional Gold** (#D4AF37), used sparingly for highlights, achievement markers, and primary calls to action. 

- **Primary:** Used for navigation, headers, and core brand elements.
- **Secondary (Gold):** Reserved for high-value interactions, excellence indicators, and "Premium" status levels.
- **Surface/Background:** A clean hierarchy of whites and very light grays (#F5F7FA) to maintain focus on content.
- **Status Colors:** High-chroma but professional tones for feedback. Success uses a forest green; Warning uses a deep ochre; Error uses a brick red to maintain the "scholarly" feel without appearing overly alarming.

## Typography

The design system utilizes **Inter** for all roles to ensure maximum legibility across data-heavy interfaces. The typographic scale is highly structured to create a clear information hierarchy.

- **Headlines:** Use a tighter letter-spacing and heavier weights to provide strong visual anchors for page sections.
- **Body Text:** Optimised for long-form reading of reports and academic feedback.
- **Labels:** Utilized for data visualization axes, status badges, and metadata. These are often uppercase with slight tracking to differentiate them from body content.

## Layout & Spacing

This design system employs a **Fixed Grid** model for desktop to maintain an organized, document-like feel, while transitioning to a fluid model for mobile.

- **Desktop (1280px+):** 12-column grid with 24px gutters. Content is centered with generous side margins to prevent eye strain.
- **Tablet:** 8-column grid with 24px gutters and 32px side margins.
- **Mobile:** 4-column fluid grid with 16px side margins.
- **Spacing Logic:** All spacing follows an 8px linear scale. Use `lg` (40px) for section vertical padding to maintain the "plenty of whitespace" requirement.

## Elevation & Depth

To maintain a professional and scholarly tone, the design system uses **Tonal Layers** and **Low-contrast Outlines** rather than heavy shadows.

- **Level 0 (Background):** The base canvas, typically the tertiary color (#F5F7FA).
- **Level 1 (Cards/Containers):** Pure white surfaces with a 1px solid border (#E2E8F0). No shadow.
- **Level 2 (Interactive/Hover):** A very soft, diffused shadow (0px 4px 12px rgba(0, 0, 0, 0.05)) to indicate lift without breaking the clean aesthetic.
- **Separators:** 1px hairline strokes in #EDF2F7 are used to divide list items and table rows.

## Shapes

The shape language is **Soft** and disciplined. The use of a small border radius (4px to 12px) balances the clinical nature of academic data with a modern, user-friendly feel.

- **Standard Elements (Inputs, Buttons, Small Cards):** 4px (0.25rem).
- **Large Containers (Performance Cards, Modals):** 8px (0.5rem).
- **Status Badges:** Fully pill-shaped to distinguish them from interactive buttons.

## Components

### Buttons
- **Primary:** Solid Academic Blue with white text. High contrast, 4px radius.
- **Secondary:** Outline Gold with Gold text. Used for secondary actions like "Download Report."
- **Ghost:** No background, Blue text. Used for tertiary actions.

### Performance Cards
These are the core of the system. They feature a white background, Level 1 border, and a 4px Gold top-accent border for "High Performance" highlights. They include a dedicated area for data visualization (sparklines or radial progress bars).

### Status Badges
Used for grades and performance standing.
- Small, uppercase label typography.
- Backgrounds use a 10% opacity version of the status color with a 100% opacity text color for maximum readability and a professional "tinted" look.

### Input Fields
Strictly rectangular with 4px radius. Uses a 1px border (#CBD5E0) that thickens to 2px Academic Blue on focus. Labels always sit above the field in `body-sm` weight.

### Data Visualization
Charts should use a custom palette: Academic Blue for primary data, Gold for targets/benchmarks, and a sequence of muted teals and greys for comparative data. All charts must include clear legends using `label-md` styles.