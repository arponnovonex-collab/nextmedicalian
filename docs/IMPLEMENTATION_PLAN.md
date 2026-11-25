# NEXT MEDICALIAN – Implementation Plan & Milestone Checklist

---

### Milestone Tracker

- [ ] **Phase 1: Project Foundation & Architecture**
  - [x] Technical Documentation (SRS, Architecture, Database, API, Implementation Plan, Test Plan, Deployment)
  - [ ] Package Dependencies (Sanctum, DomPDF, Excel, Tailwind, Lucide, Recharts)
  - [ ] Database Connection & Configuration (MySQL / SQLite test support)
  - [ ] Core Models, Migrations & Seeders
  - [ ] Authentication & Role-Based Authorization
  - [ ] Base Layouts & Route Wrappers

- [ ] **Phase 2: Exam & Geographic Center Management**
  - [ ] Zone CRUD & Seeder for Bangladesh Divisions
  - [ ] Center Management with Google Maps URL/Embed & Capacities
  - [ ] Exam Rooms & Seat Layouts
  - [ ] Examination CRUD & Marking Rules Configuration
  - [ ] Exam Center Allocations
  - [ ] Teacher Account Management & Exam Assignments

- [ ] **Phase 3: Student Management & Excel Bulk Import**
  - [ ] Phone Normalization & Registration Number Generator Service
  - [ ] Multi-Step Excel/CSV Import Workflow (Upload -> Map -> Validate -> Preview -> Confirm)
  - [ ] Existing Student Reuse & Idempotent Enrollments
  - [ ] Secure Temporary Credential Generator & Protected Export
  - [ ] Seat Allocation Service & Capacity Enforcement
  - [ ] Student Management Table with Multi-Filter Search

- [ ] **Phase 4: Student Portal & Admit Card Engine**
  - [ ] Mobile-First Student Dashboard with Exam Switcher
  - [ ] Center Location & Google Maps Integration
  - [ ] Exam Countdown & Rule Card
  - [ ] DomPDF Admit Card Generator (A4 layout, EdGenie branding)
  - [ ] High-Contrast QR Code with Signed Verification Token
  - [ ] Public Admit Verification Page & Download Tracking

- [ ] **Phase 5: Teacher Evaluation Engine & Duplicate Prevention**
  - [ ] Teacher Dashboard with Fast Script Counter
  - [ ] Rapid Student Search by Registration Number
  - [ ] Dual Evaluation Modes: Direct Marks vs Automatic Negative Calculation
  - [ ] Strict Concurrency & Duplicate Submission Prevention
  - [ ] "Marks Already Assigned" Privacy-Preserving Lockout
  - [ ] Teacher Submission History & Correction Requests

- [ ] **Phase 6: Result Processing & Merit List Engine**
  - [ ] Admin Evaluation Monitor (Stats, Absences, Teacher Breakdown)
  - [ ] Automated Merit List Calculation Engine (Overall, Zone, Center Ranks)
  - [ ] Multi-Tier Tie-Breaking Logic
  - [ ] Admin Pre-Publication Preview & Validation Warnings
  - [ ] Safe Result Publication Snapshotting
  - [ ] Student Result Dashboard & Official PDF Result Sheet

- [ ] **Phase 7: Reports, Audit Logs, Settings & Verification**
  - [ ] Reports & Attendance Sheet Exports (Excel & PDF)
  - [ ] In-App Notification System
  - [ ] Comprehensive Audit Trail Logger
  - [ ] Admin Branding & System Settings
  - [ ] Automated Test Suite (Unit & Feature Tests)
  - [ ] Production Vite Build & Hostinger Deployment Verification
