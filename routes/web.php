<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectManageController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\RequirementCrudController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\DriveAuthController;
use App\Http\Controllers\DriveSettingsController;
use App\Http\Controllers\DriveUploadSessionController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectDriveEvidenceController;
use App\Http\Controllers\ProjectBankController;
use App\Http\Controllers\AttachmentPackageRunController;
use App\Http\Controllers\MeetingAttendancePublicController;
use App\Http\Controllers\MeetingAttendanceSessionController;
use App\Http\Controllers\ProjectTransferRequestController;
use App\Http\Controllers\ProjectTransferReviewController;
use App\Http\Controllers\RequirementEvidencePreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/preview/email-welcome', function () {
    return view('emails.user-welcome', [
        'name' => 'Jonathan Jimenez',
        'email' => 'joni051@aim.com',
        'loginUrl' => url('/panel/login'),
        'logoUrl' => url('/img/logo.jpg'),
    ]);
});

Route::middleware('guest')->get('/login', function () {
    return redirect('/panel/login');
});

Route::get('/attendance/{token}', [MeetingAttendancePublicController::class, 'showSession'])->name('attendance.session');
Route::get('/attendance/{token}/register', [MeetingAttendancePublicController::class, 'showRegister'])->name('attendance.register');
Route::get('/attendance/{token}/summary', [MeetingAttendancePublicController::class, 'summary'])->name('attendance.summary');
Route::get('/attendance/{token}/download/xlsx', [MeetingAttendancePublicController::class, 'downloadXlsx'])->name('attendance.download.xlsx');
Route::get('/attendance/{token}/download/pdf', [MeetingAttendancePublicController::class, 'downloadPdf'])->name('attendance.download.pdf');
Route::post('/attendance/{token}', [MeetingAttendancePublicController::class, 'submit'])->name('attendance.submit');

Route::middleware([
    'auth',
    'verified',
    'admin',
])->get('/drive/auth', [DriveAuthController::class, 'redirect'])->name('drive.auth');

Route::middleware(['auth', 'verified', 'admin'])
    ->match(['get', 'post'], '/drive/callback', [DriveAuthController::class, 'callback'])
    ->name('drive.callback');
Route::middleware(['auth', 'verified', 'admin'])
    ->match(['get', 'post'], '/panel/drive/callback', [DriveAuthController::class, 'callback'])
    ->name('drive.callback.panel');

