# Mobile API Reference — v2.0
Base URL: `https://your-domain.de`  
Auth: `Authorization: Bearer <token>`  
Content-Type: `application/json`

---

## AUTH

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/mobile/login` | ❌ | Login → returns `token` |
| POST | `/api/mobile/logout` | ✅ | Revoke current token |
| GET  | `/api/mobile/me` | ✅ | Current user info |
| GET  | `/api/mobile/ping` | ❌ | Health check, version |

### Login
```json
POST /api/mobile/login
{ "email": "...", "password": "...", "device_name": "Flutter App" }
→ { "token": "...", "expires_at": "...", "user": { "id", "name", "email", "role" } }
```

---

## DASHBOARD & NOTIFICATIONS

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/mobile/dashboard` | KPIs, revenue, appointment counts |
| GET | `/api/mobile/notifications` | Badge counts: unread msgs, overdue, today apts, waitlist |
| GET | `/api/mobile/search?q=...` | Global search across patients, owners, invoices, appointments |

### Notification Summary Response
```json
{ "unread_messages": 3, "overdue_invoices": 2, "today_appointments": 4, "waitlist_entries": 1, "total_badge": 5 }
```

---

## PATIENTS

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/patients?page=1&per_page=20&search=&filter=` | Paginated list |
| POST | `/api/mobile/patients` | Create patient |
| GET  | `/api/mobile/patients/{id}` | Show with timeline + invoice_stats |
| POST | `/api/mobile/patients/{id}` | Update patient |
| POST | `/api/mobile/patients/{id}/loeschen` | Archive patient (status=archived) |
| POST | `/api/mobile/patients/{id}/foto` | Upload photo (multipart, field: `photo`) |
| GET  | `/api/mobile/patients/{id}/timeline` | Timeline entries |
| POST | `/api/mobile/patients/{id}/timeline` | Create timeline entry |
| POST | `/api/mobile/patients/{id}/timeline/upload` | Upload file to timeline (multipart) |
| POST | `/api/mobile/patients/{id}/timeline/{eid}/update` | Update timeline entry |
| POST | `/api/mobile/patients/{id}/timeline/{eid}/delete` | Delete timeline entry |
| GET  | `/api/mobile/patients/{id}/hausaufgaben` | Homework plans for patient |

### Create/Update Patient Fields
```json
{ "name": "Bello", "species": "Hund", "breed": "Labrador", "gender": "männlich",
  "birth_date": "2020-01-15", "owner_id": 5, "chip_number": "276...",
  "color": "schwarz", "weight": 28.5, "notes": "...", "status": "active" }
```

---

## OWNERS (Tierhalter)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/owners?page=1&per_page=20&search=` | Paginated list |
| POST | `/api/mobile/owners` | Create owner |
| GET  | `/api/mobile/owners/{id}` | Show with patients array |
| POST | `/api/mobile/owners/{id}` | Update owner |
| POST | `/api/mobile/owners/{id}/loeschen` | Delete owner |
| GET  | `/api/mobile/owners/{id}/rechnungen` | Invoices for this owner (paginated) |
| GET  | `/api/mobile/owners/{id}/patienten` | Patients for this owner |

### Owner Fields
```json
{ "first_name": "Max", "last_name": "Mustermann", "email": "...", "phone": "...",
  "address": "...", "city": "Berlin", "zip": "10115", "notes": "..." }
```

---

## INVOICES (Rechnungen)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/invoices?page=1&per_page=20&status=&search=` | Paginated list |
| POST | `/api/mobile/invoices` | Create invoice with positions |
| GET  | `/api/mobile/invoices/{id}` | Show with positions array |
| POST | `/api/mobile/invoices/{id}/update` | Update invoice + positions |
| POST | `/api/mobile/invoices/{id}/status` | Update status only |
| POST | `/api/mobile/invoices/{id}/loeschen` | Delete invoice |
| GET  | `/api/mobile/invoices/{id}/pdf` | Returns `{ pdf_url, receipt_url }` |
| GET  | `/api/mobile/invoices/stats` | Revenue stats summary |
| GET  | `/api/mobile/ueberfaellig` | Overdue alert list (>7 days open) |

### Status values: `draft` `open` `paid` `overdue` `cancelled`

