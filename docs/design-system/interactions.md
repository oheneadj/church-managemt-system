# Interaction, Feedback and Motion Specification

Reusable behaviour for every control. No page designs and no church-specific flows live here. Tokens named here are specified now and built in step 2 (see `README.md`); use these names so nothing is renamed later.

## 1. Principles and the lifecycle

1. Feedback starts at once (aim for under 100 ms) when an interaction begins.
2. Idle, hover, focus, active, disabled, loading, success and error each look different.
3. Nobody is left wondering whether an action registered.
4. Nothing jumps: loading and transitions never shift the layout.
5. Input survives a failure. Duplicate submits are impossible.
6. One set of durations and easings. Motion has a purpose or it is not there.
7. Focus is placed deliberately after every state change.
8. Every error offers a way forward.

Every action moves through: **Idle → Interaction → Processing → Success or Error → Recovery.**

| Stage | Looks like | Behaves like | Tells the user |
|---|---|---|---|
| Idle | Normal | Ready | Nothing |
| Interaction | Pressed or focused look within 100 ms | Input accepted | The control reacted |
| Processing | Spinner and progressive label ("Saving…"), same size | Further presses ignored, other work still possible | That work is under way. Under about 400 ms no spinner is shown, so there is no flash; past about 1 s say what is happening; with a measurable amount use a real progress bar |
| Success | Brief confirmation, then back to idle | Result shown only after the server confirms | "Changes saved." |
| Error | Clear failure colour plus icon plus words | Input kept, control usable again | What happened and what to do |
| Recovery | The same screen, ready | Try again, edit, or leave | Nothing is lost |

## 2. Tokens

Defined once in `resources/css/app.css` and used by name. Components never write a raw duration, easing or colour. The colours, type, radii and elevation are in `foundations.md` (Heritage Green & Gold, D-071); the values here are fast 100, standard 150, moderate 200 and slow 300 ms from that brief, plus 80 ms for a press.

| Token | Value | Used for |
|---|---|---|
| `--motion-duration-instant` | 80ms | Pressed feedback |
| `--motion-duration-micro` | 100ms (fast) | Hover, focus, checkbox, switch, tooltip |
| `--motion-duration-component` | 150ms (standard) | Dropdown, popover, accordion, tabs indicator, loading swap |
| `--motion-duration-overlay` | 200ms (moderate) | Dialog, drawer, toast |
| `--motion-duration-content` | 300ms (slow) | Larger content change |
| `--motion-ease-standard` | `cubic-bezier(0.2, 0, 0, 1)` | Most movement |
| `--motion-ease-enter` | `cubic-bezier(0, 0, 0, 1)` | Things appearing |
| `--motion-ease-exit` | `cubic-bezier(0.4, 0, 1, 1)` | Things leaving |
| `--ring` (the focus ring colour) | Solid, at least 3:1 against the page and cards in both themes, used with a 3px ring | Every focusable control |
| `--surface-hover`, `--surface-pressed` | One step off the surface, derived with `color-mix` | Hover and pressed backgrounds |
| `--opacity-disabled` | 0.5 | Disabled controls |
| `--opacity-overlay` | 0.5 light, 0.6 dark | Modal backdrop |
| `--size-spinner-sm`, `-md`, `-lg` | 14, 16, 20 px | Loading indicators |
| `--skeleton-base`, `--skeleton-shine` | Soft neutrals off `--muted` | Skeletons |
| `--feedback-success`, `-warning`, `-info`, `-error` and their `-surface` and `-border` | Semantic, light and dark, text at least 4.8:1 on its own surface (values in `foundations.md`) | Toasts, banners, inline messages, validation |
| `--space-toast` | 16px from the edge, 8px between toasts | Toast stack |

Under `prefers-reduced-motion: reduce` the duration tokens for movement collapse to 0.01ms and spinners slow down; colour and text feedback stay (section 19).

## 3. Font loading and type

- Primary font **DM Sans**, served from our own origin as a variable font (`@fontsource-variable/dm-sans`), never from a third-party CDN.
- `font-display: swap` with a system fallback stack. A metric-matched fallback (size-adjust, ascent and descent overrides) is the goal once the real metrics are measured from the font file; it is not applied yet, so do not copy numbers from memory. Only the weights in use are shipped; weights are chosen on purpose (400, 500, 600).
- The page shell and theme tokens render before the font arrives: no invisible text, no flash of unstyled content, no blank white screen.
- Consistent line heights and `text-wrap: balance` for headings and `pretty` for paragraphs.
- **Placeholders:** muted but readable (contrast kept to AA), same size and line height as entered text, visibly different from a value, a realistic example (`name@example.com`), and never the only instruction. A visible label is always present.

## 4. Component state matrix

`●` applies. Each state specifies look, behaviour, motion, accessibility, message, next state and recovery in the sections below.

