# NEXT MEDICALIAN – Central Exam Management System
## Software Requirements Specification (SRS)
**Powered by EdGenie**

---

### 1. Introduction

#### 1.1 Purpose
The purpose of this document is to specify the complete functional, non-functional, security, and architectural requirements for **NEXT MEDICALIAN**, a production-grade Centralized Offline Examination Management System powered by **EdGenie**. 

The system facilitates centralized offline medical admission preparation examinations across multiple geographic examination zones and centers throughout Bangladesh. It is strictly an offline examination administration platform: examinees sit physically for examinations at physical test centers, write on physical answer scripts, and designated teachers evaluate physical scripts and submit validated marks through a secure web portal.

#### 1.2 System Scope
The system manages the entire offline examination lifecycle:
- Centralized multi-exam configuration and lifecycle management (Draft, Registration Open/Closed, Admit Card Published, Evaluation, Result Published, Archived).
- Geographic hierarchy: Examination → Zone → Center → Room → Seat.
- Canonical phone number normalization and registration number generation for Bangladeshi students.
- Multi-step Excel/CSV bulk import with schema mapping, conflict detection, and secure temporary credential generation.
- Role-Based Access Control (Admin, Teacher, Student) with dedicated portals.
- Rapid, keyboard-friendly teacher marks evaluation with automated negative marking calculation.
- Strict duplicate marks submission prevention via database constraints, atomic transactions, and mutex/locking.
- Merit list generation with multi-tier tie-breaking (Marks → Correct Answers → Incorrect Answers → Shared Rank).
- Dynamic PDF Admit Card generation with cryptographically secure QR code verification.
- Comprehensive reporting, audit logging, system settings, and in-app notifications.

---

### 2. User Roles & Personas

| Role | Description | Access Scope |
|---|---|---|
| **Admin** | Central examination board controller, system administrator | Full control over exams, zones, centers, rooms, student imports, teacher allocations, evaluation monitoring, marks corrections, results, audit logs, and settings. |
| **Teacher** | Designated academic evaluator assigned to evaluate physical answer scripts | Evaluation portal restricted to assigned examinations only. Rapid marks entry by student registration number, personal evaluation history, and correction request submission. |
| **Student** | Candidate enrolled in offline admission mock tests | Mobile-first portal. View enrolled exams, assigned center with Google Maps location, room & seat number, download PDF admit card, and view published results. |

---

### 3. Student Registration & Phone Canonicalization

#### 3.1 Bangladeshi Phone Number Normalization
All student phone numbers must be sanitized and normalized to a canonical Bangladeshi 11-digit mobile format:
- Accepted input formats:
  - `01712345678` (standard local format)
  - `8801712345678` (country code without plus)
  - `+8801712345678` (international format)
  - `1712345678` (10 digits without leading zero)
- Sanitization rules: Strip all spaces, dashes, parentheses, and leading `+` or `88` prefixes.
- Validation regex: `^01[3-9]\d{8}$` (11 digits starting with 013-019).

#### 3.2 Registration Number & Username Generation
- Registration number is derived strictly by stripping the first leading `0` from the canonical 11-digit mobile number:
  - Canonical Phone: `01712345678`
  - Registration Number: `1712345678` (10 digits)
  - Default Username: `1712345678`
- Both phone numbers and registration numbers are stored as indexed strings to prevent numeric truncation or leading zero loss.
- Registration numbers are globally unique in the student database.
- When importing students, if a registration number already exists, the student profile is reused and enrolled into the target examination without re-generating or overwriting existing passwords.
- New student accounts are provisioned with secure 8-character random temporary passwords, flagged with `must_change_password = true`, requiring immediate password update on first login.
- Admins have access to a one-time credential export immediately after an import batch.

---

### 4. Functional Requirements

#### 4.1 Examination Management
- **Lifecycle Statuses**:
  - `draft`: Creation stage, editing allowed.
  - `registration_open`: Registrations and student imports permitted.
  - `registration_closed`: Enrollment locked.
  - `admit_card_published`: Admit cards downloadable by students.
  - `evaluation_in_progress`: Teacher marks entry active.
  - `evaluation_completed`: Evaluation phase concluded.
  - `result_published`: Student results visible to students and public verification.
  - `archived`: Read-only historical record.
- **Marking Configuration**:
  - `total_marks`: Total marks attainable (e.g., 100.00).
  - `total_questions`: Total MCQ questions (e.g., 100).
  - `marks_per_correct`: Positive mark per correct answer (e.g., 1.00).
  - `negative_marks_per_incorrect`: Negative penalty per wrong answer (e.g., 0.25).
  - `marks_per_unanswered`: Marks for unanswered items (default 0.00).
  - `pass_marks`: Minimum qualifying score (e.g., 40.00).
- Config modifications are strictly locked once evaluations commence, unless audited admin override is triggered.

