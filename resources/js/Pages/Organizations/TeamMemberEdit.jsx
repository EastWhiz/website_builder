import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function TeamMemberEdit() {
    const { membershipId, auth } = usePage().props;
    const permissions = auth?.permissions || {};
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [roles, setRoles] = useState([]);
    const [member, setMember] = useState(null);
    const [organization, setOrganization] = useState(null);
    const [form, setForm] = useState({
        membership_id: membershipId,
        name: '',
        email: '',
        phone: '',
        role_id: '',
        status: 'active',
    });

    useEffect(() => {
        const load = async () => {
            try {
                const [rolesRes, memberRes] = await Promise.all([
                    fetch(route('organization.team.roles.index'), { headers: { Accept: 'application/json' } }),
                    fetch(route('organization.team.members.show', membershipId), { headers: { Accept: 'application/json' } }),
                ]);

                const rolesJson = await rolesRes.json();
                if (rolesJson?.success && Array.isArray(rolesJson?.data)) {
                    setRoles(rolesJson.data);
                }

                const memberJson = await memberRes.json();
                if (!memberRes.ok || !memberJson?.success || !memberJson?.data?.member) {
                    Swal.fire({ title: 'Error', text: memberJson?.message || 'Failed to load member details.', icon: 'error' });
                    return;
                }

                const m = memberJson.data.member;
                setOrganization(memberJson.data.organization || null);
                setMember(m);
                setForm({
                    membership_id: m.membership_id,
                    name: m.name || '',
                    email: m.email || '',
                    phone: m.phone || '',
                    role_id: String(m.organization_role_id || ''),
                    status: String(m.membership_status).toLowerCase() === 'active' ? 'active' : 'deactivated',
                });
            } catch {
                Swal.fire({ title: 'Error', text: 'Failed to load member details.', icon: 'error' });
            } finally {
                setLoading(false);
            }
        };

        load();
    }, [membershipId]);

    const handleChange = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.name || !form.email || !form.phone || !form.role_id) {
            Swal.fire({ title: 'Missing fields', text: 'Please fill all required fields.', icon: 'warning' });
            return;
        }

        try {
            setSubmitting(true);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(route('organization.team.members.update'), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(form),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                Swal.fire({ title: 'Update failed', text: result.message || 'Could not update member.', icon: 'error' });
                return;
            }

            Swal.fire({
                title: 'Updated',
                text: result.message || 'Member updated successfully.',
                icon: 'success',
                timer: 1200,
                showConfirmButton: false,
            });
        } catch {
            Swal.fire({ title: 'Update failed', text: 'Could not update member.', icon: 'error' });
        } finally {
            setSubmitting(false);
        }
    };

    const handleManualActivate = async () => {
        if (!member || String(member.membership_status).toLowerCase() !== 'invited') return;
        const confirm = await Swal.fire({
            title: 'Activate invited member?',
            text: `This will manually activate ${member.name || 'this member'}.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Activate',
        });
        if (!confirm.isConfirmed) return;

        try {
            setSubmitting(true);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(route('organization.team.members.activate'), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ membership_id: member.membership_id }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                Swal.fire({ title: 'Activation failed', text: result.message || 'Could not activate member.', icon: 'error' });
                return;
            }
            setMember((prev) => (prev ? { ...prev, membership_status: 'active' } : prev));
            Swal.fire({
                title: 'Activated',
                text: result.message || 'Member activated successfully.',
                icon: 'success',
                timer: 1200,
                showConfirmButton: false,
            });
        } catch {
            Swal.fire({ title: 'Activation failed', text: 'Could not activate member.', icon: 'error' });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Team Member</h2>}
        >
            <Head title="Edit Team Member" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="bg-white shadow sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Member Edit</h3>
                                <Link
                                    href={route('organization.team.settings')}
                                    className="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    Back to Team Settings
                                </Link>
                            </div>

                            <div className="text-sm text-gray-600">
                                Organization: <span className="font-medium">{organization?.name || '-'}</span>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
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
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input
                                        type="email"
                                        required
                                        disabled={loading}
                                        value={form.email}
                                        onChange={(e) => handleChange('email', e.target.value)}
                                        className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                    <input
                                        type="text"
                                        required
                                        disabled={loading}
                                        value={form.phone}
                                        onChange={(e) => handleChange('phone', e.target.value)}
                                        className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <select
                                        required
                                        disabled={loading}
                                        value={form.role_id}
                                        onChange={(e) => handleChange('role_id', e.target.value)}
                                        className="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select role</option>
                                        {roles.map((r) => (
                                            <option key={r.id} value={r.id}>
                                                {r.name}
                                            </option>
                                        ))}
                                    </select>
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
                                        <option value="deactivated">Deactivate</option>
                                    </select>
                                </div>
                            </div>

                            <div className="pt-2 flex items-center gap-2">
                                <button
                                    type="submit"
                                    disabled={submitting || loading}
                                    className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {submitting ? 'Saving...' : 'Update Member'}
                                </button>

                                {permissions.member_activate_complete && String(member?.membership_status || '').toLowerCase() === 'invited' && (
                                    <button
                                        type="button"
                                        onClick={handleManualActivate}
                                        disabled={submitting || loading}
                                        className="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 disabled:opacity-50"
                                    >
                                        Activate Member
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