### Create Invoice Body
```json
{ "owner_id": 5, "patient_id": 3, "issue_date": "2025-03-20", "due_date": "2025-04-03",
  "payment_method": "rechnung", "notes": "...",
  "positions": [
    { "description": "Physiotherapie", "quantity": 1, "unit_price": 65.00, "tax_rate": 19 }
  ] }
```

---

## REMINDERS (Zahlungserinnerungen)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/erinnerungen?search=&status=` | All reminders (status: sent/unsent) |
| GET  | `/api/mobile/invoices/{id}/erinnerungen` | Reminders for invoice |
| POST | `/api/mobile/invoices/{id}/erinnerungen` | Create reminder |
| POST | `/api/mobile/invoices/{id}/erinnerungen/{rid}/loeschen` | Delete reminder |

### Create Reminder Body
```json
{ "due_date": "2025-04-10", "fee": 0, "notes": "..." }
```

---

## DUNNINGS (Mahnungen)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/mahnungen?search=&status=` | All dunnings |
| GET  | `/api/mobile/invoices/{id}/mahnungen` | Dunnings for invoice |
| POST | `/api/mobile/invoices/{id}/mahnungen` | Create dunning (level auto-incremented) |
| POST | `/api/mobile/invoices/{id}/mahnungen/{did}/loeschen` | Delete dunning |

### Create Dunning Body
```json
{ "due_date": "2025-04-17", "fee": 5.00, "notes": "..." }
```
Response includes `level` (1, 2, or 3).

---

## APPOINTMENTS (Termine)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/appointments?start=YYYY-MM-DD&end=YYYY-MM-DD` | List by date range |
| GET  | `/api/mobile/appointments/heute` | Today's appointments |
| GET  | `/api/mobile/appointments/{id}` | Show single appointment |
| POST | `/api/mobile/appointments` | Create appointment |
| POST | `/api/mobile/appointments/{id}` | Update appointment |
| POST | `/api/mobile/appointments/{id}/status` | Update status only |
| POST | `/api/mobile/appointments/{id}/loeschen` | Delete appointment |

### Status values: `scheduled` `confirmed` `cancelled` `completed` `no_show`

### Create Appointment Body
```json
{ "title": "Behandlung Bello", "start_at": "2025-03-20 10:00:00",
  "end_at": "2025-03-20 11:00:00", "patient_id": 3, "owner_id": 5,
  "treatment_type_id": 2, "status": "scheduled", "color": "#4f7cff",
  "description": "...", "notes": "...", "reminder_minutes": 60 }
```

---

## WAITLIST (Warteliste)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/warteliste` | All waitlist entries with patient/owner/treatment names |
| POST | `/api/mobile/warteliste` | Add to waitlist |
| POST | `/api/mobile/warteliste/{id}/loeschen` | Remove from waitlist |
| POST | `/api/mobile/warteliste/{id}/einplanen` | Convert to appointment + remove from list |

### Add to Waitlist Body
```json
{ "patient_id": 3, "owner_id": 5, "treatment_type_id": 2,
  "preferred_date": "2025-04-01", "notes": "..." }
```

### Schedule from Waitlist Body
```json
{ "start_at": "2025-04-01 09:00:00", "end_at": "2025-04-01 10:00:00",
  "title": "Termin Bello", "reminder_minutes": 60 }
→ { "success": true, "appointment_id": 42 }
```

---

## TREATMENT TYPES (Behandlungsarten)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/treatment-types` | All treatment types |
| GET  | `/api/mobile/behandlungsarten/{id}` | Show single |
| POST | `/api/mobile/behandlungsarten` | Create |
| POST | `/api/mobile/behandlungsarten/{id}/update` | Update |
| POST | `/api/mobile/behandlungsarten/{id}/loeschen` | Delete |

### Fields
```json
{ "name": "Physiotherapie", "color": "#22c55e", "duration_minutes": 60,
  "price": 65.00, "description": "..." }
```

---

## MESSAGING (Nachrichten)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/nachrichten` | All threads with unread count |
| GET  | `/api/mobile/nachrichten/ungelesen` | `{ "unread": N }` |
| POST | `/api/mobile/nachrichten` | Create new thread |
| GET  | `/api/mobile/nachrichten/{id}` | Thread + messages (marks read) |
| POST | `/api/mobile/nachrichten/{id}/antworten` | Reply to thread |
| POST | `/api/mobile/nachrichten/{id}/status` | Set `open` or `closed` |
| POST | `/api/mobile/nachrichten/{id}/loeschen` | Delete thread |

