<?php

use App\Http\Controllers\Api\AdminExamMonitoringController;
use App\Http\Controllers\Api\AdminExamResultController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamAssignmentController;
use App\Http\Controllers\Api\ExamPublishController;
use App\Http\Controllers\Api\EmployeeExamController;
use App\Http\Controllers\Api\EmployeeAttemptController;
use App\Http\Controllers\Api\EmployeeExamListController;
use App\Http\Controllers\Api\EmployeeHistoryController;
use App\Http\Controllers\Api\EmployeeResultController;


Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/units', [UnitController::class, 'index']);
    Route::get('/admin/dashboard/summary', [DashboardController::class, 'summary']);
    Route::apiResource('/participants', ParticipantController::class);
    Route::apiResource('/questions', QuestionController::class);
    Route::get('/admin/exams', [ExamController::class, 'index']);
Route::post('/admin/exams', [ExamController::class, 'store']);
Route::get(
    '/employee/attempts/{attempt}/result',
    [EmployeeResultController::class, 'show']
);

Route::get(
    '/employee/attempts/{attempt}/certificate',
    [EmployeeResultController::class, 'certificate']
);


// Preview dan penghapusan paket ujian
Route::post('/admin/exams/bulk-delete-preview', [ExamController::class, 'bulkDeletePreview']);
Route::post('/admin/exams/bulk-delete', [ExamController::class, 'bulkDestroy']);
Route::get('/admin/exams/{exam}/delete-preview', [ExamController::class, 'deletePreview']);
Route::delete('/admin/exams/{exam}', [ExamController::class, 'destroy']);

Route::get('/admin/exams/{exam}', [ExamController::class, 'show']);
    Route::get('/admin/exams/{exam}/assignments',[ExamAssignmentController::class, 'index']);
    Route::post('/admin/exams/{exam}/assignments',[ExamAssignmentController::class, 'store']);
    Route::post('/admin/exams/{exam}/publish', ExamPublishController::class);
    Route::post('/employee/exams/{exam}/start', [EmployeeExamController::class, 'start']);
    Route::get('/employee/attempts/{attempt}', [EmployeeAttemptController::class, 'show']);
    Route::put('/employee/attempts/{attempt}/answers/{questionId}', [EmployeeAttemptController::class, 'saveAnswer']);
    Route::post('/employee/attempts/{attempt}/submit', [EmployeeAttemptController::class, 'submit']);
    Route::get('/employee/exams', EmployeeExamListController::class);
    Route::get('/employee/history', EmployeeHistoryController::class);
    Route::get('/admin/exam-results', [AdminExamResultController::class, 'index']);
    Route::get('/admin/exam-monitoring',
    [AdminExamMonitoringController::class, 'index']);

});
