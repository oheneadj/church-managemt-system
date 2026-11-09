# Foundations: Heritage Green & Gold

The visual identity. Mature, restrained, warm and timeless: deep forest green, a muted champagne gold used sparingly, and soft warm neutrals; DM Sans; subtle borders in place of heavy shadows. Think Linear's precision, Stripe's professionalism and Notion's simplicity, with a warmer, calmer character. No glass effects, gradients, neon, oversized rounding or harsh shadows. Decision D-071; the tokens are in `resources/css/app.css`; `resources/js/test/designTokens.test.ts` guards every number on this page.

## 1. Three layers of tokens

| Layer | What it is | Examples | Who may use it |
|---|---|---|---|
| Primitive | Raw scales and values | `--color-forest-900`, `--text-h2`, `--radius-lg`, `--elevation-2`, `--motion-duration-micro` | Only to build the semantic layer, and the showcase |
| Semantic | What a value is *for* | `--background`, `--foreground`, `--primary`, `--muted-foreground`, `--border`, `--input`, `--ring`, `--feedback-success` | Every component, through Tailwind utilities (`bg-primary`, `text-muted-foreground`, `border-border`) |
| Component | A value that belongs to one component, built from semantic tokens | a button's pressed surface, a card's radius | That component |

The dark theme changes only the semantic layer. Names follow the shadcn convention the components already use (`--background`, `--card`, `--primary`...), because renaming would touch every component for no gain. The spec's dotted names map as: `background.default` is `--background`, `text.primary` is `--foreground`, `text.secondary` is `--muted-foreground`, `border.subtle` is `--border`, `action.primary` is `--primary`, `status.success` is `--feedback-success`.

## 2. Colour

### Brand and neutral colours (as given)

| Token | Hex | Where it sits |
|---|---|---|
| Primary | `#183C35` | forest 900 |
| Primary hover | `#245247` | forest 800 |
| Primary soft | `#EAF0EB` | forest 100 |
| Accent gold | `#C3A46B` | gold 400 |
| Accent soft | `#F3EBDD` | gold 100 |
| Background | `#F7F5F0` | neutral 50 |
| Surface | `#FFFFFF` | |
| Surface muted | `#F1F2EE` | neutral 100 |
| Border | `#E7EBE6` | neutral 200 |
| Text primary | `#26332F` | neutral 850 |
| Text secondary | `#718079` | neutral 500 |
| Text muted | `#98A29C` | neutral 400 |
| Dark background / surface / elevated / border | `#171D1C` `#222A28` `#2B3531` `#39423E` | neutral 950 / 900 / 800 / 700 |
| Dark text primary / secondary | `#F1F2EE` `#A6B0AA` | |
| Success, Warning, Error, Information | `#287454` `#B77928` `#B94C4C` `#426B92` | base colours |

### The scales

Generated in OKLCH so the steps look evenly spaced, with the brand hex values pinned exactly at their steps (the test checks each one).

| Step | Forest (primary) | Gold (accent) | Neutral |
|---|---|---|---|
| 50 | `#F4F8F5` | `#FAF6EF` | `#F7F5F0` |
| 100 | `#EAF0EB` | `#F3EBDD` | `#F1F2EE` |
| 200 | `#CDD8D0` | `#E3D3B7` | `#E7EBE6` |
| 300 | `#B0C0B6` | `#D3BB91` | `#BFC6C0` |
| 400 | `#94A99D` | `#C3A46B` | `#98A29C` |
| 500 | `#789286` | `#A78A58` | `#718079` |
| 600 | `#5C7C70` | `#8C7145` | `#54605B` |
| 700 | `#41675B` | `#715934` | `#39423E` |
| 800 | `#245247` | `#584322` | `#2B3531` |
| 850 | | | `#26332F` |
| 900 | `#183C35` | `#3F2D12` | `#222A28` |
| 950 | `#082520` | `#33230A` | `#171D1C` |

