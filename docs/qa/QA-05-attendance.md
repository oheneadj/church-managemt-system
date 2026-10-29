# QA-05 — Attendance

**Scope:** Service creation, manual bulk check-in, WhatsApp CHECKIN command, QR code check-in, idempotency, absence alerts.
**Sprint:** 3
**Risk:** R2 (Meta WhatsApp approval), R7 (connectivity)

---

## Setup

- Test church with 30 active members
- At least 5 members with registered WhatsApp numbers opted into the platform
- Meta WhatsApp webhook configured and verified (or use test mode if Meta not yet approved)
- Service created for today's date, status `open`

---

## Test Cases

### TC-05-01 — Create a service
**Action:** `POST /api/services` with `{ "name": "Sunday Morning Service", "service_date": "2026-10-25", "service_time": "09:00" }`
**Expected:**
- Service created with `is_open: false`
- `checkin_code` auto-generated (4–6 uppercase alphanumeric, e.g. `GRA7F`)

### TC-05-02 — Open a service for check-in
**Action:** `PATCH /api/services/{id}/open`
**Expected:**
- `is_open: true`
- Live check-in dashboard shows 0 attendees
- WhatsApp CHECKIN is now active for this service

### TC-05-03 — WhatsApp CHECKIN — happy path
**Action:** From a test WhatsApp number, send "CHECKIN" to the platform number
**Expected:**
- Webhook fires within 5 seconds
- Attendance record created for the matching member + open service
- Reply received: "✅ Checked in to Sunday Morning Service — 25 Oct 2026. Welcome, Emmanuel! God bless you. 🙏"
- Check-in count on dashboard increments by 1

### TC-05-04 — WhatsApp CHECKIN with service code
**Action:** Send "CHECKIN GRA7F" where GRA7F is the service's checkin_code
**Expected:** Same as TC-05-03 — check-in to the specific service

### TC-05-05 — WhatsApp CHECKIN — unknown phone
**Action:** Send "CHECKIN" from a WhatsApp number not registered for any member
**Expected:** Reply: "We couldn't find your number in our records. Please contact the church office."
**Expected:** No attendance record created

### TC-05-06 — WhatsApp CHECKIN — no open service
**Action:** Send "CHECKIN" when no service is currently open
**Expected:** Reply: "No service is currently open for check-in."

### TC-05-07 — WhatsApp CHECKIN — idempotency (no double check-in)
**Action:** Send "CHECKIN" twice from the same number for the same service
**Expected:**
- First: check-in recorded, confirmation sent
- Second: no duplicate attendance record created (unique constraint on service+member)
- Second reply can be: "You're already checked in! See you inside. 🙏" or the same confirmation

### TC-05-08 — Manual bulk check-in
**Action:** On the live check-in dashboard, search for members and click to mark them present
**Expected:**
- Member appears in the checked-in list immediately
- Count updates in real time (or on next 10-second poll)
- Multiple members can be marked in quick succession

### TC-05-09 — QR code check-in
**Action:** Display the QR code for the service on screen. Scan with a phone browser.
**Expected:**
- Browser opens a check-in form (no app required)
- Member enters their phone number, receives OTP, confirms
- Attendance recorded
- Confirmation shown in browser

### TC-05-10 — Close a service
**Action:** `PATCH /api/services/{id}/close`
**Expected:**
- `is_open: false`
- CHECKIN messages sent after close: member gets "No service is currently open" reply
- Attendance report is now final and shows correct totals

### TC-05-11 — Attendance report for a service
**Action:** `GET /api/services/{id}/attendance`
**Expected:**
- Returns list of attendees with check-in method (whatsapp / manual / qr) and time
- Summary: total checked in, total members, attendance rate %
- Visitors (non-members) listed separately

### TC-05-12 — Absence alert (3+ consecutive absences)
**Action:** A member misses 3 consecutive Sunday services (simulate by NOT adding them to 3 service attendance records)
**Expected:**
- Alert job fires after the 3rd missed service (scheduled, runs after service close)
- Alert appears in admin dashboard: "Emmanuel Asante has missed the last 3 services"
- No SMS sent automatically (alert is in-app only in MVP — SMS is a settings option)

### TC-05-13 — Multi-service Sunday (two services, same day)
**Action:** Create two services on the same Sunday (7am and 9am), both open at different times
**Expected:**
- A member can check in to both services (separate attendance records)
- Report shows them attending both
- CHECKIN without a code goes to the currently open service; if both open, goes to the first-opened

### TC-05-14 — Offline resilience (manual entry after the fact)
**Action:** Close a service, then try to add a manual attendance record for a member who was present but not recorded
**Expected:**
- Admin can add a late attendance record to a closed service (with a note)
- Record is added; report updates

### TC-05-15 — Weekly schedule makes the services
**Action:** `POST /api/v1/service-schedules` with name "Sunday Morning Service", weekday `sunday`, 09:00 to 11:30
**Expected:**
- Eight services are made, one on each of the next eight Sundays (today counts if it is a Sunday), closed, each with its own check-in code
- Running `services:generate-scheduled` again makes nothing; a week later it makes exactly one more

### TC-05-16 — Cancel a service; a schedule never remakes it
**Action:** Cancel a scheduled service with `DELETE /api/v1/services/{id}`, then run `services:generate-scheduled`
**Expected:**
- `204`; the service is off the list and its check-in code is free
- The date is not made again
- Cancelling a service that is open, or has a check-in or a gift, is `409` and nothing changes

### TC-05-17 — Changing a schedule
**Action:** Rename the schedule, change its times, then change its weekday
**Expected:**
- Future services nobody has touched get the new name and times, and move to the new weekday
- A service renamed by hand keeps its name; one that is open or has a check-in or gift is left alone; past services never change

---

## Pass / Fail Criteria

TC-05-03 through TC-05-07 are the WhatsApp CHECKIN critical path. If Meta is not approved, these are tested against a mock webhook. TC-05-07 (idempotency) is P0 — duplicate check-ins corrupt attendance data.
