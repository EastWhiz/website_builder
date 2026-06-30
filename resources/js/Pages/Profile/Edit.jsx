import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import ApiFormFields from './Partials/ApiFormFields';
import PixelFormFields from './Partials/PixelFormFields';
import OtpServicesFormFields from './Partials/OtpServicesFormFields';
import CrmSettingsForm from './Partials/CrmSettingsForm';
import DeepLApiKeyForm from './Partials/DeepLApiKeyForm';
import OrganizationMailSettingsForm from './Partials/OrganizationMailSettingsForm';
import TurnstileSettingsForm from './Partials/TurnstileSettingsForm';

export default function Edit({
    mustVerifyEmail,
    status,
    crmSettings,
    deepl_api_key,
    canManageOrganizationMail,
    organizationMailSettings,
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Profile
                </h2>
            }
        >
            <Head title="Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <h3 className="text-lg font-semibold text-gray-900">Integrations</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage integration instances by category.
                        </p>

                        <div className="mt-6 space-y-8">
                            <section>
                                <h4 className="text-sm font-semibold uppercase tracking-wide text-gray-600">
                                    Network APIs
                                </h4>
                                <div className="mt-3 rounded-lg border border-gray-200 p-4">
                                    <ApiFormFields
                                        mustVerifyEmail={mustVerifyEmail}
                                        status={status}
                                        className=""
                                    />
                                </div>
                            </section>

                            <section>
                                <h4 className="text-sm font-semibold uppercase tracking-wide text-gray-600">
                                    Pixels
                                </h4>
                                <div className="mt-3 rounded-lg border border-gray-200 p-4">
                                    <PixelFormFields className="" />
                                </div>
                            </section>

                            <section>
                                <h4 className="text-sm font-semibold uppercase tracking-wide text-gray-600">
                                    Services
                                </h4>
                                <div className="mt-3 rounded-lg border border-gray-200 p-4">
                                    <OtpServicesFormFields className="" />
                                </div>
                            </section>
                        </div>
                    </div>

                    {crmSettings && (
                        <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                            <CrmSettingsForm crmSettings={crmSettings} className="max-w-xl" />
                        </div>
                    )}

                    {canManageOrganizationMail && organizationMailSettings && (
                        <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                            <OrganizationMailSettingsForm
                                organizationMailSettings={organizationMailSettings}
                                className="max-w-xl"
                            />
                        </div>
                    )}

                    {canManageOrganizationMail && (
                        <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                            <TurnstileSettingsForm className="max-w-xl" />
                        </div>
                    )}

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <DeepLApiKeyForm deepl_api_key={deepl_api_key} className="max-w-xl" />
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
