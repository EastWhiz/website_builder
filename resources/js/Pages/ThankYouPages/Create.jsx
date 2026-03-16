import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
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
            <div className="py-8">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <nav className="mb-6 flex items-center gap-2 text-sm text-gray-500">
                        <Link href={route('thank-you-pages.index')} className="hover:text-gray-700">
                            Thank You Pages
                        </Link>
                        <span aria-hidden>/</span>
                        <span className="text-gray-900 font-medium">Create</span>
                    </nav>
                    <div className="rounded-xl border border-gray-200 bg-gray-50/50 p-6 sm:p-8">
                        <p className="mb-8 text-sm text-gray-600">
                            Configure the thank you page shown after form submission. Add a logo, message, and optional hero image. You can change these later.
                        </p>
                        <ThankYouPageForm
                            isEdit={false}
                            backUrl={route('thank-you-pages.index')}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
