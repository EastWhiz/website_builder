<?php

use App\Http\Controllers\AngleController;
use App\Http\Controllers\AngleTemplateController;
use App\Http\Controllers\DeepLControlller;
use App\Http\Controllers\EditedTemplateController;
use App\Http\Controllers\GrokController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UsersController;
use App\Models\Angle;
use App\Models\AngleTemplate;
use App\Models\Template;
use App\Models\TemplateContent;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::inertia('/dashboard', 'Dashboard')->name('dashboard');

        // TEMPLATES ROUTES
        Route::inertia('/templates/add', 'Templates/AddEditTemplate')->name('addTemplate');
        Route::get('/templates/edit/{id}', function ($id) {
            $existingTemplate = Template::where('id', $id)->with('contents')->first();
            return Inertia::render('Templates/AddEditTemplate', [
                'template' => $existingTemplate,
            ]);
        })->name('editTemplate');
        Route::post('/templates/add-edit', [TemplateController::class, 'addEditProcess'])->name('templates.addEdit');

        // ANGLES ROUTES
        Route::inertia('/angles/add', 'Angles/AddEditAngle')->name('addAngle');
        Route::get('/angles/edit/{id}', function ($id) {
            $existingAngle = Angle::where('id', $id)->with('contents')->first();
            return Inertia::render('Angles/AddEditAngle', [
                'angle' => $existingAngle,
            ]);
        })->name('editAngle');
        Route::post('/angles/add-edit', [AngleController::class, 'addEditProcess'])->name('angles.addEdit');

        Route::inertia('/users', 'Users/Users')->name('users');
        Route::get('/users/list', [UsersController::class, 'index'])->name('users.list');

        Route::post('/templates/delete', [TemplateController::class, 'deleteTemplate'])->name('delete.template');
        Route::post('/angles/delete', [AngleController::class, 'deleteAngle'])->name('delete.angle');
        Route::post('/angle-templates/delete', [AngleTemplateController::class, 'deleteAngleTemplate'])->name('delete.angleTemplate');
    });

    Route::middleware('role:member')->prefix('member')->group(function () {
        Route::inertia('/dashboard', 'Dashboard')->name('memberDashboard');
    });

    Route::middleware('role:admin,member')->group(function () {

        // TEMPLATES ROUTES
        Route::inertia('/templates', 'Templates/Templates')->name('templates');
        Route::get('/templates/list', [TemplateController::class, 'index'])->name('templates.list');

        // ANGLES ROUTES
        Route::inertia('/angles', 'Angles/Angles')->name('angles');
        Route::get('/angles/list', [AngleController::class, 'index'])->name('angles.list');

        Route::post('/angles-applying', [AngleTemplateController::class, 'anglesApplying'])->name('angles.applying');



        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('/users/{id}/themes', function ($id) {
            return Inertia::render('Users/UserThemes', [
                'id' => $id,
            ]);
        })->name('userThemes');
        Route::get('/users/{id}/themes/list', [UsersController::class, 'userThemesList'])->name('userThemes.list');

        Route::get('/angle-templates/preview/{id}', function ($id) {
            return Inertia::render('AngleTemplates/PreviewAngleTemplate', compact('id'));
        })->name('previewAngleTemplate');

        Route::get('/angles/preview/{id}', function ($id) {
            return Inertia::render('Angles/PreviewAngle', compact('id'));
        })->name('previewAngle');

        Route::get('/angles/contents', function (Request $request) {
            $angle = Angle::with(['contents'])->where('id', $request->angle_id)->first();
            return sendResponse(true, "Angle retrieved successfully", $angle);
        })->name('Angle.previewContent');

        Route::post('/angle-templates/contents', function (Request $request) {
            $angleTemplate = AngleTemplate::with(['angle.contents', 'template.contents', 'user'])->where('id', $request->angle_template_id)->first();
            return sendResponse(true, "Angle Template retrieved successfully", $angleTemplate);
        })->name('AngleTemplate.previewContent');

        Route::post('/angle-template/save', [AngleTemplateController::class, 'saveEditedAngleTemplate'])->name('editedAngleTemplate.save');

        Route::get('/download', [AngleTemplateController::class, 'downloadTemplate'])->name('download');
        Route::post('/deepL', [DeepLControlller::class, 'deepL'])->name('deepL');
        Route::post('/grok', [GrokController::class, 'grok'])->name('grok');
    });
});

require __DIR__ . '/auth.php';
