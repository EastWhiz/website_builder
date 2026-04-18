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
use App\Http\Controllers\OrganizationMailSettingsController;
use App\Http\Controllers\OrganizationTeamController;
use App\Http\Controllers\OrganizationContentUserAssignmentController;
use App\Http\Controllers\OrganizationAuditLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ThankYouPageController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Admin\ApiCategoryController;
use App\Http\Controllers\Admin\ApiCategoryFieldController;
use App\Http\Controllers\Admin\OrganizationContentAssignmentController;
use App\Http\Controllers\Admin\OrganizationContentCloneController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\CrmSettingsController;
use App\Http\Controllers\UserApiInstanceController;
use App\Http\Controllers\Admin\OrganizationController;
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

// Direct link to preview the thank you page (no auth required)
Route::get('/thank-you-preview', function () {
    return redirect(url('api_files/thank_you.php'));
})->name('thankYou.preview');

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

        // API Platforms Management (Admin Only)
        Route::inertia('/api-categories', 'Admin/ApiCategories')->name('api.categories.manage');
        Route::get('/api-categories/list', [ApiCategoryController::class, 'index'])->name('api.categories.index');
        Route::post('/api-categories', [ApiCategoryController::class, 'store'])->name('api.categories.store');
        Route::match(['get','post'], '/api-categories/sync-crm', [ApiCategoryController::class, 'syncAllToCrm'])->name('api.categories.syncCrm');
        Route::get('/api-categories/{id}', [ApiCategoryController::class, 'show'])->name('api.categories.show');
        Route::put('/api-categories/{id}', [ApiCategoryController::class, 'update'])->name('api.categories.update');
        Route::delete('/api-categories/{id}', [ApiCategoryController::class, 'destroy'])->name('api.categories.destroy');
        Route::post('/api-categories/{id}/toggle-active', [ApiCategoryController::class, 'toggleActive'])->name('api.categories.toggleActive');
        
        // API Category Fields Management
        Route::post('/api-categories/{categoryId}/fields', [ApiCategoryFieldController::class, 'store'])->name('api.category.fields.store');
        Route::put('/api-categories/{categoryId}/fields/{fieldId}', [ApiCategoryFieldController::class, 'update'])->name('api.category.fields.update');
        Route::delete('/api-categories/{categoryId}/fields/{fieldId}', [ApiCategoryFieldController::class, 'destroy'])->name('api.category.fields.destroy');

        // Organizations (Super Admin only)
        Route::inertia('/organizations', 'Admin/Organizations')->name('admin.organizations');
        Route::inertia('/organizations/create', 'Admin/OrganizationProvision')->name('admin.organizations.create');
        Route::get('/organizations/{id}/view', function ($id) {
            return Inertia::render('Admin/OrganizationView', compact('id'));
        })->name('admin.organizations.viewPage');
        Route::get('/organizations/{id}/edit', function ($id) {
            return Inertia::render('Admin/OrganizationEdit', compact('id'));
        })->name('admin.organizations.editPage');
        Route::get('/organizations/list', [OrganizationController::class, 'index'])->name('admin.organizations.list');
        Route::get('/organizations/{id}', [OrganizationController::class, 'show'])->name('admin.organizations.show');
        Route::post('/organizations', [OrganizationController::class, 'store'])->name('admin.organizations.store');
        Route::post('/organizations/provision', [OrganizationController::class, 'provision'])->name('admin.organizations.provision');
        Route::post('/organizations/validate-member-transfer', [OrganizationController::class, 'validateMemberTransfer'])->name('admin.organizations.validateMemberTransfer');
        Route::post('/organizations/transfer-member', [OrganizationController::class, 'transferMember'])->name('admin.organizations.transferMember');
        Route::post('/organizations/assign-content', [OrganizationContentAssignmentController::class, 'assignToOrganization'])->name('admin.organizations.assignContent');
        Route::post('/organizations/clone-content', [OrganizationContentCloneController::class, 'cloneCrossOrg'])->name('admin.organizations.cloneContent');
        Route::put('/organizations/{id}', [OrganizationController::class, 'update'])->name('admin.organizations.update');
        Route::patch('/organizations/{id}/status', [OrganizationController::class, 'updateStatus'])->name('admin.organizations.updateStatus');

        // Roles (Super Admin only) - Phase 3B
        Route::inertia('/roles-management', 'Admin/Roles')->name('admin.roles.manage');
        Route::get('/roles', [RoleController::class, 'index'])->name('admin.roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('admin.roles.store');
        Route::put('/roles/{id}', [RoleController::class, 'update'])->name('admin.roles.update');
        Route::patch('/roles/{id}/archive', [RoleController::class, 'archive'])->name('admin.roles.archive');
    });

    Route::middleware('role:member')->prefix('member')->group(function () {
        Route::inertia('/dashboard', 'Dashboard')->name('memberDashboard');
    });

    Route::middleware('role:admin,member')->group(function () {
        // Team / company settings shell (Phase 3.1)
        Route::inertia('/team-settings', 'Organizations/TeamSettings')->name('organization.team.settings');
        Route::get('/team-settings/members/{membershipId}/edit', function ($membershipId) {
            return Inertia::render('Organizations/TeamMemberEdit', compact('membershipId'));
        })->name('organization.team.members.editPage');
        Route::get('/api/team-roles', [OrganizationTeamController::class, 'rolesIndex'])->name('organization.team.roles.index');
        Route::get('/api/team-members', [OrganizationTeamController::class, 'membersIndex'])->name('organization.team.members.index');
        Route::get('/api/team-members/{membershipId}', [OrganizationTeamController::class, 'showMember'])->name('organization.team.members.show');
        Route::post('/api/team-members/invite', [OrganizationTeamController::class, 'invite'])->name('organization.team.members.invite');
        Route::patch('/api/team-members/role', [OrganizationTeamController::class, 'updateRole'])->name('organization.team.members.updateRole');
        Route::patch('/api/team-members/update', [OrganizationTeamController::class, 'updateMember'])->name('organization.team.members.update');
        Route::patch('/api/team-members/activate', [OrganizationTeamController::class, 'activateMember'])->name('organization.team.members.activate');
        Route::patch('/api/team-members/archive', [OrganizationTeamController::class, 'softDeleteMember'])->name('organization.team.members.archive');
        Route::patch('/api/team-members/restore', [OrganizationTeamController::class, 'restoreMember'])->name('organization.team.members.restore');
        Route::post('/api/organization/assign-content-to-user', [OrganizationContentUserAssignmentController::class, 'assignToUser'])
            ->name('organization.content.assign_to_user');
        Route::inertia('/audit-logs', 'Organizations/AuditLogs')->name('organization.audit.logs.page');
        Route::get('/api/audit-logs', [OrganizationAuditLogController::class, 'index'])->name('organization.audit.logs.index');

        // User API Instances (authenticated users manage their own instances)
        Route::get('/api/api-categories', [UserApiInstanceController::class, 'categories'])->name('user.api.categories.index');
        Route::get('/api/user-api-instances', [UserApiInstanceController::class, 'index'])->name('user.api.instances.index');
        Route::post('/api/user-api-instances', [UserApiInstanceController::class, 'store'])->name('user.api.instances.store');
        Route::get('/api/user-api-instances/category/{categoryId}', [UserApiInstanceController::class, 'getByCategory'])->name('user.api.instances.byCategory');
        Route::get('/api/user-api-instances/{id}', [UserApiInstanceController::class, 'show'])->name('user.api.instances.show');
        Route::put('/api/user-api-instances/{id}', [UserApiInstanceController::class, 'update'])->name('user.api.instances.update');
        Route::delete('/api/user-api-instances/{id}', [UserApiInstanceController::class, 'destroy'])->name('user.api.instances.destroy');
        Route::post('/api/user-api-instances/{id}/toggle-active', [UserApiInstanceController::class, 'toggleActive'])->name('user.api.instances.toggleActive');

        // CRM Settings (admin only - admin@gmail.com; controller enforces)
        Route::get('/api/crm-settings', [CrmSettingsController::class, 'index'])->name('crm.settings.index');
        Route::put('/api/crm-settings', [CrmSettingsController::class, 'update'])->name('crm.settings.update');

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
        Route::patch('/profile/deepl-api-key', [ProfileController::class, 'updateDeeplApiKey'])->name('profile.update-deepl-api-key');
        Route::put('/profile/organization-mail-settings', [OrganizationMailSettingsController::class, 'update'])->name('profile.organization-mail-settings.update');
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

        // Thank You Pages (custom thank-you pages for exports)
        Route::get('/thank-you-pages', [ThankYouPageController::class, 'index'])->name('thank-you-pages.index');
        Route::get('/thank-you-pages/create', [ThankYouPageController::class, 'create'])->name('thank-you-pages.create');
        Route::post('/thank-you-pages', [ThankYouPageController::class, 'store'])->name('thank-you-pages.store');
        Route::get('/thank-you-pages/{id}/edit', [ThankYouPageController::class, 'edit'])->name('thank-you-pages.edit');
        Route::put('/thank-you-pages/{id}', [ThankYouPageController::class, 'update'])->name('thank-you-pages.update');
        Route::delete('/thank-you-pages/{id}', [ThankYouPageController::class, 'destroy'])->name('thank-you-pages.destroy');
        Route::get('/thank-you-pages/{id}/preview', [ThankYouPageController::class, 'preview'])->name('thank-you-pages.preview');

        // API: list current user's thank you pages (for export dropdown)
        Route::get('/api/thank-you-pages', [ThankYouPageController::class, 'apiIndex'])->name('thank-you-pages.api-index');

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
