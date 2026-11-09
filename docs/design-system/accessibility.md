# Accessibility and Reduced Motion

Target: **WCAG 2.2 level AA** for every component and flow.

| Area | Requirement |
|---|---|
| Keyboard | Everything usable without a mouse, in a logical order, with no trap (except a modal, which traps on purpose and releases on close) |
| Focus | A visible focus ring on every focusable control (`--focus-ring`), at least 3:1 against what it sits on, and not hidden under sticky headers (2.4.11) |
| Targets | At least 24 by 24 px (2.5.8), and 44 px for the main touch controls |
| Names and roles | Every control has an accessible name; icon-only buttons are labelled; native elements are used before ARIA |
| States | `aria-invalid`, `aria-describedby`, `aria-expanded`, `aria-pressed`, `aria-checked`, `aria-busy`, `aria-sort`, `aria-current` kept in step with what is on screen |
| Announcements | Toasts and results are announced (`role="status"` or `alert`); loading is announced once; changed counts and search results are announced |
| Errors | Written in text, tied to their field, summarised at the top of a long form with links to each field; focus goes to the first invalid field on submit |
| Contrast | Text 4.5:1 (3:1 for large text), UI parts and icons 3:1, in light **and** dark; checked for each feedback token |
| Colour | Never the only carrier of meaning: add an icon, text or shape |
| Motion | See below |
| Dialogs | Focus moves in on open and returns to the trigger on close; Escape closes when safe; the background is inert |
| Forms | A visible label for every field; autocomplete tokens; no change of context on focus; consistent help |
| Authentication | Password managers, paste and autofill work; no cognitive test is required (3.3.8) |
| Zoom | Usable at 200 percent and at 375 px wide with no sideways scrolling |

## Reduced motion

When `prefers-reduced-motion: reduce` is on:
- Movement (slides, scales, parallax, shimmer travel) is removed. Fades of a short length may stay.
- Duration tokens used for movement fall to near zero in one place in the CSS, so components do not each handle it.
- Meaningful feedback stays: colour, icon and text still change, a toast still appears, a spinner still turns (slowly).
- Nothing important depends on an animation being seen.

## How it is tested

| Check | Where |
|---|---|
| Static lint for missing labels and roles | ESLint `jsx-a11y` (running) |
| Behaviour with roles and labels, keyboard and focus | Testing Library tests that query by role and name (running) |
| axe scan of the showcase and key flows in a real browser | Playwright with axe (to build, step 3) |
| Keyboard-only and zoomed passes, light and dark, on a real phone | Manual, noted in the change's acceptance report |
