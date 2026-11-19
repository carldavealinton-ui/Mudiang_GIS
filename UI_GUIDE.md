# Shared portal interface

Resident, administrator, and staff pages use the same sidebar renderer in `portal_navigation.php`. Menu entries follow the signed-in role, use the same order on every page, and identify the current page with `aria-current`. Root account pages generate the correct administration links for staff and administrators.

`assets/portal-consistency.css`, loaded through `ui_theme.php`, defines shared layout, typography, spacing, card surfaces, form controls, buttons, status badges, and mobile behavior. Page styles should focus on their own content rather than create another sidebar or override these shared controls. Profile and account security use `renderPortalPageStart()` and `renderPortalPageEnd()` to keep their layout aligned with dashboards.

Authentication screens keep their own sign-in and registration layouts while sharing the portal colors, typography, inputs, buttons, and card treatment. The language selector appears in the header actions on signed-in pages and inside the form card on authentication pages.

Header actions use one `.topbar-right` container on every signed-in page; `theme.js` groups existing page actions there before notification widgets load. Shared feature styles also align filter inputs, pagination, empty states, and notification bells and panels. All pages load Inter through `ui_theme.php`.

Reports use the same theme variables for surfaces, borders, text, buttons, and chart colors. They use the full portal content width through `renderPortalPageStart()` with `reports-content`; account forms retain the narrower `settings-content` layout. Report controls share the portal's 44px minimum height and 9px corner radius. Print layouts and exports remain available.

The sidebar menu scrolls separately from its identity and sign-out footer. Mobile pages use the existing accessible menu button and backdrop. Tables retain horizontal scrolling within their containers; the page itself stays within the viewport. Annual reports retain their print layout.

Run `node tests/ui_consistency_browser.js` to check the resident, administrator, staff, and authentication interfaces at desktop and mobile sizes. It verifies navigation, current-page indicators, heading sizes, content alignment, overflow, menu interactions, registration controls, and JavaScript errors. Review screenshots are saved in `storage/ui-review/` and contain temporary test account details. Test accounts and reports are removed afterward.
