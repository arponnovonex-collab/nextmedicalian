# NEXT MEDICALIAN – RESTful API Specification

---

### 1. Conventions & Standards

- **Base URL**: `/api`
- **Data Format**: `application/json`
- **Authentication**: Bearer Token via header: `Authorization: Bearer <sanctum_token>`
- **Timezone**: All timestamps are formatted ISO-8601 strings in `Asia/Dhaka` timezone.
- **Success Response Structure**:
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... }
}
```
- **Error Response Structure**:
```json
{
  "success": false,
  "message": "Validation failed / Unauthorized / Not found",
  "errors": {
    "field_name": ["Specific validation error message"]
  }
}
```

---

### 2. Authentication & Profile Endpoints

| Method | Endpoint | Access | Description |
|---|---|---|---|
| `POST` | `/api/auth/login` | Public | Authenticates via username/phone & password. Returns token & user profile. |
| `POST` | `/api/auth/logout` | Authenticated | Revokes current Sanctum token. |
| `GET` | `/api/auth/me` | Authenticated | Returns current authenticated user and role-specific profile. |
| `POST` | `/api/auth/change-password` | Authenticated | Changes user password. Clears `must_change_password` flag. |
| `GET` | `/api/notifications` | Authenticated | Retrieves current user's in-app notifications. |
| `PATCH` | `/api/notifications/{id}/read` | Authenticated | Marks a notification as read. |

---

### 3. Admin Endpoints

#### 3.1 Dashboard & Analytics
- `GET /api/admin/dashboard-stats?exam_id={optional}`: Returns total students, exams, evaluations, zones, charts, and breakdown metrics.

#### 3.2 Examination Management
- `GET /api/admin/exams`: List all exams with filters.
- `POST /api/admin/exams`: Create a new exam with marking rules and schedule.
- `GET /api/admin/exams/{id}`: Detailed exam information.
- `PUT /api/admin/exams/{id}`: Update exam configuration (restricted if evaluation started).
- `PATCH /api/admin/exams/{id}/status`: Transition exam status (`registration_open`, `admit_card_published`, `result_published`, etc.).
- `DELETE /api/admin/exams/{id}`: Soft archive exam.

#### 3.3 Zone, Center, & Room Management
- `GET /api/admin/zones`: List all zones.
- `POST /api/admin/zones`: Create new zone.
- `PUT /api/admin/zones/{id}`: Update zone.
- `GET /api/admin/centers`: List centers with zone filter.
- `POST /api/admin/centers`: Create center with Google Maps URL and capacities.
- `PUT /api/admin/centers/{id}`: Update center.
- `GET /api/admin/centers/{centerId}/rooms`: List rooms for a center.
- `POST /api/admin/centers/{centerId}/rooms`: Add room.
- `POST /api/admin/exams/{examId}/centers`: Assign centers and allocated capacities to exam.

#### 3.4 Student Management & Multi-Step Import
- `GET /api/admin/students`: Search and filter registered students.
- `POST /api/admin/students`: Manually create/register a single student.
- `PUT /api/admin/students/{id}`: Update student profile.
- `POST /api/admin/students/{id}/reassign-seat`: Update assigned center, room, or seat.
- `POST /api/admin/students/{id}/reset-password`: Admin-initiated password reset.
- `POST /api/admin/students/import/preview`: Multi-step upload: parse file, map columns, preview records, return validation diagnostics.
- `POST /api/admin/students/import/confirm`: Execute import batch: creates users, student profiles, and registrations, returns summary and credential export token.
- `GET /api/admin/students/import/credentials/{token}`: One-time temporary credential export CSV/XLSX.
- `GET /api/admin/students/export-attendance?exam_id={id}&center_id={optional}`: Download attendance sheet PDF/Excel.

#### 3.5 Teacher Management
- `GET /api/admin/teachers`: List teachers and assignments.
- `POST /api/admin/teachers`: Create teacher account.
- `PUT /api/admin/teachers/{id}`: Update teacher details.
- `POST /api/admin/teachers/{id}/assign-exams`: Assign teacher to exams.
- `GET /api/admin/teachers/{id}/stats`: Evaluation metrics for a teacher.

#### 3.6 Evaluation Monitoring & Marks Correction
- `GET /api/admin/evaluations/monitor?exam_id={id}`: Live evaluation progress, teacher counts, pending counts.
- `GET /api/admin/evaluations/marks?exam_id={id}`: List all submitted marks with evaluator identity.
- `GET /api/admin/evaluations/corrections`: List pending teacher correction requests.
- `POST /api/admin/evaluations/corrections/{id}/review`: Approve or reject correction request.
- `POST /api/admin/evaluations/admin-correct`: Direct audited admin marks correction.

#### 3.7 Result Processing & Publication
- `GET /api/admin/results/preview?exam_id={id}`: Preview calculated merit list, statistics, and publication warnings.
- `POST /api/admin/results/publish`: Calculate final merit ranks and snapshot results for public release.
- `GET /api/admin/results/export?exam_id={id}&format={pdf|xlsx}`: Download complete merit list.

#### 3.8 Audit Logs & Settings
- `GET /api/admin/audit-logs`: Searchable audit trail.
- `GET /api/admin/settings`: Retrieve organization and system settings.
- `PUT /api/admin/settings`: Save system settings.

---

### 4. Teacher Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/teacher/assigned-exams` | Exams assigned to the authenticated teacher. |
| `GET` | `/api/teacher/dashboard-stats` | Total scripts evaluated by teacher, today's count, deadlines. |
| `GET` | `/api/teacher/search-student?exam_id={id}&reg_no={reg_no}` | Look up student for evaluation. Checks exam assignment and duplicate marks lock. |
| `POST` | `/api/teacher/submit-marks` | Atomically submit marks (Mode A or Mode B). Protected by unique constraint and transaction lock. |
| `GET` | `/api/teacher/evaluation-history?exam_id={id}` | View list of scripts personally submitted by this teacher only. |
| `POST` | `/api/teacher/request-correction` | Submit correction request for teacher's own submitted mark. |

---

### 5. Student Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/student/dashboard` | Student registered exams, countdown, center info with Google Maps URL. |
| `GET` | `/api/student/exams/{id}/admit-card` | Stream/download dynamic PDF admit card (if published). Tracks download count. |
| `GET` | `/api/student/exams/{id}/result` | View published result with marks, ranks, and breakdown. |
| `GET` | `/api/student/exams/{id}/result-pdf` | Download official PDF result sheet (if published). |

---

### 6. Public Verification Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/public/verify/admit/{token}` | Public verification of admit card QR code. Exposes only safe, non-sensitive verification status. |
| `GET` | `/api/public/verify/result/{token}` | Public verification of published result sheet. |