---

## HOMEWORK PLANS (Hausaufgaben)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/hausaufgaben` | All plans |
| GET  | `/api/mobile/patients/{id}/hausaufgaben` | Plans for patient |
| GET  | `/api/mobile/hausaufgaben/{id}` | Plan with exercises array |

---

## ANALYTICS

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/analytics` | Full finance analytics + 3-month forecast |

### Response includes:
- `summary` — paid/open/overdue totals + counts
- `by_month` — labels + revenue arrays (12 months)
- `by_year` — yearly revenue
- `owner_speed` — avg payment days per owner
- `owner_revenue` — top 10 owners by revenue
- `aging` — overdue aging buckets (1–30, 31–60, 61–90, 90+ days)
- `top_positions` — top 10 service positions by revenue
- `forecast_history` — last 12 months actual
- `forecast_next` — next 3 months linear regression `[{ month, value }]`

---

## USERS (Admin only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/benutzer` | All users |
| GET  | `/api/mobile/benutzer/{id}` | Show user |
| POST | `/api/mobile/benutzer` | Create user |
| POST | `/api/mobile/benutzer/{id}/update` | Update user (+ optional password) |
| POST | `/api/mobile/benutzer/{id}/deaktivieren` | Deactivate user |
| GET  | `/api/mobile/benutzer/{id}/tokens` | API tokens for user |
| POST | `/api/mobile/benutzer/tokens/{tid}/widerrufen` | Revoke a token |

### Role values: `admin` `mitarbeiter`

---

## SETTINGS

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/settings` | Read safe settings |
| POST | `/api/mobile/settings` | Update settings (admin only) |

### Writable keys: `company_name`, `company_address`, `company_phone`, `company_email`, `currency`, `tax_rate`, `kleinunternehmer`, `invoice_prefix`, `invoice_due_days`, `company_iban`, `company_bic`, `company_bank`

---

## GLOBAL SEARCH

```
GET /api/mobile/search?q=Bello
→ { "query": "Bello", "patients": [...], "owners": [...], "invoices": [...], "appointments": [...], "total": 7 }
```
Min 2 characters. Returns up to 10 results per category.

---

## OWNER PORTAL ADMIN (Besitzerportal-Verwaltung)

### Portal Stats & Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/portal-admin/stats` | Summary: total/active users, pending invites, unread msgs |
| GET  | `/api/mobile/portal-admin/benutzer` | All portal users (with owner name) |
| GET  | `/api/mobile/portal-admin/benutzer/{id}` | Single portal user detail |
| POST | `/api/mobile/portal-admin/einladen` | Invite owner → creates account + returns `invite_link` |
| POST | `/api/mobile/portal-admin/benutzer/{id}/neu-einladen` | Resend invite (new token) |
| POST | `/api/mobile/portal-admin/benutzer/{id}/aktivieren` | Activate portal access |
| POST | `/api/mobile/portal-admin/benutzer/{id}/deaktivieren` | Deactivate portal access |
| POST | `/api/mobile/portal-admin/benutzer/{id}/loeschen` | Delete portal account |

### Invite Body
```json
{ "owner_id": 5, "email": "max@example.de" }
→ { "success": true, "id": 3, "invite_link": "https://praxis.de/portal/registrieren?token=...", "expires_at": "..." }
```

### Owner Portal Overview (single owner — all-in-one)

```
GET /api/mobile/portal-admin/besitzer/{id}/uebersicht
→ {
    "owner": { ... },
    "portal_user": { "id", "email", "is_active", "invite_token", "last_login", ... } | null,
    "patients": [
      {
        ...patient fields...,
        "photo_url": "...",
        "exercises": [ { "id", "title", "description", "video_url", "image_url", "sort_order", ... } ],
        "homework_plans": [ { "id", "plan_date", "status", "therapist_name", "pdf_sent_at" } ]
      }
    ]
  }
```

---

