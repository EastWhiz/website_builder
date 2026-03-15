import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ThankYouPageForm from './ThankYouPageForm';

export default function Edit({ thankYouPage }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Thank You Page
                </h2>
            }
        >
            <Head title={`Edit ${thankYouPage?.name ?? 'Thank You Page'}`} />
            <div className="py-6">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <p className="mb-6 text-sm text-gray-600">
                                Update the thank you page. Leave logo or profile image empty to keep the current file.
                            </p>
                            <ThankYouPageForm
                                initialData={thankYouPage}
                                isEdit={true}
                                pageId={thankYouPage?.id}
                                backUrl={route('thank-you-pages.index')}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