#### 4.2 Geographic Examination Center Hierarchy
- **Hierarchy**: Exam → Zone → Center → Room → Seat.
- Zones are dynamic entities (e.g., Dhaka, Chattogram, Rajshahi, Khulna, etc.).
- Centers contain: Name, Zone ID, Address, Google Maps Share URL, Google Maps Embed URL, Latitude/Longitude, Total Capacity, Contact Person, Contact Phone, Special Instructions.
- Exam Rooms contain: Room Number/Name, Floor, Capacity.
- Seats: Unique seat allocation per exam and center. Duplicate seat allocations are blocked at the database level.
- Front-end integration displays direct Google Maps links ("Open in Google Maps") and embed previews without requiring paid API keys.

#### 4.3 Multi-Step Excel/CSV Student Import
1. **Select Exam**: Target examination selection.
2. **Upload**: Drag-and-drop `.xlsx`, `.xls`, `.csv` file.
3. **Map Columns**: Map arbitrary spreadsheet headers to required system attributes (`name`, `phone`, `email`, `institution`, `hsc_batch`, `zone`, `center`, `room`, `seat`, etc.).
4. **Preview & Validate**: Server-side chunked validation validating phone numbers, center capacities, and existing enrollments.
5. **Conflict Resolution**: Display clean breakdown: valid new students, existing students to re-enroll, duplicate rows, and validation errors with row numbers.
6. **Execute & Transact**: Database transaction writes student profiles, user accounts, and exam registrations.
7. **Report & Credentials Export**: Downloadable batch summary and one-time temporary credentials sheet.

#### 4.4 Teacher Marks Evaluation Portal
- High-efficiency, keyboard-first evaluation interface.
- Evaluators select assigned exam and enter student Registration Number.
- Search loads student verification card (Name, Reg No, HSC Batch, Photo, Exam Name).
- **Two Evaluation Modes**:
  - **Mode A (Direct Marks)**: Teacher enters final obtained marks directly (manual calculation).
  - **Mode B (Automatic Calculation)**: Teacher enters `correct_answers`, `incorrect_answers`, and `unanswered_questions`. The system computes:
    $$\text{Final Marks} = (\text{Correct} \times \text{Marks/Correct}) - (\text{Incorrect} \times \text{Negative/Incorrect}) + (\text{Unanswered} \times \text{Marks/Unanswered})$$
    Enforces $\text{Correct} + \text{Incorrect} + \text{Unanswered} = \text{Total Questions}$.
- **Strict Concurrency & Duplicate Marks Prevention**:
  - Only one active marks record per `exam_registration_id`.
  - Database unique index on `exam_registration_id`.
  - Pessimistic row locking during submission.
  - If a student's marks have already been entered by any teacher, the UI displays: **"Marks Already Assigned"**, disables the form, and suppresses the previous teacher's identity or marks to ensure confidentiality and prevent collusion.
- Once submitted, records are locked. Evaluators can only view their own submission history and can submit formal **Correction Requests** with old marks, new marks, and reasons for admin review.

#### 4.5 Result Processing & Merit List Engine
- Comprehensive metrics: Total evaluated, average, highest, lowest score, pass percentage.
- **Tie-Breaking Hierarchy**:
  1. Final Obtained Marks (Descending)
  2. Total Correct Answers (Descending, if available)
  3. Total Incorrect Answers (Ascending, if available)
  4. Shared Rank (Identical rank assigned; next rank skips accordingly)
- Multi-dimensional ranks: Overall Merit Position, Zone-wise Merit Position, Center-wise Merit Position.
- Admin Pre-publication Preview: identifies unassigned marks, pending corrections, and unconfirmed attendance.
- Results remain hidden from student portal and public endpoints until explicitly published by an admin.

#### 4.6 Admit Card & Result PDFs
- Dynamic A4 PDF generation via DomPDF.
- EdGenie & NEXT MEDICALIAN official branding.
- Secure Admit Card features:
  - Student photo (or default placeholder).
  - Student registration number, phone, institution, HSC batch.
  - Examination schedule, center address, room, and seat.
  - High-resolution QR Code pointing to an opaque, signed verification URL (`/verify/admit/{signed_token}`).
  - Public verification page exposes strictly minimal verification metadata (Student Name initial, Reg No, Exam Name, Valid Status) to protect student privacy.
- Download tracking records IP, user agent, and timestamp.

#### 4.7 Audit Logging & Security
- Every administrative action, marks submission, correction, password reset, and status update is logged with user ID, role, action type, IP address, user agent, old values, and new values.
- Rate limiting on authentication (`/api/login`), password reset, and evaluation endpoints.
- Role-based authorization enforced via Laravel Policies and Sanctum API token scopes.

---

### 5. Non-Functional Requirements

- **Performance**: Sub-200ms API response time for evaluation lookups; sub-1s batch imports for chunks of 500 records.
- **Reliability**: Zero data loss guarantee via transactional rollbacks on import and marks submission.
- **Security**: OWASP Top 10 compliance, bcrypt password hashing (work factor 12), parameterized queries, strict XSS escaping, token-based API authentication.
- **Timezone**: Strict `Asia/Dhaka` (UTC+6) application and database timezone.
- **Deployability**: Standard PHP 8.2+ with MySQL 8.0/MariaDB compatibility on standard cPanel/Hostinger environments.
