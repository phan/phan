# OpenWolf Reframe — UI Framework Knowledge Base

When the user asks to change, pick, migrate, or "reframe" their UI framework, use this file to guide the conversation and generate the right migration prompt.

## Decision Questions

Ask these in order. Stop early if the answer narrows to 1-2 frameworks.

1. **What framework/library does your project currently use?** (React, Vue, Svelte, plain HTML, etc.)
2. **What's your priority?** (a) Stunning animations, (b) Fast setup / ship quickly, (c) Full design control, (d) Accessibility-first, (e) Business/enterprise look
3. **Do you use Tailwind CSS already?** (Yes → most options work. No → Chakra UI or DaisyUI are easier without it)
4. **Light mode, dark mode, or both?**
5. **Is this a landing page, a dashboard/app, or both?**

## Quick Selection Guide

| Priority | Recommended |
|----------|-------------|
| Fastest setup | DaisyUI, Preline UI |
| Stunning animations | Aceternity UI, Magic UI |
| Maximum control | shadcn/ui, Headless UI |
| Most free components | Origin UI (400+) |
| Multi-framework (React + Vue + Svelte) | Park UI, DaisyUI, Flowbite |
| AI product aesthetic | Cult UI |
| Polished defaults + accessibility | HeroUI, Chakra UI |
| Business/enterprise | Flowbite, shadcn/ui |

## Comparison Matrix

| Framework | Styling | Animation | Setup | Best For | Cost |
|-----------|---------|-----------|-------|----------|------|
| shadcn/ui | Tailwind + Radix | Minimal | Medium | Full apps, dashboards | Free |
| Aceternity UI | Tailwind + Framer Motion | Heavy, cinematic | Easy (copy-paste) | Animated landing pages | Free / Pro |
| Magic UI | Tailwind + Motion | Purposeful, polished | Easy (shadcn CLI) | SaaS marketing | Free / Pro |
| DaisyUI | Tailwind plugin | CSS transitions | Very easy | Rapid prototyping | Free |
| HeroUI | Tailwind + React Aria | Smooth built-in | Easy | React apps, accessible | Free |
| Chakra UI | CSS-in-JS (Emotion) | Built-in transitions | Easy | Themed React apps | Free |
| Flowbite | Tailwind plugin | CSS transitions | Very easy | Multi-framework, business | Free / Pro |
| Preline UI | Tailwind plugin | CSS + minimal JS | Very easy | Speed-focused builds | Free / Pro |
| Park UI | Tailwind + Ark UI | Minimal | Medium | Multi-framework, design systems | Free |
| Origin UI | Tailwind + shadcn | Minimal | Easy (copy-paste) | Max component variety | Free |
| Headless UI | Custom Tailwind | Custom (Transition) | Medium-Hard | Full design control | Free |
| Cult UI | Tailwind + shadcn | Modern, subtle | Medium | AI apps, full-stack | Free |

---

## Framework Prompts

After the user selects a framework, use the corresponding prompt below. **Adapt it to the user's actual project** — replace generic references with their real file structure, existing routes, and components from `.wolf/anatomy.md`.

---

### shadcn/ui

**Stack:** React, Tailwind CSS, Radix UI
**Install:** `npx shadcn@latest init`
**Site:** ui.shadcn.com

