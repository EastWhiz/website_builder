<?php

use App\Http\Controllers\AngleController;
use App\Http\Controllers\AngleTemplateController;
use App\Http\Controllers\ApiCredentialsController;
use App\Http\Controllers\DeepLControlller;
use App\Http\Controllers\EditedTemplateController;
use App\Http\Controllers\GrokController;
use App\Http\Controllers\OtpServiceController;
use App\Http\Controllers\OtpServiceCredentialController;
use App\Http\Controllers\OtpVerificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Admin\ApiCategoryController;
use App\Http\Controllers\Admin\ApiCategoryFieldController;
use App\Http\Controllers\UserApiInstanceController;
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

// Public OTP API routes (no auth required - forms are public-facing)
Route::post('/api/otp/generate', [OtpVerificationController::class, 'generate'])->name('otp.generate');
Route::post('/api/otp/verify', [OtpVerificationController::class, 'verify'])->name('otp.verify');
Route::post('/api/otp/regenerate', [OtpVerificationController::class, 'regenerate'])->name('otp.regenerate');

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

        Route::post('/templates/delete', [TemplateController::class, 'deleteTemplate'])->name('delete.template');
    Route::post('/templates/rename', [TemplateController::class, 'renameTemplate'])->name('rename.template');

        Route::inertia('/users', 'Users/Users')->name('users');
        Route::get('/users/list', [UsersController::class, 'index'])->name('users.list');
        Route::post('/users', [UsersController::class, 'store'])->name('createUser');
        Route::post('/users/reset-password', [UsersController::class, 'resetPassword'])->name('resetPassword');
        Route::delete('/users', [UsersController::class, 'destroy'])->name('deleteUser');

        Route::post('/angles/assign-to-users', [AngleController::class, 'assignToUsers'])->name('assign.to.users');

        // OTP Services Management (Admin Only)
        Route::inertia('/otp-services', 'OtpServices/OtpServices')->name('otp.services.manage');
        
        // OTP Services Management (Admin Only)
        Route::inertia('/otp-services', 'OtpServices/OtpServices')->name('otp.services.manage');
        Route::get('/otp-services/list', [OtpServiceController::class, 'adminIndex'])->name('otp.services.admin.index');
        Route::post('/otp-services', [OtpServiceController::class, 'store'])->name('otp.services.admin.store');
        Route::put('/otp-services/{id}', [OtpServiceController::class, 'update'])->name('otp.services.admin.update');
        Route::delete('/otp-services/{id}', [OtpServiceController::class, 'destroy'])->name('otp.services.admin.destroy');

        // API Categories Management (Admin Only)
        Route::inertia('/api-categories', 'Admin/ApiCategories')->name('api.categories.manage');
        Route::get('/api-categories/list', [ApiCategoryController::class, 'index'])->name('api.categories.index');
        Route::post('/api-categories', [ApiCategoryController::class, 'store'])->name('api.categories.store');
        Route::get('/api-categories/{id}', [ApiCategoryController::class, 'show'])->name('api.categories.show');
        Route::put('/api-categories/{id}', [ApiCategoryController::class, 'update'])->name('api.categories.update');
        Route::delete('/api-categories/{id}', [ApiCategoryController::class, 'destroy'])->name('api.categories.destroy');
        Route::post('/api-categories/{id}/toggle-active', [ApiCategoryController::class, 'toggleActive'])->name('api.categories.toggleActive');
        
        // API Category Fields Management
        Route::post('/api-categories/{categoryId}/fields', [ApiCategoryFieldController::class, 'store'])->name('api.category.fields.store');
        Route::put('/api-categories/{categoryId}/fields/{fieldId}', [ApiCategoryFieldController::class, 'update'])->name('api.category.fields.update');
        Route::delete('/api-categories/{categoryId}/fields/{fieldId}', [ApiCategoryFieldController::class, 'destroy'])->name('api.category.fields.destroy');
    });

    Route::middleware('role:member')->prefix('member')->group(function () {
        Route::inertia('/dashboard', 'Dashboard')->name('memberDashboard');
    });

    Route::middleware('role:admin,member')->group(function () {

        // User API Instances (authenticated users manage their own instances)
        Route::get('/api/user-api-instances', [UserApiInstanceController::class, 'index'])->name('user.api.instances.index');
        Route::post('/api/user-api-instances', [UserApiInstanceController::class, 'store'])->name('user.api.instances.store');
        Route::get('/api/user-api-instances/category/{categoryId}', [UserApiInstanceController::class, 'getByCategory'])->name('user.api.instances.byCategory');
        Route::get('/api/user-api-instances/{id}', [UserApiInstanceController::class, 'show'])->name('user.api.instances.show');
        Route::put('/api/user-api-instances/{id}', [UserApiInstanceController::class, 'update'])->name('user.api.instances.update');
        Route::delete('/api/user-api-instances/{id}', [UserApiInstanceController::class, 'destroy'])->name('user.api.instances.destroy');
        Route::post('/api/user-api-instances/{id}/toggle-active', [UserApiInstanceController::class, 'toggleActive'])->name('user.api.instances.toggleActive');

        // ANGLES ROUTES
        Route::inertia('/angles/add', 'Angles/AddEditAngle')->name('addAngle');
        Route::get('/angles/edit/{id}', function ($id) {
            $existingAngle = Angle::where('id', $id)->with('contents')->first();
            return Inertia::render('Angles/AddEditAngle', [
                'angle' => $existingAngle,
            ]);
        })->name('editAngle');
        Route::post('/angles/add-edit', [AngleController::class, 'addEditProcess'])->name('angles.addEdit');

        Route::post('/angles/delete', [AngleController::class, 'deleteAngle'])->name('delete.angle');
        Route::post('/angle-templates/delete', [AngleTemplateController::class, 'deleteAngleTemplate'])->name('delete.angleTemplate');
        Route::post('/angle-templates/rename', [AngleTemplateController::class, 'renameAngleTemplate'])->name('rename.angleTemplate');

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

        // API Credentials routes
        Route::post('/api/credentials', [ApiCredentialsController::class, 'store'])->name('api.credentials.store');
        Route::get('/api/credentials', [ApiCredentialsController::class, 'show'])->name('api.credentials.show');
        Route::delete('/api/credentials', [ApiCredentialsController::class, 'destroy'])->name('api.credentials.destroy');
        Route::get('/api/credentials/{provider}', [ApiCredentialsController::class, 'getProviderCredentials'])->name('api.credentials.provider');

        // OTP Services routes (service definitions)
        Route::get('/otp-services', [OtpServiceController::class, 'index'])->name('otp.services.index');
        Route::get('/otp-services/{id}', [OtpServiceController::class, 'show'])->name('otp.services.show');

        // OTP Service Credentials routes (user-specific credentials)
        Route::get('/otp-service-credentials', [OtpServiceCredentialController::class, 'index'])->name('otp.service.credentials.index');
        Route::post('/otp-service-credentials', [OtpServiceCredentialController::class, 'store'])->name('otp.service.credentials.store');
        Route::get('/otp-service-credentials/{id}', [OtpServiceCredentialController::class, 'show'])->name('otp.service.credentials.show');
        Route::get('/otp-service-credentials/service/{serviceId}', [OtpServiceCredentialController::class, 'getByServiceId'])->name('otp.service.credentials.byService');
        Route::put('/otp-service-credentials/{id}', [OtpServiceCredentialController::class, 'update'])->name('otp.service.credentials.update');
        Route::delete('/otp-service-credentials/{id}', [OtpServiceCredentialController::class, 'destroy'])->name('otp.service.credentials.destroy');
        Route::delete('/otp-service-credentials', [OtpServiceCredentialController::class, 'destroyAll'])->name('otp.service.credentials.destroyAll');

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

        Route::post('/angles/contents', function (Request $request) {
            $angle = Angle::with(['contents'])->where('id', $request->angle_id)->first();
            return sendResponse(true, "Angle retrieved successfully", $angle);
        })->name('Angle.previewContent');

        Route::post('/angles/save', [AngleController::class, 'saveEditedAngle'])->name('editedAngle.save');

        Route::post('/angle-templates/contents', function (Request $request) {
            $angleTemplate = AngleTemplate::with(['angle.contents', 'template.contents', 'user'])->where('id', $request->angle_template_id)->first();
            return sendResponse(true, "Angle Template retrieved successfully", $angleTemplate);
        })->name('AngleTemplate.previewContent');

        Route::post('/angle-template/save', [AngleTemplateController::class, 'saveEditedAngleTemplate'])->name('editedAngleTemplate.save');

        Route::get('/download', [AngleTemplateController::class, 'downloadTemplate'])->name('download');
        Route::post('/deepL', [DeepLControlller::class, 'deepL'])->name('deepL');
        Route::post('/grok', [GrokController::class, 'grok'])->name('grok');

        Route::post('/angles/duplicate/{angle}', [AngleController::class, 'duplicateAngle'])->name('duplicate.angle');
        Route::post('/angles/duplicate-multiple', [AngleController::class, 'duplicateMultipleAngles'])->name('duplicate.angles');
        Route::post('/angles/translate', [AngleController::class, 'translateAngle'])->name('translate.angle');
        Route::post('/angle-templates/duplicate/{angleTemplate}', [AngleTemplateController::class, 'duplicateAngleTemplate'])->name('duplicate.angleTemplate');
        Route::post('/angle-templates/translate', [AngleTemplateController::class, 'translateAngleTemplate'])->name('translate.angleTemplate');
    });
});

require __DIR__ . '/auth.php';
