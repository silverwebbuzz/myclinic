# Sprint 1 — Test cases

## Prerequisites

- [ ] Sprint 0 complete (migrations 001–003, seeds)
- [ ] `composer install` (includes `firebase/php-jwt`, `league/oauth2-google`)
- [ ] `.env` with `JWT_SECRET`, `APP_URL`
- [ ] Optional: `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` for OAuth
- [ ] Optional: `MAILGUN_*` for live email (otherwise check `storage/logs/mail.log`)

## Manual tests

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Register page | GET `/register` | HTML form with clinic name, slug, email, password | ☐ |
| 2 | Slug check | GET `/api/check-slug?slug=newclinic` | `{"available":true}` | ☐ |
| 3 | Register submit | POST valid registration | Redirect `/onboarding/plan-selection`; cookies set | ☐ |
| 4 | Login page | GET `/login` | Login form + forgot link | ☐ |
| 5 | Login valid | POST correct credentials + remember me | Redirect per onboarding_step; session row created | ☐ |
| 6 | Login invalid | POST wrong password | Error message; 401 | ☐ |
| 7 | CAPTCHA | 3+ failed logins same email | Checkbox “I am not a robot” required | ☐ |
| 8 | Brute force | 5 failed logins | 429 on next attempt | ☐ |
| 9 | Forgot password | POST `/forgot-password` | Always redirects with success message; email in mail log | ☐ |
| 10 | Reset password | Open link from mail log | Set new password; all sessions revoked | ☐ |
| 11 | Change password | POST `/settings/password` while logged in | Success message | ☐ |
| 12 | Sessions list | GET `/settings/sessions` | Lists devices with “This device” on current | ☐ |
| 13 | Revoke others | POST `/settings/sessions/revoke-all` | Other sessions removed | ☐ |
| 14 | Logout | POST `/logout` with CSRF | Redirect `/login`; cookies cleared | ☐ |
| 15 | CSRF | POST without `_csrf` | 419 error page | ☐ |
| 16 | Refresh token | GET `/api/refresh-token` with `mc_refresh` cookie | `{"ok":true}`; new cookies | ☐ |
| 17 | Google OAuth | GET `/auth/google` (if configured) | Redirect to Google; callback logs in or registers | ☐ |
| 18 | Auto-refresh | Wait for JWT expiry with refresh cookie | Next request still authenticated | ☐ |

## Sign-off

- Date:
- Notes:
