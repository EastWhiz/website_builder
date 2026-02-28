import DynamicApiForm from '@/Components/Api/DynamicApiForm';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Fragment, useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function ApiFormFields({ mustVerifyEmail, status, className = '' }) {
    const [groupedData, setGroupedData] = useState([]);
    const [categories, setCategories] = useState([]);
    const [expandedPlatformId, setExpandedPlatformId] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showAddModal, setShowAddModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editingInstance, setEditingInstance] = useState(null);
    const [editingCategoryId, setEditingCategoryId] = useState(null);
    const [addPlatformId, setAddPlatformId] = useState('');
    const [formName, setFormName] = useState('');
    const [formValues, setFormValues] = useState({});
    const [formErrors, setFormErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        setLoading(true);
        try {
            const [groupedRes, categoriesRes] = await Promise.all([
                fetch(route('user.api.instances.index'), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                }),
                fetch(route('user.api.categories.index'), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                }),
            ]);
            const groupedResult = groupedRes.ok ? await groupedRes.json() : { success: false, data: null };
            const categoriesResult = categoriesRes.ok ? await categoriesRes.json() : { success: false, data: null };
            if (groupedResult.success && groupedResult.data != null) {
                const raw = groupedResult.data;
                setGroupedData(Array.isArray(raw) ? raw : Object.values(raw));
            } else {
                setGroupedData([]);
            }
            if (categoriesResult.success && categoriesResult.data != null) {
                const raw = categoriesResult.data;
                setCategories(Array.isArray(raw) ? raw : Object.values(raw));
            } else {
                setCategories([]);
            }
        } catch (e) {
            console.error('API Platforms load error:', e);
            setGroupedData([]);
            setCategories([]);
        } finally {
            setLoading(false);
        }
    };

    const getHeaders = () => {
        const h = { 'Content-Type': 'application/json', Accept: 'application/json' };
        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf) h['X-CSRF-TOKEN'] = csrf.content;
        return h;
    };

    const toggleExpand = (categoryId) => {
        setExpandedPlatformId((prev) => (prev === categoryId ? null : categoryId));
    };

    const openAddModal = () => {
        setAddPlatformId('');
        setFormName('');
        setFormValues({});
        setFormErrors({});
        setShowAddModal(true);
        setShowEditModal(false);
    };

    const openEditModal = (instance, categoryId) => {
        setEditingInstance(instance);
        setEditingCategoryId(categoryId);
        setFormName(instance.name);
        setFormValues({ ...(instance.credentials || {}) });
        setFormErrors({});
        setShowEditModal(true);
        setShowAddModal(false);
    };

    const closeModals = () => {
        setShowAddModal(false);
        setShowEditModal(false);
        setEditingInstance(null);
        setEditingCategoryId(null);
        setAddPlatformId('');
    };

    const selectedCategoryForAdd = categories.find((c) => c.id === parseInt(addPlatformId, 10));
    const selectedCategoryForEdit = categories.find((c) => c.id === editingCategoryId);

    const handleAdd = async (e) => {
        e.preventDefault();
        if (!addPlatformId || !selectedCategoryForAdd) {
            Swal.fire({ title: 'Error', text: 'Please select a platform.', icon: 'error' });
            return;
        }
        setFormErrors({});
        setSubmitting(true);
        try {
            const res = await fetch(route('user.api.instances.store'), {
            method: 'POST',
                headers: getHeaders(),
            body: JSON.stringify({
                    api_category_id: parseInt(addPlatformId, 10),
                    name: formName,
                    values: formValues,
            }),
            });
            const result = await res.json();
                if (result.success) {
                Swal.fire({ title: 'Success!', text: result.message, icon: 'success', timer: 1500, showConfirmButton: false });
                closeModals();
                loadData();
                } else {
                setFormErrors(result.errors || {});
                Swal.fire({ title: 'Error', text: result.message || 'Validation failed.', icon: 'error' });
            }
        } catch (e) {
            console.error(e);
            Swal.fire({ title: 'Error', text: 'Request failed.', icon: 'error' });
        } finally {
            setSubmitting(false);
        }
    };

    const handleUpdate = async (e) => {
        e.preventDefault();
        if (!editingInstance) return;
        setFormErrors({});
        setSubmitting(true);
        try {
            const res = await fetch(route('user.api.instances.update', { id: editingInstance.id }), {
                method: 'PUT',
                headers: getHeaders(),
                body: JSON.stringify({ name: formName, values: formValues }),
            });
            const result = await res.json();
            if (result.success) {
                Swal.fire({ title: 'Success!', text: result.message, icon: 'success', timer: 1500, showConfirmButton: false });
                closeModals();
                loadData();
            } else {
                setFormErrors(result.errors || {});
                Swal.fire({ title: 'Error', text: result.message || 'Validation failed.', icon: 'error' });
            }
        } catch (e) {
            console.error(e);
            Swal.fire({ title: 'Error', text: 'Request failed.', icon: 'error' });
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = (instance, categoryId) => {
        Swal.fire({
            title: 'Are you sure?',
            text: `Delete "${instance.name}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete',
        }).then(async (result) => {
            if (!result.isConfirmed) return;
                try {
                const res = await fetch(route('user.api.instances.destroy', { id: instance.id }), {
                        method: 'DELETE',
                    headers: getHeaders(),
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({ title: 'Deleted!', text: data.message, icon: 'success', timer: 1500, showConfirmButton: false });
                    loadData();
                    if (expandedPlatformId === categoryId) setExpandedPlatformId(null);
                    } else {
                    Swal.fire({ title: 'Error', text: data.message || 'Delete failed.', icon: 'error' });
                }
            } catch (e) {
                console.error(e);
                Swal.fire({ title: 'Error', text: 'Request failed.', icon: 'error' });
            }
        });
    };

    const setFormValue = (fieldName, value) => {
        setFormValues((prev) => ({ ...prev, [fieldName]: value }));
    };

    if (loading) {
        return (
            <section className={className}>
                <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span className="ml-2 text-gray-600">Loading API platforms...</span>
                </div>
            </section>
        );
    }

                return (
        <section className={className}>
            <div className="flex items-center justify-between mb-4">
                        <div>
                    <h3 className="text-lg font-medium text-gray-900">API Platforms</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Platforms under which you have created APIs. Click a row to expand and manage your APIs.
                            </p>
                        </div>
                <PrimaryButton onClick={openAddModal}>Add New API</PrimaryButton>
                    </div>

            {groupedData.length === 0 ? (
                <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                    You have not added any APIs yet. Click &quot;Add New API&quot; to create one.
                        </div>
            ) : (
                <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th scope="col" className="w-8 px-4 py-3 text-left"></th>
                                <th scope="col" className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Platform
                                </th>
                                <th scope="col" className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    APIs
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                            {groupedData.map((group) => {
                                const isExpanded = expandedPlatformId === group.category.id;
                return (
                                    <Fragment key={group.category.id}>
                                        <tr
                                            key={group.category.id}
                                            onClick={() => toggleExpand(group.category.id)}
                                            className="cursor-pointer transition hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-block transition-transform ${isExpanded ? 'rotate-90' : ''}`}
                                                >
                                                    <svg className="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                                {group.category.name}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500">
                                                {group.instances.length} API{group.instances.length !== 1 ? 's' : ''}
                                            </td>
                                        </tr>
                                        {isExpanded && (
                                            <tr key={`${group.category.id}-expanded`}>
                                                <td colSpan={3} className="bg-gray-50 px-4 py-3">
                                                    <div className="rounded-md border border-gray-200 bg-white">
                                                        <table className="min-w-full">
                                                            <thead>
                                                                <tr className="border-b border-gray-200 bg-gray-50/80">
                                                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500">API Name</th>
                                                                    <th className="px-4 py-2 text-right text-xs font-medium text-gray-500">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody className="divide-y divide-gray-100">
                                                                {group.instances.map((inst) => (
                                                                    <tr key={inst.id} className="hover:bg-gray-50/50">
                                                                        <td className="px-4 py-2 text-sm text-gray-900">
                                                                            {inst.name}
                                                                            {!inst.is_active && (
                                                                                <span className="ml-2 text-xs text-amber-600">(inactive)</span>
                                                                            )}
                                                                        </td>
                                                                        <td className="px-4 py-2 text-right">
                                                                            <button
                                                                                type="button"
                                                                                onClick={(e) => {
                                                                                    e.stopPropagation();
                                                                                    openEditModal(inst, group.category.id);
                                                                                }}
                                                                                className="text-indigo-600 hover:text-indigo-800 text-sm font-medium mr-3"
                                                                            >
                                                                                Edit
                                                                            </button>
                                                                            <button
                                                                                type="button"
                                                                                onClick={(e) => {
                                                                                    e.stopPropagation();
                                                                                    handleDelete(inst, group.category.id);
                                                                                }}
                                                                                className="text-red-600 hover:text-red-800 text-sm font-medium"
                                                                            >
                                                                                Delete
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                        </div>
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                        </div>
            )}

            {/* Add New API modal */}
            {showAddModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                    <div className="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={closeModals} />
                        <div className="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Add New API</h3>
                            <form onSubmit={handleAdd}>
                    <div className="space-y-4">
                        <div>
                                        <InputLabel htmlFor="add_platform" value="API Platform" />
                                        <select
                                            id="add_platform"
                                            value={addPlatformId}
                                            onChange={(e) => {
                                                setAddPlatformId(e.target.value);
                                                setFormValues({});
                                                setFormErrors({});
                                            }}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            required
                                        >
                                            <option value="">Select a platform</option>
                                            {categories.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.name}
                                                </option>
                                            ))}
                                        </select>
                                        {categories.length === 0 && (
                                            <p className="mt-1 text-xs text-gray-500">No active platforms. Ask an admin to add some.</p>
                                        )}
                        </div>
                                    {selectedCategoryForAdd && (
                                        <>
                        <div>
                                                <InputLabel htmlFor="add_api_name" value="API Name" />
                            <TextInput
                                                    id="add_api_name"
                                className="mt-1 block w-full"
                                                    value={formName}
                                                    onChange={(e) => setFormName(e.target.value)}
                                                    required
                                                />
                                                <InputError className="mt-2" message={formErrors.name} />
                        </div>
                                            <DynamicApiForm
                                                fields={selectedCategoryForAdd.fields || []}
                                                values={formValues}
                                                errors={formErrors}
                                                onValueChange={setFormValue}
                                            />
                                        </>
                                    )}
                        </div>
                                <div className="mt-6 flex gap-3">
                                    <PrimaryButton type="submit" disabled={submitting || !addPlatformId}>
                                        {submitting ? 'Creating...' : 'Create'}
                                    </PrimaryButton>
                                    <SecondaryButton type="button" onClick={closeModals}>
                                        Cancel
                                    </SecondaryButton>
                        </div>
                            </form>
                        </div>
                    </div>
                        </div>
            )}

            {/* Edit API modal */}
            {showEditModal && selectedCategoryForEdit && editingInstance && (
                <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                    <div className="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={closeModals} />
                        <div className="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Edit API: {editingInstance.name}</h3>
                            <form onSubmit={handleUpdate}>
                                <DynamicApiForm
                                    fields={selectedCategoryForEdit.fields || []}
                                    values={formValues}
                                    errors={formErrors}
                                    onValueChange={setFormValue}
                                    name={formName}
                                    onNameChange={setFormName}
                                    nameError={formErrors.name}
                                    nameLabel="API Name"
                                    nameId="edit_api_name"
                                />
                                <div className="mt-6 flex gap-3">
                                    <PrimaryButton type="submit" disabled={submitting}>
                                        {submitting ? 'Saving...' : 'Save'}
                                    </PrimaryButton>
                                    <SecondaryButton type="button" onClick={closeModals}>
                                        Cancel
                                    </SecondaryButton>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </section>
    );
}