Route::middleware([
    'auth',
    'verified',
])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('filament.admin.resources.projects.index'))->name('dashboard');

    Route::get('/projects', fn () => redirect()->route('filament.admin.resources.projects.index'))->name('projects.index');
    Route::get('/projects/create', fn () => redirect()->route('filament.admin.resources.projects.create'))->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}/edit', fn ($project) => redirect()->route('filament.admin.resources.projects.edit', ['record' => $project]))->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');

    Route::get('/projects/{project}/checklist', [ChecklistController::class, 'show'])->name('projects.checklist');
    Route::put('/projects/{project}/checklist', [ChecklistController::class, 'update'])->name('projects.checklist.update');

    Route::get('/projects/{project}/manage', [ProjectManageController::class, 'show'])->name('projects.manage');
    Route::post('/projects/{project}/manage/renumber', [ProjectManageController::class, 'renumberUploads'])->name('projects.manage.renumber');
    Route::post('/projects/{project}/manage/custom-certifications', [ProjectManageController::class, 'storeCustomCertification'])->name('projects.manage.custom_certifications.store');
    Route::post('/projects/{project}/manage/{requirement}', [ProjectManageController::class, 'upload'])->name('projects.manage.upload');
    Route::post('/projects/{project}/requirements/{requirement}/uploads/init', [DriveUploadSessionController::class, 'init'])->name('projects.requirements.uploads.init');
    Route::patch('/drive-upload-sessions/{session}/progress', [DriveUploadSessionController::class, 'progress'])->name('drive-upload-sessions.progress');
    Route::post('/drive-upload-sessions/{session}/complete', [DriveUploadSessionController::class, 'complete'])->name('drive-upload-sessions.complete');
    Route::post('/drive-upload-sessions/{session}/fail', [DriveUploadSessionController::class, 'fail'])->name('drive-upload-sessions.fail');
    Route::post('/drive-upload-sessions/{session}/cancel', [DriveUploadSessionController::class, 'cancel'])->name('drive-upload-sessions.cancel');
    Route::post('/drive-upload-sessions/{session}/verify', [DriveUploadSessionController::class, 'verify'])->name('drive-upload-sessions.verify');
    Route::get('/projects/{project}/drive/files', [ProjectDriveEvidenceController::class, 'listFiles'])->name('projects.drive.files');
    Route::post('/projects/{project}/requirements/{requirement}/link-drive-file', [ProjectDriveEvidenceController::class, 'linkFile'])->name('projects.requirements.link_drive_file');
    Route::delete('/projects/{project}/requirements/{requirement}/drive-evidences/{evidence}', [ProjectDriveEvidenceController::class, 'unlinkFile'])->name('projects.requirements.unlink_drive_file');
    Route::delete('/projects/{project}/requirements/{requirement}/drive-evidences/{evidence}/file', [ProjectDriveEvidenceController::class, 'deleteDriveFile'])->name('projects.requirements.delete_drive_file');
    Route::post('/projects/{project}/requirements/link-drive-files-bulk', [ProjectDriveEvidenceController::class, 'linkFilesBulk'])->name('projects.requirements.link_drive_files_bulk');
    Route::get('/projects/{project}/attachments-pdf/runs', [AttachmentPackageRunController::class, 'index'])->name('projects.attachments.runs.index');
    Route::post('/projects/{project}/attachments-pdf/runs', [AttachmentPackageRunController::class, 'store'])->name('projects.attachments.runs.store');
    Route::get('/projects/{project}/attachments-pdf/runs/{run}', [AttachmentPackageRunController::class, 'show'])->name('projects.attachments.runs.show');
    Route::post('/projects/{project}/attachments-pdf/runs/{run}/cancel', [AttachmentPackageRunController::class, 'cancel'])->name('projects.attachments.runs.cancel');
    Route::get('/projects/{project}/attachments-pdf/runs/{run}/preview', [AttachmentPackageRunController::class, 'preview'])->name('projects.attachments.runs.preview');
    Route::get('/projects/{project}/attachments-pdf/runs/{run}/download', [AttachmentPackageRunController::class, 'download'])->name('projects.attachments.runs.download');
    Route::get('/requirement-evidences/{evidence}/preview', [RequirementEvidencePreviewController::class, 'show'])->name('requirement-evidences.preview');
    Route::get('/requirement-evidences/{evidence}/download', [RequirementEvidencePreviewController::class, 'download'])->name('requirement-evidences.download');
    Route::post('/projects/{project}/mga-transfer/send', [ProjectTransferRequestController::class, 'send'])->name('projects.mga_transfer.send');
    Route::post('/projects/{project}/mga-transfer/{transferRequest}/approve', [ProjectTransferRequestController::class, 'approve'])->name('projects.mga_transfer.approve');
    Route::post('/projects/{project}/mga-transfer/{transferRequest}/reject', [ProjectTransferRequestController::class, 'reject'])->name('projects.mga_transfer.reject');
    Route::post('/projects/{project}/mga-transfer/{transferRequest}/acknowledge', [ProjectTransferRequestController::class, 'acknowledge'])->name('projects.mga_transfer.acknowledge');
    Route::post('/panel/project-transfer-requests/{transferRequest}/comments', [ProjectTransferReviewController::class, 'saveComments'])->name('project-transfer-requests.comments');
    Route::post('/panel/project-transfer-requests/{transferRequest}/{decision}', [ProjectTransferReviewController::class, 'decide'])
        ->where('decision', 'approve|reject')
        ->name('project-transfer-requests.decide');

    Route::get('/projects/{project}/documents', [ProjectDocumentController::class, 'index'])->name('projects.documents');
    Route::get('/projects/{project}/documents/{documentTemplate}', [ProjectDocumentController::class, 'download'])->name('projects.documents.download');
    Route::post('/projects/{project}/documents/zip', [ProjectDocumentController::class, 'downloadZip'])->name('projects.documents.zip');
    Route::get('/meeting-attendance-sessions/{session}/summary', [MeetingAttendanceSessionController::class, 'summary'])->name('attendance.sessions.summary');
    Route::get('/meeting-attendance-sessions/{session}/download/xlsx', [MeetingAttendanceSessionController::class, 'downloadXlsx'])->name('attendance.sessions.download.xlsx');
    Route::get('/meeting-attendance-sessions/{session}/download/pdf', [MeetingAttendanceSessionController::class, 'downloadPdf'])->name('attendance.sessions.download.pdf');
    Route::put('/projects/{project}/bank/profile', [ProjectBankController::class, 'updateProfile'])->name('projects.bank.profile.update');
    Route::put('/projects/{project}/bank/signatories', [ProjectBankController::class, 'updateSignatories'])->name('projects.bank.signatories.update');
    Route::put('/projects/{project}/bank/financing', [ProjectBankController::class, 'updateFinancingRows'])->name('projects.bank.financing.update');
    Route::put('/projects/{project}/bank/activities', [ProjectBankController::class, 'updateActivityRows'])->name('projects.bank.activities.update');
    Route::get('/projects/{project}/bank/download/{templateType}', [ProjectBankController::class, 'downloadExcel'])->name('projects.bank.download.excel');
    Route::get('/projects/{project}/bank/download-zip', [ProjectBankController::class, 'downloadZip'])->name('projects.bank.download.zip');

    Route::get('/drive/settings', fn () => redirect('/panel/drive-oauth-settings'))->name('drive.settings.edit');
    Route::put('/drive/settings', [DriveSettingsController::class, 'update'])->name('drive.settings.update');

    Route::get('/requirements/import', [RequirementController::class, 'importForm'])->name('requirements.import');
    Route::post('/requirements/import', [RequirementController::class, 'import'])->name('requirements.import.store');
    Route::get('/requirements/export', [RequirementController::class, 'export'])->name('requirements.export');
    Route::get('/requirements', fn () => redirect()->route('filament.admin.resources.requirements.index'));
    Route::get('/requirements/create', fn () => redirect()->route('filament.admin.resources.requirements.create'));
    Route::get('/requirements/{requirement}/edit', fn ($requirement) => redirect()->route('filament.admin.resources.requirements.edit', ['record' => $requirement]));

    Route::get('/document-templates', fn () => redirect()->route('filament.admin.resources.document-templates.index'))
        ->name('document_templates.index');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', fn () => redirect()->route('filament.admin.resources.users.index'))->name('users.index');
        Route::get('/roles', fn () => redirect()->route('filament.admin.resources.roles.index'))->name('roles.index');
        Route::get('/permissions', fn () => redirect()->route('filament.admin.resources.permissions.index'))->name('permissions.index');
    });
});
