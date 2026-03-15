import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ThankYouPageForm from './ThankYouPageForm';

export default function Create() {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Create Thank You Page
                </h2>
            }
        >
            <Head title="Create Thank You Page" />
            <div className="py-6">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <p className="mb-6 text-sm text-gray-600">
                                Configure the thank you page shown after form submission. Logo is required. Profile image is optional.
                            </p>
                            <ThankYouPageForm
                                isEdit={false}
                                backUrl={route('thank-you-pages.index')}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