| Component | Default | Hover | Focus-visible | Active | Selected | Filled | Disabled | Read-only | Loading | Success | Warning | Error | Empty | Expanded or collapsed |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Button | ● | ● | ● | ● | | | ● | | ● | ● | | ● | | |
| Text input | ● | ● | ● | | | ● | ● | ● | ● | ● | | ● | ● | |
| Password input | ● | ● | ● | | | ● | ● | | | ● | ● | ● | ● | |
| Select | ● | ● | ● | | ● | ● | ● | | ● | | | ● | ● | ● |
| Checkbox and radio | ● | ● | ● | ● | ● | | ● | | | | | ● | | |
| Switch | ● | ● | ● | ● | ● | | ● | | ● | | | ● | | |
| Tabs | ● | ● | ● | ● | ● | | ● | | | | | | | |
| Accordion | ● | ● | ● | ● | | | ● | | | | | | | ● |
| Dialog and drawer | ● | | | | | | | | ● | ● | | ● | | |
| Menu and popover | ● | ● | ● | | ● | | ● | | ● | | | | ● | ● |
| Table | ● | ● (row) | ● | | ● (row) | | | | ● | | | ● | ● | |
| Toast | ● | | ● | | | | | | ● | ● | ● | ● | | |
| File upload | ● | ● | ● | ● | | ● | ● | | ● | ● | | ● | ● | |

Every cell is covered by a test or by the showcase page before a component is called done.

## 5. Buttons

| State | Behaviour |
|---|---|
| Default | Clear hierarchy: one primary per view, then secondary, outline, ghost, link |
| Hover | Background shifts one step, `--motion-duration-micro` |
| Focus-visible | `--focus-ring`, never removed |
| Pressed | Immediate (`--motion-duration-instant`), 1px settle only, no scaling |
| Loading | Spinner replaces or precedes the icon, label becomes the progressive form ("Save changes" → "Saving…"), `aria-busy="true"`, `aria-disabled` rather than removing focus, width and height unchanged (the button keeps the width of its widest label), repeat presses ignored |
| Success | Optional check and "Saved" for about 1.5 s, only after the server confirms, then idle |
| Error | The button returns to idle and the error appears beside the cause (inline, toast or banner), never only as a red button |
| Disabled | `--opacity-disabled`, no pointer events, and where the reason is not obvious it is stated in text next to it |

API: `<Button loading={isPending} loadingLabel="Saving…">Save changes</Button>`. The delay before the spinner is `LOADING_INDICATOR_DELAY_MS` (400) in `constants/interaction.ts`. The width held is the one the button had at rest, so a loading label should be no longer than the resting label plus the spinner. `loading` does nothing with `asChild`; a link is not a submit. The component owns the spinner, the width lock, the busy state and the blocked second click; the screen supplies only the state. Under about 400 ms the spinner does not show. Icon-only buttons have an accessible name and a tooltip. Destructive buttons follow section 15.

## 6. Page loading and navigation

- **Start-up:** a stable shell (header, navigation) renders first. Fonts and theme tokens are in place before content. No blank white screen.
- **Navigation:** persistent layout stays mounted; only the content area changes. Slow navigations show a thin progress bar at the top after about 400 ms. Scroll returns to the top on a new page and is restored on Back. Focus moves to the new page heading.
- **Choosing a pattern:**

| Situation | Use |
|---|---|
| Structured content with a known shape | Skeleton |
| A small section or one component | Inline loading in that spot |
| A user-triggered action | Button loading |
| Work with measurable progress | Progress bar with real values |
| App start-up or a truly blocking step | Full-screen loading, and only then |

Never a full-page spinner for routine fetching or a background refresh: keep the old data on screen, show a small "Updating…" cue, and swap when new data arrives (TanStack Query `placeholderData` and `isFetching`).

**Skeletons:** same dimensions and structure as the content (so no layout shift), `--skeleton-base` with a restrained shimmer, still under reduced motion, none for something that finishes almost at once, announced to assistive technology once as "Loading" and not shape by shape.

## 7. Inputs and forms

States: empty, hover, focus, filled, disabled, read-only, valid, invalid, loading, autofilled, required, optional. The label says "(optional)" for optional fields; required is the default and is indicated consistently.

- **Focus:** brand border plus `--focus-ring`, never colour alone. The caret does not jump when validation runs.
- **When to validate:** on blur, and on submit. Not on every keystroke. A field already marked invalid re-checks as the user types so the error clears the moment it is fixed.
- **Message:** one sentence next to the field, linked with `aria-describedby`, `aria-invalid="true"` on the field, saying how to fix it ("Enter a date in the past").
- **Submit:** validate, focus the first invalid field, show loading, block repeat submits, keep all data, show a result, restore interaction. Server field errors are mapped back to their fields.
- **Long forms:** warn before leaving with unsaved changes; keep a draft where losing the work would hurt. Multi-step forms keep the step in the URL.
- Browser autofill and password managers are supported (`autoComplete` set on every field that has a standard value). Autofilled fields look filled.

