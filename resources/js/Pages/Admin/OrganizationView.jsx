import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function OrganizationView() {
    const { id } = usePage().props;
    const [loading, setLoading] = useState(true);
    const [org, setOrg] = useState(null);

    useEffect(() => {
        const load = async () => {
            try {
                const res = await fetch(route('admin.organizations.show', id), { headers: { Accept: 'application/json' } });
                const result = await res.json();
                if (!res.ok || !result.success || !result.data) {
                    Swal.fire({ title: 'Error', text: result.message || 'Failed to load organization.', icon: 'error' });
                    return;
                }
                setOrg(result.data);
            } catch {
                Swal.fire({ title: 'Error', text: 'Failed to load organization.', icon: 'error' });
            } finally {
                setLoading(false);
            }
        };
        load();
    }, [id]);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">View Organization</h2>}>
            <Head title="View Organization" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="bg-white shadow sm:rounded-lg">
                        <div className="p-6 space-y-6">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Organization Provisioning</h3>
                                <Link href={route('admin.organizations')} className="text-sm text-indigo-600 hover:text-indigo-800">
                                    Back to Organizations
                                </Link>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Organization Name</label>
                                    <input type="text" readOnly value={loading ? '' : (org?.name || '')} className="w-full rounded-md border-gray-300 bg-gray-50" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <input type="text" readOnly value={loading ? '' : (org?.status || '')} className="w-full rounded-md border-gray-300 bg-gray-50" />
                                </div>
                            </div>

                            <div className="border-t pt-4">
                                <h4 className="text-md font-medium text-gray-900 mb-3">Owner Account</h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Owner Name</label>
                                        <input type="text" readOnly value={loading ? '' : (org?.owner?.name || '')} className="w-full rounded-md border-gray-300 bg-gray-50" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Owner Email</label>
                                        <input type="text" readOnly value={loading ? '' : (org?.owner?.email || '')} className="w-full rounded-md border-gray-300 bg-gray-50" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Owner Phone</label>
                                        <input type="text" readOnly value={loading ? '' : (org?.owner?.phone || '')} className="w-full rounded-md border-gray-300 bg-gray-50" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Owner Password</label>
                                        <input type="password" readOnly value="********" className="w-full rounded-md border-gray-300 bg-gray-50" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
