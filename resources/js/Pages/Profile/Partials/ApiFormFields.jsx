import DynamicApiForm from '@/Components/Api/DynamicApiForm';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function ApiFormFields({ mustVerifyEmail, status, className = '' }) {
    const [categories, setCategories] = useState([]);
    const [selectedCategoryId, setSelectedCategoryId] = useState(null);
    const [instances, setInstances] = useState([]);
    const [loadingCategories, setLoadingCategories] = useState(true);
    const [loadingInstances, setLoadingInstances] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editingInstance, setEditingInstance] = useState(null);
    const [formName, setFormName] = useState('');
    const [formValues, setFormValues] = useState({});
    const [formErrors, setFormErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        loadCategories();
    }, []);

    useEffect(() => {
        if (selectedCategoryId) {
            loadInstances(selectedCategoryId);
        } else {
            setInstances([]);
        }
    }, [selectedCategoryId]);

    const loadCategories = async () => {
        try {
            setLoadingCategories(true);
            const res = await fetch(route('user.api.categories.index'), {
                headers: { Accept: 'application/json' },
            });
            const result = await res.json();
            if (result.success && result.data?.length) {
                setCategories(result.data);
                if (!selectedCategoryId) setSelectedCategoryId(result.data[0].id);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoadingCategories(false);
        }
    };

    const loadInstances = async (categoryId) => {
        try {
            setLoadingInstances(true);
            const res = await fetch(route('user.api.instances.byCategory', { categoryId }), {
                headers: { Accept: 'application/json' },
            });
            const result = await res.json();
            setInstances(result.success ? result.data : []);
        } catch (e) {
            console.error(e);
            setInstances([]);
        } finally {
            setLoadingInstances(false);
        }
    };

    const getHeaders = () => {
        const h = { 'Content-Type': 'application/json', Accept: 'application/json' };
        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf) h['X-CSRF-TOKEN'] = csrf.content;
        return h;
    };

    const selectedCategory = categories.find((c) => c.id === selectedCategoryId);

    const openCreate = () => {
        setEditingInstance(null);
        setFormName('');
        setFormValues(
            selectedCategory?.fields?.reduce((acc, f) => ({ ...acc, [f.name]: '' }), {}) || {}
        );
        setFormErrors({});
        setShowCreateModal(true);
        setShowEditModal(false);
    };

    const openEdit = (instance) => {
        setEditingInstance(instance);
        setFormName(instance.name);
        setFormValues({ ...(instance.credentials || {}) });
        setFormErrors({});
        setShowEditModal(true);
        setShowCreateModal(false);
    };

    const closeModals = () => {
        setShowCreateModal(false);
        setShowEditModal(false);
        setEditingInstance(null);
    };

    const handleCreate = async (e) => {
        e.preventDefault();
        setFormErrors({});
        setSubmitting(true);
        try {
            const res = await fetch(route('user.api.instances.store'), {
                method: 'POST',
                headers: getHeaders(),
                body: JSON.stringify({
                    api_category_id: selectedCategoryId,
                    name: formName,
                    values: formValues,
                }),
            });
            const result = await res.json();
            if (result.success) {
                Swal.fire({ title: 'Success!', text: result.message, icon: 'success', timer: 1500, showConfirmButton: false });
                closeModals();
                loadInstances(selectedCategoryId);
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
                loadInstances(selectedCategoryId);
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

    const handleDelete = (instance) => {
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
                    loadInstances(selectedCategoryId);
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

    if (loadingCategories) {
        return (
            <section className={className}>
                <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span className="ml-2 text-gray-600">Loading API platforms...</span>
                </div>
            </section>
        );
    }

    if (!categories.length) {
        return (
            <section className={className}>
                <h3 className="text-lg font-medium text-gray-900">API Instances</h3>
                <p className="mt-2 text-sm text-gray-500">No API platforms available. An admin can add platforms in API Platforms.</p>
            </section>
        );
    }

    return (
        <section className={className}>
            <h3 className="text-lg font-medium text-gray-900">API Instances</h3>
            <p className="mt-1 text-sm text-gray-500">Manage your API credentials by category. Create one or more instances per category.</p>

            {/* Category tabs */}
            <div className="mt-4 border-b border-gray-200">
                <nav className="-mb-px flex flex-wrap gap-2">
                    {categories.map((c) => (
                        <button
                            key={c.id}
                            onClick={() => setSelectedCategoryId(c.id)}
                            className={`whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium ${
                                selectedCategoryId === c.id
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            }`}
                        >
                            {c.name}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Instance list for selected category */}
            <div className="mt-6">
                {selectedCategory && (
                    <>
                        <div className="flex items-center justify-between mb-4">
                            <h4 className="text-sm font-medium text-gray-700">Instances: {selectedCategory.name}</h4>
                            <PrimaryButton onClick={openCreate}>Create Instance</PrimaryButton>
                        </div>

                        {loadingInstances ? (
                            <div className="py-4 text-gray-500 text-sm">Loading...</div>
                        ) : instances.length === 0 ? (
                            <p className="text-sm text-gray-500">No instances yet. Click &quot;Create Instance&quot; to add one.</p>
                        ) : (
                            <ul className="divide-y divide-gray-200 rounded-md border border-gray-200">
                                {instances.map((inst) => (
                                    <li key={inst.id} className="flex items-center justify-between px-4 py-3">
                                        <div>
                                            <span className="font-medium text-gray-900">{inst.name}</span>
                                            {!inst.is_active && (
                                                <span className="ml-2 text-xs text-amber-600">(inactive)</span>
                                            )}
                                        </div>
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => openEdit(inst)}
                                                className="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => handleDelete(inst)}
                                                className="text-red-600 hover:text-red-800 text-sm font-medium"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </>
                )}
            </div>

            {/* Create modal */}
            {showCreateModal && selectedCategory && (
                <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div className="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onClick={closeModals} />
                        <div className="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Create instance: {selectedCategory.name}</h3>
                            <form onSubmit={handleCreate}>
                                <DynamicApiForm
                                    fields={selectedCategory.fields || []}
                                    values={formValues}
                                    errors={formErrors}
                                    onValueChange={setFormValue}
                                    name={formName}
                                    onNameChange={setFormName}
                                    nameError={formErrors.name}
                                    nameLabel="Instance name"
                                    nameId="create_instance_name"
                                />
                                <div className="mt-6 flex gap-3">
                                    <PrimaryButton type="submit" disabled={submitting}>
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

            {/* Edit modal */}
            {showEditModal && selectedCategory && editingInstance && (
                <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div className="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onClick={closeModals} />
                        <div className="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Edit instance: {editingInstance.name}</h3>
                            <form onSubmit={handleUpdate}>
                                <DynamicApiForm
                                    fields={selectedCategory.fields || []}
                                    values={formValues}
                                    errors={formErrors}
                                    onValueChange={setFormValue}
                                    name={formName}
                                    onNameChange={setFormName}
                                    nameError={formErrors.name}
                                    nameLabel="Instance name"
                                    nameId="edit_instance_name"
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
