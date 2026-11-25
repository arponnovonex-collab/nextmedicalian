<?php

use App\Http\Controllers\Api\Admin\BulkStudentImportController;
use App\Http\Controllers\Api\Admin\CenterController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\EvaluationMonitorController;
use App\Http\Controllers\Api\Admin\ExamController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\ResultController as AdminResultController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\StudentController;
use App\Http\Controllers\Api\Admin\StudentImportController;
use App\Http\Controllers\Api\Admin\TeacherController;
use App\Http\Controllers\Api\Admin\ZoneController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicVerificationController;
use App\Http\Controllers\Api\Student\StudentPortalController;
use App\Http\Controllers\Api\Teacher\TeacherEvaluationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::prefix('public')->group(function () {
    Route::get('/verify/admit/{token}', [PublicVerificationController::class, 'verifyAdmitCard']);
    Route::get('/verify/result/{token}', [PublicVerificationController::class, 'verifyResult']);
    Route::get('/template/student-import.csv', [StudentImportController::class, 'template']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    Route::get('/notifications', [AuthController::class, 'notifications']);
    Route::patch('/notifications/{id}/read', [AuthController::class, 'markNotificationRead']);

    /*
    |--------------------------------------------------------------------------
    | Admin Routes (role:admin)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard-stats', [AdminDashboardController::class, 'stats']);

        // Exams
        Route::apiResource('exams', ExamController::class);
        Route::patch('/exams/{id}/status', [ExamController::class, 'updateStatus']);
        Route::post('/exams/{id}/centers', [ExamController::class, 'syncCenters']);

        // Zones & Centers & Rooms
        Route::apiResource('zones', ZoneController::class);
        Route::apiResource('centers', CenterController::class);
        Route::get('/centers/{centerId}/rooms', [CenterController::class, 'rooms']);
        Route::post('/centers/{centerId}/rooms', [CenterController::class, 'addRoom']);
        Route::delete('/centers/{centerId}/rooms/{roomId}', [CenterController::class, 'deleteRoom']);

        // Student Management & Excel Import
        Route::get('/students', [StudentController::class, 'index']);
        Route::post('/students', [StudentController::class, 'store']);
        Route::post('/students/import-medicalian-dataset', [BulkStudentImportController::class, 'import']);
        Route::post('/students/import/medicalian-dataset', [BulkStudentImportController::class, 'import']);
        Route::get('/students/import/template', [StudentImportController::class, 'template']);
        Route::post('/students/import/preview', [StudentImportController::class, 'preview']);
        Route::post('/students/import/confirm', [StudentImportController::class, 'confirm']);
        Route::get('/students/import/credentials/{token}', [StudentImportController::class, 'exportCredentials']);

        Route::get('/students/{id}', [StudentController::class, 'show']);
        Route::put('/students/{id}', [StudentController::class, 'update']);
        Route::post('/students/{id}/reassign-seat', [StudentController::class, 'reassignSeat']);
        Route::post('/students/{id}/reset-password', [StudentController::class, 'resetPassword']);
        Route::patch('/students/{id}/toggle-status', [StudentController::class, 'toggleStatus']);
        Route::get('/students/{id}/admit-card', [StudentController::class, 'downloadAdmitCard']);

        // Teachers Management
        Route::apiResource('teachers', TeacherController::class);
        Route::post('/teachers/{id}/assign-exams', [TeacherController::class, 'assignExams']);
        Route::post('/teachers/{id}/reset-password', [TeacherController::class, 'resetPassword']);
        Route::patch('/teachers/{id}/toggle-status', [TeacherController::class, 'toggleStatus']);
        Route::get('/teachers/{id}/stats', [TeacherController::class, 'stats']);

        // Evaluation Monitoring & Marks Correction
        Route::get('/evaluations/monitor', [EvaluationMonitorController::class, 'monitor']);
        Route::get('/evaluations/marks', [EvaluationMonitorController::class, 'marks']);
        Route::get('/evaluations/corrections', [EvaluationMonitorController::class, 'corrections']);
        Route::post('/evaluations/corrections/{id}/review', [EvaluationMonitorController::class, 'reviewCorrection']);
        Route::post('/evaluations/admin-correct', [EvaluationMonitorController::class, 'adminCorrect']);
        Route::post('/evaluations/record-absence', [EvaluationMonitorController::class, 'recordAbsence']);

        // Results & Merit List Management
        Route::get('/results/preview', [AdminResultController::class, 'preview']);
        Route::post('/results/publish', [AdminResultController::class, 'publish']);
        Route::get('/results/export', [AdminResultController::class, 'exportMeritList']);

        // Reports & Downloads
        Route::get('/reports/attendance', [ReportController::class, 'attendanceSheet']);
        Route::get('/reports/seat-plan', [ReportController::class, 'seatPlan']);
        Route::get('/reports/evaluators', [ReportController::class, 'evaluatorsReport']);
        Route::get('/reports/center-admit-cards', [ReportController::class, 'centerAdmitCardsZip']);

        // System Settings & Audit Logs
        Route::get('/audit-logs', [SettingsController::class, 'auditLogs']);
        Route::get('/settings', [SettingsController::class, 'getSettings']);
        Route::put('/settings', [SettingsController::class, 'updateSettings']);
    });

    /*
    |--------------------------------------------------------------------------
    | Teacher Routes (role:teacher)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:teacher')->prefix('teacher')->group(function () {
        Route::get('/assigned-exams', [TeacherEvaluationController::class, 'assignedExams']);
        Route::get('/dashboard-stats', [TeacherEvaluationController::class, 'dashboardStats']);
        Route::get('/search-student', [TeacherEvaluationController::class, 'searchStudent']);
        Route::post('/submit-marks', [TeacherEvaluationController::class, 'submitMarks']);
        Route::get('/evaluation-history', [TeacherEvaluationController::class, 'evaluationHistory']);
        Route::post('/request-correction', [TeacherEvaluationController::class, 'requestCorrection']);
    });

    /*
    |--------------------------------------------------------------------------
    | Student Routes (role:student)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/dashboard', [StudentPortalController::class, 'dashboard']);
        Route::get('/exams/{examId}/admit-card', [StudentPortalController::class, 'downloadAdmitCard']);
        Route::get('/exams/{examId}/result', [StudentPortalController::class, 'viewResult']);
        Route::get('/exams/{examId}/result-pdf', [StudentPortalController::class, 'downloadResultPdf']);
    });
});
