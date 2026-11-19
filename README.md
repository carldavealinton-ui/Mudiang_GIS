# Barangay Mudiang - GIS Complaint Mapping and Resolution Tracking System

## Overview
A web-based Geographic Information System (GIS) for managing and tracking community complaints in Barangay Mudiang. The system allows residents to report issues with precise location mapping and enables administrators to track resolution progress.

## Features
- **Consistent portal interface**: Shared navigation, page spacing, controls, and responsive layouts across resident, staff, administration, and authentication pages. See [UI guide](UI_GUIDE.md).
- **Resident usability improvements**: Account drafts, report filters and pagination, profile editing, nearby issue support, announcements, recovery, and English/Filipino/Cebuano interface options. See [Resident features guide](RESIDENT_FEATURES_GUIDE.md) for usage, migration, and email configuration.
- **Interactive GIS Mapping**: Leaflet.js integration for precise location marking
- **User Registration & Authentication**: Residents can create accounts and login
- **Resident Dashboard**: View and track personal complaints
- **Public Complaint Submission**: Guest users can also submit complaints
- **Admin Dashboard**: Comprehensive complaint management interface
- **Annual Reports**: Detailed yearly statistics with charts and graphs
- **Status Tracking**: Track complaints from pending to resolution
- **Resident-selected Importance**: Residents choose Low, Medium, or High when filing a report; staff can review the importance level.
- **Photo and Video Evidence**: Attach up to four JPG, PNG, WebP, MP4, WebM, or MOV files, with previews and upload progress (20 MB per file, 50 MB combined).
- **Protected Evidence Viewing**: Complaint owners and authorized staff can view or download attachments; videos support byte-range playback.
- **Real-time Map Visualization**: Color-coded markers based on complaint status
- **Resolution Notes**: Document actions taken for each complaint

## Installation

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser

### Setup Steps

1. **Copy Files**
   - Place all files in `C:\xampp\htdocs\System\`

2. **Create Database**
   - Start XAMPP (Apache and MySQL)
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Import `database.sql` or run the SQL commands manually

3. **Configure Database**
   - Edit `config.php` if needed (default settings work with XAMPP)
   - Default credentials:
     - Host: localhost
     - Database: barangay_gis
     - Username: root
     - Password: (empty)

4. **Access the System**
   - Public Interface: http://localhost/System/
   - Resident Registration: http://localhost/System/register.php
   - Login: http://localhost/System/login.php
   - Admin Login: Use login page with admin credentials
   - Default Admin Credentials:
     - Username: admin
     - Password: password

## Deploying to Render

This project includes a Dockerfile and Render Blueprint (`render.yaml`). It runs as an Apache/PHP web service and needs a MySQL-compatible database reachable from Render. Render's managed database offering is PostgreSQL, so provision MySQL separately or deploy a MySQL service and configure its private connection details.

1. Push this repository to GitHub and create a Render **Blueprint** from it.
2. Provision a MySQL database reachable from the web service. Import `database.sql` into a new database named `barangay_gis` (or set `DB_NAME` to the database you created). Do not use your local XAMPP database address in Render.
3. In the Render web service environment, set `DB_HOST`, `DB_USER`, and `DB_PASSWORD` to the database connection values. Set `APP_BASE_URL` to the deployed HTTPS URL. Add a Google Maps browser API key as `GOOGLE_MAPS_API_KEY` if map tiles/geocoding features require it, and restrict that key to your deployed domain.
4. Set `DB_PORT` if the database provider uses a port other than `3306`. `DB_NAME` defaults to `barangay_gis`.
5. Keep the Render disk mounted at `/var/www/html/storage`; uploads and saved mail settings are written there. The included Blueprint requests a persistent disk, which requires a Render plan that supports disks.
6. After deployment, sign in with the seeded administrator account and immediately change its password. The initial database schema seeds `admin` / `password`; do not leave these credentials active on a public deployment.

The repository ignores local `storage/` contents and generated `output/` files. Existing local evidence, profile pictures, and mail credentials are not copied to GitHub. Configure outbound mail through the application's mail settings after setting `APP_BASE_URL`.

## Usage

### For Residents (Registered Users)
1. Register an account at the registration page
2. Login with your credentials
3. Access your personal dashboard
4. Submit complaints with automatic profile information
5. Track status of your complaints
6. View resolution notes and updates

### For Guest Users
1. Visit the public page
2. Click "Submit Complaint (Guest)"
3. Click on the map to select location
4. Fill in complaint details manually
5. Submit the form

### For Administrators
1. Login to admin panel
2. View all complaints on dashboard with statistics
3. Access Annual Reports for yearly analysis
4. Click "Edit" on any complaint to:
   - Update status (Pending/In Progress/Resolved)
   - Set priority level
   - Assign to staff member
   - Add resolution notes
5. View complaints on interactive map
6. Generate and print annual reports

## System Structure

```
System/
├── config.php              # Database configuration
├── database.sql            # Database schema
├── index.php               # Public complaint submission page
├── login.php               # User login page
├── register.php            # Resident registration
├── logout.php              # Logout handler
├── resident_dashboard.php  # Resident personal dashboard
├── submit_complaint_form.php # Complaint form for logged-in residents
├── get_complaints.php      # API endpoint for map data
├── submit_complaint.php    # Complaint submission handler
└── admin/
    ├── login.php           # Admin authentication (deprecated - use main login)
    ├── dashboard.php       # Admin complaint management
    ├── edit_complaint.php  # Complaint editing interface
    ├── annual_report.php   # Yearly statistics and reports
    └── logout.php          # Session termination
