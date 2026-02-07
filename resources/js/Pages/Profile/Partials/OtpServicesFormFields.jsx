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
    const [services, setServices] = useState([]);
    const [activeServiceId, setActiveServiceId] = useState(null);
    const [showAddForm, setShowAddForm] = useState(false);
    const [newServiceName, setNewServiceName] = useState('');

    const { data, setData, errors, processing, recentlySuccessful, reset } =
        useForm({
            service_name: '',
            access_key: '',
            endpoint_url: '',
        });

    // Load all existing OTP service credentials on component mount
    useEffect(() => {
        loadAllOtpServices();
    }, []);

    // Auto-construct endpoint URL for UniMatrix when access key changes
    useEffect(() => {
        if (data.service_name && data.service_name.toLowerCase() === 'unimatrix' && data.access_key) {
            const constructedUrl = `https://api.unimtx.com/?action=sms.message.send&accessKeyId=${data.access_key}`;
            if (data.endpoint_url !== constructedUrl) {
                setData('endpoint_url', constructedUrl);
            }
        }
    }, [data.access_key, data.service_name]);

    const loadAllOtpServices = async () => {
        try {
            const response = await fetch(route('otp.service.credentials.index'), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                setServices(result.data);
                if (result.data.length > 0 && !activeServiceId) {
                    setActiveServiceId(result.data[0].id);
                    loadServiceData(result.data[0]);
                }
            }
        } catch (error) {
            console.error('Error loading OTP service credentials:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadServiceData = (service) => {
        setData({
            service_name: service.service_name,
            access_key: service.access_key || '',
            endpoint_url: service.endpoint_url || '',
        });
        setActiveServiceId(service.id);
        setShowAddForm(false);
    };

    const handleTabClick = (service) => {
        loadServiceData(service);
    };

    const handleAddNewService = () => {
        setShowAddForm(true);
        setActiveServiceId(null);
        setData({
            service_name: '',
            access_key: '',
            endpoint_url: '',
        });
    };

    const submit = (e) => {
        e.preventDefault();

        if (!data.service_name || data.service_name.trim() === '') {
            Swal.fire({
                title: 'Error!',
                text: 'Please enter a service name.',
                icon: 'error',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        // For UniMatrix, construct the endpoint URL with access key
        let endpointUrl = data.endpoint_url;
        const serviceName = data.service_name.trim().toLowerCase();
        
        if (serviceName === 'unimatrix' && data.access_key) {
            endpointUrl = `https://api.unimtx.com/?action=sms.message.send&accessKeyId=${data.access_key}`;
        }

        // Send data to Laravel backend
        fetch(route('otp.service.credentials.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                service_name: serviceName,
                access_key: data.access_key,
                endpoint_url: endpointUrl,
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
                    // Reload all services
                    loadAllOtpServices();
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
                    const response = await fetch(route('otp.service.credentials.destroy', { id: serviceId }), {
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
                        
                        // Reload all services
                        loadAllOtpServices();
                        
                        // Clear form if deleted service was active
                        if (activeServiceId === serviceId) {
                            setActiveServiceId(null);
                            setData({
                                service_name: '',
                                access_key: '',
                                endpoint_url: '',
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
                        setServices([]);
                        setActiveServiceId(null);
                        setShowAddForm(false);
                        setData({
                            service_name: '',
                            access_key: '',
                            endpoint_url: '',
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
        return icons[serviceName.toLowerCase()] || '🔐';
    };

    const formatServiceName = (name) => {
        return name.charAt(0).toUpperCase() + name.slice(1);
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
                        {services.map((service) => (
                            <div key={service.id} className="flex items-center group">
                                <button
                                    onClick={() => handleTabClick(service)}
                                    className={`${activeServiceId === service.id
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        } whitespace-nowrap py-2 px-3 border-b-2 font-medium text-sm flex items-center space-x-2`}
                                >
                                    <span>{getServiceIcon(service.service_name)}</span>
                                    <span>{formatServiceName(service.service_name)}</span>
                                </button>
                                <button
                                    onClick={() => deleteService(service.id, service.service_name)}
                                    className="ml-2 text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity"
                                    title={`Delete ${formatServiceName(service.service_name)}`}
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        ))}
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
                                    <InputLabel htmlFor="service_name" value="Service Name" />
                                    <TextInput
                                        id="service_name"
                                        type="text"
                                        className="mt-1 block w-full"
                                        value={data.service_name}
                                        onChange={(e) => setData('service_name', e.target.value)}
                                        placeholder="e.g., unimatrix, twilio, nexmo"
                                        autoComplete="off"
                                    />
                                    <InputError className="mt-2" message={errors.service_name} />
                                    <p className="mt-1 text-sm text-gray-500">
                                        Enter a unique name for this OTP service (lowercase, no spaces).
                                    </p>
                                </div>
                                <div>
                                    <InputLabel htmlFor="access_key" value="Access Key" />
                                    <TextInput
                                        id="access_key"
                                        type="text"
                                        className="mt-1 block w-full"
                                        value={data.access_key}
                                        onChange={(e) => setData('access_key', e.target.value)}
                                        placeholder="Enter your Access Key"
                                        autoComplete="off"
                                    />
                                    <InputError className="mt-2" message={errors.access_key} />
                                    <p className="mt-1 text-sm text-gray-500">
                                        Your service Access Key for authentication. Keep this secure and never share it publicly.
                                    </p>
                                </div>
                                <div>
                                    <InputLabel htmlFor="endpoint_url" value="End Point URL" />
                                    <TextInput
                                        id="endpoint_url"
                                        type="url"
                                        className="mt-1 block w-full"
                                        value={data.endpoint_url}
                                        onChange={(e) => setData('endpoint_url', e.target.value)}
                                        placeholder={data.service_name && data.service_name.toLowerCase() === 'unimatrix' 
                                            ? 'https://api.unimtx.com/?action=sms.message.send&accessKeyId=YOUR_ACCESS_KEY'
                                            : 'https://api.example.com/v1/otp'}
                                        autoComplete="off"
                                        disabled={data.service_name && data.service_name.toLowerCase() === 'unimatrix'}
                                    />
                                    <InputError className="mt-2" message={errors.endpoint_url} />
                                    <p className="mt-1 text-sm text-gray-500">
                                        {data.service_name && data.service_name.toLowerCase() === 'unimatrix' 
                                            ? 'For UniMatrix, the endpoint URL will be automatically constructed with your access key.'
                                            : 'The API endpoint URL for OTP services.'}
                                    </p>
                                </div>
                            </div>
                        </>
                    ) : activeServiceId ? (
                        <>
                            <h3 className="text-md font-medium text-gray-900 mb-4">
                                {formatServiceName(data.service_name)} Configuration
                            </h3>
                            <div className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="access_key" value="Access Key" />
                                    <TextInput
                                        id="access_key"
                                        type="text"
                                        className="mt-1 block w-full"
                                        value={data.access_key}
                                        onChange={(e) => setData('access_key', e.target.value)}
                                        placeholder="Enter your Access Key"
                                        autoComplete="off"
                                    />
                                    <InputError className="mt-2" message={errors.access_key} />
                                    <p className="mt-1 text-sm text-gray-500">
                                        Your service Access Key for authentication. Keep this secure and never share it publicly.
                                    </p>
                                </div>
                                <div>
                                    <InputLabel htmlFor="endpoint_url" value="End Point URL" />
                                    <TextInput
                                        id="endpoint_url"
                                        type="url"
                                        className="mt-1 block w-full"
                                        value={data.endpoint_url}
                                        onChange={(e) => setData('endpoint_url', e.target.value)}
                                        placeholder={data.service_name && data.service_name.toLowerCase() === 'unimatrix' 
                                            ? 'https://api.unimtx.com/?action=sms.message.send&accessKeyId=YOUR_ACCESS_KEY'
                                            : 'https://api.example.com/v1/otp'}
                                        autoComplete="off"
                                        disabled={data.service_name && data.service_name.toLowerCase() === 'unimatrix'}
                                    />
                                    <InputError className="mt-2" message={errors.endpoint_url} />
                                    <p className="mt-1 text-sm text-gray-500">
                                        {data.service_name && data.service_name.toLowerCase() === 'unimatrix' 
                                            ? 'For UniMatrix, the endpoint URL will be automatically constructed with your access key.'
                                            : 'The API endpoint URL for OTP services.'}
                                    </p>
                                </div>
                            </div>
                        </>
                    ) : (
                        <div className="text-center py-8 text-gray-500">
                            <p>No OTP services configured yet.</p>
                            <p className="mt-2 text-sm">Click "Add New Service" to get started.</p>
                        </div>
                    )}
                </div>

                {(showAddForm || activeServiceId) && (
                    <div className="flex items-center gap-4 mt-6">
                        <PrimaryButton disabled={processing}>
                            {showAddForm ? 'Add Service' : 'Save Credentials'}
                        </PrimaryButton>

                        {services.length > 0 && (
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
