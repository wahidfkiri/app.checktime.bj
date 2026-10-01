<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CheckTimeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\WorkHourController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\DelayController;
use App\Http\Controllers\DailyAttendanceController;
use App\Http\Controllers\DailyAttendanceTestController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleRotationController;
use App\Http\Controllers\ScheduleAssignmentController;
use App\Http\Controllers\EmployeeScheduleController;
use App\Http\Controllers\EmployeePermissionController;
use App\Http\Controllers\CustomReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SignataireController;
use App\Http\Controllers\BiometricController;
use App\Http\Controllers\MissionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/welcome', function () {
    return view('welcome');
});

Route::get('/', function () {
    return view('auth.login');
})->middleware('guest');



Route::get('/checktime/token', [CheckTimeController::class, 'testToken']);
Route::get('/checktime/devices', [CheckTimeController::class, 'getDevices']);
Route::get('/checktime/employees', [CheckTimeController::class, 'getEmployees']);
Route::post('/checktime/employees/create', [CheckTimeController::class, 'createEmployee']);
Route::get('/checktime/transactions', [CheckTimeController::class, 'getTransactions']);

Auth::routes();

Route::get('/home', function() { 
    return redirect()->route('dashboard');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('guest');
});

Route::middleware(['auth','web', 'role:client','client.active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/stats', [DashboardController::class, 'getStatsJson'])->name('super-admin.stats');
    Route::get('/client/{client}/details', [DashboardController::class, 'getClientDetails'])->name('client.details');
Route::get('/api/weekly-stats', [DashboardController::class, 'getWeeklyStats'])->name('api.weekly-stats');
    Route::get('/admin/clients', function () {
        return view('clients.index');
    })->name('admin.clients');

    // Route principale pour afficher la liste (avec DataTable)
    Route::get('/clients', [ClientController::class, 'index'])
        ->name('clients.index');
    
  


Route::prefix('devices')->name('devices.')->group(function () {
    Route::get('/', [DeviceController::class, 'index'])->name('index');
    Route::get('/local', [DeviceController::class, 'getLocalDevices'])->name('local');
    Route::post('/sync', [DeviceController::class, 'sync'])->name('sync');
    Route::post('/force-sync', [DeviceController::class, 'forceSync'])->name('force-sync');
    Route::get('/status', [DeviceController::class, 'syncStatus'])->name('status');
    Route::post('/reset', [DeviceController::class, 'resetAndSync'])->name('reset');   
});

Route::prefix('leaves')->name('leaves.')->group(function () {
    Route::get('/', [LeaveController::class, 'index'])->name('index');
    Route::post('/', [LeaveController::class, 'store'])->name('store');
    Route::put('/{id}', [LeaveController::class, 'update'])->name('update');
    Route::get('/{id}/edit', [LeaveController::class, 'edit'])->name('edit');
    Route::delete('/{id}', [LeaveController::class, 'destroy'])->name('destroy');
    Route::get('/datatable', [LeaveController::class, 'datatable'])->name('datatable');
    Route::put('/{id}/status', [LeaveController::class, 'updateStatus'])->name('status');
});

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile/update', [ProfileController::class, 'update'])->name('profile.update');


   

// Horaires rotatifs
Route::prefix('rotations')->name('rotations.')->group(function () {
    Route::get('/', [ScheduleRotationController::class, 'index'])->name('index');
    Route::post('/', [ScheduleRotationController::class, 'store'])->name('store');
    Route::put('/{scheduleRotation}', [ScheduleRotationController::class, 'update'])->name('update');
    Route::delete('/{scheduleRotation}', [ScheduleRotationController::class, 'destroy'])->name('destroy');
    Route::post('/generate', [ScheduleRotationController::class, 'generateNextRotations'])->name('generate');
});



// Gestion des autorisations (Absences/Retards/Congés)
Route::prefix('authorizations')->name('authorizations.')->group(function () {
    // Absences
    Route::prefix('absences')->name('absences.')->group(function () {
        Route::get('/', [AbsenceController::class, 'index'])->name('index');
        Route::post('/', [AbsenceController::class, 'store'])->name('store');
        Route::put('/{absence}', [AbsenceController::class, 'update'])->name('update');
        Route::delete('/{absence}', [AbsenceController::class, 'destroy'])->name('destroy');
        Route::post('/{absence}/approve', [AbsenceController::class, 'approve'])->name('approve');
        Route::post('/{absence}/reject', [AbsenceController::class, 'reject'])->name('reject');
    });
    
    // Retards
    Route::prefix('delays')->name('delays.')->group(function () {
        Route::get('/', [DelayController::class, 'index'])->name('index');
        Route::post('/', [DelayController::class, 'store'])->name('store');
        Route::put('/{delay}', [DelayController::class, 'update'])->name('update');
        Route::delete('/{delay}', [DelayController::class, 'destroy'])->name('destroy');
    });


    // Routes supplémentaires
    Route::prefix('employee-permissions')->name('employee-permissions.')->group(function () {
        Route::get('/', [EmployeePermissionController::class, 'index'])->name('index');
        Route::post('/', [EmployeePermissionController::class, 'store'])->name('store');
        Route::get('/{employeePermission}', [EmployeePermissionController::class, 'show'])->name('show'); 
        Route::get('/{employeePermission}/edit', [EmployeePermissionController::class, 'edit'])->name('edit');
        Route::put('/{employeePermission}', [EmployeePermissionController::class, 'update'])->name('update');
        Route::delete('/{employeePermission}', [EmployeePermissionController::class, 'destroy'])->name('delete');
        Route::post('/{employeePermission}/approve', [EmployeePermissionController::class, 'approve'])->name('approve');
        Route::post('/{employeePermission}/reject', [EmployeePermissionController::class, 'reject'])->name('reject');
        Route::get('/employee/{employee}', [EmployeePermissionController::class, 'byEmployee'])->name('by-employee');
        Route::get('/client/{client}', [EmployeePermissionController::class, 'byClient'])->name('by-client');
        Route::get('/export', [EmployeePermissionController::class, 'export'])->name('export');
    });
    // Congés (existant)
    Route::resource('leaves', LeaveController::class);
});

// Gestion des présences
Route::prefix('daily-attendance')->group(function () {
    Route::get('/', [DailyAttendanceController::class, 'index'])->name('daily-attendance.index');
    Route::get('/data', [DailyAttendanceController::class, 'getData'])->name('daily-attendance.data');
    Route::post('/sync', [DailyAttendanceController::class, 'sync'])->name('daily-attendance.sync');
    Route::get('/sync-status', [DailyAttendanceController::class, 'syncStatus'])->name('daily-attendance.sync-status');
    Route::get('/test-api', [DailyAttendanceController::class, 'testSync'])->name('daily-attendance.test-api');
    Route::get('/debug-codes', [DailyAttendanceController::class, 'debugEmpCodes'])->name('daily-attendance.debug-codes');
     Route::get('/get-employee-by-code', [DailyAttendanceController::class, 'getEmployeeByCode'])->name('daily-attendance.get-employee-by-code');
     Route::post('/export-pdf', [DailyAttendanceController::class, 'exportPDF'])->name('daily-attendance.export-pdf');
     Route::get('/api-diagnostic', [DailyAttendanceController::class, 'apiDiagnostic'])->name('api.diagnostic');
});

// Rapports
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/absences-delays', [ReportController::class, 'absencesDelays'])->name('absences-delays');
    Route::get('/attendances', [ReportController::class, 'absencesDelays'])->name('attendance');
    Route::get('/custom', [ReportController::class, 'custom'])->name('custom');
    Route::get('/export', [ReportController::class, 'export'])->name('export');
    Route::get('/automated', [ReportController::class, 'automated'])->name('automated');
    Route::get('/data', [ReportController::class, 'getData'])->name('data');
    Route::get('/debug', [ReportController::class, 'debugGetData'])->name('reports.debug');
    Route::post('/export/pdf', [ReportController::class, 'exportPdf'])->name('export.pdf');
    Route::get('/preview/pdf', [ReportController::class, 'previewPdf'])->name('preview.pdf');

    Route::post('/custom/export-pdf', [CustomReportController::class, 'exportCustomPdf'])
        ->name('reports.custom.export.pdf');
    
    // Route pour vérifier le statut
    Route::get('/custom/check-pdf-status/{request_id}', [CustomReportController::class, 'checkPdfStatus'])
        ->name('reports.custom.check-pdf-status');
    
    // Route pour télécharger
    Route::get('/custom/download-pdf/{request_id}', [CustomReportController::class, 'downloadPdf'])
        ->name('reports.custom.download-pdf');
    
    // Route synchrone (pour backup)
    Route::post('/custom/export-pdf-sync', [CustomReportController::class, 'exportCustomPdfSync'])
        ->name('reports.custom.export.pdf.sync');
});


