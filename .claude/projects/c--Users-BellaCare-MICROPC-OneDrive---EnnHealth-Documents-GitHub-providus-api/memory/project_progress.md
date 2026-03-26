---
name: Production Readiness Progress
description: Tracking what's been built, what's deployed, and what's next for Credentik SaaS launch
type: project
---

## Completed (2026-03-20)

### Security
- Removed all hardcoded credentials from migrations/seeders/frontend
- Passwords via env vars (SUPERADMIN_PASSWORD, DEMO_USER_PASSWORD)
- `/auth/seed-demo` moved behind superadmin auth
- SESSION_SECURE_COOKIE defaults to true
- XSS fix in contract acceptance
- Demo credentials removed from frontend HTML

### Email System
- 25 blade email templates (ShiftPulse-inspired design)
- 24 Mail classes covering all scenarios
- Base layout with gradient headers, dark mode support, Outlook VML

### Frontend UI/UX
- Dark mode (system preference + manual toggle + localStorage)
- Semantic design tokens for all surfaces
- Command palette (Ctrl+K) with quick actions
- Toast queue with undo support
- Modal focus traps (a11y)
- Inline form validation (data-validate attribute system)
- URL-based filter state for deep linking
- Skip-to-content link, ARIA labels throughout

### Backend Features
- Plan limit enforcement (providers/users/applications per tier)
- 5 scheduled automations: followup reminders, task reminders, stale app escalation, document expirations, monthly exclusion screening
- NP-specific provider fields: collaborative practice, scope of practice, prescriptive authority, address, DOB, professional IDs
- Step-based onboarding wizard API (personal → credentials → practice → bio)

### Infrastructure
- 3 superadmin accounts (superadmin@credentik.com + 2 EnnHealth contacts)
- Railway env vars configured, seeders run
- Railway CLI linked to providus-web service

## Next Priority
- Revenue intelligence / ROI calculation (unique competitive moat)
- State board PSV integration (top 10 states)
- 2FA/MFA
- Provider self-service portal frontend (wizard UI)

**Why:** People are interested in buying. Need to close Availity + PSV + Revenue Intelligence gaps for market viability.

**How to apply:** Focus on features that differentiate from Medallion/Modio/Verifiable. Revenue intelligence is the unique moat per competitive strategy.