### Exercises (Übungen) per Patient

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/portal-admin/patienten/{id}/uebungen` | All exercises for patient |
| POST | `/api/mobile/portal-admin/patienten/{id}/uebungen` | Create exercise (JSON or multipart with `image`) |
| GET  | `/api/mobile/portal-admin/uebungen/{id}` | Show single exercise |
| POST | `/api/mobile/portal-admin/uebungen/{id}/update` | Update exercise |
| POST | `/api/mobile/portal-admin/uebungen/{id}/loeschen` | Delete exercise |

### Exercise Fields
```json
{ "title": "Schulterübung", "description": "...", "video_url": "https://youtube.com/...",
  "sort_order": 0, "is_active": 1 }
```
Image upload: multipart/form-data with field `image` (jpg/png/webp/gif).  
Response includes `image_url` field for displaying the image.

---

### Homework Plans (Hausaufgabenpläne)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/portal-admin/hausaufgabenplaene?owner_id=&patient_id=` | List plans (filterable) |
| POST | `/api/mobile/portal-admin/hausaufgabenplaene` | Create plan + tasks |
| GET  | `/api/mobile/portal-admin/hausaufgabenplaene/{id}` | Show plan with full `tasks` array |
| POST | `/api/mobile/portal-admin/hausaufgabenplaene/{id}/update` | Update plan fields + tasks |
| POST | `/api/mobile/portal-admin/hausaufgabenplaene/{id}/loeschen` | Delete plan + tasks |
| GET  | `/api/mobile/portal-admin/hausaufgabenplaene/{id}/pdf` | Returns `{ pdf_url }` → open in browser/WebView |
| POST | `/api/mobile/portal-admin/hausaufgabenplaene/{id}/senden` | ⚠️ Use browser URL instead (PDF generation is server-side) |

### Create Plan Body
```json
{
  "patient_id": 3,
  "owner_id": 5,
  "plan_date": "2025-03-20",
  "physio_principles": "...",
  "short_term_goals": "...",
  "long_term_goals": "...",
  "therapy_means": "...",
  "general_notes": "...",
  "next_appointment": "2025-04-10",
  "therapist_name": "Dr. Müller",
  "status": "active",
  "tasks": [
    {
      "title": "Massage",
      "description": "5 Minuten täglich",
      "frequency": "2x täglich",
      "duration": "5 Minuten",
      "therapist_notes": "Sanfter Druck",
      "template_id": null
    }
  ]
}
```

### Plan Detail Response (GET /{id})
```json
{
  "id": 12, "plan_date": "2025-03-20", "patient_name": "Bello", "owner_name": "Max Mustermann",
  "therapist_name": "Dr. Müller", "status": "active", "pdf_sent_at": null,
  "tasks": [
    { "id": 45, "plan_id": 12, "title": "Massage", "frequency": "2x täglich", "duration": "5 Min", ... }
  ]
}
```

---

### Homework Templates (Vorlagen)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/portal-admin/vorlagen` | All active templates grouped by category |

---

## OWNER PORTAL — BESITZERPORTAL (Owner/Pet perspective)

> These endpoints use a **separate token** issued by `POST /api/mobile/portal/login`. Pass it as `Authorization: Bearer <token>` like all other endpoints. Portal users are **owners**, not staff.

### Auth

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/mobile/portal/login` | Login as owner → returns `{ token, expires_at, user, owner_id }` |
| POST | `/api/mobile/portal/logout` | Invalidate portal token |
| POST | `/api/mobile/portal/passwort-setzen/{token}` | Set password from invite link (public, no auth) |

### Login Body
```json
{ "email": "besitzer@example.com", "password": "..." }
```

### Set Password Body
```json
{ "password": "...", "confirm_password": "..." }
```

### Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/mobile/portal/dashboard` | Summary: pets, upcoming appointments, open invoices, unread messages |

### Meine Tiere (Pets)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/portal/tiere` | All pets of this owner (with `photo_url`) |
| GET  | `/api/mobile/portal/tiere/{id}` | Full pet detail: timeline, exercises, homework plans, TCP data |
| POST | `/api/mobile/portal/tiere/{id}/bearbeiten` | Edit pet (JSON or `multipart/form-data` for photo upload) |

