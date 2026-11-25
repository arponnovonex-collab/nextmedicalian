# NEXT MEDICALIAN – System Architecture Document

---

### 1. High-Level Architecture Overview

NEXT MEDICALIAN is engineered as a decoupled, modern full-stack web application adhering to the **Single Page Application (SPA) + RESTful API** architectural pattern.

```
+------------------------------------------------------------------------+
|                            CLIENT TIER                                 |
|                                                                        |
|    +--------------------+  +--------------------+  +-----------------+ |
|    |  Admin Dashboard   |  | Teacher Portal     |  | Student Portal  | |
|    |  (React 19 + Vite) |  | (Keyboard-First)   |  | (Mobile-First)  | |
|    +--------------------+  +--------------------+  +-----------------+ |
|               |                       |                     |          |
|               +-----------------------+---------------------+          |
|                                       | (HTTPS / JSON / Bearer Token)  |
+---------------------------------------|--------------------------------+
                                        v
+------------------------------------------------------------------------+
|                          APPLICATION TIER                              |
|                          (Laravel 12 / PHP 8.2)                        |
|                                                                        |
|  +-------------------+  +--------------------+  +--------------------+ |
|  | Route & Sanctum   |  | Request Validation |  | Role & Policy      | |
|  | Auth Middleware   |  | (Form Requests)    |  | Authorization      | |
|  +-------------------+  +--------------------+  +--------------------+ |
|                                   |                                    |
|  +------------------------------------------------------------------+  |
|  |                      CONTROLLER LAYER                            |  |
|  |  (Thin controllers: Auth, Exam, Center, Import, Eval, Result)    |  |
|  +------------------------------------------------------------------+  |
|                                   |                                    |
|  +------------------------------------------------------------------+  |
|  |                        SERVICE LAYER                             |  |
|  |  * PhoneNormalizationService    * StudentImportService           |  |
|  |  * EvaluationService (Locking)  * ResultProcessingService        |  |
|  |  * AdmitCardService (DomPDF/QR) * AuditLogService                |  |
|  |  * ExportReportService          * SeatAllocationService          |  |
|  +------------------------------------------------------------------+  |
|                                   |                                    |
|  +------------------------------------------------------------------+  |
|  |                    DATA ACCESS & MODELS (ELOQUENT)               |  |
|  |  User, Profile, Exam, Zone, Center, Room, Registration, Marks...  |  |
|  +------------------------------------------------------------------+  |
+-----------------------------------|------------------------------------+
                                    v
+------------------------------------------------------------------------+
|                            DATABASE TIER                               |
|                     MySQL 8.0 / MariaDB 10.4+                          |
|             (ACID Transactions, Foreign Keys, Unique Indexes)          |
+------------------------------------------------------------------------+
```

---

### 2. Architectural Principles & Patterns

1. **Separation of Concerns (SoC)**:
   - **Controllers**: Handle HTTP input mapping, call service methods, and return standardized JSON API resources or responses.
   - **Form Requests**: Enforce strict server-side validation rules before execution reaches controllers.
   - **Service Layer**: Encapsulates all domain and business logic (marks computation, rank calculation, file imports, PDF generation).
   - **Eloquent Models**: Define relational mappings, query scopes, and casts.

2. **Security & Authorization**:
   - Token-based API authentication via **Laravel Sanctum**.
   - Role-Based Access Control (RBAC) enforced via dedicated middleware (`role:admin`, `role:teacher`, `role:student`) and Laravel Policies (`ExamPolicy`, `EvaluationPolicy`, `AdmitCardPolicy`).
   - All authorization derives strictly from the authenticated token context (`Auth::user()`), never client-supplied IDs.

3. **Concurrency & Data Integrity**:
   - Database-level unique constraint: `UNIQUE(exam_registration_id)` on `marks`.
   - Transactional isolation with pessimistic locking (`DB::transaction` with `lockForUpdate()`) during marks submission and seat allocations to prevent race conditions.

4. **Timezone Uniformity**:
   - The entire platform operates in `Asia/Dhaka` (UTC+6). All deadlines, start times, and audit logs are recorded and parsed in this timezone.

---

### 3. Frontend Architecture

- **Build Tool**: Vite 7 with React 19.
- **Styling**: Tailwind CSS with custom theme (Deep Navy `#0F172A` / `#1E293B`, Medical Blue `#2563EB`, Success Emerald `#059669`, Warning Amber `#D97706`, Danger Rose `#E11D48`).
- **State & Context Management**: React Context (`AuthContext`, `ExamContext`, `NotificationContext`) with custom hooks.
- **Routing**: `react-router-dom` with strict protected route wrappers (`RequireAuth`, `RequireRole`).
- **Icons**: `lucide-react` for clean, professional iconography.
- **Charts**: `recharts` for dynamic, responsive data visualizations on the admin and teacher dashboards.
- **PDF & Printing**: Native browser print stylesheets and direct PDF streaming endpoints for admit cards and result sheets.

---

### 4. Integration & Third-Party Services

- **PDF Generation**: `barryvdh/laravel-dompdf` for server-side vector A4 rendering.
- **QR Code Generation**: `simplesoftwareio/simple-qrcode` or standalone SVG generator for high-contrast, scalable QR codes containing signed verification tokens.
- **Spreadsheet Processing**: `phpoffice/phpspreadsheet` / `maatwebsite/excel` for high-throughput streaming imports and exports.
- **Maps**: Google Maps Universal Embed and direct web URLs (`https://www.google.com/maps/search/?api=1&query=...`), eliminating API key costs.
