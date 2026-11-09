# Design System: Interaction, Feedback and Motion

> The visual look of the app is set by `resources/css/app.css` (colour tokens) and the shared components in `resources/js/components/ui/` and `components/form/`. This folder is the other half: how every control **behaves**. It is a contract, not a suggestion: see `CONTRACT.md`. Decision: D-070.

Stack these rules are written for: React 19, TypeScript, Tailwind CSS v4, shadcn/ui on Radix, TanStack Query, React Hook Form with Zod, Sonner for toasts, Vitest with Testing Library, Playwright. (The brief this came from assumed Vue; the rules are framework-neutral and are written here as React.)

| File | What it holds |
|---|---|
| [`CONTRACT.md`](./CONTRACT.md) | The mandatory rules, how each is checked, and the exception process |
| [`interactions.md`](./interactions.md) | The interaction lifecycle, the motion and interaction tokens, and the behaviour of every component family |
| [`accessibility.md`](./accessibility.md) | WCAG 2.2 AA requirements and reduced motion |
| [`ux-writing.md`](./ux-writing.md) | How messages are written, with before and after examples |
| [`acceptance-criteria.md`](./acceptance-criteria.md) | The checklist a component or feature must pass, and the anti-patterns that fail it |

## What is built and what is still to build

This is honest on purpose, so the contract is never claimed to be enforced where it is not.

| Part | State |
|---|---|
| Colour tokens, light and dark | Built (`resources/css/app.css`) |
| Shared `Button`, `Input`, `Dialog`, `AlertDialog`, `Tabs`, `Skeleton`, `Toaster` (Sonner), `ConfirmDialog`, `EmptyState`, `ErrorNotice`, `SearchBox`, `useDebouncedValue` | Built, but not yet to this spec in every state |
| Motion and interaction tokens | **To build** (step 2): specified in `interactions.md` section 2 |
| `Button` loading state with width kept and no double submit | **To build** (step 2) |
| `PasswordInput` with the show and hide toggle | **To build** (step 2); today the password fields are plain inputs |
| DM Sans as the primary font (the app uses Geist Variable today) | **To build** (step 2), D-070 |
| Reduced motion rules in CSS | **To build** (step 2) |
| Component showcase page (states and transitions), with no business content | **To build** (step 3) |
| Automated checks: axe in Playwright, a hard-coded colour check, required status on the main branch | **To build** (step 3). ESLint with `jsx-a11y`, TypeScript, Vitest and Playwright already run in CI |

Until a part is built, new work must still follow the spec using the closest existing component, and must not add a second pattern.
