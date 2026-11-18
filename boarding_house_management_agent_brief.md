# Boarding House Management System --- Agent Build Brief

## 1. Project objective

Build a simple, usable Boarding House Management System for managing:

1.  Property Management
2.  Tenant Management
3.  Room Allocation & Occupancy
4.  Lease & Contract Management
5.  Billing & Payment Management

The system is an internal web application for the boarding house
administrator or staff. Prioritize correct core workflows, clear
screens, and maintainable code.

## 2. Required technology stack

### Frontend

-   Vue.js
-   Bootstrap 5 for layout, forms, tables, modals, and responsive
    styling
-   Axios for HTTP requests
-   Vue Router for page navigation
-   Use the Vue version and project structure already present in the
    repository. If starting from scratch, use Vue 3 with Vite.

### Backend

-   Laravel
-   MySQL
-   Laravel migrations, Eloquent models, request validation, and API
    routes
-   Keep controllers simple and resource-oriented.
-   Use straightforward controller methods for CRUD and business
    actions. Do not introduce repository, service, DTO, action, or other
    abstraction layers unless a specific requirement genuinely needs
    one.

### General

-   Frontend and backend communicate through JSON REST API endpoints.
-   Keep frontend and backend responsibilities separate.
-   Store dates consistently and display them in a readable format.
-   Use Laravel validation and return useful validation messages.
-   Follow the existing repository conventions if they are already
    established.

## 3. Scope and non-goals

### In scope

-   Manage multiple properties.
-   Manage rooms under each property.
-   Register and maintain tenant records.
-   Allocate tenants to rooms and track move-in/move-out history.
-   Create and manage lease agreements.
-   Generate billing records for rent and other charges.
-   Record full or partial payments.
-   View basic occupancy and billing summaries.

### Not in the initial version

Do not build these unless explicitly requested later: - Tenant
self-service portal or mobile application - Online payment gateway
integration - Automated SMS, email, or messaging notifications -
Accounting/general ledger - Payroll or staff scheduling - Utility
meter-reading automation - Maintenance/work-order module - Document
e-signatures - Multi-role permission matrix - Complex dashboard
analytics - Bed-level allocation, unless the business confirms that
rooms are rented by individual bed spaces

Do not add features simply because they may be useful in a larger
property-management product.

## 4. Main modules and features

### 4.1 Property Management

Purpose: Maintain the boarding house properties managed by the system.

Features: - List properties. - Create, view, edit, and deactivate a
property. - Store property name, address, contact number, description,
and status. - View the rooms belonging to a property.

Fields: - `id` - `name` (required) - `address` (required) -
`contact_number` (optional) - `description` (optional) - `status`
(`active`, `inactive`) - `created_at`, `updated_at`

Rules: - A property can have many rooms. - Do not allow a property to be
deleted if it has rooms or historical transactions. Prefer deactivation.

### 4.2 Room Management

Purpose: Maintain rooms under each property.

Features: - List rooms, filter by property and status. - Create, view,
edit, and deactivate a room. - Store room number, floor, capacity,
monthly rental rate, and description. - Show current occupancy and
available capacity.

Fields: - `id` - `property_id` (required FK) - `room_number`
(required) - `floor` (optional) - `capacity` (required positive
integer) - `monthly_rate` (required decimal, non-negative) -
`description` (optional) - `status` (`active`, `maintenance`,
`inactive`) - `created_at`, `updated_at`

Rules: - Room number must be unique within the same property. - A room
belongs to one property. - Do not let a room's active occupancy exceed
its capacity. - A room under maintenance or inactive cannot receive a
new allocation. - Availability should be calculated from active room
assignments and capacity, not maintained as a manually edited
`available/occupied` status. - Do not allow deletion of rooms with
assignment or billing history. Deactivate them instead.

### 4.3 Tenant Management

Purpose: Maintain tenant information.

Features: - List, search, view, create, and edit tenants. - Display
current room/lease information where applicable. - View a tenant's
previous and current assignments and billing history. - Mark a tenant
inactive when they no longer have an active tenancy.

Fields: - `id` - `first_name` (required) - `middle_name` (optional) -
`last_name` (required) - `email` (optional) - `contact_number`
(required) - `address` (optional) - `emergency_contact_name`
(optional) - `emergency_contact_number` (optional) - `status` (`active`,
`inactive`) - `created_at`, `updated_at`

Rules: - Do not delete tenants with lease, assignment, or payment
history. - A tenant may have multiple historical leases, but should not
have overlapping active leases unless explicitly supported by the
business.

### 4.4 Room Allocation & Occupancy

Purpose: Assign tenants to rooms and maintain an accurate occupancy
history.

