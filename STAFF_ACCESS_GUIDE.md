# Administrator and staff responsibilities

| Feature | Administrator | Staff |
|---|---|---|
| Landing page | System dashboard | Dedicated personal workspace |
| Complaint records and evidence | All complaints | Currently assigned complaints only |
| Resident messaging | All registered complaint threads | Currently assigned threads only |
| New complaint notification | Receives notification | Receives an alert when assigned |
| Resident message and overdue alerts | All relevant reports | Currently assigned reports only |
| Assign or reassign complaints | Allowed | Not allowed |
| Change importance | Allowed | Allowed on assigned reports |
| Change deadlines | Allowed | Not allowed |
| Status changes | Can correct status with an audit record | Filed → Acknowledged → Processing → Resolved, one step at a time |
| Remarks, closing notes, completion evidence | Allowed | Allowed for assigned cases |
| Reopen resolved complaints | Can correct status; residents can request reopening | Cannot reopen directly |
| Complaint activity timeline | All complaints | Full history of currently assigned complaints |
| Global activity page | All recorded complaint actions | Their own recorded actions only |
| Activity CSV export | Allowed | Not allowed |
| Resident directory, annual reports, Operations analytics | Allowed | Not allowed |
| Register, deactivate or recover accounts | Allowed within existing account protections | Not allowed |
| Change own password | Allowed | Allowed |

Permissions are enforced in page handlers, messaging APIs, inbox queries, evidence downloads, map data and notifications. Hiding menu links is an additional UI measure. Reassignment removes the previous staff member's access to the case; their authored events remain in the audit history. Their personal activity page still includes their previously recorded actions, but opening a report they no longer own is blocked.

Unassigned complaints remain with the administrator until an active staff account is selected. Existing name-only assignments must be linked to an account using the administrator's assignment dropdown before staff can access them.

Staff cannot skip stages or move a case backwards. Closing notes remain required for resolution, and a resident-confirmed closing record cannot be overwritten. Residents retain access to their own complaints and can confirm a resolution or request reopening with an explanation.

The staff workspace explains these responsibilities and limits. Administrators should assign each case to one accountable staff member and use reassignment when responsibility changes.