```
Build a modern, minimalist landing page using Next.js 14+ (App Router), TypeScript, Tailwind CSS, and shadcn/ui components.

ARCHITECTURE:
- Use Next.js App Router with server components by default, client components only when interactivity is needed
- Organize: /app for routes, /components/ui for shadcn primitives, /components/sections for page sections, /lib for utilities
- Use CSS variables for theming via shadcn's built-in system

DESIGN PRINCIPLES:
- Minimalist with generous whitespace (py-24 to py-32 section spacing)
- Max-width container (max-w-6xl mx-auto) with responsive padding
- Typography hierarchy: text-5xl/6xl hero → text-3xl section titles → text-lg body
- Muted color palette with ONE bold accent color for CTAs
- Subtle micro-interactions on hover (scale, opacity transitions)
- Dark mode support via shadcn's theme provider

SECTIONS TO BUILD:
1. Navbar — sticky, transparent-to-solid on scroll, logo left, nav center, CTA button right
2. Hero — large headline (max 8 words), subtext (max 2 lines), primary CTA button + secondary ghost button, optional hero image or abstract illustration
3. Social proof — logo cloud of 5-6 partner/client logos in grayscale
4. Features — 3-column bento grid with icon, title, description per card using shadcn Card component
5. How it works — 3-step numbered process with connecting line
6. Testimonials — carousel or grid of 3 testimonial cards with avatar, quote, name, role
7. Pricing — 3-tier pricing table using shadcn Card, highlight the recommended plan with ring/border accent
8. FAQ — accordion using shadcn Accordion component
9. CTA — full-width section with headline, subtext, and primary action button
10. Footer — 4-column grid (brand, product links, company links, legal), copyright at bottom

COMPONENT USAGE:
- Button (primary, secondary, ghost, outline variants)
- Card, CardHeader, CardContent, CardFooter
- Accordion, AccordionItem, AccordionTrigger, AccordionContent
- Badge for labels/tags
- Separator for dividers
- NavigationMenu for navbar

CODE QUALITY:
- Fully responsive (mobile-first)
- Semantic HTML (section, nav, main, footer)
- Accessible (proper aria labels, keyboard navigation)
- Performant (lazy load images, optimize fonts via next/font)
- Clean component separation (each section is its own component)
```

---

### Aceternity UI

**Stack:** React, Next.js, Tailwind CSS, Framer Motion
**Install:** Copy-paste or `npx shadcn@latest add [component]`
**Site:** ui.aceternity.com

```
Build a visually stunning, animation-rich landing page using Next.js 14+, TypeScript, Tailwind CSS, and Aceternity UI components with Framer Motion.

ARCHITECTURE:
- Next.js App Router with client components for animated sections
- Organize: /app, /components/aceternity (copied components), /components/sections, /lib
- Install Framer Motion for animation engine

DESIGN PRINCIPLES:
- Dark theme as primary (bg-black/bg-slate-950 backgrounds)
- Dramatic contrast with glowing accents (cyan, purple, or emerald)
- Layered depth via gradients, glows, and glassmorphism
- Cinematic scroll-triggered animations with staggered reveals
- Generous padding (py-32+), large typography (text-6xl/7xl hero)
- Noise/grain texture overlays for visual richness

SECTIONS TO BUILD WITH ACETERNITY COMPONENTS:
1. Navbar — floating navbar with backdrop blur, animated on scroll
2. Hero — use Aceternity's "Spotlight" or "Lamp" effect as background, massive headline with TextGenerateEffect or TypewriterEffect, gradient text on key words, two CTAs with HoverBorderGradient buttons
3. Logo cloud — use InfiniteMovingCards for auto-scrolling partner logos
4. Features — BentoGrid layout with animated cards, each card has BackgroundGradient or CardSpotlight hover effect
5. Product showcase — use Aceternity's 3DCard or ParallaxScroll for product screenshots/mockups
6. Testimonials — AnimatedTestimonials or InfiniteMovingCards with quote, avatar, name
7. How it works — use TracingBeam component for a connected step-by-step flow
8. Pricing — dark cards with GlowingStarsBackground, highlighted tier has animated border (MovingBorder)
9. CTA — full-width with BackgroundBeams or Meteors effect behind headline and button
10. Footer — clean 4-column layout with subtle separator

ANIMATION GUIDELINES:
- Page load: stagger hero elements (title → subtitle → buttons) with 0.15s delay each
- Scroll: use Framer Motion's whileInView for reveal animations (fade up + slight scale)
- Hover: cards lift (translateY -4px) with glow intensification
- Keep animations performant (use transform/opacity only, will-change where needed)
- Respect prefers-reduced-motion

CODE QUALITY:
- Mobile-first responsive design
- Lazy load heavy animation components
- Each section is a standalone component file
- Accessible despite heavy animation (aria labels, focus states)
```

