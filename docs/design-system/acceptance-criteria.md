# Acceptance Criteria and Anti-patterns

## Component quality gate

A shared component or a change to one is accepted only when every applicable line is true.

- [ ] Uses semantic tokens only: no hex or rgb colours, raw durations or easings
- [ ] Reuses an existing pattern, or the new pattern is added to the shared library, with a showcase entry
- [ ] Every applicable state in `interactions.md` section 4 is built and visible on the showcase
- [ ] Shows loading feedback where it can wait, keeps its size, and ignores repeat activation
- [ ] On failure it keeps the input, says what happened in plain words, and offers a way to recover
- [ ] Works by keyboard, with a visible focus ring, correct role, name and state
- [ ] Respects `prefers-reduced-motion`
- [ ] Reads correctly in light and dark, with contrast checked
- [ ] Has Testing Library tests for its states, its loading and double-press protection, and its error path
- [ ] No layout shift when its state changes (checked in the browser)

## Feature acceptance (UI work)

- [ ] Only shared components were used for buttons, fields, dialogs, toasts, skeletons and empty and error states
- [ ] Each form: labels, validation on blur and submit, first invalid field focused, input kept after failure, server errors on their fields, submit blocked while working
- [ ] Each asynchronous action: feedback within 100 ms, progress label, outcome message after confirmation only
- [ ] Money, permission and deletion actions wait for the server before showing success
- [ ] Each list or table: skeleton on first load, no blanking on refresh, empty state, no-results state, error state with retry
- [ ] Works at 375 px wide and at 200 percent zoom
- [ ] Messages follow `ux-writing.md`
- [ ] Tests, lint, type check and the browser flow pass
- [ ] The report names the components reused or made, states built, accessibility checks run, tests and results, and any exception

## Measurable targets

| Measure | Target |
|---|---|
| Time to visible feedback after a press | 100 ms or less |
| Spinner shown for work shorter than | never under about 400 ms |
| Transition length | 300 ms or less |
| Layout shift when a state changes | none (CLS near 0) |
| Remote search wait after typing stops | 250 to 350 ms |
| Toast time | success 3 to 4 s, info 4 to 5 s, errors until dismissed when important |

## Anti-patterns (each one fails review)

- A button that does not change while a request runs, or that can be pressed twice
- A loading spinner that resizes the button or shifts the layout
- A full-screen spinner for routine fetching, or a table replaced by a spinner on filter
- "Saved" shown before the server confirms, or optimistic success for money, permissions or deletion
- An error that clears what the user typed, or appears only as a toast with no field marked
- A validation error shown before the person has touched the field, or on every keystroke
- A placeholder used as the label
- A revealed password that stays revealed without the user asking, or a toggle that moves the caret or clears the value
- Colour as the only signal; focus ring removed; a hit area smaller than 24 px
- A "Confirm" or "OK" button; a confirmation for a routine action; a dialog opened from a dialog
- Animation that bounces, shakes, spins for show, delays a click, or ignores reduced motion
- A raw status code, exception or stack trace shown as the message
- A one-off button, input, toast or skeleton when a shared one exists
- A hard-coded colour, duration or easing in feature code
- A skeleton with a different shape from the content, or a made-up upload percentage
