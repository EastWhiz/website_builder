import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import SecondaryButton from '@/Components/SecondaryButton';

export default function OtpServices({ auth }) {
    const [services, setServices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingService, setEditingService] = useState(null);
    const [formData, setFormData] = useState({
        name: '',
        is_active: true,
        fields: [
            {
                name: '',
                label: '',
                required: false,
                placeholder: '',
                encrypt: false,
            }
        ]
    });

    useEffect(() => {
        loadServices();
    }, []);

    const loadServices = async () => {
        try {
            setLoading(true);
            const response = await fetch(route('otp.services.admin.index'), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                setServices(result.data);
            }
        } catch (error) {
            console.error('Error loading OTP services:', error);
            Swal.fire({
                title: 'Error!',
                text: 'Failed to load OTP services.',
                icon: 'error',
            });
        } finally {
            setLoading(false);
        }
    };

    const handleAddField = () => {
        setFormData({
            ...formData,
            fields: [
                ...formData.fields,
                {
                    name: '',
                    label: '',
                    required: false,
                    placeholder: '',
                    encrypt: false,
                }
            ]
        });
    };

    const handleRemoveField = (index) => {
        const newFields = formData.fields.filter((_, i) => i !== index);
        setFormData({
            ...formData,
            fields: newFields
        });
    };

    const handleFieldChange = (index, field, value) => {
        const newFields = [...formData.fields];
        newFields[index] = {
            ...newFields[index],
            [field]: value
        };
        setFormData({
            ...formData,
            fields: newFields
        });
    };

    const handleEdit = (service) => {
        setEditingService(service);
        setFormData({
            name: service.name,
            is_active: service.is_active,
            fields: service.fields && service.fields.length > 0 ? service.fields : [
                {
                    name: '',
                    label: '',
                    required: false,
                    placeholder: '',
                    encrypt: false,
                }
            ]
        });
        setShowForm(true);
    };

    const handleCancel = () => {
        setShowForm(false);
        setEditingService(null);
        setFormData({
            name: '',
            is_active: true,
            fields: [
                {
                    name: '',
                    label: '',
                    required: false,
                    placeholder: '',
                    encrypt: false,
                }
            ]
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        // Validate form
        if (!formData.name.trim()) {
            Swal.fire({
                title: 'Error!',
                text: 'Service name is required.',
                icon: 'error',
            });
            return;
        }

        if (!formData.fields || formData.fields.length === 0) {
            Swal.fire({
                title: 'Error!',
                text: 'At least one field is required.',
                icon: 'error',
            });
            return;
        }

        // Validate fields
        for (let i = 0; i < formData.fields.length; i++) {
            const field = formData.fields[i];
            if (!field.name.trim() || !field.label.trim()) {
                Swal.fire({
                    title: 'Error!',
                    text: `Field ${i + 1}: Name and Label are required.`,
                    icon: 'error',
                });
                return;
            }
        }

        try {
            const url = editingService
                ? route('otp.services.admin.update', { id: editingService.id })
                : route('otp.services.admin.store');
            const method = editingService ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData),
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    title: 'Success!',
                    text: result.message,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
                handleCancel();
                loadServices();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: result.message || 'An error occurred.',
                    icon: 'error',
                });
            }
        } catch (error) {
            console.error('Error saving service:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while saving the service.',
                icon: 'error',
            });
        }
    };

    const handleDelete = async (service) => {
        Swal.fire({
            title: 'Are you sure?',
            text: `This will permanently delete the "${service.name}" OTP service. This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'No, cancel!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await fetch(route('otp.services.admin.destroy', { id: service.id }), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                    });

                    const result = await response.json();

                    if (result.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: result.message,
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        loadServices();
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: result.message || 'An error occurred while deleting the service.',
                            icon: 'error',
                        });
                    }
                } catch (error) {
                    console.error('Error deleting service:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while deleting the service.',
                        icon: 'error',
                    });
                }
            }
        });
    };

    const formatServiceName = (name) => {
        if (!name) return '';
        return name.charAt(0).toUpperCase() + name.slice(1);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">OTP Services Management</h2>}
        >
            <Head title="OTP Services Management" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-lg font-medium">OTP Services</h3>
                                {!showForm && (
                                    <PrimaryButton onClick={() => setShowForm(true)}>
                                        Add New Service
                                    </PrimaryButton>
                                )}
                            </div>

                            {showForm && (
                                <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                                    <h4 className="text-md font-medium mb-4">
                                        {editingService ? 'Edit Service' : 'Add New Service'}
                                    </h4>
                                    <form onSubmit={handleSubmit}>
                                        <div className="space-y-4">
                                            <div>
                                                <InputLabel htmlFor="name" value="Service Name *" />
                                                <TextInput
                                                    id="name"
                                                    type="text"
                                                    className="mt-1 block w-full"
                                                    value={formData.name}
                                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                                    placeholder="e.g., unimatrix, twilio"
                                                    required
                                                />
                                                <p className="mt-1 text-sm text-gray-500">
                                                    Unique identifier for the service (lowercase, no spaces)
                                                </p>
                                            </div>

                                            <div>
                                                <label className="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={formData.is_active}
                                                        onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                                                        className="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    />
                                                    <span className="ml-2 text-sm text-gray-700">Active (visible to users)</span>
                                                </label>
                                            </div>

                                            <div>
                                                <div className="flex justify-between items-center mb-2">
                                                    <InputLabel value="Fields *" />
                                                    <SecondaryButton type="button" onClick={handleAddField}>
                                                        Add Field
                                                    </SecondaryButton>
                                                </div>
                                                {formData.fields.map((field, index) => (
                                                    <div key={index} className="mb-4 p-4 border border-gray-200 rounded-lg">
                                                        <div className="flex justify-between items-center mb-2">
                                                            <span className="text-sm font-medium">Field {index + 1}</span>
                                                            {formData.fields.length > 1 && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleRemoveField(index)}
                                                                    className="text-red-600 hover:text-red-800 text-sm"
                                                                >
                                                                    Remove
                                                                </button>
                                                            )}
                                                        </div>
                                                        <div className="grid grid-cols-2 gap-4">
                                                            <div>
                                                                <InputLabel htmlFor={`field_name_${index}`} value="Field Name *" />
                                                                <TextInput
                                                                    id={`field_name_${index}`}
                                                                    type="text"
                                                                    className="mt-1 block w-full"
                                                                    value={field.name}
                                                                    onChange={(e) => handleFieldChange(index, 'name', e.target.value)}
                                                                    placeholder="e.g., access_key"
                                                                    required
                                                                />
                                                            </div>
                                                            <div>
                                                                <InputLabel htmlFor={`field_label_${index}`} value="Field Label *" />
                                                                <TextInput
                                                                    id={`field_label_${index}`}
                                                                    type="text"
                                                                    className="mt-1 block w-full"
                                                                    value={field.label}
                                                                    onChange={(e) => handleFieldChange(index, 'label', e.target.value)}
                                                                    placeholder="e.g., Access Key"
                                                                    required
                                                                />
                                                            </div>
                                                            <div>
                                                                <InputLabel htmlFor={`field_placeholder_${index}`} value="Placeholder" />
                                                                <TextInput
                                                                    id={`field_placeholder_${index}`}
                                                                    type="text"
                                                                    className="mt-1 block w-full"
                                                                    value={field.placeholder || ''}
                                                                    onChange={(e) => handleFieldChange(index, 'placeholder', e.target.value)}
                                                                    placeholder="Optional placeholder text"
                                                                />
                                                            </div>
                                                            <div className="space-y-2">
                                                                <label className="flex items-center">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={field.required || false}
                                                                        onChange={(e) => handleFieldChange(index, 'required', e.target.checked)}
                                                                        className="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                    />
                                                                    <span className="ml-2 text-sm text-gray-700">Required</span>
                                                                </label>
                                                                <label className="flex items-center">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={field.encrypt || false}
                                                                        onChange={(e) => handleFieldChange(index, 'encrypt', e.target.checked)}
                                                                        className="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                    />
                                                                    <span className="ml-2 text-sm text-gray-700">Encrypt (sensitive data)</span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>

                                            <div className="flex justify-end space-x-3">
                                                <SecondaryButton type="button" onClick={handleCancel}>
                                                    Cancel
                                                </SecondaryButton>
                                                <PrimaryButton type="submit">
                                                    {editingService ? 'Update Service' : 'Create Service'}
                                                </PrimaryButton>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            )}

                            {loading ? (
                                <div className="text-center py-8">
                                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                                    <p className="mt-2 text-gray-600">Loading services...</p>
                                </div>
                            ) : services.length === 0 ? (
                                <div className="text-center py-8 text-gray-500">
                                    <p>No OTP services found.</p>
                                    <p className="mt-2 text-sm">Click "Add New Service" to create one.</p>
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
                                                    Status
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {services.map((service) => (
                                                <tr key={service.id}>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {formatServiceName(service.name)}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <div className="text-sm text-gray-500">
                                                            {service.fields && service.fields.length > 0 ? (
                                                                <div className="flex flex-wrap gap-1">
                                                                    {service.fields.map((field, idx) => (
                                                                        <span
                                                                            key={idx}
                                                                            className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800"
                                                                        >
                                                                            {field.label}
                                                                        </span>
                                                                    ))}
                                                                </div>
                                                            ) : (
                                                                <span className="text-gray-400">No fields</span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                            service.is_active
                                                                ? 'bg-green-100 text-green-800'
                                                                : 'bg-gray-100 text-gray-800'
                                                        }`}>
                                                            {service.is_active ? 'Active' : 'Inactive'}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button
                                                            onClick={() => handleEdit(service)}
                                                            className="text-blue-600 hover:text-blue-900 mr-4"
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(service)}
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

