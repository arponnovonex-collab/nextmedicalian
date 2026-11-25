# NEXT MEDICALIAN – Test Plan & Quality Assurance

---

### 1. Test Strategy Overview

Quality assurance for NEXT MEDICALIAN focuses rigorously on:
1. **Data Integrity & Concurrency**: Preventing duplicate marks submissions, race conditions, and negative mark calculation inaccuracies.
2. **Phone Normalization & Unique Registration Numbers**: Ensuring every variation of Bangladeshi mobile numbers resolves accurately to canonical format and derived registration numbers.
3. **Security & Privacy**: Strict role-based endpoint isolation, student privacy protection, and sanitization.
4. **Offline Examination Workflows**: Admit card generation, seating checks, and result merit calculations.

---

### 2. Test Suites & Test Cases

#### Suite 1: Phone Normalization & Registration Number Derivation
- `test_phone_normalization_standard_format`: Input `01712345678` -> Canonical `01712345678`, Reg No `1712345678`.
- `test_phone_normalization_with_country_code`: Input `+8801712345678` or `8801712345678` -> Canonical `01712345678`, Reg No `1712345678`.
- `test_phone_normalization_without_leading_zero`: Input `1712345678` -> Canonical `01712345678`, Reg No `1712345678`.
- `test_phone_validation_rejects_invalid_prefixes`: Input `01012345678` or `01212345678` -> Fails validation.
- `test_phone_validation_rejects_invalid_lengths`: Input `017123` or `0171234567890` -> Fails validation.

#### Suite 2: Authentication & Role-Based Access Control
- `test_admin_can_login_and_access_admin_routes`.
- `test_teacher_cannot_access_admin_routes`.
- `test_student_cannot_access_teacher_or_admin_routes`.
- `test_unauthenticated_request_is_rejected_with_401`.
- `test_must_change_password_flag_enforces_password_update`.

#### Suite 3: Excel Import & Student Account Reuse
- `test_import_creates_new_student_and_user_with_temp_password`.
- `test_import_reuses_existing_student_for_second_exam_without_overwriting_password`.
- `test_import_detects_duplicate_registration_in_same_exam`.
- `test_import_allocates_center_and_seat_within_capacity`.

#### Suite 4: Teacher Marks Evaluation & Strict Duplicate Prevention
- `test_teacher_can_submit_marks_for_assigned_exam`.
- `test_teacher_cannot_submit_marks_for_unassigned_exam`.
- `test_automatic_negative_marking_calculation`:
  $$(70 \times 1.0) - (20 \times 0.25) + 0 = 65.00$$
- `test_questions_count_validation`: Correct + Incorrect + Unanswered must equal Total Questions.
- `test_strict_duplicate_marks_prevention`:
  When Teacher A submits marks for student X, a subsequent submission by Teacher B for student X throws duplicate conflict and returns 409 "Marks Already Assigned".
- `test_teacher_can_only_view_own_evaluated_history`.
- `test_teacher_cannot_see_marks_submitted_by_other_teachers`.

#### Suite 5: Result Processing & Merit List Generation
- `test_merit_list_ranks_highest_marks_first`.
- `test_tie_breaking_higher_correct_answers_wins`.
- `test_tie_breaking_lower_incorrect_answers_wins`.
- `test_shared_rank_assigned_when_ties_cannot_be_broken`.
- `test_unpublished_results_hidden_from_students`.
- `test_published_results_accessible_to_enrolled_student`.

#### Suite 6: Admit Card Generation
- `test_admit_card_generation_requires_center_allocation`.
- `test_admit_card_generation_blocked_before_publish_date`.
- `test_qr_code_contains_valid_signed_verification_token`.
- `test_public_verification_page_masks_personal_details`.

---

### 3. Execution Commands
```bash
# Backend Automated Tests
php artisan test

# Frontend Build & Type Verification
npm.cmd run build
```
