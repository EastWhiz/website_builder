import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function PixelFormFields({
    className = '',
}) {
    const user = usePage().props.auth.user;
    const [activeTab, setActiveTab] = useState('facebook');
    const [loading, setLoading] = useState(true);

    const { data, setData, errors, processing, recentlySuccessful, reset } =
        useForm({
            facebook_pixel_url: '',
            second_pixel_url: '',
        });

    // Load existing pixel URLs on component mount
    useEffect(() => {
        loadExistingPixels();
    }, []);

    const loadExistingPixels = async () => {
        try {
            const response = await fetch(route('api.credentials.show'), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                // Update form data with existing pixel URLs
                const credentials = result.data;
                setData({
                    facebook_pixel_url: credentials.facebook_pixel_url || '',
                    second_pixel_url: credentials.second_pixel_url || '',
                });
            }
        } catch (error) {
            console.error('Error loading existing pixel URLs:', error);
        } finally {
            setLoading(false);
        }
    };

    const submit = (e) => {
        e.preventDefault();

        // Send data to Laravel backend
        fetch(route('api.credentials.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Handle success
                    console.log('Pixel URLs saved successfully');
                } else {
                    // Handle errors
                    console.error('Error saving pixel URLs:', result.errors);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    };

    const deleteAllPixels = async () => {
        if (!confirm('Are you sure you want to delete all pixel URLs? This action cannot be undone.')) {
            return;
        }

        try {
            // We'll only clear the pixel fields, not all credentials
            setData({
                facebook_pixel_url: '',
                second_pixel_url: '',
            });

            // Submit the cleared data
            const response = await fetch(route('api.credentials.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    facebook_pixel_url: '',
                    second_pixel_url: '',
                }),
            });

            const result = await response.json();

            if (result.success) {
                console.log('Pixel URLs cleared successfully');
            } else {
                console.error('Error clearing pixel URLs:', result.message);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    };

    if (loading) {
        return (
            <section className={className}>
                <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span className="ml-2 text-gray-600">Loading pixel configurations...</span>
                </div>
            </section>
        );
    }

    const pixelProviders = [
        { key: 'facebook', name: 'Facebook Pixel', icon: '📘' },
        { key: 'second', name: 'Other Pixel', icon: '📊' },
    ];

    const renderPixelFields = () => {
        switch (activeTab) {
            case 'facebook':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="facebook_pixel_url" value="Facebook Pixel URL" />
                            <TextInput
                                id="facebook_pixel_url"
                                type="url"
                                className="mt-1 block w-full"
                                value={data.facebook_pixel_url}
                                onChange={(e) => setData('facebook_pixel_url', e.target.value)}
                                placeholder="https://conversionpixel.com/fb.php"
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={errors.facebook_pixel_url} />
                            <p className="mt-1 text-sm text-gray-500">
                                Enter your Facebook Pixel tracking URL. This will be used for conversion tracking.
                            </p>
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <h4 className="font-medium text-blue-900 mb-2">Facebook Pixel Setup:</h4>
                            <ul className="text-sm text-blue-800 list-disc list-inside space-y-1">
                                <li>Go to Facebook Events Manager</li>
                                <li>Copy your Pixel ID</li>
                                <li>Use format: https://www.facebook.com/tr?id=YOUR_PIXEL_ID&ev=PageView</li>
                                <li>Replace YOUR_PIXEL_ID with your actual Pixel ID</li>
                            </ul>
                        </div>
                    </div>
                );

            case 'second':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="second_pixel_url" value="Other Pixel URL" />
                            <TextInput
                                id="second_pixel_url"
                                type="url"
                                className="mt-1 block w-full"
                                value={data.second_pixel_url}
                                onChange={(e) => setData('second_pixel_url', e.target.value)}
                                placeholder="http://plz.hold1sec.com/postback"
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={errors.second_pixel_url} />
                            <p className="mt-1 text-sm text-gray-500">
                                Enter your secondary pixel tracking URL (Google Analytics, TikTok, etc.).
                            </p>
                        </div>
                        <div className="bg-green-50 border border-green-200 rounded-md p-4">
                            <h4 className="font-medium text-green-900 mb-2">Common Pixel Providers:</h4>
                            <ul className="text-sm text-green-800 list-disc list-inside space-y-1">
                                {/* <li><strong>Google Analytics:</strong> gtag or analytics.js URLs</li>
                                <li><strong>TikTok Pixel:</strong> TikTok Events API endpoint</li>
                                <li><strong>Twitter:</strong> Twitter conversion tracking</li>
                                <li><strong>LinkedIn:</strong> LinkedIn Insight Tag</li> */}
                                <li><strong>Custom:</strong> Any third-party tracking service</li>
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
                    Pixel Management
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Configure your tracking pixels for conversion tracking and analytics. These URLs will be used dynamically in your forms and landing pages.
                </p>
            </header>

            {/* Tab Navigation */}
            <div className="mt-6">
                <div className="border-b border-gray-200">
                    <nav className="-mb-px flex space-x-8">
                        {pixelProviders.map((provider) => (
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

            {/* Pixel Configuration Form */}
            <form onSubmit={submit} className="mt-6">
                <div className="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 className="text-md font-medium text-gray-900 mb-4">
                        {pixelProviders.find(p => p.key === activeTab)?.name} Configuration
                    </h3>

                    {renderPixelFields()}
                </div>

                <div className="flex items-center gap-4 mt-6">
                    <PrimaryButton disabled={processing}>
                        Save {pixelProviders.find(p => p.key === activeTab)?.name} URL
                    </PrimaryButton>

                    <button
                        type="button"
                        onClick={deleteAllPixels}
                        className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        disabled={processing}
                    >
                        Clear All Pixels
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