#### Pet Detail Response
```json
{
  "pet": { "id": 1, "name": "Bello", "species": "Hund", "photo_url": "/storage/patients/1/photo.jpg", "..." },
  "timeline": [ { "id": 1, "type": "treatment", "title": "...", "content": "...", "file_url": null, "created_at": "..." } ],
  "exercises": [ { "id": 1, "title": "Schulterflexion", "image_url": "...", "video_url": "...", "sort_order": 1 } ],
  "homework_plans": [ { "id": 1, "plan_date": "2025-03-20", "therapist_name": "...", "status": "active" } ],
  "tcp_progress": [ { "score": 7.5, "category_name": "Schmerz", "entry_date": "2025-03-20" } ],
  "tcp_natural": null,
  "tcp_reports": null,
  "tcp_feedback": [ { "rating": "gut", "exercise_title": "...", "feedback_date": "..." } ]
}
```
> `tcp_*` fields are `null` if the TherapyCare Pro plugin is not active or visibility is disabled for this pet.

#### Edit Pet Fields (JSON body or form fields)
```json
{ "name": "Bello", "species": "Hund", "breed": "Labrador", "birth_date": "2020-01-15",
  "gender": "männlich", "color": "gelb", "chip_number": "123456789" }
```
For photo: use `multipart/form-data` with field `photo` (JPEG/PNG/WebP).

### Rechnungen (Invoices — owner view)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/mobile/portal/rechnungen` | All invoices with `pdf_url` |
| GET | `/api/mobile/portal/rechnungen/{id}/pdf-url` | Returns `{ pdf_url }` to open in browser/url_launcher |

### Termine (Appointments — owner view)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/mobile/portal/termine` | `{ upcoming: [...], past: [...] }` with patient + treatment names |

### Nachrichten (Messaging — owner sends/receives messages to practice)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/portal/nachrichten/ungelesen` | `{ unread: N }` badge count |
| GET  | `/api/mobile/portal/nachrichten` | All threads with `unread_count` per thread |
| GET  | `/api/mobile/portal/nachrichten/{id}` | Thread + messages (marks thread as read) |
| POST | `/api/mobile/portal/nachrichten/neu` | Start new thread |
| POST | `/api/mobile/portal/nachrichten/{id}/antworten` | Reply to existing thread |

#### New Thread Body
```json
{ "subject": "Frage zu Bello", "body": "Hallo, ich wollte fragen..." }
```

#### Reply Body
```json
{ "body": "Danke für die Antwort!" }
```

### Profil (Owner's own portal profile)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/portal/profil` | `{ portal_user, owner }` — portal account + full owner record |
| POST | `/api/mobile/portal/profil/passwort` | Change own password |

#### Change Password Body
```json
{ "current_password": "...", "new_password": "...", "confirm_password": "..." }
```

---

## PATIENT INTAKE (Patientenanmeldung)

> The **submit** endpoint is **public** (no auth). Admin endpoints require staff Bearer token.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/mobile/anmeldung` | Public: submit new patient registration (rate-limited: 5/10min per IP) |
| GET  | `/api/mobile/anmeldung/benachrichtigungen` | Admin: badge count of `neu` submissions |
| GET  | `/api/mobile/anmeldung?status=&page=` | Admin: paginated inbox with status counts |
| GET  | `/api/mobile/anmeldung/{id}` | Admin: show submission (auto-marks as `in_bearbeitung`) |
| POST | `/api/mobile/anmeldung/{id}/annehmen` | Admin: accept → creates owner + patient, returns `{ patient_id, owner_id }` |
| POST | `/api/mobile/anmeldung/{id}/ablehnen` | Admin: reject |

### Submit Body (required fields marked *)
```json
{
  "owner_first_name": "Max*", "owner_last_name": "Mustermann*",
  "owner_email": "max@example.com*", "owner_phone": "0123456789*",
  "owner_street": "Musterstr. 1", "owner_zip": "12345", "owner_city": "Musterstadt",
  "patient_name": "Bello*", "patient_species": "Hund*",
  "patient_breed": "Labrador", "patient_gender": "männlich",
  "patient_birth_date": "2020-01-15", "patient_color": "gelb", "patient_chip": "123",
  "reason": "Lahmheit vorne rechts*",
  "appointment_wish": "Montag oder Dienstag", "notes": "..."
}
```

### Status Values
`neu` → `in_bearbeitung` → `uebernommen` | `abgelehnt`

---

## PATIENT INVITE (Einladungslinks)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/einladungen/benachrichtigungen` | Pending invite count `{ pending: N }` |
| GET  | `/api/mobile/einladungen?page=` | List all invite tokens |
| POST | `/api/mobile/einladungen` | Create + send invite → returns `{ invite_url, whatsapp_url, expires_at }` |
| POST | `/api/mobile/einladungen/{id}/widerrufen` | Revoke invite |
| GET  | `/api/mobile/einladungen/{id}/whatsapp` | Get WhatsApp share URL for existing invite |