// Paramètres
Route::prefix('settings')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/update', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/test-rh', [SettingsController::class, 'testRhEmail'])->name('settings.test.rh');
    Route::post('/test-employees', [SettingsController::class, 'testEmployeesEmail'])->name('settings.test.employees');
    Route::get('/status', [SettingsController::class, 'getStatus'])->name('settings.status');

    // Signataires (cartouche de signatures des rapports)
    Route::get('/signataires', [SignataireController::class, 'index'])->name('settings.signataires.index');
    Route::post('/signataires/postes', [SignataireController::class, 'storePoste'])->name('settings.signataires.postes.store');
    Route::put('/signataires/postes/{id}', [SignataireController::class, 'updatePoste'])->name('settings.signataires.postes.update');
    Route::delete('/signataires/postes/{id}', [SignataireController::class, 'destroyPoste'])->name('settings.signataires.postes.destroy');
    Route::post('/signataires/responsables', [SignataireController::class, 'storeSignataire'])->name('settings.signataires.responsables.store');
    Route::delete('/signataires/responsables/{id}', [SignataireController::class, 'destroySignataire'])->name('settings.signataires.responsables.destroy');
});
// Routes pour le rapport personnalisé
Route::get('/rapport/presence-ponctualite', [CustomReportController::class, 'presencePonctualite'])->name('reports.custom.presence');
Route::post('/rapport/presence-ponctualite/generate', [CustomReportController::class, 'generateCustomReport'])->name('reports.custom.generate');
Route::post('/rapport/presence-ponctualite/export-pdf', [CustomReportController::class, 'exportCustomPdf'])->name('reports.custom.export.pdf');
Route::post('/rapport/presence-ponctualite/export-dept-pdf', [CustomReportController::class, 'exportCustomPdfByDept'])->name('reports.export-department-pdf');


Route::prefix('missions')->name('missions.')->group(function () {
    Route::get('/', [MissionController::class, 'index'])->name('index');
    Route::post('/', [MissionController::class, 'store'])->name('store');
    Route::get('/generate-reference', [MissionController::class, 'generateReference'])->name('generate-reference');
    Route::get('/{id}', [MissionController::class, 'show'])->name('show');
    Route::put('/{id}', [MissionController::class, 'update'])->name('update');
    Route::delete('/{id}', [MissionController::class, 'destroy'])->name('destroy');
});

});

Route::middleware(['auth','web'])->group(function () {
    // Route pour les transactions
    Route::get('/api/transactions', [BiometricController::class, 'getTransactions']);
    
    // Route pour la vérification biométrique (identification par id unique de l'employé)
    Route::get('/api/biometric/{id}', [BiometricController::class, 'getBiometricVerification']);
});




