# Requirements Document

## Introduction

The Unified Approvals Inbox is a new module in BugSayMis that gives each approver role a single page where they can see and act on every pending request that requires their attention — across all eight request modules. Currently, approvers must navigate to a separate approval page per module, which means a Division Chief must visit up to eight different pages to clear their queue. The inbox consolidates these into one view, organised by request type via tabs, with full request details in a modal and approve/decline actions inside that modal. Existing per-module approval pages are kept intact; the inbox is purely additive.

---

## Glossary

- **Approvals_Inbox**: The new unified page at `/approvals` that aggregates pending items for the authenticated approver.
- **Inbox_Controller**: `App\Http\Controllers\ApprovalInboxController` — the single Laravel controller that serves the inbox page and proxies approve/decline actions.
- **Request_Type**: One of eight categories: `it_job_requests`, `facility_requests`, `vehicle_requests`, `work_requests`, `service_requests`, `messengerial_requests`, `gate_passes`, `leave_applications`.
- **Pending_Item**: A request record whose current `status` value places it in the authenticated user's approval queue for their role.
- **Tab**: A UI element on the Inbox page labelled with the request type name and a badge showing the count of pending items for that type.
- **Detail_Modal**: A slide-over or centred modal that displays all fields of a selected request and contains the Approve and Decline action buttons.
- **Approve_Action**: A POST request to `/approvals/{type}/{id}/approve` that delegates to the existing per-module approve endpoint logic.
- **Decline_Action**: A POST request to `/approvals/{type}/{id}/decline` that delegates to the existing per-module decline endpoint logic, requiring a non-empty reason.
- **Badge_Count**: The integer count of total pending items across all tabs, displayed on the sidebar navigation item.
- **SnapshotService**: `App\Services\SnapshotService` — existing service used to record immutable approval audit trail entries.
- **Division_Chief**: A user with the `DivisionChief` role.
- **FAD_Chief**: A user whose `position` column contains the string `FAD`.
- **GSU_Head**: A user with the `GSU Head` role.
- **OCD**: A user with the `OCD` role (Office of the Campus Director).
- **HR_Officer**: A user with the `hr.leave.approve` permission.
- **Signed_URL_Flow**: The existing email-based approval mechanism using Laravel signed routes; it is not modified by this feature.

---

## Requirements

### Requirement 1: Role-Scoped Pending Item Aggregation

**User Story:** As an approver, I want the inbox to show only the requests that are genuinely waiting for my specific role's action, so that I do not see items that belong to another approver's queue.

#### Acceptance Criteria