### Create Invite Body
```json
{ "email": "besitzer@example.com", "phone": "+4915112345678", "note": "Frau Müller, Katze Luna" }
```
Either `email` or `phone` is required (both allowed).

### Invite Response
```json
{
  "ok": true, "id": 42,
  "invite_url": "https://praxis.example.com/einladung/abc123...",
  "whatsapp_url": "https://wa.me/4915112345678?text=...",
  "expires_at": "2025-03-27 10:00:00"
}
```
> Open `invite_url` in browser or share `whatsapp_url` via `url_launcher`.

---

## PROFILE (Eigenes Profil)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/profil` | Current user profile (no password) |
| POST | `/api/mobile/profil` | Update `name` and/or `email` |
| POST | `/api/mobile/profil/passwort` | Change own password |

### Change Password Body
```json
{ "current_password": "...", "new_password": "...", "confirm_password": "..." }
```

---

## THERAPY CARE PRO (tcp)

### Progress Tracking (Fortschrittsverfolgung)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/tcp/fortschritt/kategorien` | All progress categories |
| GET  | `/api/mobile/tcp/patienten/{id}/fortschritt?from=&to=` | Entries + categories for patient |
| POST | `/api/mobile/tcp/patienten/{id}/fortschritt` | Add progress entry |
| POST | `/api/mobile/tcp/fortschritt/{entry_id}/loeschen` | Delete entry |

### Create Progress Entry Body
```json
{ "category_id": 2, "score": 7.5, "notes": "Deutliche Verbesserung",
  "entry_date": "2025-03-20", "appointment_id": 12 }
```

### Exercise Feedback (Übungsrückmeldungen)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/tcp/patienten/{id}/feedback?days=30` | Feedback list + summary for patient |
| GET  | `/api/mobile/tcp/feedback/problematisch` | All `bad` feedback from last 7 days |

### Therapy Reports (Therapieberichte)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/tcp/patienten/{id}/berichte` | All reports for patient |
| POST | `/api/mobile/tcp/patienten/{id}/berichte` | Create report |
| GET  | `/api/mobile/tcp/berichte/{id}` | Show single report |
| GET  | `/api/mobile/tcp/berichte/{id}/pdf` | Returns `{ pdf_url }` for download |
| POST | `/api/mobile/tcp/berichte/{id}/loeschen` | Delete report |

### Create Report Body
```json
{ "title": "Abschlussbericht", "report_date": "2025-03-20",
  "diagnosis": "...", "treatment_summary": "...", "recommendations": "...",
  "content": "...", "next_appointment": "2025-04-15" }
```

### Exercise Library (Übungsbibliothek)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/tcp/bibliothek?category=&search=` | All exercises (with `image_url`, `video_url`) |
| GET  | `/api/mobile/tcp/bibliothek/{id}` | Single exercise |
| POST | `/api/mobile/tcp/bibliothek` | Create exercise |
| POST | `/api/mobile/tcp/bibliothek/{id}/update` | Update exercise |
| POST | `/api/mobile/tcp/bibliothek/{id}/loeschen` | Delete exercise |

### Exercise Fields
```json
{ "title": "Schulterflexion", "category": "physio", "description": "...",
  "duration_minutes": 10, "repetitions": "3x10", "video_url": "https://...",
  "instructions": "...", "contraindications": "...", "equipment": "...",
  "difficulty": "leicht" }
```
Difficulty values: `leicht` `mittel` `schwer`

### Natural Therapy (Naturheilkunde)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/tcp/patienten/{id}/naturheilkunde` | Sessions for patient |
| POST | `/api/mobile/tcp/patienten/{id}/naturheilkunde` | Add session |
| POST | `/api/mobile/tcp/naturheilkunde/{id}/update` | Update session |
| POST | `/api/mobile/tcp/naturheilkunde/{id}/loeschen` | Delete session |

### Natural Therapy Body
```json
{ "therapy_type": "Akupunktur", "products_used": "...", "dosage": "...",
  "application_method": "...", "response": "gut", "notes": "...",
  "session_date": "2025-03-20" }
```

