import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';

export default function OtpServicesFormFields({
    className = '',
}) {
    const user = usePage().props.auth.user;
    const [loading, setLoading] = useState(true);
    const [availableServices, setAvailableServices] = useState([]);
    const [userCredentials, setUserCredentials] = useState([]);
    const [selectedServiceId, setSelectedServiceId] = useState(null);
    const [selectedService, setSelectedService] = useState(null);
    const [showAddForm, setShowAddForm] = useState(false);

    const { data, setData, errors, processing, recentlySuccessful, reset } =
        useForm({
            service_id: null,
            credentials: {},
        });

    // Load available services and user credentials on component mount
    useEffect(() => {
        loadAvailableServices();
        loadUserCredentials();
    }, []);

    const loadAvailableServices = async () => {
        try {
            const response = await fetch(route('otp.services.index'), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                setAvailableServices(result.data);
            }
        } catch (error) {
            console.error('Error loading available OTP services:', error);
        }
    };

    const loadUserCredentials = async () => {
        try {
            const response = await fetch(route('otp.service.credentials.index'), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                setUserCredentials(result.data);
                if (result.data.length > 0 && !selectedServiceId) {
                    // Load first credential
                    loadServiceCredential(result.data[0].service_id);
                }
            }
        } catch (error) {
            console.error('Error loading user OTP service credentials:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadServiceCredential = async (serviceId) => {
        try {
            const response = await fetch(route('otp.service.credentials.byService', { serviceId }), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                setSelectedServiceId(serviceId);
                setSelectedService(result.data.service_fields);
                setData({
                    service_id: serviceId,
                    credentials: result.data.credentials || {},
                });
                setShowAddForm(false);
            } else {
                // No credential exists, load service definition
                loadServiceDefinition(serviceId);
            }
        } catch (error) {
            console.error('Error loading service credential:', error);
        }
    };

    const loadServiceDefinition = async (serviceId) => {
        try {
            const response = await fetch(route('otp.services.show', { id: serviceId }), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                setSelectedServiceId(serviceId);
                setSelectedService(result.data.fields);
                setData({
                    service_id: serviceId,
                    credentials: {},
                });
                setShowAddForm(false);
            }
        } catch (error) {
            console.error('Error loading service definition:', error);
        }
    };

    const handleServiceClick = (serviceId) => {
        loadServiceCredential(serviceId);
    };

    const handleAddNewService = () => {
        setShowAddForm(true);
        setSelectedServiceId(null);
        setSelectedService(null);
        setData({
            service_id: null,
            credentials: {},
        });
    };

    const handleServiceSelect = (serviceId) => {
        const service = availableServices.find(s => s.id === parseInt(serviceId));
        if (service) {
            setSelectedServiceId(service.id);
            setSelectedService(service.fields);
            setData({
                service_id: service.id,
                credentials: {},
            });
        }
    };

    const handleFieldChange = (fieldName, value) => {
        const updatedCredentials = {
            ...data.credentials,
            [fieldName]: value
        };

        setData('credentials', updatedCredentials);
    };

    const submit = (e) => {
        e.preventDefault();

        if (!data.service_id) {
            Swal.fire({
                title: 'Error!',
                text: 'Please select a service.',
                icon: 'error',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        // Send data to Laravel backend
        fetch(route('otp.service.credentials.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                service_id: data.service_id,
                credentials: data.credentials,
            }),
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: result.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    // Reload user credentials
                    loadUserCredentials();
                    setShowAddForm(false);
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: result.message || 'An error occurred while saving credentials.',
                        icon: 'error',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while saving credentials.',
                    icon: 'error',
                    timer: 1500,
                    showConfirmButton: false
                });
            });
    };

    const deleteService = async (serviceId, serviceName) => {
        const credential = userCredentials.find(c => c.service_id === serviceId);
        if (!credential) return;

        Swal.fire({
            title: 'Are you sure?',
            text: `This will permanently delete the "${serviceName}" OTP service. This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'No, cancel!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await fetch(route('otp.service.credentials.destroy', { id: credential.id }), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                    });

                    const result = await response.json();

                    if (result.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: result.message || 'OTP service deleted successfully',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        
                        // Reload user credentials
                        loadUserCredentials();
                        
                        // Clear form if deleted service was active
                        if (selectedServiceId === serviceId) {
                            setSelectedServiceId(null);
                            setSelectedService(null);
                            setData({
                                service_id: null,
                                credentials: {},
                            });
                        }
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: result.message || 'An error occurred while deleting the service.',
                            icon: 'error',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while deleting the service.',
                        icon: 'error',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            }
        });
    };

    const deleteAllOtpServices = async () => {
        Swal.fire({
            title: 'Are you sure?',
            text: 'This will permanently delete all OTP service credentials. This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete all!',
            cancelButtonText: 'No, cancel!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await fetch(route('otp.service.credentials.destroyAll'), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                    });

                    const result = await response.json();

                    if (result.success) {
                        setUserCredentials([]);
                        setSelectedServiceId(null);
                        setSelectedService(null);
                        setShowAddForm(false);
                        setData({
                            service_id: null,
                            credentials: {},
                        });

                        Swal.fire({
                            title: 'Success!',
                            text: result.message || 'All OTP service credentials cleared successfully',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: result.message || 'An error occurred while clearing credentials.',
                            icon: 'error',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while clearing credentials.',
                        icon: 'error',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            }
        });
    };

    if (loading) {
        return (
            <section className={className}>
                <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span className="ml-2 text-gray-600">Loading OTP service configurations...</span>
                </div>
            </section>
        );
    }

    const getServiceIcon = (serviceName) => {
        const icons = {
            'unimatrix': '📱',
            'twilio': '📞',
            'nexmo': '💬',
            'messagebird': '🐦',
        };
        return icons[serviceName?.toLowerCase()] || '🔐';
    };

    const formatServiceName = (name) => {
        if (!name) return '';
        return name.charAt(0).toUpperCase() + name.slice(1);
    };

    const renderDynamicFields = () => {
        if (!selectedService || !Array.isArray(selectedService)) {
            return null;
        }

        return selectedService.map((field) => {
            const fieldValue = data.credentials?.[field.name] || '';

            return (
                <div key={field.name} className="mt-4">
                    <InputLabel 
                        htmlFor={field.name} 
                        value={field.label + (field.required ? ' *' : '')} 
                    />
                    <TextInput
                        id={field.name}
                        type="text"
                        className="mt-1 block w-full"
                        value={fieldValue}
                        onChange={(e) => handleFieldChange(field.name, e.target.value)}
                        placeholder={field.placeholder || ''}
                        required={field.required}
                        autoComplete="off"
                    />
                    <InputError className="mt-2" message={errors[`credentials.${field.name}`]} />
                </div>
            );
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    OTP Services Management
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Configure your OTP (One-Time Password) service providers for SMS verification. You can add multiple services and manage them separately.
                </p>
            </header>

            {/* Services List / Tabs */}
            <div className="mt-6">
                <div className="border-b border-gray-200">
                    <nav className="-mb-px flex space-x-4 flex-wrap">
                        {userCredentials.map((credential) => {
                            const service = availableServices.find(s => s.id === credential.service_id);
                            const serviceName = service?.name || 'Unknown';
                            return (
                                <div key={credential.id} className="flex items-center group">
                                    <button
                                        onClick={() => handleServiceClick(credential.service_id)}
                                        className={`${selectedServiceId === credential.service_id
                                            ? 'border-blue-500 text-blue-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                            } whitespace-nowrap py-2 px-3 border-b-2 font-medium text-sm flex items-center space-x-2`}
                                    >
                                        <span>{getServiceIcon(serviceName)}</span>
                                        <span>{formatServiceName(serviceName)}</span>
                                    </button>
                                    <button
                                        onClick={() => deleteService(credential.service_id, serviceName)}
                                        className="ml-2 text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity"
                                        title={`Delete ${formatServiceName(serviceName)}`}
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            );
                        })}
                        <button
                            onClick={handleAddNewService}
                            className={`${showAddForm
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                } whitespace-nowrap py-2 px-3 border-b-2 font-medium text-sm flex items-center space-x-2`}
                        >
                            <span>➕</span>
                            <span>Add New Service</span>
                        </button>
                    </nav>
                </div>
            </div>

            {/* OTP Service Configuration Form */}
            <form onSubmit={submit} className="mt-6">
                <div className="bg-white border border-gray-200 rounded-lg p-6">
                    {showAddForm ? (
                        <>
                            <h3 className="text-md font-medium text-gray-900 mb-4">
                                Add New OTP Service
                            </h3>
                            <div className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="service_select" value="Select Service" />
                                    <select
                                        id="service_select"
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        value={data.service_id || ''}
                                        onChange={(e) => handleServiceSelect(e.target.value)}
                                    >
                                        <option value="">-- Select a service --</option>
                                        {availableServices.map((service) => {
                                            // Check if user already has this service
                                            const hasService = userCredentials.some(c => c.service_id === service.id);
                                            
                                            return (
                                                <option 
                                                    key={service.id} 
                                                    value={service.id}
                                                    disabled={hasService}
                                                >
                                                    {formatServiceName(service.name)}{hasService ? ' (Already configured)' : ''}
                                                </option>
                                            );
                                        })}
                                    </select>
                                    <InputError className="mt-2" message={errors.service_id} />
                                    {availableServices.length > 0 && availableServices.every(service => 
                                        userCredentials.some(c => c.service_id === service.id)
                                    ) && (
                                        <p className="mt-2 text-sm text-amber-600">
                                            All available OTP services have been configured. To add more services, please contact your administrator.
                                        </p>
                                    )}
                                    {availableServices.length === 0 && (
                                        <p className="mt-2 text-sm text-gray-500">
                                            No OTP services are currently available. Please contact your administrator.
                                        </p>
                                    )}
                                </div>
                                {selectedService && renderDynamicFields()}
                            </div>
                        </>
                    ) : selectedServiceId && selectedService ? (
                        <>
                            <h3 className="text-md font-medium text-gray-900 mb-4">
                                {formatServiceName(availableServices.find(s => s.id === selectedServiceId)?.name)} Configuration
                            </h3>
                            <div className="space-y-4">
                                {renderDynamicFields()}
                            </div>
                        </>
                    ) : (
                        <div className="text-center py-8 text-gray-500">
                            <p>No OTP services configured yet.</p>
                            <p className="mt-2 text-sm">Click "Add New Service" to get started.</p>
                        </div>
                    )}
                </div>

                {(showAddForm || selectedServiceId) && (
                    <div className="flex items-center gap-4 mt-6">
                        <PrimaryButton disabled={processing}>
                            {showAddForm ? 'Add Service' : 'Save Credentials'}
                        </PrimaryButton>

                        {userCredentials.length > 0 && (
                            <button
                                type="button"
                                onClick={deleteAllOtpServices}
                                className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                disabled={processing}
                            >
                                Clear All Services
                            </button>
                        )}

                        <Transition
                            show={recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-gray-600">Saved successfully!</p>
                        </Transition>
                    </div>
                )}
            </form>
        </section>
    );
}
