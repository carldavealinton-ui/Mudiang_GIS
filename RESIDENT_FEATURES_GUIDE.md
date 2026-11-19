# Resident features

The resident features migration has been applied to the local `barangay_gis` database. For another installation, run:

```powershell
C:\xampp\php\php.exe tools/migrate_resident_features.php
```

The command is additive and can be run again. It creates the new feature tables and indexes without replacing existing records. The initial setup script also creates the tables.

Residents can:

- Save a complaint draft automatically or explicitly. Text and coordinates sync to their account; a device copy is retained if the connection fails. Restore a draft in the form or use **Continue your report** on the dashboard. Files must be selected again. Successful submission clears the saved draft.
- Filter their reports by text, category, status, and date. Results show ten reports per page.
- Edit their name, phone number, and address in **My profile**. Phone numbers accept digits only, up to twelve digits. Historical report contact details remain as recorded at submission.
- Find up to five matching unresolved reports within 300 metres of their pin and select **I'm also affected**. Only category, status, approximate distance, and support totals are shared. Private descriptions, addresses, photos, and conversations stay private. Supported concerns appear in **Issues I support**, showing the most recent twenty supported issues.
- Read current barangay announcements and see what happens next for each report. The target date remains the staff-defined date; no response deadline is invented.
- Choose English, Filipino, or Cebuano for common interface labels and guidance. The choice persists on that browser. Resident messages, announcement content, and staff remarks stay in their original language; unlisted interface copy remains English.
- Request password recovery for an active resident account with a verified email address. Reset tokens are stored as hashes, expire after thirty minutes, and work once. Recovery is limited to five requests per fifteen-minute window per email and IP. Password changes revoke existing sessions and outstanding reset tokens. Staff recovery continues through administrator account access.

Administrators can create, schedule, expire, edit, or unpublish advisories in **Announcements**. Only administrators can publish announcements. Dates use Philippine time. Residents see the ten most recent active advisories. Staff see the community support count while reviewing a complaint.

## Email configuration

Registration and recovery now use Gmail SMTP through PHPMailer 7.1.1. In **Administrator → Email settings**, enter your Gmail address, a Google app password, sender name, and website URL, then save. Select **Send test email to my Gmail** to verify delivery. Google app passwords require [2-Step Verification](https://support.google.com/mail/answer/185833?hl=en). The connection uses `smtp.gmail.com:587` with STARTTLS, as documented in [Google's SMTP settings](https://support.google.com/mail/answer/7104828?hl=en). No real emails were sent by the automated tests.

Credentials are stored in `storage/mail-settings.json`, protected by the existing Apache `Require all denied` rule for `storage`, and excluded from source control. Saved passwords are not displayed on the settings page or logged. If moving to another web server, preserve the rule that denies direct HTTP access to storage. The third-party library and its license are in `vendor/PHPMailer`.

For deployment, set the website URL in **Email settings** to the installation's HTTPS URL, for example `https://portal.example.com/System`. The optional PHP/Apache environment variable `APP_BASE_URL` overrides that setting. The local fallback is `http://localhost/System`. Verification and recovery links deliberately do not use the request's Host header. Never put mail passwords or other secrets in source control.

## Performance

Dashboard report and evidence queries are limited to the displayed page. Mini maps load when they approach the viewport. Notifications refresh every thirty seconds while the tab is visible; message inbox polling uses fifteen seconds. The active conversation still polls every five seconds while visible. Large selected photos can be reduced to a maximum dimension of 2,000 pixels, retaining their format, only when the result is smaller. Uncheck the optimization option before selecting files to preserve originals. Videos are unchanged and existing upload limits still apply.

## Verification

```powershell
C:\xampp\php\php.exe tests/resident_features.php
C:\xampp\php\php.exe tests/complaint_messaging.php
C:\xampp\php\php.exe tests/mail_settings.php
node tests/resident_browser.js
```

The browser check uses the local Chrome installation, a temporary browser profile, and temporary resident fixtures. Tests remove the reports and accounts they create. They do not send real emails. Tests cover resident access, CSRF, privacy, filters, draft recovery, announcement publication, password reset expiration/replay and session revocation, browser interactions, language switching, and a mobile viewport.

Password recovery follows the [OWASP Forgot Password guidance](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html) on neutral responses, random expiring tokens, single use, and rate limiting.
