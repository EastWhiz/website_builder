import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';

/** Display order and human-readable labels (keys must match `RoleController::allowedPermissionKeys`). */
const PERMISSION_ENTRIES = [
    { key: 'org.create', label: 'Create organization' },
    { key: 'org.update_status', label: 'Update organization status' },
    { key: 'member.invite', label: 'Invite member' },
    { key: 'member.activate_complete', label: 'Manually activate invited member' },
    { key: 'member.role.assign', label: 'Assign member roles (inline)' },
    { key: 'member.edit', label: 'Edit team member profile' },
    { key: 'member.soft_delete', label: 'Archive / soft-delete member' },
    { key: 'member.restore', label: 'Restore archived member' },
    { key: 'role.view', label: 'View roles' },
    { key: 'role.create', label: 'Create roles' },
    { key: 'role.update', label: 'Update roles' },
    { key: 'role.archive', label: 'Archive roles' },
    { key: 'content.view_own', label: 'View own content' },
    { key: 'content.view_org_all', label: 'View all content in organization' },
    { key: 'content.create', label: 'Create content' },
    { key: 'content.update_own', label: 'Update own content' },
    { key: 'content.update_org_all', label: 'Update any content in organization' },
    { key: 'content.soft_delete', label: 'Soft-delete content' },
    { key: 'content.transfer_in_org', label: 'Transfer content within organization' },
    { key: 'content.move_cross_org', label: 'Move content across organizations' },
    { key: 'content.clone_cross_org', label: 'Clone content across organizations' },
    { key: 'integration.catalog.create', label: 'Create integration catalog entries' },
    { key: 'integration.catalog.update', label: 'Update integration catalog' },
    { key: 'integration.catalog.archive', label: 'Archive integration catalog' },
    { key: 'integration.instance.view_own', label: 'View own integration instances' },
    { key: 'integration.instance.view_org', label: 'View organization integration instances' },
    { key: 'integration.instance.create', label: 'Create integration instances' },
    { key: 'integration.instance.update', label: 'Update integration instances' },
    { key: 'integration.instance.soft_del', label: 'Soft-delete integration instances' },
    { key: 'audit.view_org', label: 'View audit logs (organization)' },
    { key: 'audit.view_cross_org', label: 'View audit logs (cross-organization)' },
    { key: 'permission.matrix.view', label: 'View permission matrix' },
    { key: 'permission.matrix.update', label: 'Update permission matrix' },
];