```

## Complaint Status Legend
- **Red Marker**: Pending complaints
- **Orange Marker**: In Progress complaints
- **Green Marker**: Resolved complaints

## Customization

### Adjust Map Center
Edit the coordinates in `index.php` and `dashboard.php`:
```javascript
const map = L.map('map').setView([14.5995, 120.9842], 15);
// Change [latitude, longitude] to your barangay's coordinates
```

### Add Complaint Types
Edit the dropdown in `index.php`:
```html
<option value="Your Type">Your Type</option>
```

## Security Notes
- Change default admin password immediately
- Use strong passwords for production
- Consider adding HTTPS in production environment
- Implement CSRF protection for production use

## Technologies Used
- PHP 7.4+
- MySQL
- Leaflet.js (Open-source mapping library)
- Chart.js (Data visualization for reports)
- OpenStreetMap tiles
- HTML5/CSS3/JavaScript

## Support
For issues or questions, contact your barangay IT administrator.

### Evidence uploads
Existing installations can apply `migrations/20261001_complaint_evidence.sql` to create the evidence table if needed. This update also supports the existing `path`, `original_name`, `mime_type`, and `size` table format.

The upload form is at `submit_complaint_form.php`. Evidence appears on the resident dashboard and the administrator complaint review screen. The admin table shows importance and attachment counts, and new-complaint notifications include the selected priority.

Files are stored under `storage/evidence/` and served through the authenticated `evidence.php` endpoint. Keep `storage/.htaccess` in place to block direct access. Project `.htaccess` sets Apache PHP limits to 20 MB per file and 64 MB per request; `.user.ini` supplies equivalent settings for CGI/FastCGI deployments. The application enforces a maximum of four files and 50 MB combined.
### Complaint conversations
Residents can select **Message staff** beside a report on their dashboard. Administrators and staff can reply from the conversation panel on the report review page. Each conversation belongs to one complaint and is available only to its resident owner and authorized staff.

Messages refresh every five seconds while the conversation page is visible. Resident messages generate unread bell alerts and popup notifications for all administrators and staff; admin and staff replies notify the resident. Notification links open the relevant conversation. Message history supports earlier-message loading, and failed sends preserve the draft. Request tokens prevent retries from creating duplicate messages or alerts.

For another existing installation, apply `migrations/20261001_complaint_messages.sql` after the base database schema. The migration preserves earlier messages in the existing `user_id` table format. The local database has already been upgraded.

With Apache and MySQL running locally, run `C:\xampp\php\php.exe tests\complaint_messaging.php` from the project folder for the messaging integration checks. The script uses temporary test accounts and reports, then removes their messages and notifications.
### Dedicated Messages pages
Open **Messages** from the resident or administrator sidebar. Residents use `messages.php`; admins and staff use `admin/messages.php`. The inbox lists complaint conversations with latest-message previews, unread counts, category/report search, an Unread filter, and pagination. Selecting a report opens its conversation alongside the inbox, with importance and a link to the report details. Inbox counts and previews refresh every five seconds while the page is visible. Message notification links now open these pages directly.

### Civic visual design
The local Bootstrap framework is customized through ssets/theme.css and ssets/civic-palette.css. The final palette uses eucalyptus, limestone, brass, and terracotta. The palette layer supplies responsive inbox, evidence form, notification, and directory layouts. ssets/theme.js adds staggered panel entrances; reduced-motion preferences disable animations and transitions. Print styling removes navigation and interactive controls.

### Report workflow and admin activity
Reports now use **Filed → Acknowledged → Processing → Resolved**. Staff choose the status with buttons on the report review page. Existing Pending reports are mapped to Filed; In Progress reports are mapped to Processing. Dashboard counts, map legends, resident badges, and the annual status chart include all four stages.

The admin **Activity** page and each report's **Report activity** timeline record the acting staff member's name and username, action, and date. Staff messages, status changes, importance changes, assignments, resolution-note edits, and appended remarks are recorded. Report remarks are visible to the resident and generate resident notifications. Outdated update forms cannot overwrite another staff member's newer changes.

The local database has been upgraded. Other existing installations should apply `migrations/20261001_report_activity.sql` after the conversation migration; original staff message authors and timestamps are preserved in the history.
### Resident report accountability logs
The administrator **Activity Logs** page shows each recorded actor, action, report number, resident name, timestamp, action details, and the report's current status. Filter by resident/report search, recorded actor, action, or an inclusive date range. Pagination preserves the filters. **Print page** prints the current filtered page; **Export CSV** downloads all matching records. CSV cells are protected against spreadsheet formula interpretation. Residents cannot access or export these logs.
