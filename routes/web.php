<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectManageController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\DriveAuthController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\RequirementCrudController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [ProjectController::class, 'index'])->name('dashboard');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');

    Route::get('/projects/{project}/checklist', [ChecklistController::class, 'show'])->name('projects.checklist');
    Route::put('/projects/{project}/checklist', [ChecklistController::class, 'update'])->name('projects.checklist.update');

    Route::get('/projects/{project}/manage', [ProjectManageController::class, 'show'])->name('projects.manage');
    Route::post('/projects/{project}/manage/{requirement}', [ProjectManageController::class, 'upload'])->name('projects.manage.upload');

    Route::get('/projects/{project}/documents', [ProjectDocumentController::class, 'index'])->name('projects.documents');
    Route::get('/projects/{project}/documents/{documentTemplate}', [ProjectDocumentController::class, 'download'])->name('projects.documents.download');
    Route::post('/projects/{project}/documents/zip', [ProjectDocumentController::class, 'downloadZip'])->name('projects.documents.zip');

    Route::get('/drive/auth', [DriveAuthController::class, 'redirect'])->name('drive.auth');
    Route::get('/drive/callback', [DriveAuthController::class, 'callback'])->name('drive.callback');

    Route::get('/requirements/import', [RequirementController::class, 'importForm'])->name('requirements.import');
    Route::post('/requirements/import', [RequirementController::class, 'import'])->name('requirements.import.store');
    Route::get('/requirements/export', [RequirementController::class, 'export'])->name('requirements.export');
    Route::get('/requirements', [RequirementCrudController::class, 'index'])->name('requirements.crud.index');
    Route::get('/requirements/create', [RequirementCrudController::class, 'create'])->name('requirements.crud.create');
    Route::post('/requirements', [RequirementCrudController::class, 'store'])->name('requirements.crud.store');
    Route::get('/requirements/{requirement}/edit', [RequirementCrudController::class, 'edit'])->name('requirements.crud.edit');
    Route::put('/requirements/{requirement}', [RequirementCrudController::class, 'update'])->name('requirements.crud.update');
    Route::patch('/requirements/{requirement}/toggle-check', [RequirementCrudController::class, 'toggleCheck'])->name('requirements.crud.toggle_check');
    Route::patch('/requirements/{requirement}/toggle-visible', [RequirementCrudController::class, 'toggleVisible'])->name('requirements.crud.toggle_visible');
    Route::delete('/requirements/{requirement}', [RequirementCrudController::class, 'destroy'])->name('requirements.crud.destroy');

    Route::get('/document-templates', [DocumentTemplateController::class, 'index'])->name('document_templates.index');
    Route::post('/document-templates', [DocumentTemplateController::class, 'store'])->name('document_templates.store');
    Route::delete('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])->name('document_templates.destroy');
});
