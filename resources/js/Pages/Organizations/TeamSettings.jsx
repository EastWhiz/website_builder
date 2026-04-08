import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function TeamSettings() {
    const [loading, setLoading] = useState(true);
    const [members, setMembers] = useState([]);
    const [organization, setOrganization] = useState(null);
    const [archived, setArchived] = useState(false);
    const [q, setQ] = useState('');
    const [inviteForm, setInviteForm] = useState({
        name: '',
        email: '',
        phone: '',
        role_id: '',
    });
    const [roles, setRoles] = useState([]);
    const [inviting, setInviting] = useState(false);
    const [roleDrafts, setRoleDrafts] = useState({});
    const [updatingRoleFor, setUpdatingRoleFor] = useState(null);
    const [memberActionLoadingId, setMemberActionLoadingId] = useState(null);

    const loadMembers = async () => {
        try {
            setLoading(true);
            const url = new URL(route('organization.team.members.index'));
            url.searchParams.set('page_count', '20');
            url.searchParams.set('archived', archived ? 'true' : 'false');
            if (q.trim() !== '') {
                url.searchParams.set('q', q.trim());
            }

            const res = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
            });
            const result = await res.json();
            if (result.success && result.data) {
                setOrganization(result.data.organization || null);
                setMembers(result.data.members?.data || []);
            } else {
                setOrganization(null);
                setMembers([]);
            }
        } catch {
            setOrganization(null);
            setMembers([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadMembers();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [archived]);

    useEffect(() => {
        fetch(route('organization.team.roles.index'), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((res) => {
                if (res?.success && Array.isArray(res?.data)) {
                    setRoles(res.data);
                }
            })
            .catch(() => {});
    }, []);

    const handleInvite = async (e) => {
        e.preventDefault();
        if (!inviteForm.name || !inviteForm.email || !inviteForm.phone || !inviteForm.role_id) {
            Swal.fire({ title: 'Missing fields', text: 'Please fill all invite fields.', icon: 'warning' });
            return;
        }
        try {
            setInviting(true);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(route('organization.team.members.invite'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(inviteForm),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                Swal.fire({ title: 'Invite failed', text: result.message || 'Could not send invite.', icon: 'error' });
                return;
            }
            Swal.fire({ title: 'Invitation sent', text: result.message, icon: 'success' });
            setInviteForm({ name: '', email: '', phone: '', role_id: '' });
            loadMembers();
        } catch {
            Swal.fire({ title: 'Invite failed', text: 'Could not send invite.', icon: 'error' });
        } finally {
            setInviting(false);
        }
    };

    const handleRoleUpdate = async (membershipId) => {
        const roleId = roleDrafts[membershipId];
        if (!roleId) {
            Swal.fire({ title: 'Missing role', text: 'Please select a role first.', icon: 'warning' });
            return;
        }
        try {
            setUpdatingRoleFor(membershipId);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(route('organization.team.members.updateRole'), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    membership_id: membershipId,
                    role_id: roleId,
                }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                Swal.fire({ title: 'Update failed', text: result.message || 'Could not update role.', icon: 'error' });
                return;
            }
            Swal.fire({
                title: 'Updated',
                text: result.message || 'Member role updated.',
                icon: 'success',
                timer: 1200,
                showConfirmButton: false,
            });
            loadMembers();
        } catch {
            Swal.fire({ title: 'Update failed', text: 'Could not update role.', icon: 'error' });
        } finally {
            setUpdatingRoleFor(null);
        }
    };

    const runMemberAction = async (membershipId, routeName, successTitle) => {
        try {
            setMemberActionLoadingId(membershipId);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(route(routeName), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ membership_id: membershipId }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                Swal.fire({ title: 'Action failed', text: result.message || 'Could not complete action.', icon: 'error' });
                return;
            }
            Swal.fire({
                title: successTitle,
                text: result.message || 'Action completed successfully.',
                icon: 'success',
                timer: 1200,
                showConfirmButton: false,
            });
            loadMembers();
        } catch {
            Swal.fire({ title: 'Action failed', text: 'Could not complete action.', icon: 'error' });
        } finally {
            setMemberActionLoadingId(null);
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Team / Company Settings</h2>}
        >
            <Head title="Team Settings" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="bg-white shadow sm:rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900">Team Members</h3>
                        <p className="mt-1 text-sm text-gray-600">
                            Organization: <span className="font-medium">{organization?.name || '-'}</span>
                        </p>

                        <form onSubmit={handleInvite} className="mt-4 border rounded-md p-4 bg-gray-50">
                            <p className="text-sm font-medium text-gray-800 mb-3">Invite member</p>
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <input
                                    type="text"
                                    placeholder="Name"
                                    value={inviteForm.name}
                                    onChange={(e) => setInviteForm((v) => ({ ...v, name: e.target.value }))}
                                    className="border rounded px-3 py-2 text-sm"
                                />
                                <input
                                    type="email"
                                    placeholder="Email"
                                    value={inviteForm.email}
                                    onChange={(e) => setInviteForm((v) => ({ ...v, email: e.target.value }))}
                                    className="border rounded px-3 py-2 text-sm"
                                />
                                <input
                                    type="text"
                                    placeholder="Phone"
                                    value={inviteForm.phone}
                                    onChange={(e) => setInviteForm((v) => ({ ...v, phone: e.target.value }))}
                                    className="border rounded px-3 py-2 text-sm"
                                />
                                <select
                                    value={inviteForm.role_id}
                                    onChange={(e) => setInviteForm((v) => ({ ...v, role_id: e.target.value }))}
                                    className="border rounded px-3 py-2 text-sm"
                                >
                                    <option value="">Select role</option>
                                    {roles.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="mt-3 text-xs text-gray-500">
                                Activation link is sent via email; invited user sets password using reset-password flow.
                            </div>
                            <div className="mt-3">
                                <button
                                    type="submit"
                                    disabled={inviting}
                                    className="px-3 py-2 text-sm rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-60"
                                >
                                    {inviting ? 'Sending...' : 'Send Invite'}
                                </button>
                            </div>
                        </form>

                        <div className="mt-4 flex items-center gap-3 flex-wrap">
                            <input
                                type="text"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Search name/email/phone/role"
                                className="border rounded px-3 py-2 text-sm min-w-[260px]"
                            />
                            <button
                                type="button"
                                onClick={loadMembers}
                                className="px-3 py-2 text-sm rounded bg-indigo-600 text-white hover:bg-indigo-700"
                            >
                                Search
                            </button>
                            <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={archived}
                                    onChange={(e) => setArchived(e.target.checked)}
                                />
                                Show archived
                            </label>
                        </div>

                        <div className="mt-5 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left">Name</th>
                                        <th className="px-4 py-2 text-left">Email</th>
                                        <th className="px-4 py-2 text-left">Phone</th>
                                        <th className="px-4 py-2 text-left">Role</th>
                                        <th className="px-4 py-2 text-left">Status</th>
                                        <th className="px-4 py-2 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {loading ? (
                                        <tr>
                                            <td className="px-4 py-3 text-gray-500" colSpan={6}>
                                                Loading...
                                            </td>
                                        </tr>
                                    ) : members.length === 0 ? (
                                        <tr>
                                            <td className="px-4 py-3 text-gray-500" colSpan={6}>
                                                No members found.
                                            </td>
                                        </tr>
                                    ) : (
                                        members.map((m) => (
                                            <tr key={m.membership_id}>
                                                <td className="px-4 py-2">{m.name}</td>
                                                <td className="px-4 py-2">{m.email}</td>
                                                <td className="px-4 py-2">{m.phone}</td>
                                                <td className="px-4 py-2">
                                                    <select
                                                        value={roleDrafts[m.membership_id] ?? String(m.organization_role_id ?? '')}
                                                        onChange={(e) =>
                                                            setRoleDrafts((prev) => ({
                                                                ...prev,
                                                                [m.membership_id]: e.target.value,
                                                            }))
                                                        }
                                                        className="border rounded px-2 py-1 text-xs min-w-[140px]"
                                                    >
                                                        <option value="">Select role</option>
                                                        {roles.map((r) => (
                                                            <option key={r.id} value={r.id}>
                                                                {r.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className="px-4 py-2">{m.membership_status || '-'}</td>
                                                <td className="px-4 py-2">
                                                    <button
                                                        type="button"
                                                        disabled={updatingRoleFor === m.membership_id}
                                                        onClick={() => handleRoleUpdate(m.membership_id)}
                                                        className="px-2 py-1 text-xs rounded bg-slate-700 text-white hover:bg-slate-800 disabled:opacity-60"
                                                    >
                                                        {updatingRoleFor === m.membership_id ? 'Saving...' : 'Save Role'}
                                                    </button>
                                                    {archived ? (
                                                        <button
                                                            type="button"
                                                            disabled={memberActionLoadingId === m.membership_id}
                                                            onClick={() =>
                                                                runMemberAction(
                                                                    m.membership_id,
                                                                    'organization.team.members.restore',
                                                                    'Member Restored'
                                                                )
                                                            }
                                                            className="ml-2 px-2 py-1 text-xs rounded border border-green-500 text-green-700 hover:bg-green-50 disabled:opacity-60"
                                                        >
                                                            {memberActionLoadingId === m.membership_id ? '...' : 'Restore'}
                                                        </button>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            disabled={memberActionLoadingId === m.membership_id}
                                                            onClick={() =>
                                                                runMemberAction(
                                                                    m.membership_id,
                                                                    'organization.team.members.archive',
                                                                    'Member Archived'
                                                                )
                                                            }
                                                            className="ml-2 px-2 py-1 text-xs rounded border border-red-500 text-red-700 hover:bg-red-50 disabled:opacity-60"
                                                        >
                                                            {memberActionLoadingId === m.membership_id ? '...' : 'Archive'}
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