## 8. Password field

`PasswordInput` is the only way to enter a password.

- Masked by default. An eye button reveals it; an eye-off button hides it. The button is a real `<button type="button">` named "Show password" or "Hide password" and `aria-pressed` reflects the state. It is reachable by keyboard and has a touch target of at least 44 px.
- Toggling keeps focus and the caret position, never clears or changes the value, and never reveals the password without the user's action. The field's height and layout do not change.
- Pasting is allowed. Autofill is allowed. `autoComplete` is `current-password` or `new-password` as appropriate.
- **Creating a password:** the requirements are listed and each one updates as the user types (shown as text plus an icon, not colour alone). A strength meter is optional and is advisory, never a block. No arbitrary character-class rules beyond the project rule (D-054, 12 characters minimum and a breach check). A confirmation field checks "Passwords match" on blur.

## 9. Feedback and notifications

| Kind | Use | Time on screen |
|---|---|---|
| Toast: success | A confirmed result that needs no follow-up. **Every kind is a deep, solid colour with light text** (success `#0E6143`, warning `#814600`, error `#9A2F33`, information `#2F577D`, each at least 7.4:1 with white) and always carries its icon; progress and plain toasts use the neutral surface | 3 to 4 s |
| Toast: info | Background work started, or something worth knowing | 4 to 5 s |
| Toast: warning | A risk the user can act on | Until dismissed or long enough to read |
| Toast: error | An action failed | Stays until dismissed when it matters, with a retry action when retrying can help |
| Toast: progress | Work under way | Until it ends or fails, then it becomes a success or error |
| Inline | Tied to one field or component | Persistent until fixed |
| Banner | Page-level, persistent (offline, session ending) | Until the condition ends |
| Dialog | A decision or deliberate attention | Until the user chooses |

Toasts appear in one place (bottom right on wide screens, bottom centre on phones), enter and leave with fade and a short translate over `--motion-duration-overlay`, are announced politely (`role="status"`, errors `role="alert"`), never repeat the same message in a stack, never steal focus, and pause their timer on hover and focus. Critical information is never dismissed before it can be read. No confirmation dialog for a routine action.

## 10. Empty, error and recovery states

One shared pattern for: no data, no search results, no matching filters, network failure, permission denied, session expired, server error, service unavailable, failed upload, failed submission. Each states **what happened, why it matters when that is known, and what to do next**, in plain words, with a primary action ("Try again", "Clear filters", "Sign in again"). Retry is offered only when it can help. The cause is never invented when it is unknown. Raw status codes and exception text never appear as the message; a reference id may sit in small text for support. The components are `EmptyState` and `ErrorNotice`.

## 11. Motion language

| Layer | Duration |
|---|---|
| Micro (hover, press, focus, check, switch) | 80 to 100 ms |
| Component (menu, popover, accordion, tab indicator) | 150 ms |
| Overlay (dialog, drawer, toast) | 200 ms |
| Content (a larger region swapping) | 300 ms |

Opacity, a small translate (up to 8 px) and a gentle scale (0.96 to 1) only. No big slides, no zoom, nothing that delays the next action; an interaction is never blocked waiting for an animation to end. Exits are quicker than entrances. Elements animate together as one unit, not one by one.

## 12. Menus, dropdowns and popovers

Open on press with a fade and a 0.96 to 1 scale from the trigger over `--motion-duration-component`, with no delay before opening. Placed relative to the trigger and flipped or shifted near the viewport edge (Radix collision handling). Escape closes and returns focus to the trigger. Outside press closes. Arrow keys move, Home and End jump, typing searches, Enter or Space selects. Correct roles and labels (`menu`, `menuitem`, `listbox`, `option`).

## 13. Modals and drawers

Open with a fade (backdrop to `--opacity-overlay`) and a small rise or slide. Initial focus goes to the first field, or to the least destructive action in a confirmation. Background is inert and scroll is locked. Close by a visible close button and by Escape when that is safe; when there are unsaved changes, ask first. Focus returns to the trigger. A form dialog keeps values when validation fails, shows loading on its own button, shows errors inside the dialog, and closes only after confirmed success. No dialog opens another dialog.

## 14. Tables and data