Features: - Allocate a tenant to an available room. - Show rooms with
their capacity, current occupants, and available spaces. - Record
move-in and move-out dates. - View current and historical room
assignments. - Support moving a tenant to another room by ending the old
assignment and creating a new lease/assignment as appropriate.

Table: `room_assignments`

Fields: - `id` - `lease_id` (required FK) - `room_id` (required FK) -
`move_in_date` (required date) - `move_out_date` (nullable date) -
`status` (`reserved`, `active`, `completed`, `cancelled`) -
`created_at`, `updated_at`

Rules: - A room assignment represents the occupancy period for a
lease. - Count only `active` assignments as current occupants. A
reservation should not count as an active occupant, but should be
considered when confirming a reservation if the business chooses to
reserve capacity. - Never exceed room capacity. - Validate that move-out
date is not earlier than move-in date. - When a tenant moves out, set
the assignment to `completed` and record `move_out_date`; preserve the
historical record. - Avoid overlapping active assignments for the same
lease. - The lease and assignment must refer to the same tenant and room
through the lease's tenant and room fields. Do not accept mismatched IDs
from the frontend.

### 4.5 Lease & Contract Management

Purpose: Store the agreed rental terms for a tenant's stay.

Features: - Create a lease when allocating a tenant to a room. - View
and edit lease details where appropriate. - Track lease start and end
dates, agreed monthly rent, deposit, and status. - Mark leases as
active, expired, terminated, or cancelled. - Show the lease associated
with a tenant's room assignment.

Table: `leases`

Fields: - `id` - `tenant_id` (required FK) - `room_id` (required FK) -
`lease_number` (required unique identifier) - `start_date` (required
date) - `end_date` (nullable date) - `monthly_rent` (required decimal,
non-negative) - `deposit_amount` (required decimal, default 0) - `terms`
(optional text) - `status` (`draft`, `active`, `expired`, `terminated`,
`cancelled`) - `created_at`, `updated_at`

Rules: - `monthly_rent` is a snapshot of the agreed rate. Later changes
to the room's standard rate must not change existing leases. - A lease
belongs to one tenant and one room. - Validate that end date is not
earlier than start date. - Do not hard-delete leases with billing or
assignment history. - Terminating or expiring a lease must not delete
its billing, payment, or assignment records. - Keep lease status and
assignment status consistent through explicit backend actions.

### 4.6 Billing & Payment Management

Purpose: Create charges and record payments against those charges.

#### Table: `billing_records`

Fields: - `id` - `lease_id` (required FK) - `billing_number` (required
unique identifier) - `billing_date` (required date) - `due_date`
(required date) - `period_start` (nullable date) - `period_end`
(nullable date) - `description` (required short description,
e.g. Monthly rent, Water bill) - `amount` (required decimal, greater
than zero) - `status` (`unpaid`, `partially_paid`, `paid`, `overdue`,
`cancelled`) - `created_at`, `updated_at`

Features: - Create a billing record for a lease. - View bills by tenant,
lease, due date, and status. - Show amount, amount paid, and outstanding
balance. - Allow an administrator to record a payment against a bill. -
Support multiple payments against one bill. - Allow different charge
descriptions (rent, utilities, fees, or other charges).

#### Table: `payments`

Fields: - `id` - `billing_record_id` (required FK) - `payment_reference`
(nullable; unique when provided) - `payment_date` (required date) -
`amount_paid` (required decimal, greater than zero) - `payment_method`
(`cash`, `gcash`, `bank_transfer`, `other`) - `notes` (optional text) -
`created_at`, `updated_at`

Rules: - One billing record can have many payments. - A payment must
belong to one billing record. - Do not accept a payment greater than the
bill's remaining balance unless overpayments are explicitly required. -
Never edit a bill's amount to make it appear paid. - Calculate
`amount_paid` as the sum of its valid payment records. - Calculate
`balance` as `billing_records.amount - amount_paid`. - Update bill
status based on balance: `paid` when balance is zero; `partially_paid`
when some amount is paid; otherwise `unpaid` or `overdue` depending on
due date. - `overdue` is a derived state when the due date has passed
and balance is greater than zero. It can be calculated when listing
bills; avoid relying on a scheduled job for the initial version. - Do
not hard-delete payment records. If correction is needed, implement a
simple void/cancel mechanism with an audit note rather than silently
removing the transaction. - Create the payment and update/recalculate
the bill status in a database transaction.

## 5. Database relationships

Use these relationships:

-   `properties` has many `rooms`.
-   `rooms` belongs to `properties`.
-   `tenants` has many `leases`.
-   `rooms` has many `leases`.
-   `leases` belongs to one `tenant` and one `room`.
-   `leases` has many `room_assignments`.
-   `room_assignments` belongs to one `lease` and one `room`.
-   `leases` has many `billing_records`.
-   `billing_records` belongs to one `lease`.
-   `billing_records` has many `payments`.
-   `payments` belongs to one `billing_record`.