---

### Magic UI

**Stack:** React, TypeScript, Tailwind CSS, Motion (Framer Motion)
**Install:** `npx shadcn@latest add [component]` (shadcn CLI compatible)
**Site:** magicui.design

```
Build a polished, trend-forward SaaS landing page using Next.js 14+, TypeScript, Tailwind CSS, and Magic UI animated components.

ARCHITECTURE:
- Next.js App Router, server components where possible
- Magic UI components installed via shadcn CLI into /components/magicui
- Section components in /components/sections
- Shared config in /lib (cn utility, site config)

DESIGN PRINCIPLES:
- Clean and modern, inspired by Linear/Vercel aesthetic
- Light or dark mode with smooth toggle transition
- Restrained animation — purposeful motion that guides attention, not distracts
- High contrast text, muted backgrounds (slate-50 light / slate-950 dark)
- Monospace or geometric sans-serif for headings, clean sans for body
- ONE signature color (blue-500, violet-500, or emerald-500) for accents

SECTIONS TO BUILD WITH MAGIC UI COMPONENTS:
1. Navbar — clean horizontal nav, use MagicUI ShimmerButton for primary CTA
2. Hero — large headline with AnimatedGradientText on key phrase, NumberTicker for a live stat ("10,000+ users"), DotPattern or GridPattern as subtle background, two buttons (ShimmerButton primary + outline secondary)
3. Social proof — Marquee component for auto-scrolling logos horizontally
4. Features — use MagicCard components in a 3-column grid, each with icon + title + description, subtle hover glow effect
5. Bento showcase — BentoGrid layout showing product capabilities, mix of large and small tiles with different content types (text, mini-demos, stats)
6. Metrics — AnimatedList showing live activity feed OR NumberTicker showing key stats (users, uptime, speed)
7. Testimonials — Marquee of testimonial cards (vertical or horizontal scroll)
8. Pricing — 3 tiers with clean card design, NeonGradientCard for featured plan, BorderBeam on hover
9. CTA — centered layout with AnimatedShinyText headline, Particles or Meteors background effect
10. Footer — minimal 3-4 column grid, muted text, social icons

ANIMATION STRATEGY:
- Use BlurIn / FadeIn for section entrance animations
- NumberTicker for any statistics or metrics
- Marquee for any horizontally scrolling content
- Keep transitions under 600ms, use ease-out curves
- Mobile: reduce or disable particle/meteor effects for performance

CODE QUALITY:
- Fully responsive with mobile breakpoints
- Semantic HTML throughout
- next/font for optimized typography
- Image optimization via next/image
- Component-per-section architecture
```

---

### DaisyUI

**Stack:** Tailwind CSS plugin (framework-agnostic)
**Install:** `npm i -D daisyui` + add to tailwind.config
**Site:** daisyui.com

