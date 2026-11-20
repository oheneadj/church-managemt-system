# Design System Enforcement Contract

**Status:** Mandatory. **Scope:** every user-facing screen and every shared component in `resources/js`. **Authority:** this contract outranks personal preference and one-off decisions. It sits beside the Universal agreement and `addendum/Addendum — React.md`; where both speak, the stricter rule applies.

A guideline says "buttons should have loading states". This contract says: every asynchronous action uses the shared `Button`, cannot be submitted twice, shows loading feedback, and has a test that proves it before the change is merged.

## 1. Component reuse
- Use the shared components in `components/ui/` and `components/form/`. Look there before building anything (React addendum section 4).
- Do not copy a component to change its look: add a variant or a prop.
- A new pattern that two screens need goes into the shared library, with its test and a place on the showcase page.
- Raw `<button>`, `<input>`, `<select>` and `<textarea>` are allowed only inside a shared component that makes them accessible.

## 2. Tokens, not values
- Colours, type, spacing, radii, shadows and motion come from the tokens in `resources/css/app.css` (Tailwind theme names or `var(--token)`).
- No hex or `rgb()` colours, no hard-coded durations or easings, and no arbitrary pixel values for these in feature code. The one place new values are made is the token file, with a note of why.
- Light and dark both use the semantic tokens (`--background`, `--primary`, `--destructive` and the feedback tokens in `interactions.md`).

## 3. Interaction states
Every interactive component implements every state that applies to it, from the matrix in `interactions.md` section 4: default, hover, focus-visible, active, selected, disabled, read-only, loading, success, error, empty, expanded and collapsed.
Every asynchronous action gives feedback within 100 ms, cannot run twice at once, and tells the user how it ended. Loading never moves the layout.

## 4. Forms
- Every field has a persistent, visible label. A placeholder is an example, never the instruction.
- Errors are written in plain words, sit next to the field, are tied to it with `aria-describedby`, and are never shown before the user has had a chance to fill the field.
- A failed submit keeps everything the user typed. A server error is mapped back to its field, not only a toast.
- Password fields use `PasswordInput` (show and hide, accessible name, caret kept) and the right `autoComplete`.
- The submit button shows "Saving…" style progress and cannot be pressed again while it works.

## 5. Loading and feedback
- First load of structured content uses a skeleton shaped like the content. A full-screen loader is only for starting the app or a blocking step. A background refresh never blanks the screen.
- Success is shown only after the server confirms it. The exception is a low-risk action that can be undone, shown at once and rolled back with an explanation if it fails. **Money, permissions and deletions always wait for the server.**
- A failure says what happened and what to do next, and offers Try again when trying again can help.
- A destructive action follows the three levels in `interactions.md` section 15.

## 6. Motion
- Durations and easings come from the motion tokens. Nothing animates without a purpose.
- `prefers-reduced-motion: reduce` removes movement and keeps state feedback (colour, text, icon change).
- No bouncing, no big sliding or zooming, no animating every element on a page separately.

## 7. Accessibility
WCAG 2.2 AA. Everything works by keyboard, with a visible focus ring. Names, roles and states are exposed. Meaning is never carried by colour or motion alone. Modals trap and return focus. Details in `accessibility.md`.

## 8. Writing
Messages are short, human and respectful, and say what to do. No raw status codes or exception text as the main message. Labels name the action ("Delete member", not "Confirm"). See `ux-writing.md`.

## 9. How it is checked

| Rule | Checked by | Status |
|---|---|---|
| Types and component props | `tsc` in CI | Running |
| Missing labels, roles, keyboard handlers | ESLint `jsx-a11y` in CI | Running |
| Behaviour of each state, loading, double submit, errors | Vitest and Testing Library, one test per shared component | Running for existing components; required for every new one |
| Real flows by keyboard and in a real browser | Playwright (`npm run e2e`) | Running |
| The showcase page exists, behaves, and stays out of production | `DesignSystemShowcasePage.test.tsx`, `check-showcase-excluded.sh` in CI | Running |
| Accessibility violations on the showcase and key flows | axe with Playwright | To build (step 3b) |
| Hard-coded colours and durations in feature code | An ESLint or Stylelint rule | To build (step 3b) |
| Look unchanged | Playwright screenshots of the showcase | To build (step 3b) |
| Merge blocked when a check fails | Required status checks on the protected branches | Repository setting, to be turned on by the owner |

Checks cannot judge whether a message is kind or a loading state feels right. Those need a human reviewer, and the acceptance checklist in `acceptance-criteria.md` is what the reviewer works from.

## 10. Done means
A feature is not done until: shared components are used; no unapproved values were added; every state that applies is built; loading and failure paths are tested; keyboard and accessibility checks pass; light and dark are both checked; the tests pass; and these docs are updated if a pattern changed. UI work is reported with: components reused or made, states built, accessibility checks run, tests and results, and any exception.

## 11. Exceptions
An exception is written down in the change's CHANGELOG entry with: the rule bypassed, why, who approved it, how far it reaches, and when it will be fixed if temporary. Nobody, human or AI agent, bypasses this contract silently. A change that breaks an applicable rule is not ready to release.