### Required tables

1.  `properties`
2.  `rooms`
3.  `tenants`
4.  `leases`
5.  `room_assignments`
6.  `billing_records`
7.  `payments`

Use foreign keys, indexes on foreign key columns, and appropriate unique
constraints. Use decimal columns for money, never floating-point
columns.

## 6. Backend implementation requirements

### API structure

Use Laravel API routes with predictable REST-style endpoints. Example:

  ---------------------------------------------------------------------------------------
  Method                  Endpoint                                Purpose
  ----------------------- --------------------------------------- -----------------------
  GET                     `/api/properties`                       List properties

  POST                    `/api/properties`                       Create property

  GET                     `/api/properties/{id}`                  View property

  PUT/PATCH               `/api/properties/{id}`                  Update property

  DELETE                  `/api/properties/{id}`                  Delete only if safe;
                                                                  otherwise deactivate

  GET                     `/api/rooms`                            List/filter rooms

  POST                    `/api/rooms`                            Create room

  PUT/PATCH               `/api/rooms/{id}`                       Update room

  GET                     `/api/tenants`                          List/search tenants

  POST                    `/api/tenants`                          Create tenant

  PUT/PATCH               `/api/tenants/{id}`                     Update tenant

  GET                     `/api/leases`                           List/filter leases

  POST                    `/api/leases`                           Create lease

  GET                     `/api/leases/{id}`                      View lease

  PUT/PATCH               `/api/leases/{id}`                      Update lease

  POST                    `/api/leases/{id}/allocate`             Create/activate room
                                                                  assignment

  POST                    `/api/room-assignments/{id}/move-out`   Complete an assignment

  GET                     `/api/room-assignments`                 List
                                                                  occupancy/assignment
                                                                  history

  GET                     `/api/billing-records`                  List/filter bills

  POST                    `/api/billing-records`                  Create bill

  GET                     `/api/billing-records/{id}`             View bill and payments

  POST                    `/api/billing-records/{id}/payments`    Record payment

  GET                     `/api/payments`                         List payment history
  ---------------------------------------------------------------------------------------

Adjust endpoint names to match the existing application conventions, but
keep the API consistent.

### Controller approach

-   Use one resource controller per main resource (for example,
    `PropertyController`, `RoomController`, `TenantController`,
    `LeaseController`, `RoomAssignmentController`,
    `BillingRecordController`, and `PaymentController`).
-   Keep controllers readable and direct.
-   Use `$request->validate()` or Laravel Form Requests if validation
    becomes lengthy.
-   Use Eloquent relationships instead of manually writing repetitive
    joins.
-   Use database transactions for multi-step operations such as lease
    allocation, move-out, and payment recording.
-   Return consistent JSON responses and suitable HTTP status codes.
-   Handle missing records with normal Laravel 404 behavior.
-   Do not expose stack traces or internal exception details to the
    frontend.
-   Do not put all application logic in one giant controller.

### Validation and data integrity

Validate all input on the backend, even if the frontend also validates
it.

At minimum: - Required fields and string lengths. - Valid dates and date
order. - Positive room capacity. - Non-negative rental and deposit
amounts. - Existing foreign key references. - Unique room number within
a property. - Unique lease and billing numbers. - Room capacity before
allocation. - Remaining bill balance before payment. - Consistent
tenant/room/lease relationships.

Use database transactions when a request changes multiple related
records. Prevent race conditions around room capacity and payment
balances by rechecking the relevant records inside the transaction,
using row locks where appropriate.

## 7. Frontend requirements

### Main layout

Create a simple admin dashboard layout: - Fixed or collapsible left
sidebar. - Top navigation bar with system name and current page. - Main
content area. - Responsive layout for desktop and tablet.

Sidebar navigation: 1. Dashboard 2. Properties 3. Rooms 4. Tenants 5.
Room Allocation 6. Leases 7. Billing & Payments

Do not create a complicated multi-level navigation system.

### Pages

Provide straightforward list and form pages for each module: -
Dashboard: basic counts for properties, rooms, current tenants, occupied
spaces, and outstanding balances. - Properties: list, add, edit, view. -
Rooms: list, add, edit, view occupancy. - Tenants: list, add, edit,
tenant details and history. - Room Allocation: available rooms, allocate
tenant, current occupants, move-out, assignment history. - Leases: list,
create, view, edit, terminate. - Billing & Payments: bills list, create
bill, bill details, record payment, payment history.

Use Bootstrap tables, cards, forms, badges, alerts, and modals. Prefer
simple pages over elaborate dashboards.

### Frontend behavior

-   Use Axios through a small shared API client.
-   Keep API calls organized by feature or resource.
-   Show loading indicators while requests are running.
-   Show clear success and error messages.
-   Display Laravel validation errors beside the relevant fields where
    practical.