```
Build a clean, professional landing page using Tailwind CSS and DaisyUI component classes.

ARCHITECTURE:
- Framework of choice with Tailwind CSS + DaisyUI plugin
- Use DaisyUI's semantic class system (btn, card, hero, navbar, etc.)
- Select a DaisyUI theme (or create custom) in tailwind.config.js
- Organize by sections in /components/sections

DESIGN PRINCIPLES:
- Pick ONE DaisyUI theme as base (e.g., "winter" for clean light, "night" for dark, "corporate" for professional)
- Customize theme colors in tailwind.config to match brand
- Minimalist layout with clean spacing (p-8 to p-16 sections)
- Let DaisyUI's built-in design system handle consistency
- Typography via DaisyUI's prose class for content sections

SECTIONS TO BUILD WITH DAISYUI CLASSES:
1. Navbar — "navbar" component with "dropdown" for mobile menu, "btn btn-primary" for CTA
2. Hero — "hero" component with "hero-content", large headline, subtext, "btn btn-primary" + "btn btn-ghost"
3. Social proof — logo row using "flex" with "opacity-50 grayscale" on images
4. Features — "card" components in a responsive grid, each with "card-body", uses "bg-base-200" for contrast
5. Stats — "stats" component with "stat" items showing key metrics
6. Steps — "steps" component (horizontal on desktop, vertical on mobile)
7. Testimonials — "chat" component styled as testimonials OR "card" grid with "avatar"
8. Pricing — "card" components with "badge badge-primary" for recommended plan
9. FAQ — "collapse" or "collapse-plus" components for expandable Q&A
10. Footer — "footer" component with 3-4 columns, "footer-title" for headings

CODE QUALITY:
- Fully responsive using Tailwind breakpoints
- Zero custom CSS needed (DaisyUI handles it)
- Accessible by default (DaisyUI follows ARIA patterns)
- Fast load times (CSS-only, no JS shipped)
- Works with any framework or plain HTML
```

---

### HeroUI (formerly NextUI)

**Stack:** React, Tailwind CSS, React Aria
**Install:** `npm i @heroui/react`
**Site:** heroui.com

```
Build an elegant, accessible landing page using Next.js 14+, TypeScript, Tailwind CSS, and HeroUI components.

ARCHITECTURE:
- Next.js App Router with HeroUI Provider wrapping the app
- Import only needed components from @heroui/react (tree-shakeable)
- Organize: /app, /components/sections, /lib
- Use HeroUI's built-in theme system for consistent styling

DESIGN PRINCIPLES:
- Soft, modern aesthetic with rounded corners and smooth surfaces
- Light mode primary with dark mode support via HeroUI's theme toggle
- Subtle shadows and blur effects (HeroUI's built-in elevation system)
- Clean sans-serif typography, generous line heights
- Smooth transitions on all interactive elements (HeroUI provides these by default)

SECTIONS TO BUILD WITH HEROUI COMPONENTS:
1. Navbar — HeroUI Navbar with NavbarBrand, NavbarContent, NavbarItem, NavbarMenuToggle for mobile
2. Hero — large headline, descriptive subtext, Button (color="primary" size="lg") + Button (variant="bordered")
3. Social proof — Avatar group for client logos or Chip components for trust badges
4. Features — Card components in grid, each with CardHeader, CardBody, use Divider between sections
5. Product demo — Tabs component to show different product views/features interactively
6. Testimonials — Card grid with User component (avatar + name + role) inside each card
7. Pricing — Card components with Chip for "Popular" badge, Listbox for feature lists
8. FAQ — Accordion component with AccordionItem for each question
9. CTA — centered section with Input + Button combo for email capture
10. Footer — clean grid with Link components, Divider above copyright

CODE QUALITY:
- Fully responsive with HeroUI's responsive props
- Accessible by default (React Aria foundation)
- Server-side rendering compatible
- Optimized bundle (import individual components)
- TypeScript strict mode
```

---

### Chakra UI

**Stack:** React, Emotion (CSS-in-JS)
**Install:** `npm i @chakra-ui/react`
**Site:** chakra-ui.com

