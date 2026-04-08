import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Swal from 'sweetalert2';

export default function OrganizationProvision() {
    const [form, setForm] = useState({
        org_name: '',
        org_status: 'active',
        owner_name: '',
        owner_email: '',
        owner_phone: '',
        owner_password: '',
    });
    const [submitting, setSubmitting] = useState(false);

    const handleChange = (key, value) => {
        setForm((prev) => ({ ...prev, [key]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            setSubmitting(true);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const res = await fetch(route('admin.organizations.provision'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify(form),
            });
            const result = await res.json();
            if (!res.ok || !result.success) {
                Swal.fire({
                    title: 'Failed',
                    text: result.message || 'Could not create organization.',
                    icon: 'error',
                });
                return;
            }

            Swal.fire({
                title: 'Created',
                text: 'Organization and owner created successfully.',
                icon: 'success',
                timer: 1200,
                showConfirmButton: false,
            });

            setForm({
                org_name: '',
                org_status: 'active',
                owner_name: '',
                owner_email: '',
                owner_phone: '',
                owner_password: '',
            });
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: 'Could not create organization.',
                icon: 'error',
            });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Organization</h2>}
        >
            <Head title="Create Organization" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="bg-white shadow sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Organization Provisioning</h3>
                                <Link
                                    href={route('admin.organizations')}
                                    className="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    Back to Organizations
                                </Link>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Organization Name</label>
                                    <input
                                        type="text"
                                        required
                                        value={form.org_name}
                                        onChange={(e) => handleChange('org_name', e.target.value)}
                                        className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select
                                        value={form.org_status}
                                        onChange={(e) => handleChange('org_status', e.target.value)}
                                        className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="active">Active</option>
                                        <option value="on_hold">On hold</option>
                                        <option value="deactivated">Deactivated</option>
                                    </select>
                                </div>
                            </div>

                            <div className="border-t pt-4">
                                <h4 className="text-md font-medium text-gray-900 mb-3">Owner Account</h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Owner Name</label>
                                        <input
                                            type="text"
                                            required
                                            value={form.owner_name}
                                            onChange={(e) => handleChange('owner_name', e.target.value)}
                                            className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Owner Email</label>
                                        <input
                                            type="email"
                                            required
                                            value={form.owner_email}
                                            onChange={(e) => handleChange('owner_email', e.target.value)}
                                            className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Owner Phone</label>
                                        <input
                                            type="text"
                                            required
                                            value={form.owner_phone}
                                            onChange={(e) => handleChange('owner_phone', e.target.value)}
                                            className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Owner Password</label>
                                        <input
                                            type="password"
                                            required
                                            minLength={8}
                                            value={form.owner_password}
                                            onChange={(e) => handleChange('owner_password', e.target.value)}
                                            className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {submitting ? 'Creating...' : 'Create Organization'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