(Step 850 exists only in the neutral scale, where the brief's text colour falls between 800 and 900.)

### Where the brief's colours cannot carry text

Not every brand colour works for every use, and the brief asked for that to be respected. These were measured, not assumed:

| Brief colour | Measured | Decision |
|---|---|---|
| Text secondary `#718079` | 3.80:1 on the page, 4.15:1 on white | Fails the 4.5:1 text rule. **Secondary text uses neutral 600 `#54605B` (6.0:1).** `#718079` is kept for the **field outline** (3:1 needed, 3.8 to 4.2 given) and large text |
| Text muted `#98A29C` | 2.63:1 | Never text. Decoration and disabled marks only |
| Accent gold `#C3A46B` | 2.37:1 on white | Never text or an essential icon. Gold that must be read is **gold 700 `#715934` (6.1:1)**; gold 400 is for rules, ornaments and fills |
| Warning `#B77928` | 3.63:1 on white | The warning **text** colour is `#985C00` (5.4:1); the base hex remains the brand warning colour for fills and icons beside text |
| Error `#B94C4C` | 5.00:1 on white, 4.5 on its tint | Text and the destructive colour use `#B14545` for margin on tinted surfaces |
| Success, Information | 5.65 and 5.60:1 | Used as given |
| In dark, success, error, information, warning | 2.6 to 4.0:1 on the dark surface | Lightened for dark (`#68B18E` `#F3807D` `#7BA6D0` `#DD9D50`), not inverted |

### Semantic tokens

| Token | Light | Dark | Used for |
|---|---|---|---|
| `--background` | `#F7F5F0` | `#171D1C` | The page |
| `--card`, `--popover` | `#FFFFFF` | `#222A28`, `#2B3531` | Surfaces; popovers and menus sit one step higher in dark |
| `--muted` | `#F1F2EE` | `#2B3531` | Quiet fills, skeletons, disabled fields |
| `--foreground` | `#26332F` (12.1:1) | `#F1F2EE` (15.2:1) | Text |
| `--muted-foreground` | `#54605B` (6.0:1) | `#A6B0AA` (7.7:1) | Secondary text, placeholders, helper text |
| `--primary` / `--primary-foreground` | `#183C35` / white (12.1:1) | `#B0C0B6` / `#171D1C` (9.0:1) | The main action |
| `--secondary`, `--accent` | `#EAF0EB` | `#2B3531` | Soft fills and hover surfaces. (shadcn's "accent" is a hover surface, not the gold) |
| `--accent-gold`, `-strong`, `-soft` | `#C3A46B`, `#715934`, `#F3EBDD` | `#C3A46B`, `#D3BB91`, a dark tint | Gold: decoration, readable gold, fill |
| `--border` | `#E7EBE6` | `#39423E` | Quiet dividers and card edges (decorative) |
| `--input` | `#718079` (3.8 to 4.2:1) | `#718079` (3.5 to 4.1:1) | The outline of a field, because a field is found by its edge |
| `--ring` | `#41675B` (5.8:1) | `#B0C0B6` (7.7:1) | The focus ring, 3px, solid |
| `--destructive` | `#B14545` (5.5:1) | `#F3807D` (5.7:1) | Destructive text and tints |
| `--feedback-{success,warning,info,error}` and `-surface`, `-border` | Each text colour is at least 4.8:1 on its own surface | Same | Toasts, banners, badges, validation |

Rules: gold is a supporting accent, used sparingly (a rule under a heading, a small ornament, a highlight), never a large fill, never a button, never text in light mode. Status is never shown by colour alone: it always has an icon or words as well. Use semantic tokens in components, never a primitive and never a hex.

## 3. Typography

DM Sans, self-hosted. The scale (size, line height, letter spacing, weight) lives in the tokens and is used as `text-display`, `text-h1` and so on; one utility sets all four.

| Style | Size / line height | Weight | Letter spacing |
|---|---|---|---|
| Hero (one per view: the number a view leads with) | 48 / 52 | 600 | -0.03em |
| Display | 36 / 44 | 600 | -0.02em |
| Heading 1 | 30 / 38 | 600 | -0.015em |
| Heading 2 | 24 / 32 | 600 | -0.01em |
| Heading 3 | 20 / 28 | 600 | -0.005em |
| Heading 4 | 18 / 26 | 600 | 0 |
| Body large | 16 / 24 | 400 | 0 |
| Body (the page default) | 14 / 22 | 400 | 0 |
| Body small | 13 / 20 | 400 | 0 |
| Caption | 12 / 18 | 400 | +0.01em |

On screens under 40rem (about 640px) Display steps down to 30px and Heading 1 to 26px. Numbers in tables and in `data-numeric` elements use tabular figures so columns line up (money already does). Inputs stay 16px on phones so a browser does not zoom the page when one is focused. Placeholder, helper and error text follow `interactions.md`.

## 3a. Control sizes

Buttons and fields share one scale, so a button beside a field lines up. Phones get the 44px touch target; larger screens get the denser size. (The first build used a 32px default; it was too small and below our own 44px rule.)

| Size | Phone | Desktop | Padding | Icon | Use |
|---|---|---|---|---|---|
| Extra small | 32px | 28px | 10px | 14px | Dense toolbars, inline chips |
| Small | 40px | 36px | 12px | 16px | Secondary actions in a row, table toolbars |
| **Default** | **44px** | **40px** | 16px | 20px | Most buttons and every field |
| Large | 44px | 44px | 20px | 20px | The main action on a form or card |
| Icon (default / small / extra small) | 44 / 40 / 32 | 40 / 36 / 28 | | 20 / 20 / 14 | Icon-only buttons; always with an accessible name |

Variants: **primary** (the main action, one per view), secondary, outline, ghost, link, **destructive** (quiet tinted red, for starting a risky action) and **danger** (solid red with light text, for the last step of a hard-to-undo action such as the confirm button in a delete dialog). Fields (`Input`, `SelectField`, `SearchBox`) are 44px on a phone and 40px on desktop, with a 16px text size on phones so a browser does not zoom.

## 4. Spacing

A 4px base: Tailwind's spacing scale, unchanged, where one step is 0.25rem. The brief's tokens map to utilities as 0 (`0`), 2 (`0.5`), 4 (`1`), 6 (`1.5`), 8 (`2`), 12 (`3`), 16 (`4`), 20 (`5`), 24 (`6`), 32 (`8`), 40 (`10`), 48 (`12`), 64 (`16`), 80 (`20`), 96 (`24`). Comfortable density: control padding 8 to 12px, gaps inside a component 4 to 8px, between related groups 16 to 24px, between sections 32 to 48px.

## 5. Radius

| Token | Size | Utility | Use |
|---|---|---|---|
| Extra small | 4px | `rounded-sm` | Tags, small marks |
| Small | 6px | `rounded-md` | Tabs, small controls |
| Medium | 8px | `rounded-lg` | **Controls: buttons, fields, menus' items** |
| Large | 12px | `rounded-xl` | **Surfaces: cards, dialogs, toasts, popovers** |
| Extra large | 16px | `rounded-2xl` | Large feature surfaces, sparingly |
| Full | 9999px | `rounded-4xl` or `rounded-full` | Badges, avatars, switches |

The utility names are shadcn's; the sizes are the brief's. Anything rounder than 16px (except full) is not used.

## 5a. Charts and stat cards

Chart colours are not the brand's muted greens and golds: those fail the chart rule that every series must be told apart (chroma at least 0.10, and separable under colour blindness), so chart hues follow the brand families (forest, gold, berry, blue, terracotta) at chart strength. The set was validated with the data-visualisation checks (lightness band, chroma floor, colour-blind and normal-vision separation of neighbouring pairs, 3:1 contrast against the card); the hex values are pinned in `designTokens.test.ts`, so a change must be re-validated, not eyeballed.

| Slot | Hue | Light | Dark | Ink on the fill (light / dark) |
|---|---|---|---|---|
| 1 | Forest | `#007D58` | `#249C74` | white / dark |
| 2 | Gold | `#B78A2C` | `#AA7E1A` | dark / dark |
| 3 | Berry | `#893365` | `#C15F95` | white / dark |
| 4 | Blue | `#3275B4` | `#5497D9` | white / dark |
| 5 | Terracotta | `#D15E49` | `#CC624E` | dark / dark |

Worst neighbouring pair under colour blindness: 11.2 (light) and 9.9 (dark), against a target of 8; all five at 3:1 or more on the card, and every ink at 4.5:1 or more on its fill. Context and "Other" use neutral 500 (`--viz-other`), the grid and baseline are hairlines one step off the surface, and a series that means bad (a loss, an error) wears `--viz-negative`, the destructive colour, never a series colour.

Rules (from the data-visualisation method):
- **One hue for one series**, slots 1 to 5 in order for several, never cycled; a sixth folds into "Other" or becomes small charts. Colour follows the series, never its rank.
- **Pick the form first:** a single number is a stat card, not a one-bar chart; a whole in a few parts is a segmented bar (six parts at most), not a pie; many parts, or close values, are ranked bars or a table.
- **Thin marks:** columns at most 24px wide with a 4px rounded top on a square baseline; lines 2px; markers 8px with a 2px ring in the card's colour; a 2px gap of the card's colour between touching fills; areas at 10 percent. Never a border drawn around a mark.
- **Labels:** a legend for two or more series (none for one: the title names it), values labelled only at the end or the extreme, text always in text colours (never the series colour), and a label printed on a fill only where it fits, in that fill's ink.
- **Always readable another way:** every chart sits in a figure with a Chart / Table switch, hover and keyboard readouts repeat what the table says and never gate it, and a refresh keeps the old chart dimmed instead of blanking it.
- **Stat card designs:** trend (the default), icon (with a mini column chart), progress (one number against its goal, as a real meter whose track is a lighter step of the fill) and featured (the deep filled card with the one 48px hero figure and a fine gold rule; exactly one per view). The icon only helps scanning and never carries meaning; on the filled card the arrow and the words carry the change.
- **Stat card:** label in sentence case, the number in proportional figures, a change as an arrow plus signed words against a named period (never colour alone; the colour is what the change means, so an increase in something bad is red), and an optional 12-point trend. The card is its own container: the trend sits beside the number when the card is wide enough and drops under it when it is not.

## 6. Elevation

| Level | Token | Use |
|---|---|---|
| 0 | `--elevation-0` (none) | Flat surface, the page |
| 1 | `--elevation-1` | Cards, inputs at rest: a hairline of shadow |
| 2 | `--elevation-2` (`shadow-md`) | Floating: menus, popovers, tooltips |
| 3 | `--elevation-3` (`shadow-lg`) | Overlays: dialogs, drawers |
| 4 | `--elevation-4` (`shadow-xl`) | High priority: confirmations, toasts that need attention |

Shadows are low-opacity and neutral (the dark green-black `rgb(23 29 28)` in light, black in dark) with the same shape in both themes; a 1px border does most of the work, so a surface is never defined by shadow alone.

## 7. Motion

Fast 100ms, standard 150ms, moderate 200ms, slow 300ms, and 80ms for a press. Natural easing (`--motion-ease-standard`, `-enter`, `-exit`). The rules, reduced motion and per-component behaviour are in `interactions.md` sections 2 and 11 and `accessibility.md`.

| Brief | Token | Value |
|---|---|---|
| (press) | `--motion-duration-instant` | 80ms |
| Fast | `--motion-duration-micro` | 100ms |
| Standard | `--motion-duration-component` | 150ms |
| Moderate | `--motion-duration-overlay` | 200ms |
| Slow | `--motion-duration-content` | 300ms |

## 8. Iconography

**Lucide** only, as outline icons, never mixed with another library and never decorative for its own sake. Default 20px, small 16px, large 24px; stroke 1.75 to 2px (Lucide's default 2 is used). Icons beside text align to the text's centre with an 8px gap (6px in small buttons) and carry `aria-hidden="true"` unless they stand alone, in which case the control has an accessible name. Many existing buttons use 16px icons, which is the small size; icon sizes are tidied as screens are touched (D-070 step 4).

## 9. Themes

Light and dark are both complete and keep the same identity, hierarchy and behaviour. Dark is not an inversion: its surfaces are the given dark greens, the primary becomes a lighter sage so it can be read, status colours are lightened for contrast, and shadows are stronger because a dark page hides weak ones. The `dark` class on the root element turns it on (the showcase has a switch). Choosing the theme per person is not built yet.

## 10. Usage rules and anti-patterns

- Gold as a button, a large fill, or text in light mode
- A hex, an `rgb()`, a raw Tailwind palette colour (`bg-red-100`) or a primitive in a component: use a semantic token
- `#718079` or `#98A29C` for body text, helper text or placeholders; use `--muted-foreground`
- A status shown only by colour
- A heavy or coloured shadow; a gradient; glass; a corner rounder than 16px (except full)
- A second icon library; a decorative icon with no job
- Text under 12px; a heading style that is not in the scale; body text that is not 14px or 16px
- Inverting the light theme to make the dark one
- A dual-axis chart, a rainbow of series, a number on every point, a pie or donut for close values, or a chart with no table behind it