```
Build a clean, accessible landing page using Next.js 14+, TypeScript, and Chakra UI v3.

ARCHITECTURE:
- Next.js App Router with ChakraProvider at root
- Use Chakra's design tokens and theme extension for brand customization
- Organize: /app, /components/sections, /theme (custom theme config)

DESIGN PRINCIPLES:
- Clean and warm aesthetic using Chakra's polished defaults
- Consistent spacing using Chakra's space scale (4, 8, 12, 16, 20, 24)
- Typography: use Chakra's Heading and Text components
- Color mode support (light/dark) via useColorMode hook
- Accessible-first: every component follows WAI-ARIA by default

SECTIONS TO BUILD WITH CHAKRA COMPONENTS:
1. Navbar — Flex with HStack, IconButton for mobile menu, Button for CTA
2. Hero — VStack centered, Heading size="4xl", Text, HStack with two Buttons
3. Social proof — HStack/Wrap of grayscale Image components
4. Features — SimpleGrid columns={{base:1, md:3}} of VStack cards with Icon, Heading, Text
5. Stats — Stat component group with StatLabel, StatNumber, StatHelpText
6. Testimonials — SimpleGrid of Card with Avatar, Text (quote), Heading (name)
7. Pricing — SimpleGrid of Card, Badge for "Popular", List for features
8. FAQ — Accordion with AccordionItem, AccordionButton, AccordionPanel
9. CTA — Box with bg gradient, VStack centered, Heading, Text, Button
10. Footer — SimpleGrid columns={{base:2, md:4}} with VStack, Heading, Link

THEME EXTENSION:
const theme = extendTheme({
  colors: { brand: { 50: '...', 500: '...', 900: '...' } },
  fonts: { heading: 'Your Font', body: 'Your Font' },
  components: { Button: { defaultProps: { colorScheme: 'brand' } } }
})

CODE QUALITY:
- Fully responsive using Chakra's responsive array/object syntax
- Dark mode ready with useColorModeValue
- Accessible by default
- TypeScript throughout with proper prop types
```

---

### Flowbite

**Stack:** Tailwind CSS (React, Vue, Svelte, Angular)
**Install:** `npm i flowbite flowbite-react`
**Site:** flowbite.com

```
Build a professional, conversion-optimized landing page using Next.js 14+, Tailwind CSS, and Flowbite React components.

ARCHITECTURE:
- Next.js App Router with Flowbite React components
- Import from flowbite-react (Button, Card, Navbar, Footer, etc.)
- Flowbite Tailwind plugin in tailwind.config for interactive styles

DESIGN PRINCIPLES:
- Business-professional aesthetic (clean, trustworthy, conversion-focused)
- Cool neutral palette (slate/gray) with strong brand accent
- Focus on readability and clear information hierarchy

SECTIONS TO BUILD WITH FLOWBITE COMPONENTS:
1. Navbar — Flowbite Navbar with Navbar.Brand, Navbar.Toggle, Navbar.Collapse
2. Hero — Jumbotron-style, large Heading, Button group
3. Social proof — row of client logos
4. Features — Card components in responsive grid with icon badge, title, description
5. Content — alternating image + text rows
6. Testimonials — Blockquote or Card with Rating component for stars
7. Pricing — Card with List for features, Badge for highlighted plan
8. FAQ — Accordion component
9. Newsletter CTA — TextInput + Button in a styled Banner
10. Footer — Flowbite Footer with Footer.LinkGroup, Footer.Brand, Footer.Copyright

CODE QUALITY:
- Fully responsive (components are responsive by default)
- Accessible (follows WAI-ARIA)
- Works server-side with Next.js
- Production-ready component patterns
```

---

### Preline UI

**Stack:** Tailwind CSS (HTML-first, React/Vue plugins)
**Install:** `npm i preline` + add plugin to tailwind.config
**Site:** preline.co

