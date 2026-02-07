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
    const [activeTab, setActiveTab] = useState('unimatrix');
    const [loading, setLoading] = useState(true);

    const { data, setData, errors, processing, recentlySuccessful, reset } =
        useForm({
            access_key: '',
            endpoint_url: '',
        });

    // Load existing OTP service credentials on component mount and when activeTab changes
    useEffect(() => {
        loadExistingOtpServices();
    }, [activeTab]);

    const loadExistingOtpServices = async () => {
        try {
            const response = await fetch(route('otp.service.credentials.byService', { serviceName: activeTab }), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                // Update form data with existing OTP service credentials
                const credentials = result.data;
                setData({
                    access_key: credentials.access_key || '',
                    endpoint_url: credentials.endpoint_url || '',
                });
            } else {
                // No existing credentials found, reset form
                setData({
                    access_key: '',
                    endpoint_url: '',
                });
            }
        } catch (error) {
            console.error('Error loading existing OTP service credentials:', error);
        } finally {
            setLoading(false);
        }
    };

    const submit = (e) => {
        e.preventDefault();

        // Send data to Laravel backend
        fetch(route('otp.service.credentials.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                service_name: activeTab,
                access_key: data.access_key,
                endpoint_url: data.endpoint_url,
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

    const deleteAllOtpServices = async () => {
        if (!confirm('Are you sure you want to delete all OTP service credentials? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(route('otp.service.credentials.destroyAll'), {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success) {
                // Clear form data
                setData({
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

    const otpProviders = [
        { key: 'unimatrix', name: 'UniMatrix', icon: '📱' },
    ];

    const renderOtpFields = () => {
        switch (activeTab) {
            case 'unimatrix':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="access_key" value="Access Key" />
                            <TextInput
                                id="access_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.access_key}
                                onChange={(e) => setData('access_key', e.target.value)}
                                placeholder="Enter your UniMatrix Access Key"
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={errors.access_key} />
                            <p className="mt-1 text-sm text-gray-500">
                                Your UniMatrix Access Key for authentication. Keep this secure and never share it publicly.
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
                                placeholder="https://api.unimatrix.com/v1/otp"
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={errors.endpoint_url} />
                            <p className="mt-1 text-sm text-gray-500">
                                The UniMatrix API endpoint URL for OTP services.
                            </p>
                        </div>

                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <h4 className="font-medium text-blue-900 mb-2">UniMatrix Setup Guide:</h4>
                            <ul className="text-sm text-blue-800 list-disc list-inside space-y-1">
                                <li>Sign up for a UniMatrix account</li>
                                <li>Navigate to your UniMatrix dashboard</li>
                                <li>Find your Access Key in the API settings</li>
                                <li>Copy your API endpoint URL</li>
                                <li>Enter your credentials above to enable OTP services</li>
                            </ul>
                        </div>
                    </div>
                );

            default:
                return null;
        }
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    OTP Services Management
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Configure your OTP (One-Time Password) service providers for SMS verification. These credentials will be used to send verification codes to users.
                </p>
            </header>

            {/* Tab Navigation */}
            <div className="mt-6">
                <div className="border-b border-gray-200">
                    <nav className="-mb-px flex space-x-8">
                        {otpProviders.map((provider) => (
                            <button
                                key={provider.key}
                                onClick={() => setActiveTab(provider.key)}
                                className={`${activeTab === provider.key
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    } whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm flex items-center space-x-2`}
                            >
                                <span>{provider.icon}</span>
                                <span>{provider.name}</span>
                            </button>
                        ))}
                    </nav>
                </div>
            </div>

            {/* OTP Service Configuration Form */}
            <form onSubmit={submit} className="mt-6">
                <div className="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 className="text-md font-medium text-gray-900 mb-4">
                        {otpProviders.find(p => p.key === activeTab)?.name} Configuration
                    </h3>

                    {renderOtpFields()}
                </div>

                <div className="flex items-center gap-4 mt-6">
                    <PrimaryButton disabled={processing}>
                        Save {otpProviders.find(p => p.key === activeTab)?.name} Credentials
                    </PrimaryButton>

                    <button
                        type="button"
                        onClick={deleteAllOtpServices}
                        className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        disabled={processing}
                    >
                        Clear All OTP Services
                    </button>

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
            </form>
        </section>
    );
}

