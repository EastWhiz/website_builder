import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import SecondaryButton from '@/Components/SecondaryButton';

export default function ApiCategories({ auth }) {
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingCategory, setEditingCategory] = useState(null);
    const [formData, setFormData] = useState({
        name: '',
        is_active: true,
        sort_order: 0,
    });
    const [managingFields, setManagingFields] = useState(null);
    const [fields, setFields] = useState([]);
    const [showFieldForm, setShowFieldForm] = useState(false);
    const [editingField, setEditingField] = useState(null);
    const [fieldFormData, setFieldFormData] = useState({
        name: '',
        label: '',
        type: 'text',
        placeholder: '',
        is_required: false,
        encrypt: false,
        sort_order: 0,
    });

    useEffect(() => {
        loadCategories();
    }, []);

    const loadCategories = async () => {
        try {
            setLoading(true);
            const response = await fetch(route('api.categories.index'), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                setCategories(result.data);
            }
        } catch (error) {
            console.error('Error loading API categories:', error);
            Swal.fire({
                title: 'Error!',
                text: 'Failed to load API categories.',
                icon: 'error',
            });
        } finally {
            setLoading(false);
        }
    };

    const handleEdit = (category) => {
        setEditingCategory(category);
        setFormData({
            name: category.name,
            is_active: category.is_active,
            sort_order: category.sort_order,
        });
        setShowForm(true);
    };

    const handleCancel = () => {
        setShowForm(false);
        setEditingCategory(null);
        setFormData({
            name: '',
            is_active: true,
            sort_order: 0,
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!formData.name.trim()) {
            Swal.fire({
                title: 'Error!',
                text: 'Category name is required.',
                icon: 'error',
            });
            return;
        }

        try {
            setLoading(true);
            const url = editingCategory
                ? route('api.categories.update', editingCategory.id)
                : route('api.categories.store');
            const method = editingCategory ? 'PUT' : 'POST';

            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };

            // Add CSRF token if available
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken.content;
            }

            const response = await fetch(url, {
                method: method,
                headers: headers,
                body: JSON.stringify(formData),
            });

            let result;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const text = await response.text();
                throw new Error(`Invalid response: ${text}`);
            }

            if (!response.ok) {
                // Handle validation errors or other HTTP errors
                let errorMessage = result.message || 'An error occurred.';
                if (result.errors) {
                    const errorMessages = Object.values(result.errors).flat().join(', ');
                    errorMessage = errorMessages || errorMessage;
                }
                Swal.fire({
                    title: 'Error!',
                    text: errorMessage,
                    icon: 'error',
                });
                return;
            }

            if (result.success) {
                Swal.fire({
                    title: 'Success!',
                    text: editingCategory
                        ? 'API category updated successfully.'
                        : 'API category created successfully.',
                    icon: 'success',
                });
                handleCancel();
                loadCategories();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: result.message || 'An error occurred.',
                    icon: 'error',
                });
            }
        } catch (error) {
            console.error('Error saving API category:', error);
            Swal.fire({
                title: 'Error!',
                text: error.message || 'Failed to save API category.',
                icon: 'error',
            });
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async (id) => {
        const result = await Swal.fire({
            title: 'Are you sure?',
            text: 'This will delete the category and all its fields.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
        });

        if (result.isConfirmed) {
            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                };

                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken.content;
                }

                const response = await fetch(route('api.categories.destroy', id), {
                    method: 'DELETE',
                    headers: headers,
                });

                const deleteResult = await response.json();

                if (deleteResult.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'API category deleted successfully.',
                        icon: 'success',
                    });
                    loadCategories();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: deleteResult.message || 'Cannot delete category.',
                        icon: 'error',
                    });
                }
            } catch (error) {
                console.error('Error deleting API category:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to delete API category.',
                    icon: 'error',
                });
            }
        }
    };

    const handleToggleActive = async (id) => {
        try {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken.content;
            }

            const response = await fetch(route('api.categories.toggleActive', id), {
                method: 'POST',
                headers: headers,
            });

            const result = await response.json();

            if (result.success) {
                loadCategories();
            }
        } catch (error) {
            console.error('Error toggling category status:', error);
        }
    };

    const handleManageFields = async (category) => {
        setManagingFields(category);
        await loadFields(category.id);
    };

    const loadFields = async (categoryId) => {
        try {
            const response = await fetch(route('api.categories.show', categoryId));
            const result = await response.json();
            if (result.success && result.data.fields) {
                setFields(result.data.fields);
            }
        } catch (error) {
            console.error('Error loading fields:', error);
        }
    };

    const handleAddField = () => {
        setEditingField(null);
        setFieldFormData({
            name: '',
            label: '',
            type: 'text',
            placeholder: '',
            is_required: false,
            encrypt: false,
            sort_order: fields.length,
        });
        setShowFieldForm(true);
    };

    const handleEditField = (field) => {
        setEditingField(field);
        setFieldFormData({
            name: field.name,
            label: field.label,
            type: field.type,
            placeholder: field.placeholder || '',
            is_required: field.is_required,
            encrypt: field.encrypt,
            sort_order: field.sort_order,
        });
        setShowFieldForm(true);
    };

    const handleCancelField = () => {
        setShowFieldForm(false);
        setEditingField(null);
        setFieldFormData({
            name: '',
            label: '',
            type: 'text',
            placeholder: '',
            is_required: false,
            encrypt: false,
            sort_order: 0,
        });
    };

    const handleSubmitField = async (e) => {
        e.preventDefault();

        if (!fieldFormData.name.trim() || !fieldFormData.label.trim()) {
            Swal.fire({
                title: 'Error!',
                text: 'Field name and label are required.',
                icon: 'error',
            });
            return;
        }

        try {
            const url = editingField
                ? route('api.category.fields.update', { categoryId: managingFields.id, fieldId: editingField.id })
                : route('api.category.fields.store', managingFields.id);
            const method = editingField ? 'PUT' : 'POST';

            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken.content;
            }

            const response = await fetch(url, {
                method: method,
                headers: headers,
                body: JSON.stringify(fieldFormData),
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    title: 'Success!',
                    text: editingField ? 'Field updated successfully.' : 'Field created successfully.',
                    icon: 'success',
                });
                handleCancelField();
                await loadFields(managingFields.id);
                loadCategories();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: result.message || 'An error occurred.',
                    icon: 'error',
                });
            }
        } catch (error) {
            console.error('Error saving field:', error);
            Swal.fire({
                title: 'Error!',
                text: 'Failed to save field.',
                icon: 'error',
            });
        }
    };

    const handleDeleteField = async (fieldId) => {
        const result = await Swal.fire({
            title: 'Are you sure?',
            text: 'This will delete the field.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
        });

        if (result.isConfirmed) {
            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                };

                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken.content;
                }

                const response = await fetch(
                    route('api.category.fields.destroy', { categoryId: managingFields.id, fieldId }),
                    {
                        method: 'DELETE',
                        headers: headers,
                    }
                );

                const deleteResult = await response.json();

                if (deleteResult.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'Field deleted successfully.',
                        icon: 'success',
                    });
                    await loadFields(managingFields.id);
                    loadCategories();
                }
            } catch (error) {
                console.error('Error deleting field:', error);
            }
        }
    };

    const handleReorderFields = async () => {
        const fieldIds = fields.map((f) => f.id);
        try {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken.content;
            }

            const response = await fetch(route('api.category.fields.reorder', managingFields.id), {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({ field_ids: fieldIds }),
            });

            const result = await response.json();
            if (result.success) {
                await loadFields(managingFields.id);
            }
        } catch (error) {
            console.error('Error reordering fields:', error);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">API Categories</h2>}
        >
            <Head title="API Categories" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            {managingFields ? (
                                <div>
                                    <div className="flex justify-between items-center mb-6">
                                        <div>
                                            <h3 className="text-lg font-medium">Manage Fields: {managingFields.name}</h3>
                                        </div>
                                        <div className="flex gap-2">
                                            <SecondaryButton onClick={() => setManagingFields(null)}>
                                                Back to Categories
                                            </SecondaryButton>
                                            <PrimaryButton onClick={handleAddField}>Add Field</PrimaryButton>
                                        </div>
                                    </div>

                                    {showFieldForm ? (
                                        <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                                            <h4 className="text-md font-medium mb-4">
                                                {editingField ? 'Edit Field' : 'Add Field'}
                                            </h4>
                                            <form onSubmit={handleSubmitField}>
                                                <div className="space-y-4">
                                                    <div className="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <InputLabel htmlFor="field_name" value="Field Name *" />
                                                            <TextInput
                                                                id="field_name"
                                                                type="text"
                                                                className="mt-1 block w-full"
                                                                value={fieldFormData.name}
                                                                onChange={(e) =>
                                                                    setFieldFormData({ ...fieldFormData, name: e.target.value })
                                                                }
                                                                placeholder="e.g., api_key"
                                                                required
                                                            />
                                                        </div>
                                                        <div>
                                                            <InputLabel htmlFor="field_label" value="Label *" />
                                                            <TextInput
                                                                id="field_label"
                                                                type="text"
                                                                className="mt-1 block w-full"
                                                                value={fieldFormData.label}
                                                                onChange={(e) =>
                                                                    setFieldFormData({ ...fieldFormData, label: e.target.value })
                                                                }
                                                                placeholder="e.g., API Key"
                                                                required
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <InputLabel htmlFor="field_type" value="Type *" />
                                                            <select
                                                                id="field_type"
                                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                value={fieldFormData.type}
                                                                onChange={(e) =>
                                                                    setFieldFormData({ ...fieldFormData, type: e.target.value })
                                                                }
                                                                required
                                                            >
                                                                <option value="text">Text</option>
                                                                <option value="password">Password</option>
                                                                <option value="email">Email</option>
                                                                <option value="url">URL</option>
                                                                <option value="number">Number</option>
                                                                <option value="textarea">Textarea</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <InputLabel htmlFor="field_placeholder" value="Placeholder" />
                                                            <TextInput
                                                                id="field_placeholder"
                                                                type="text"
                                                                className="mt-1 block w-full"
                                                                value={fieldFormData.placeholder}
                                                                onChange={(e) =>
                                                                    setFieldFormData({ ...fieldFormData, placeholder: e.target.value })
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <InputLabel htmlFor="field_sort_order" value="Sort Order" />
                                                            <TextInput
                                                                id="field_sort_order"
                                                                type="number"
                                                                className="mt-1 block w-full"
                                                                value={fieldFormData.sort_order}
                                                                onChange={(e) =>
                                                                    setFieldFormData({
                                                                        ...fieldFormData,
                                                                        sort_order: parseInt(e.target.value) || 0,
                                                                    })
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="flex gap-4">
                                                        <label className="flex items-center">
                                                            <input
                                                                type="checkbox"
                                                                checked={fieldFormData.is_required}
                                                                onChange={(e) =>
                                                                    setFieldFormData({ ...fieldFormData, is_required: e.target.checked })
                                                                }
                                                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                            />
                                                            <span className="ml-2 text-sm text-gray-700">Required</span>
                                                        </label>
                                                        <label className="flex items-center">
                                                            <input
                                                                type="checkbox"
                                                                checked={fieldFormData.encrypt}
                                                                onChange={(e) =>
                                                                    setFieldFormData({ ...fieldFormData, encrypt: e.target.checked })
                                                                }
                                                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                            />
                                                            <span className="ml-2 text-sm text-gray-700">Encrypt</span>
                                                        </label>
                                                    </div>

                                                    <div className="flex items-center gap-4">
                                                        <PrimaryButton type="submit">
                                                            {editingField ? 'Update' : 'Create'}
                                                        </PrimaryButton>
                                                        <SecondaryButton type="button" onClick={handleCancelField}>
                                                            Cancel
                                                        </SecondaryButton>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    ) : null}

                                    {fields.length === 0 ? (
                                        <div className="text-center py-8 text-gray-500">
                                            No fields found. Add a field to get started.
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-200">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                            Name
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                            Label
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                            Type
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                            Required
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                            Encrypt
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                            Sort Order
                                                        </th>
                                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                                            Actions
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white divide-y divide-gray-200">
                                                    {fields
                                                        .sort((a, b) => a.sort_order - b.sort_order)
                                                        .map((field) => (
                                                            <tr key={field.id}>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                    {field.name}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                    {field.label}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                    {field.type}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap">
                                                                    {field.is_required ? (
                                                                        <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                            Yes
                                                                        </span>
                                                                    ) : (
                                                                        <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                                            No
                                                                        </span>
                                                                    )}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap">
                                                                    {field.encrypt ? (
                                                                        <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                                            Yes
                                                                        </span>
                                                                    ) : (
                                                                        <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                                            No
                                                                        </span>
                                                                    )}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                    {field.sort_order}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                                    <button
                                                                        onClick={() => handleEditField(field)}
                                                                        className="text-indigo-600 hover:text-indigo-900 mr-4"
                                                                    >
                                                                        Edit
                                                                    </button>
                                                                    <button
                                                                        onClick={() => handleDeleteField(field.id)}
                                                                        className="text-red-600 hover:text-red-900"
                                                                    >
                                                                        Delete
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            ) : !showForm ? (
                                <>
                                    <div className="flex justify-between items-center mb-6">
                                        <h3 className="text-lg font-medium">API Categories</h3>
                                        <PrimaryButton onClick={() => setShowForm(true)}>
                                            Create Category
                                        </PrimaryButton>
                                    </div>

                                    {loading ? (
                                        <div className="text-center py-8">Loading...</div>
                                    ) : categories.length === 0 ? (
                                        <div className="text-center py-8 text-gray-500">
                                            No API categories found. Create one to get started.
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-200">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Name
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Fields
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Sort Order
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Status
                                                        </th>
                                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Actions
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white divide-y divide-gray-200">
                                                    {categories.map((category) => (
                                                        <tr key={category.id}>
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <div className="text-sm font-medium text-gray-900">
                                                                    {category.name}
                                                                </div>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <div className="text-sm text-gray-500">
                                                                    {category.fields_count || 0}
                                                                </div>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <div className="text-sm text-gray-500">
                                                                    {category.sort_order}
                                                                </div>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <span
                                                                    className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                                        category.is_active
                                                                            ? 'bg-green-100 text-green-800'
                                                                            : 'bg-red-100 text-red-800'
                                                                    }`}
                                                                >
                                                                    {category.is_active ? 'Active' : 'Inactive'}
                                                                </span>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                                <button
                                                                    onClick={() => handleToggleActive(category.id)}
                                                                    className="text-indigo-600 hover:text-indigo-900 mr-4"
                                                                >
                                                                    {category.is_active ? 'Deactivate' : 'Activate'}
                                                                </button>
                                                                <button
                                                                    onClick={() => handleManageFields(category)}
                                                                    className="text-indigo-600 hover:text-indigo-900 mr-4"
                                                                >
                                                                    Manage Fields
                                                                </button>
                                                                <button
                                                                    onClick={() => handleEdit(category)}
                                                                    className="text-indigo-600 hover:text-indigo-900 mr-4"
                                                                >
                                                                    Edit
                                                                </button>
                                                                <button
                                                                    onClick={() => handleDelete(category.id)}
                                                                    className="text-red-600 hover:text-red-900"
                                                                >
                                                                    Delete
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div>
                                    <h3 className="text-lg font-medium mb-6">
                                        {editingCategory ? 'Edit API Category' : 'Create API Category'}
                                    </h3>
                                    <form onSubmit={handleSubmit}>
                                        <div className="space-y-4">
                                            <div>
                                                <InputLabel htmlFor="name" value="Category Name *" />
                                                <TextInput
                                                    id="name"
                                                    type="text"
                                                    className="mt-1 block w-full"
                                                    value={formData.name}
                                                    onChange={(e) =>
                                                        setFormData({ ...formData, name: e.target.value })
                                                    }
                                                    required
                                                />
                                                <InputError message={null} className="mt-2" />
                                            </div>

                                            <div>
                                                <InputLabel htmlFor="sort_order" value="Sort Order" />
                                                <TextInput
                                                    id="sort_order"
                                                    type="number"
                                                    className="mt-1 block w-full"
                                                    value={formData.sort_order}
                                                    onChange={(e) =>
                                                        setFormData({
                                                            ...formData,
                                                            sort_order: parseInt(e.target.value) || 0,
                                                        })
                                                    }
                                                />
                                            </div>

                                            <div className="flex items-center">
                                                <input
                                                    id="is_active"
                                                    type="checkbox"
                                                    className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                    checked={formData.is_active}
                                                    onChange={(e) =>
                                                        setFormData({ ...formData, is_active: e.target.checked })
                                                    }
                                                />
                                                <InputLabel htmlFor="is_active" value="Active" className="ml-2" />
                                            </div>

                                            <div className="flex items-center gap-4">
                                                <PrimaryButton type="submit" disabled={loading}>
                                                    {editingCategory ? 'Update' : 'Create'}
                                                </PrimaryButton>
                                                <SecondaryButton type="button" onClick={handleCancel}>
                                                    Cancel
                                                </SecondaryButton>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

