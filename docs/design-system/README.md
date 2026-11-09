# Design System: Interaction, Feedback and Motion

> The visual look of the app is set by `resources/css/app.css` (colour tokens) and the shared components in `resources/js/components/ui/` and `components/form/`. This folder is the other half: how every control **behaves**. It is a contract, not a suggestion: see `CONTRACT.md`. Decision: D-070.

Stack these rules are written for: React 19, TypeScript, Tailwind CSS v4, shadcn/ui on Radix, TanStack Query, React Hook Form with Zod, Sonner for toasts, Vitest with Testing Library, Playwright. (The brief this came from assumed Vue; the rules are framework-neutral and are written here as React.)

| File | What it holds |
|---|---|
| [`foundations.md`](./foundations.md) | The visual identity, Heritage Green & Gold: colour scales and semantic tokens (light and dark, with measured contrast), type, spacing, radius, elevation, motion, icons, themes, and the rules and anti-patterns |
| [`CONTRACT.md`](./CONTRACT.md) | The mandatory rules, how each is checked, and the exception process |
| [`interactions.md`](./interactions.md) | The interaction lifecycle, the motion and interaction tokens, and the behaviour of every component family |
| [`accessibility.md`](./accessibility.md) | WCAG 2.2 AA requirements and reduced motion |
| [`ux-writing.md`](./ux-writing.md) | How messages are written, with before and after examples |
| [`acceptance-criteria.md`](./acceptance-criteria.md) | The checklist a component or feature must pass, and the anti-patterns that fail it |

## What is built and what is still to build

This is honest on purpose, so the contract is never claimed to be enforced where it is not.

| Part | State |
|---|---|
| Heritage Green & Gold: colour scales 50 to 950, semantic tokens in light and dark, type scale, radius, elevation, motion values | **Built** (D-071, `resources/css/app.css`), guarded by `designTokens.test.ts`. Component-by-component visual standards and a foundations section in the showcase are the next step |
| Shared `Button`, `Input`, `Dialog`, `AlertDialog`, `Tabs`, `Skeleton`, `Toaster` (Sonner), `ConfirmDialog`, `EmptyState`, `ErrorNotice`, `SearchBox`, `useDebouncedValue` | Built, but not yet to this spec in every state |
| Motion and interaction tokens, feedback colours, reduced-motion rules | **Built** (step 2a, `resources/css/app.css`), guarded by `resources/js/test/designTokens.test.ts` |
| Control sizes (44px on phones, 40px on desktop), the solid `danger` button, deep solid toasts per kind | **Built** (D-071 follow-up) |
| `Table`, `SortableHead`, `TableRowActions`, `TableSkeletonRows`, `TableEmpty` and the Tables section of the showcase | **Built**. The four report tables still use plain markup and move to it as they are touched (step 4) |
| `Button` loading state: blocks a second press, keeps focus and width, spinner only after 400 ms | **Built** (step 2b). The optional "Saved" success flash is not built yet; screens still use `disabled={isPending}` and are moved to `loading` as they are touched (step 4) |
| `PasswordInput` and `PasswordField` (show and hide, focus and caret kept, 44 px eye) and `PasswordRequirements` (length, ticked as typed) | **Built** (step 2c) and used by sign-in, platform sign-in, choose a password, and start a church. No strength meter and no "passwords match" line yet; matching is still checked on submit |
| DM Sans as the primary font, self-hosted | **Built** (step 2a). Metric-matched fallback overrides are **not** applied: the real font metrics have not been measured, so the system stack is used and a small shift on first load is possible |
| Focus ring contrast | **Fixed** (step 2a): `--ring` was 2.6:1 against white, now 4.8:1, and components use it solid |
| Field outline contrast | **Fixed** (D-071): `--input` is `#718079`, 3.8 to 4.2:1 in light and 3.5 to 4.1:1 in dark |
| Component showcase page at `/design-system` (buttons, fields, password, toasts, loading, dialogs, tabs, motion), with no business content | **Built** (step 3a). Present while developing (`npm run dev`) or in a build made with `VITE_DESIGN_SYSTEM_SHOWCASE=true`; a normal production build leaves it out, and CI fails if it does not (`scripts/check-showcase-excluded.sh`) |
| Automated checks: axe in Playwright, a hard-coded colour and duration check, screenshots, required status on the main branch | **To build** (step 3b). ESLint with `jsx-a11y`, TypeScript, Vitest, the token contrast test and the showcase exclusion check already run in CI |

Until a part is built, new work must still follow the spec using the closest existing component, and must not add a second pattern.