-   Confirm destructive or consequential actions (deactivation,
    move-out, lease termination, payment void).
-   Refresh affected data after successful create/update actions.
-   Do not duplicate backend business rules in the frontend. Frontend
    checks are for user experience; the backend is authoritative.

## 8. UI theme and visual direction

Use a clean **blue-and-white** admin interface.

Suggested palette: - Primary blue: `#2563EB` - Dark blue: `#1E40AF` -
Light blue background: `#EFF6FF` - White surface: `#FFFFFF` - Main text:
`#1F2937` - Muted text: `#6B7280` - Borders: `#E5E7EB` - Success:
Bootstrap success - Warning: Bootstrap warning - Danger: Bootstrap
danger

Guidelines: - White cards and content surfaces. - Light gray or very
light blue page background. - Blue primary buttons and active
navigation. - Use consistent spacing, typography, border radius, and
form sizes. - Use status badges with restrained colors. - Keep tables
readable and forms uncluttered. - Avoid gradients, excessive shadows,
decorative animations, and oversized dashboard widgets. - Make the
interface responsive and accessible.

## 9. Suggested dashboard summary

Keep the dashboard limited to useful operational information: - Total
properties. - Total rooms. - Occupied spaces / total capacity. - Current
active tenants. - Unpaid and overdue billing count. - Total outstanding
balance.

These values must come from actual database records. Do not use
hardcoded sample values in the finished application.

## 10. Suggested implementation order

Build in small, testable steps:

1.  Inspect the existing repository and identify the current Vue,
    Laravel, and database setup.
2.  Set up database configuration and migrations for the seven required
    tables.
3.  Create Eloquent models and relationships.
4.  Implement Property CRUD.
5.  Implement Room CRUD and occupancy calculation.
6.  Implement Tenant CRUD.
7.  Implement Lease creation and room allocation with capacity
    validation.
8.  Implement move-out and assignment history.
9.  Implement billing record creation and listing.
10. Implement payment recording, partial payment handling, and balance
    calculation.
11. Build the Vue layout and module pages.
12. Connect each page to the Laravel API using Axios.
13. Add validation, loading, empty, success, and error states.
14. Test the main workflows end to end.

Do not build every screen before testing the backend workflow. Complete
one module at a time.

## 11. Minimum acceptance criteria

The system is ready for its first usable version when:

-   An administrator can create and update a property.
-   An administrator can add rooms under a property.
-   Room numbers cannot be duplicated within the same property.
-   An administrator can register and update tenants.
-   A tenant can be assigned to a room only when capacity is available.
-   The system prevents room over-occupancy, including when two
    allocation requests happen close together.
-   An administrator can create a lease with an agreed rental rate and
    dates.
-   The system retains previous room assignments after move-out.
-   An administrator can create a bill for a lease.
-   An administrator can record multiple partial payments against a
    bill.
-   The system correctly calculates paid amount and remaining balance.
-   The system identifies overdue bills.
-   The dashboard displays real summary values.
-   Forms show useful validation errors and actions show success/failure
    feedback.
-   The application uses the blue-and-white theme consistently.

## 12. Instructions to the coding agent --- avoid overengineering

These instructions are mandatory:

1.  **Inspect before changing.** Review the current project structure
    and existing dependencies before creating or replacing files.
2.  **Keep it simple.** Use Vue components, Bootstrap, Axios, Laravel
    controllers, Eloquent models, migrations, and API routes.
3.  Do not introduce microservices, complex layered architecture,
    repositories, CQRS, event sourcing, or generic CRUD frameworks.
4.  Do not add packages when native Vue, Bootstrap, Axios, or Laravel
    functionality is sufficient.
5.  Do not create abstractions for one-time operations.
6.  Do not build features outside the stated scope.
7.  Do not create duplicate fields or tables that store the same
    information without a clear reason.
8.  Do not trust frontend values for room availability, payment balance,
    or lease validity. Recalculate and validate these on the backend.
9.  Preserve historical financial and occupancy records. Prefer status
    changes over deletion.
10. Use clear names and conventional Laravel/Vue project structure.
11. Add comments only where the reason for the code is not obvious.
12. If a requirement is ambiguous, choose the simplest safe
    implementation and document the assumption instead of building a
    complex generalized solution.
13. Before finishing, run available migrations, tests, and frontend
    build checks. Fix errors introduced by the implementation.
14. Summarize what was implemented, list any assumptions, and identify
    anything unfinished. Do not claim a feature is complete if it has
    not been implemented and tested.

**Final direction:** Build a small, dependable boarding house
administration system. Favor readable code, reliable data relationships,
and working end-to-end workflows over architectural complexity or
unnecessary features.
