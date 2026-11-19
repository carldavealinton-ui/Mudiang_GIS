# Complaint service demonstration

## Demonstration accounts

Use a resident account, an administrator account, and two separately registered staff accounts. Use fictional demonstration data. In Staff Accounts, create each staff member with their own name and username. New staff must replace their temporary password before accessing reports.

## Demonstration sequence

1. Sign in as the resident and submit a complaint with importance level, location, a photo and optionally a short video.
2. Open the administrator dashboard to show the new-report notification and review the evidence.
3. Assign the report to Staff 1 using the account dropdown. Set a target completion date and acknowledge the complaint.
4. Sign in as Staff 1, open the assigned report from My workspace, add a remark, move it to Processing, and message the resident.
5. Have the administrator reassign the case to Staff 2, then sign in as Staff 2 and add a separate remark or message. Open Activity Logs to show that the two staff members have distinct recorded identities.
6. Try resolving the report without closing notes to demonstrate validation. Add resolution details and optional completion evidence, then mark it Resolved.
7. Return to the resident's report page. Show the timeline, closing notes, and completion evidence. Request reopening with a reason; show the recorded event and staff notification.
8. Resolve the reopened report, then have the resident confirm the outcome. Show the recorded confirmation.
9. Open Operations. Demonstrate status, priority, category, date and staff filters; review staff workload and average acknowledgement/resolution times. Compare repeated-location markers and address counts.
10. For an overdue demonstration, set an open report's target date to yesterday. Open the assigned staff's notification bell. Each viewer receives at most one overdue alert per report per day.
11. In Account access, demonstrate staff deactivation and the resulting revocation of an existing session. Reactivate the account. Demonstrate temporary-password recovery and the mandatory password change.
12. Export filtered Activity Logs as CSV and demonstrate the printed operations report.

## What the measurements mean

- Performance totals use the complaint's current assignee. Reassignment changes whose workload includes that report; historical actions remain attributed to their original authors in Activity Logs.
- First acknowledgement time measures submission to the first recorded move beyond Filed. Resolution time measures submission to the latest recorded resolution, including elapsed time before reopening.
- Historical complaints without recorded milestones are excluded from time averages. Dates are not fabricated for old reports.
- Larger map markers indicate multiple reports at coordinates rounded to three decimal places. Repeated written addresses are listed separately. These indicators support investigation; they do not automatically establish duplicate complaints.
- Target dates are explicitly chosen by staff. Existing reports without a target date are shown as not scheduled.
- Overdue alerts are generated while administrators or staff use the portal and during notification polling. There is no background email or SMS scheduler.
- Recovery is administrator-assisted. The administrator verifies identity and issues a temporary password privately; no email reset delivery is claimed.

## Installation and checks

The local database has been migrated. For another existing installation, apply `migrations/20261001_workflow.sql` after the previous migrations. New installations include it through `setup.php` and `database.sql`. The migration uses MariaDB's idempotent column/index syntax.

Run PHP syntax checks and `C:\xampp\php\php.exe tests\complaint_messaging.php` to verify messaging, role restrictions, accountability, assignment, deadlines, feedback, account controls and login throttling. The integration suite uses temporary accounts/reports and removes its fixtures.

Before the defense, check the pages in desktop and mobile browsers, including keyboard navigation, reduced-motion preference, photo/video upload and playback, and print layout. Maps and icon/font resources need network access. Prepare the walkthrough with the actual network available at the venue.
