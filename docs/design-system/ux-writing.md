# UX Writing

Every message is **human, short, respectful, clear and about what to do next.** No jargon, no blame, no shouting, no robotic wording, no stack of exclamation marks. Say what happened, then what the person can do.

| Instead of | Write |
|---|---|
| Operation completed successfully | Changes saved. |
| An unexpected exception occurred | Something went wrong. Please try again. |
| Invalid credentials supplied | The email or password is incorrect. |
| Data submission in progress | Saving… |
| Request failed with status 500 | We couldn't save your changes. Try again in a moment. |
| Error: field required | Enter the member's first name. |
| Unauthorized | You don't have access to this page. Ask a church admin if you need it. |
| Session expired | You were signed out to keep your account safe. Sign in again to carry on. |
| Are you sure? OK / Cancel | **Delete member** / Keep member, with: "This removes Ama Mensah and cannot be undone." |
| No data | No members yet. Add the first one to get started. |
| No results | No members match "mensa". Clear the search to see everyone. |

Rules:
- Buttons name the action: "Save changes", "Send message", "Delete member". Never "Submit", "OK" or "Confirm" on their own.
- Progress uses the present tense with an ellipsis: "Saving…", "Sending…".
- Success is calm and short and says what was saved. Errors say what failed and what to try.
- Do not invent a cause. If the cause is unknown say "Something went wrong" and offer Try again.
- Never put a raw status code, exception, SQL or stack text in the interface. A short reference code may be shown in small text for support.
- Never put a person's name, phone number or amount in a log line; the screen may show them to people allowed to see them.
- Money is written as `GHS 12.50`. Dates are `9 Nov 2026`. Sentences, not fragments, in messages; sentence case, not Title Case or capitals.
- Placeholders are examples (`name@example.com`), never the instruction.