```
Build a sleek, modern landing page using Next.js 14+, Tailwind CSS, and Preline UI components.

ARCHITECTURE:
- Next.js App Router with Preline's Tailwind plugin
- Use Preline's HTML component patterns (class-based, minimal JS)
- Import preline JS for interactive components (dropdowns, modals, accordions)

DESIGN PRINCIPLES:
- Modern SaaS aesthetic (clean lines, airy spacing, professional)
- Neutral base (white/slate) with vibrant accent for CTAs
- Large section padding (py-16 to py-24)

SECTIONS TO BUILD WITH PRELINE PATTERNS:
1. Navbar — header component with mega menu support, sticky on scroll
2. Hero — centered or split layout, large headline with gradient text, two CTA buttons
3. Social proof — logo ticker or static row with grayscale filter
4. Features — icon + text cards in 3-column grid
5. Tabs showcase — tab component showing different product features per tab
6. Testimonials — grid or slider of testimonial cards
7. Pricing — pricing table pattern with toggle for monthly/annual
8. Stats — counter section with large numbers + labels
9. FAQ — accordion component with smooth expand/collapse
10. CTA + Footer — email input + button CTA above multi-column footer

CODE QUALITY:
- Fully responsive with Tailwind breakpoints
- Accessible (follows ARIA standards)
- Lightweight (CSS + minimal JS)
- Works with any framework via HTML classes
```

---

### Park UI

**Stack:** React/Vue/Solid, Ark UI (headless), Tailwind CSS
**Install:** Copy-paste components (shadcn-style)
**Site:** park-ui.com

```
Build a refined, design-system-driven landing page using Next.js 14+, TypeScript, Tailwind CSS, and Park UI components.

ARCHITECTURE:
- Next.js App Router with Park UI components (Ark UI + Tailwind CSS)
- Copy-paste component installation (like shadcn)
- Organize: /app, /components/ui (Park UI primitives), /components/sections

DESIGN PRINCIPLES:
- Polished and systematic (design-system quality)
- Neutral, sophisticated palette with deliberate accent usage
- Consistent spacing and sizing via Park UI's token scale
- Light/dark mode via Park UI's built-in theme tokens

SECTIONS TO BUILD WITH PARK UI COMPONENTS:
1. Navbar — navigation components, clean horizontal layout, mobile drawer
2. Hero — Heading + Text with proper token sizing, Button pair (solid + outline)
3. Social proof — subtle logo row with muted styling
4. Features — Card components in grid with Icon, Heading, Text
5. Tabs showcase — Tabs component for interactive product feature display
6. Testimonials — Card-based layout with Avatar + quote
7. Pricing — Card grid with Badge for recommended tier
8. FAQ — Accordion component (Ark UI primitive, styled via Park UI)
9. CTA — centered section with Input + Button
10. Footer — grid layout with Link groups and Separator

CODE QUALITY:
- Fully responsive and accessible
- Ark UI headless primitives for rock-solid accessibility
- TypeScript strict mode
- Component-per-section architecture
```

---

### Origin UI

**Stack:** React, Tailwind CSS, shadcn/ui foundation
**Install:** Copy-paste (400+ free components)
**Site:** originui.com

```
Build a polished, component-rich landing page using Next.js 14+, TypeScript, Tailwind CSS, and Origin UI components (built on shadcn/ui).

ARCHITECTURE:
- Next.js App Router with shadcn/ui base + Origin UI component variants
- Copy-paste Origin UI components into /components/ui
- Origin UI extends shadcn patterns with 400+ variant options

DESIGN PRINCIPLES:
- Professional and versatile (many style variants per component)
- Pick a cohesive subset of variants that match your brand
- Consistent spacing and typography aligned with shadcn's token system
- Focus on component quality and consistency over flashy animation

SECTIONS TO BUILD:
1. Navbar — choose from Origin UI's multiple navbar variants
2. Hero — select a hero variant (centered, split, with background pattern)
3. Social proof — logo cloud variant
4. Features — pick feature section variant (grid, list, bento, with images)
5. Content — alternating image/text sections
6. Testimonials — choose testimonial variant (cards, quotes, carousel)
7. Pricing — select pricing table variant (simple, comparison, with toggle)
8. FAQ — accordion variant
9. CTA — pick CTA variant (centered, with input, split layout)
10. Footer — choose footer variant (simple, multi-column, with newsletter)

CODE QUALITY:
- Fully responsive (all components are mobile-first)
- Accessible (shadcn/ui + Radix UI foundation)
- TypeScript throughout
- Minimal dependencies
```