1. WHEN a Division_Chief loads the Approvals_Inbox, THE Inbox_Controller SHALL return only records whose `status` matches the pending-DC status for each module: `Pending Division Chief Approval` (IT Job Requests, Messengerial Requests), `Pending` (Vehicle Requests where `division_chief_id = auth()->id()`), `Pending` (Facility Requests where the requester's division chief is the authenticated user), `Pending` (Work Requests where `division_chief_id = auth()->id()` or the requester belongs to the authenticated user's division), `Pending Division Chief Approval` (Service Requests), `Pending` (Gate Passes where the requester's division chief is the authenticated user), and `pending` (Leave Applications where `division_chief_id = auth()->id()` and `status = 'hr_verified'`).

2. WHEN a FAD_Chief loads the Approvals_Inbox, THE Inbox_Controller SHALL return only records with status `Pending FAD Approval` (Facility Requests) and `GSU Approved` (Work Requests) and `Pending FAD Approval` (Service Requests).

3. WHEN a GSU_Head loads the Approvals_Inbox, THE Inbox_Controller SHALL return only records with status `Approved` (Vehicle Requests awaiting driver assignment, i.e. `driver_id IS NULL`) and `Pending FAD Approval` (Facility Requests forwarded for GSU action) and `GSU Approved` (Work Requests awaiting GSU completion assignment).

4. WHEN an OCD user loads the Approvals_Inbox, THE Inbox_Controller SHALL return only records with status `Pending OCD Approval` (IT Job Requests), `Approved` (Vehicle Requests), `Pending OCD Approval` (Facility Requests), `Division Approved` (Gate Passes), and `forwarded` (Leave Applications).

5. WHEN an HR_Officer loads the Approvals_Inbox, THE Inbox_Controller SHALL return only Leave Applications with `status = 'pending'`.

6. IF the authenticated user does not hold any of the five approver roles, THEN THE Inbox_Controller SHALL return an HTTP 403 response.

7. THE Inbox_Controller SHALL eager-load only the columns and relations required for the inbox list view to avoid N+1 queries, using a maximum of two levels of eager loading per query.

---

### Requirement 2: Tab-Based Navigation with Live Counts

**User Story:** As an approver, I want to see each request type in its own tab with a count badge, so that I can quickly identify which queues need attention without scrolling through a mixed list.

#### Acceptance Criteria

1. THE Approvals_Inbox SHALL render one Tab per Request_Type that has at least one Pending_Item for the authenticated user's role.

2. WHEN a Tab is selected, THE Approvals_Inbox SHALL display only the Pending_Items belonging to that Request_Type in the list below the tabs.

3. THE Approvals_Inbox SHALL display a numeric badge on each Tab showing the count of Pending_Items for that Request_Type.

4. WHEN all Pending_Items for a Request_Type are resolved (approved or declined), THE Approvals_Inbox SHALL remove that Tab from the visible tab list without requiring a full page reload.

5. THE Approvals_Inbox SHALL display a total Badge_Count on the sidebar "Approvals" navigation item equal to the sum of all Pending_Items across all Request_Types for the authenticated user.

6. IF the authenticated user has zero Pending_Items across all Request_Types, THEN THE Approvals_Inbox SHALL display an empty-state message in place of the tab list.

---

### Requirement 3: Request Detail Modal

**User Story:** As an approver, I want to open a modal that shows the full details of a request before I decide to approve or decline, so that I have all the information I need without leaving the inbox.

#### Acceptance Criteria

1. WHEN an approver clicks the view button on a Pending_Item row, THE Approvals_Inbox SHALL open a Detail_Modal displaying all relevant fields for that request type.

2. THE Detail_Modal SHALL display at minimum: the requestor's name, the date the request was filed or created, the current status, and all type-specific fields (e.g., purpose and destination for Vehicle Requests; leave type and dates for Leave Applications; issue and category for Work Requests).

3. THE Detail_Modal SHALL display an Approve button and a Decline button inside the modal footer.

4. WHILE the Detail_Modal is open, THE Approvals_Inbox SHALL prevent interaction with the list behind the modal by rendering a backdrop overlay.

5. WHEN the approver clicks the close button or the backdrop, THE Detail_Modal SHALL close without submitting any action.

---

### Requirement 4: In-Modal Approve Action

**User Story:** As an approver, I want to approve a request directly from the Detail_Modal, so that I can act without navigating to the per-module approval page.

#### Acceptance Criteria

1. WHEN an approver clicks the Approve button inside the Detail_Modal, THE Approvals_Inbox SHALL display a confirmation prompt before submitting.

2. WHEN the approver confirms the approval, THE Inbox_Controller SHALL POST to the existing per-module approve endpoint with the authenticated user's session, reusing all existing status-transition logic, SnapshotService calls, and email notifications without duplicating them.

3. WHEN the Approve_Action succeeds, THE Approvals_Inbox SHALL remove the approved item from the current tab list and decrement the Tab badge count and the sidebar Badge_Count by one.

4. WHEN the Approve_Action succeeds, THE Approvals_Inbox SHALL display a success toast notification identifying the request type and reference number.

5. IF the Approve_Action fails due to a status conflict (the request was already acted upon), THEN THE Inbox_Controller SHALL return an HTTP 409 response and THE Approvals_Inbox SHALL display an error message without removing the item from the list.

6. IF the Approve_Action fails due to an authorisation error, THEN THE Inbox_Controller SHALL return an HTTP 403 response and THE Approvals_Inbox SHALL display an error message.

---

### Requirement 5: In-Modal Decline Action

**User Story:** As an approver, I want to decline a request from the Detail_Modal with a mandatory reason, so that the requester receives a clear explanation.

#### Acceptance Criteria

1. WHEN an approver clicks the Decline button inside the Detail_Modal, THE Approvals_Inbox SHALL replace the modal footer with a textarea input labelled "Reason for Decline" and a "Submit Decline" button.

2. THE Approvals_Inbox SHALL require the decline reason textarea to contain at least one non-whitespace character before enabling the "Submit Decline" button.

3. WHEN the approver submits a non-empty decline reason, THE Inbox_Controller SHALL POST to the existing per-module decline endpoint, passing the reason, and reusing all existing status-transition logic, SnapshotService calls, and email notifications.

4. WHEN the Decline_Action succeeds, THE Approvals_Inbox SHALL remove the declined item from the current tab list and decrement the Tab badge count and the sidebar Badge_Count by one.

5. WHEN the Decline_Action succeeds, THE Approvals_Inbox SHALL display a success toast notification identifying the request type and reference number.

6. IF the Decline_Action fails due to a status conflict, THEN THE Inbox_Controller SHALL return an HTTP 409 response and THE Approvals_Inbox SHALL display an error message without removing the item from the list.

---

### Requirement 6: Sidebar Navigation Integration

**User Story:** As an approver, I want a dedicated "Approvals" link in the sidebar with a live pending count badge, so that I can see at a glance whether I have items to action.

#### Acceptance Criteria

1. THE AdminLayout SHALL render an "Approvals" navigation item linking to `/approvals` in the sidebar, visible only to users who hold at least one of the five approver roles.

2. THE AdminLayout SHALL display a Badge_Count on the "Approvals" navigation item showing the total number of Pending_Items for the authenticated user across all Request_Types.

3. WHEN the Badge_Count is zero, THE AdminLayout SHALL hide the badge element rather than displaying a zero.

4. THE Badge_Count displayed in the sidebar SHALL be computed server-side by the Inbox_Controller and passed as an Inertia shared prop so that it is available on every page load without a separate AJAX call.

5. THE existing per-module approval navigation links SHALL remain in the sidebar unchanged.

---

### Requirement 7: No Disruption to Existing Approval Flows

**User Story:** As a system administrator, I want the inbox to be purely additive so that existing email-based signed URL approvals, per-module approval pages, and all existing routes continue to work exactly as before.

#### Acceptance Criteria

1. THE Inbox_Controller SHALL NOT modify any existing model, migration, route, controller method, mail class, or signed URL generation logic.

2. WHEN an approver uses the Approvals_Inbox to approve or decline a request, THE system SHALL produce the same database state changes, ApprovalSnapshot records, and outbound email notifications as if the approver had used the existing per-module approval page.

3. THE Signed_URL_Flow for email-based approvals SHALL remain fully functional and independent of the inbox feature.

4. THE existing per-module approval pages SHALL remain accessible at their current routes and SHALL continue to function without modification.

5. IF a request is approved or declined via the Signed_URL_Flow while it is visible in an approver's inbox, THEN THE Approvals_Inbox SHALL reflect the updated state on the next page load.

---

### Requirement 8: Access Control and Route Protection

**User Story:** As a security-conscious developer, I want all inbox routes to be protected so that only authenticated users with an approver role can access them.

#### Acceptance Criteria

1. THE route `GET /approvals` SHALL be protected by the `auth` middleware and SHALL return HTTP 403 for authenticated users who do not hold any of the five approver roles.

2. THE route `POST /approvals/{type}/{id}/approve` SHALL be protected by the `auth` middleware and SHALL return HTTP 403 if the authenticated user is not authorised to approve the specified request.

3. THE route `POST /approvals/{type}/{id}/decline` SHALL be protected by the `auth` middleware and SHALL return HTTP 403 if the authenticated user is not authorised to decline the specified request.

4. WHEN the `{type}` path segment does not match one of the eight valid Request_Type slugs, THE Inbox_Controller SHALL return an HTTP 404 response.

5. WHEN the `{id}` path segment does not correspond to an existing record of the given Request_Type, THE Inbox_Controller SHALL return an HTTP 404 response.

---

### Requirement 9: Performance — Inbox Page Load

**User Story:** As an approver with a large queue, I want the inbox page to load quickly so that I am not blocked waiting for data.

#### Acceptance Criteria

1. THE Inbox_Controller SHALL execute no more than ten database queries to build the full inbox payload for any single role, using eager loading and query scoping to prevent N+1 patterns.

2. WHEN the total number of Pending_Items across all tabs exceeds 200, THE Inbox_Controller SHALL paginate each tab's list at 20 items per page and pass pagination metadata to the frontend.

3. THE Approvals_Inbox SHALL display a loading indicator while an Approve_Action or Decline_Action POST request is in flight, and SHALL disable the action buttons during that period to prevent duplicate submissions.