export default function Roles() {
    const { auth } = usePage().props;
    const permissions = auth?.permissions || {};
    const canRoleCrud = !!permissions.org_role_crud;
    /** Super / platform admins only; matches RoleController::isPrivilegedPlatformAdmin + org.role.crud. */
    const canManageSystemRoles = canRoleCrud;
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [scope, setScope] = useState('organization');
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState({
        scope: 'organization',
        key: '',
        name: '',
        description: '',
        is_active: true,
        permissions: [],
    });

    const title = useMemo(() => (editingId ? 'Edit Role' : 'Create Role'), [editingId]);

    const loadRoles = async () => {
        try {
            setLoading(true);
            const url = new URL(route('admin.roles.index'));
            url.searchParams.set('scope', scope);
            const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
            const result = await res.json();
            if (!res.ok || !result.success) {
                Swal.fire({ title: 'Error', text: result.message || 'Failed to load roles.', icon: 'error' });
                return;
            }
            setRoles(result.data || []);
        } catch {
            Swal.fire({ title: 'Error', text: 'Failed to load roles.', icon: 'error' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!canRoleCrud) {
            return;
        }
        loadRoles();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [scope, canRoleCrud]);

    const resetForm = () => {
        setEditingId(null);
        setForm({
            scope,
            key: '',
            name: '',
            description: '',
            is_active: true,
            permissions: [],
        });
    };

    const handleEdit = (role) => {
        setEditingId(role.id);
        setForm({
            scope: role.scope,
            key: role.key,
            name: role.name,
            description: role.description || '',
            is_active: !!role.is_active,
            permissions: Array.isArray(role.permissions) ? role.permissions : [],
        });
    };

    const togglePermission = (perm) => {
        setForm((prev) => ({
            ...prev,
            permissions: prev.permissions.includes(perm)
                ? prev.permissions.filter((p) => p !== perm)
                : [...prev.permissions, perm],
        }));
    };

    const handleSave = async (e) => {
        e.preventDefault();
        if (!form.name || !form.key) {
            Swal.fire({ title: 'Missing fields', text: 'Role key and name are required.', icon: 'warning' });
            return;
        }

        try {
            setSaving(true);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const payload = {
                scope: form.scope,
                key: form.key,
                name: form.name,
                description: form.description,
                is_active: form.is_active,
                permissions: form.permissions,
            };

            const routeName = editingId ? route('admin.roles.update', editingId) : route('admin.roles.store');
            const method = editingId ? 'PUT' : 'POST';
            if (editingId) {
                delete payload.scope;
                delete payload.key;
            }

            const res = await fetch(routeName, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            });
            const result = await res.json();
            if (!res.ok || !result.success) {
                Swal.fire({ title: 'Save failed', text: result.message || 'Could not save role.', icon: 'error' });
                return;
            }

            Swal.fire({
                title: 'Saved',
                text: result.message || 'Role saved successfully.',
                icon: 'success',
                timer: 1200,
                showConfirmButton: false,
            });
            resetForm();
            loadRoles();
        } catch {
            Swal.fire({ title: 'Save failed', text: 'Could not save role.', icon: 'error' });
        } finally {
            setSaving(false);
        }
    };

    const handleArchive = async (role) => {
        const confirm = await Swal.fire({
            title: 'Archive role?',
            text: `This will archive "${role.name}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Archive',
        });
        if (!confirm.isConfirmed) return;

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res = await fetch(route('admin.roles.archive', role.id), {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            const result = await res.json();
            if (!res.ok || !result.success) {
                Swal.fire({ title: 'Archive failed', text: result.message || 'Could not archive role.', icon: 'error' });
                return;
            }
            Swal.fire({ title: 'Archived', text: result.message || 'Role archived.', icon: 'success', timer: 1200, showConfirmButton: false });
            loadRoles();
        } catch {
            Swal.fire({ title: 'Archive failed', text: 'Could not archive role.', icon: 'error' });
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Role Management</h2>}>
            <Head title="Role Management" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="bg-white shadow sm:rounded-lg p-6">
                        {!canRoleCrud ? (
                            <div className="text-sm text-red-700">You are not authorized to manage roles.</div>
                        ) : (
                        <>
                        <div className="flex items-center gap-3 mb-4">
                            <label className="text-sm font-medium text-gray-700">Scope</label>
                            <select
                                value={scope}
                                onChange={(e) => {
                                    setScope(e.target.value);
                                    setForm((prev) => ({ ...prev, scope: e.target.value }));
                                }}
                                className="rounded-md border-gray-300 text-sm"
                            >
                                <option value="organization">Organization</option>
                                <option value="platform">Platform</option>
                            </select>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h3 className="text-md font-semibold mb-3">Roles</h3>
                                <div className="overflow-x-auto border rounded-md">
                                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left">Name</th>
                                                <th className="px-3 py-2 text-left">Key</th>
                                                <th className="px-3 py-2 text-left">Status</th>
                                                <th className="px-3 py-2 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {loading ? (
                                                <tr><td colSpan={4} className="px-3 py-3 text-gray-500">Loading...</td></tr>
                                            ) : roles.length === 0 ? (
                                                <tr><td colSpan={4} className="px-3 py-3 text-gray-500">No roles found.</td></tr>
                                            ) : (
                                                roles.map((r) => (
                                                    <tr key={r.id}>
                                                        <td className="px-3 py-2">
                                                            <div className="font-medium text-gray-900">{r.name}</div>
                                                            {r.is_system ? <span className="text-xs text-amber-700">System role</span> : null}
                                                        </td>
                                                        <td className="px-3 py-2 text-gray-600">{r.key}</td>
                                                        <td className="px-3 py-2">
                                                            <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${r.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                                {r.is_active ? 'Active' : 'Archived'}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <button
                                                                type="button"
                                                                onClick={() => handleEdit(r)}
                                                                className="text-indigo-600 hover:text-indigo-800 mr-3 disabled:opacity-40 disabled:cursor-not-allowed"
                                                                disabled={r.is_system && !canManageSystemRoles}
                                                            >
                                                                Edit
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleArchive(r)}
                                                                className="text-red-600 hover:text-red-800 disabled:opacity-40 disabled:cursor-not-allowed"
                                                                disabled={(r.is_system && !canManageSystemRoles) || !r.is_active}
                                                            >
                                                                Archive
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h3 className="text-md font-semibold mb-3">{title}</h3>
                                <form onSubmit={handleSave} className="space-y-3 border rounded-md p-4 bg-gray-50">
                                    {!editingId && (
                                        <>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Scope</label>
                                                <select
                                                    value={form.scope}
                                                    onChange={(e) => setForm((prev) => ({ ...prev, scope: e.target.value }))}
                                                    className="w-full rounded-md border-gray-300 text-sm"
                                                >
                                                    <option value="organization">Organization</option>
                                                    <option value="platform">Platform</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Key</label>
                                                <input
                                                    type="text"
                                                    value={form.key}
                                                    onChange={(e) => setForm((prev) => ({ ...prev, key: e.target.value }))}
                                                    className="w-full rounded-md border-gray-300 text-sm"
                                                    placeholder="e.g. org_custom_manager"
                                                />
                                            </div>
                                        </>
                                    )}

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                        <input
                                            type="text"
                                            value={form.name}
                                            onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
                                            className="w-full rounded-md border-gray-300 text-sm"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                        <textarea
                                            value={form.description}
                                            onChange={(e) => setForm((prev) => ({ ...prev, description: e.target.value }))}
                                            className="w-full rounded-md border-gray-300 text-sm"
                                            rows={2}
                                        />
                                    </div>

                                    <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            checked={!!form.is_active}
                                            onChange={(e) => setForm((prev) => ({ ...prev, is_active: e.target.checked }))}
                                        />
                                        Active
                                    </label>

                                    <div>
                                        <p className="text-sm font-medium text-gray-700 mb-1">Permissions</p>
                                        <div className="max-h-56 overflow-auto border rounded-md bg-white p-2">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-1">
                                                {PERMISSION_ENTRIES.map(({ key, label }) => (
                                                    <label key={key} className="inline-flex items-start gap-2 text-xs text-gray-700">
                                                        <input
                                                            type="checkbox"
                                                            className="mt-0.5 shrink-0"
                                                            checked={form.permissions.includes(key)}
                                                            onChange={() => togglePermission(key)}
                                                        />
                                                        <span className="font-medium text-gray-900">{label}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 pt-1">
                                        <button
                                            type="submit"
                                            disabled={saving}
                                            className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 disabled:opacity-60"
                                        >
                                            {saving ? 'Saving...' : editingId ? 'Update Role' : 'Create Role'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={resetForm}
                                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                        >
                                            Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        </>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