---

### Headless UI

**Stack:** React or Vue, Tailwind CSS (by Tailwind Labs)
**Install:** `npm i @headlessui/react`
**Site:** headlessui.com

```
Build a custom-designed, fully accessible landing page using Next.js 14+, TypeScript, Tailwind CSS, and Headless UI for interactive components.

ARCHITECTURE:
- Next.js App Router with Tailwind CSS for all styling
- Headless UI only for interactive behavior (menus, dialogs, tabs, disclosure, transitions)
- All visual design is custom Tailwind — Headless UI provides zero styling
- Organize: /app, /components/ui (custom-styled wrappers), /components/sections

DESIGN PRINCIPLES:
- Completely custom aesthetic — you control every pixel
- Minimalist, editorial-quality design (think Stripe, Linear, Vercel)
- Monochrome base with ONE signature accent color
- Large whitespace, precise typography, meticulous alignment
- Every interactive element is keyboard-navigable and screen-reader friendly

SECTIONS TO BUILD:
1. Navbar — custom with Headless UI Popover for dropdowns, Disclosure for mobile menu
2. Hero — pure Tailwind, dramatic typography, clean CTAs
3. Social proof — pure Tailwind logo row
4. Features — custom card grid, optional Tab component for tabbed showcase
5. Product showcase — TabGroup for interactive product views
6. Testimonials — pure Tailwind card grid
7. Pricing — RadioGroup for plan selection, Switch for monthly/annual toggle
8. FAQ — Disclosure component for accessible expand/collapse
9. CTA — pure Tailwind centered section
10. Footer — pure Tailwind grid layout

HEADLESS UI COMPONENTS TO LEVERAGE:
- Popover — navbar dropdowns
- Disclosure — mobile menu, FAQ items
- Dialog — modals/overlays
- Transition — smooth enter/leave animations
- RadioGroup — plan selection
- Switch — toggles
- TabGroup — tabbed content

CODE QUALITY:
- 100% accessible (Headless UI's core guarantee)
- Fully responsive custom Tailwind design
- Keyboard navigation on all interactive elements
- TypeScript with proper generics
- Zero design dependencies — total creative control
```

---

### Cult UI

**Stack:** React, Next.js, Tailwind CSS, shadcn/ui foundation
**Install:** Copy-paste or CLI
**Site:** cult-ui.com

```
Build a modern, AI-forward landing page using Next.js 14+, TypeScript, Tailwind CSS, and Cult UI components.

ARCHITECTURE:
- Next.js App Router with Cult UI components (extends shadcn/ui)
- Full-stack ready (Cult UI includes backend-aware patterns)
- Use Cult UI's AI-specific blocks if building an AI product

DESIGN PRINCIPLES:
- Tech-forward, developer-centric aesthetic
- Dark mode primary with high-contrast accents
- Modern gradients and subtle glow effects
- Clean code-like typography (mono fonts for accents)
- Purposeful animation that communicates AI/tech sophistication

SECTIONS TO BUILD:
1. Navbar — modern floating nav with glass effect
2. Hero — bold headline with animated text effect, live demo or interactive element, gradient mesh background
3. Features — AI blocks for feature cards, animated icons or micro-interactions
4. Live demo — interactive component showing product in action
5. Code showcase — syntax-highlighted code examples using Cult UI's code block components
6. Testimonials — modern card layout with subtle animation
7. Pricing — clean tier comparison with feature matrix
8. Open source / community — GitHub stars counter, contributor avatars
9. CTA — compelling action section with email input or waitlist signup
10. Footer — developer-friendly footer with docs links, API reference, status page

CODE QUALITY:
- Full-stack patterns (API routes + client components)
- TypeScript strict mode throughout
- Accessible interactive components
- Production-ready architecture patterns
```
