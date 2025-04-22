<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
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
        Route::inertia('/templates', 'Templates/Templates')->name('templates');
        Route::inertia('/templates/add', 'Templates/AddEditTemplate')->name('addTemplate');
        Route::get('/templates/edit/{id}', function ($id) {
            $existingTemplate = Template::where('id', $id)->with('contents')->first();
            return Inertia::render('Templates/AddEditTemplate', [
                'template' => $existingTemplate,
            ]);
        })->name('editTemplate');

        Route::post('/templates/preview/contents', function (Request $request) {
            $thisTemplate = Template::find($request->template_id);
            return response()->json([
                'message' => 'Data retrieved successfully',
                'data' => [
                    'template' => $thisTemplate,
                    'css' => TemplateContent::where('template_uuid', $thisTemplate->uuid)->where('type', 'css')->first(),
                    'js' => TemplateContent::where('template_uuid', $thisTemplate->uuid)->where('type', 'js')->first(),
                    'body' => TemplateContent::where('template_uuid', $thisTemplate->uuid)->where('type', 'html')->where('name', "BD1")->first(),
                    'body2' => TemplateContent::where('template_uuid', $thisTemplate->uuid)->where('type', 'html')->where('name', "BD2")->first(),
                    'body3' => TemplateContent::where('template_uuid', $thisTemplate->uuid)->where('type', 'html')->where('name', "BD3")->first(),
                ],
                'status' => 200
            ]);
        })->name('templates.previewContent');
        Route::get('/templates/list', [TemplateController::class, 'index'])->name('templates.list');
        Route::post('/templates/add-edit', [TemplateController::class, 'addEditProcess'])->name('templates.addEdit');

        Route::inertia('/frontend', 'FrontEnd')->name('frontend');
    });

    Route::middleware('role:member')->prefix('member')->group(function () {
        Route::inertia('/dashboard', 'Dashboard')->name('memberDashboard');
    });

    Route::middleware('role:admin,member')->group(function () {
        Route::get('/templates/preview/{id}', function ($id) {
            return Inertia::render('Templates/PreviewTemplate', compact('id'));
        })->name('previewTemplate');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

require __DIR__ . '/auth.php';