### TCP Reminder Queue

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/tcp/erinnerungen/vorlagen` | Active reminder templates |
| GET  | `/api/mobile/tcp/patienten/{id}/erinnerungen` | Queued reminders for patient |
| POST | `/api/mobile/tcp/patienten/{id}/erinnerungen` | Queue a reminder |

### Queue Reminder Body
```json
{ "type": "appointment_reminder", "subject": "Terminerinnerung",
  "body": "...", "send_at": "2025-03-25 09:00:00",
  "template_id": 3, "owner_id": 5, "appointment_id": 12 }
```

---

## TAX EXPORT PRO (Steuerexport — Admin only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/steuerexport?from=&to=&status=` | Invoices for period + aggregate stats |
| GET  | `/api/mobile/steuerexport/export-url?from=&to=&status=` | CSV/ZIP/PDF/DATEV download URLs |
| GET  | `/api/mobile/steuerexport/audit-log?limit=50` | GoBD audit trail |
| POST | `/api/mobile/steuerexport/{id}/finalisieren` | GoBD-finalize invoice (immutable) |
| POST | `/api/mobile/steuerexport/{id}/stornieren` | GoBD-cancel invoice |

### Period Response
```json
{
  "invoices": [...],
  "stats": { "total_count": 42, "total_paid": 3200.00, "sum_net": 2800.00, "sum_tax": 400.00, "sum_gross": 3200.00 },
  "period": { "from": "2025-01-01", "to": "2025-12-31" }
}
```

### Export URLs Response
```json
{ "csv_url": "/steuerexport/export-csv?...", "zip_url": "...", "pdf_url": "...", "datev_url": "..." }
```
→ Open these URLs in a browser or `url_launcher` in Flutter.

---

## MAILBOX (IMAP/SMTP)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/mailbox/status` | `{ configured, unread }` |
| GET  | `/api/mobile/mailbox/nachrichten?folder=INBOX&page=1` | Message list (paginated) |
| GET  | `/api/mobile/mailbox/nachrichten/{uid}?folder=INBOX` | Full message (marks as read) |
| POST | `/api/mobile/mailbox/senden` | Send email via SMTP |
| POST | `/api/mobile/mailbox/nachrichten/{uid}/loeschen?folder=INBOX` | Delete message |

### Message List Item
```json
{ "uid": 42, "subject": "...", "from": "user@example.com", "from_name": "Max",
  "date": "Thu, 20 Mar 2025 10:00:00 +0100", "unseen": true, "size": 2048 }
```

### Send Body
```json
{ "to": "empfaenger@example.com", "subject": "Betreff", "body": "<p>HTML oder Text</p>" }
```

> ⚠️ Mailbox requires `imap_host`, `imap_user`, `imap_pass` in Settings. Returns `{ error: "..." }` if not configured.

---

## GOOGLE CALENDAR SYNC

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/google-kalender/status` | Connection status + last 10 sync logs |
| POST | `/api/mobile/google-kalender/sync` | ⚠️ Returns web URL (OAuth not mobile-compatible) |

### Status Response
```json
{ "connected": true, "calendar_name": "Tierphysio Praxis",
  "last_sync_at": "2025-03-20 09:00:00",
  "recent_logs": [ { "action": "push", "status": "ok", "message": "...", "created_at": "..." } ] }
```

---

## SYSTEM STATUS (Admin only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET  | `/api/mobile/system/status` | Full health check |
| GET  | `/api/mobile/system/cronjobs` | Last 50 cron job runs |

### System Status Response
```json
{
  "ok": true,
  "timestamp": "2025-03-20 10:00:00",
  "checks": {
    "database": "ok",
    "smtp_configured": true,
    "google_calendar": { "enabled": true, "last_sync": "..." },
    "tcp_reminder_queue_pending": 3,
    "overdue_invoices": 2,
    "portal_active_users": 12
  }
}
```

---

## ERROR FORMAT

All errors return:
```json
{ "error": "Fehlerbeschreibung" }
```
HTTP codes: `400` Bad Request · `401` Unauthorized · `403` Forbidden · `404` Not Found · `409` Conflict · `422` Unprocessable · `500` Server Error

---

## PAGINATION FORMAT

Paginated lists return:
```json
{ "items": [...], "total": 150, "page": 1, "per_page": 20 }
```