- Initial load uses row skeletons that keep the table's size. Filtering and paging keep the old rows visible, dimmed slightly, with a small refreshing cue; the table is never replaced by a spinner.
- Row hover and a clear selected-row look (not colour alone: a tick in the row's checkbox). Sort controls are buttons inside the heading, which carries `aria-sort`. Pagination stays in place. Bulk selection shows how many are selected and what can be done. **Row actions are icon buttons at the end of the row (view, edit, delete), always visible, each named for its row ("Edit Alpha item"), and delete asks first.** Figures are right-aligned with tabular numerals. The shared `Table` scrolls sideways on a narrow screen instead of squeezing.
- Filters stay in the URL and survive navigation. When data refreshes, the user's place, selection and filters are kept, and it is clear when the data was last updated.

## 15. Search, filters and destructive actions

**Search:** visible focus, clear button, a small spinner while remote search runs, a "No results for…" message with a way to clear. **Remote search waits 250 to 350 ms after typing stops** (`useDebouncedValue`); local filtering of data already on screen does not wait. Results shown always belong to the latest query: an older answer arriving late is discarded and a stale list is dimmed while a new one loads. Active filters show as chips that can be removed one by one, with a Reset action; all keyboard reachable.

**Destructive actions, three levels:**

| Level | Pattern |
|---|---|
| Low risk (dismiss a notice, change a view) | Do it at once, with feedback |
| Reversible (archive, deactivate, move) | Do it and offer **Undo** for about 8 s, or a clear way back |
| High risk or irreversible (delete, void a gift, erase personal data, switch off, deactivate) | Confirmation dialog whose confirm button is the **solid danger button** (`destructive` on `ConfirmDialog`; an action that is not risky, such as sending a message, keeps the ordinary button), with a specific action label ("Delete member"), the consequence in plain words, the least destructive button focused first, and where the damage is large a typed confirmation. Money and permissions wait for the server before success is shown |

Never a misleading default, a confusing "Cancel / OK" pair, or a pre-ticked box that does the harmful thing.

## 16. Select, checkbox, radio and switch

- **Select:** shows the chosen value clearly; searchable when there are many options; arrow, Home, End and type-ahead; loading and empty states; disabled options look and read as disabled.
- **Checkbox and radio:** clear checked and unchecked looks (a mark, not only colour), visible focus, a hit area of at least 24 px (44 px on touch, including the label), a restrained `--motion-duration-micro` transition.
- **Switch:** moves at once, `role="switch"` with `aria-checked`, on and off are written or shown as text as well as position. A switch that saves to the server shows a small loading state, stays disabled meanwhile, and **rolls back with a message if saving fails**. It never claims "saved" before the server says so.

## 17. File upload

Drag and drop, click to browse (a real button), type and size checked before upload with the limits shown up front, real progress from the upload (never a made-up percentage), success, failure with the reason, **Retry**, **Remove**, and **Cancel** while uploading. In a batch, files that are fine are kept when one fails. A drop zone has a visible focus and a text alternative.

## 18. Date and time

Uses the browser's own date input (D-066) for keyboard access and mobile. Today is marked, selected is clear (not colour alone), disabled dates are explained, ranges show both ends, validation says what is wrong ("The end date is before the start date"). Dates are shown in one unambiguous format (`9 Nov 2026`) and times in the church's time zone, which is stated where it could confuse.

## 19. Optimistic updates and background work

Optimistic updates are for low-risk, reversible actions (reordering, marking read, toggling a view). On failure: restore the earlier state, say what happened, offer a way to retry. **Never optimistic for money, permissions, deletions or anything the server may refuse for a business reason.** Background work shows its state as pending, done or failed, somewhere the user can find it again.

## 20. Micro-interactions

A short, closed list. Each reinforces feedback and nothing else.

| Interaction | Behaviour |
|---|---|
| Button hover and press | Surface shift, 1px settle |
| Input focus | Border and ring fade in |
| Checkbox | Mark draws in over `--motion-duration-micro` |
| Switch | Thumb slides over `--motion-duration-micro` |
| Dropdown | Fade and 0.96 scale |
| Tooltip | Appears after about 400 ms of hover, immediately on focus, never covers its trigger |
| Tab indicator | Slides to the selected tab |
| Accordion | Height and opacity, no layout jump |
| Spinner | Steady rotation; slowed, not removed, under reduced motion |
| Progress | Width follows real values |
| Toast | Fade and 8px rise in, quicker out |
| Skeleton | Slow shimmer, still under reduced motion |
| Success | A check fades in, no confetti |
| Error | Icon plus text; a field may nudge its border colour, never shake |
| Copy to clipboard | The control changes to "Copied" for about 2 s and announces it |
| Password toggle | Icon swaps, label swaps, focus stays |

## 21. Perceived performance

Feedback within 100 ms; transitions under 300 ms; no spinner for work under about 400 ms; cumulative layout shift kept near zero by reserving space (skeletons, fixed button size, image sizes); the interface stays usable during background work; render progressively. A loading indicator tells the truth about waiting. It is never a substitute for making the thing faster.
