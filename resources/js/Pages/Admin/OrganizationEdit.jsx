import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function OrganizationEdit() {
    const { id } = usePage().props;
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [form, setForm] = useState({
        name: '',
        status: 'active',
        owner_name: '',
        owner_email: '',
        owner_phone: '',
        owner_password: '',
    });

    useEffect(() => {
        const load = async () => {
            try {
                const res = await fetch(route('admin.organizations.show', id), { headers: { Accept: 'application/json' } });
                const result = await res.json();
                if (!res.ok || !result.success || !result.data) {
                    Swal.fire({ title: 'Error', text: result.message || 'Failed to load organization.', icon: 'error' });
                    return;
                }
                const org = result.data;
                setForm({
                    name: org?.name || '',
                    status: org?.status || 'active',
                    owner_name: org?.owner?.name || '',
                    owner_email: org?.owner?.email || '',
                    owner_phone: org?.owner?.phone || '',
                    owner_password: '',
                });
            } catch {
                Swal.fire({ title: 'Error', text: 'Failed to load organization.', icon: 'error' });
            } finally {
                setLoading(false);
            }
        };
        load();
    }, [id]);

    const handleChange = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            setSubmitting(true);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res = await fetch(route('admin.organizations.update', id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(form),
            });
            const result = await res.json();
            if (!res.ok || !result.success) {
                Swal.fire({ title: 'Failed', text: result.message || 'Could not update organization.', icon: 'error' });
                return;
            }
            Swal.fire({
                title: 'Updated',
                text: 'Organization updated successfully.',
                icon: 'success',
                timer: 1200,
                showConfirmButton: false,
            });
        } catch {
            Swal.fire({ title: 'Error', text: 'Could not update organization.', icon: 'error' });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Organization</h2>}>
            <Head title="Edit Organization" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="bg-white shadow sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Organization Provisioning</h3>
                                <Link href={route('admin.organizations')} className="text-sm text-indigo-600 hover:text-indigo-800">
                                    Back to Organizations
                                </Link>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Organization Name</label>
                                    <input
                                        type="text"
                                        required
                                        disabled={loading}
                                        value={form.name}
                                        onChange={(e) => handleChange('name', e.target.value)}
                                        className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select
                                        disabled={loading}
                                        value={form.status}
                                        onChange={(e) => handleChange('status', e.target.value)}
                                        className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="active">Active</option>
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
                                            disabled={loading}
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
                                            disabled={loading}
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
                                            disabled={loading}
                                            value={form.owner_phone}
                                            onChange={(e) => handleChange('owner_phone', e.target.value)}
                                            className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Owner Password</label>
                                        <input
                                            type="password"
                                            minLength={8}
                                            disabled={loading}
                                            value={form.owner_password}
                                            onChange={(e) => handleChange('owner_password', e.target.value)}
                                            placeholder="Leave blank to keep current password"
                                            className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={submitting || loading}
                                    className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {submitting ? 'Saving...' : 'Update Organization'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
