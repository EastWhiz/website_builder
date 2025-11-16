import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function ApiFormFields({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;
    const [activeTab, setActiveTab] = useState('aweber');
    const [loading, setLoading] = useState(true);

    const { data, setData, patch, errors, processing, recentlySuccessful, reset } =
        useForm({
            aweber_client_id: '',
            aweber_client_secret: '',
            aweber_account_id: '',
            aweber_list_id: '',
            electra_affid: '',
            electra_api_key: '',
            dark_username: '',
            dark_password: '',
            dark_api_key: '',
            dark_ai: '',
            dark_ci: '',
            dark_gi: '',
            elps_username: '',
            elps_password: '',
            elps_api_key: '',
            elps_ai: '',
            elps_ci: '',
            elps_gi: '',
            meeseeks_api_key: '',
            novelix_api_key: '',
            novelix_affid: '',
            tigloo_username: '',
            tigloo_password: '',
            tigloo_api_key: '',
            tigloo_ai: '',
            tigloo_ci: '',
            tigloo_gi: '',
            koi_api_key: '',
            pastile_username: '',
            pastile_password: '',
            pastile_api_key: '',
            pastile_ai: '',
            pastile_ci: '',
            pastile_gi: '',
        });

    // Load existing credentials on component mount
    useEffect(() => {
        loadExistingCredentials();
    }, []);

    const loadExistingCredentials = async () => {
        try {
            const response = await fetch(route('api.credentials.show'), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success && result.data) {
                // Update form data with existing credentials
                const credentials = result.data;
                setData({
                    aweber_client_id: credentials.aweber_client_id || '',
                    aweber_client_secret: credentials.aweber_client_secret || '',
                    aweber_account_id: credentials.aweber_account_id || '',
                    aweber_list_id: credentials.aweber_list_id || '',
                    electra_affid: credentials.electra_affid || '',
                    electra_api_key: credentials.electra_api_key || '',
                    dark_username: credentials.dark_username || '',
                    dark_password: credentials.dark_password || '',
                    dark_api_key: credentials.dark_api_key || '',
                    dark_ai: credentials.dark_ai || '',
                    dark_ci: credentials.dark_ci || '',
                    dark_gi: credentials.dark_gi || '',
                    elps_username: credentials.elps_username || '',
                    elps_password: credentials.elps_password || '',
                    elps_api_key: credentials.elps_api_key || '',
                    elps_ai: credentials.elps_ai || '',
                    elps_ci: credentials.elps_ci || '',
                    elps_gi: credentials.elps_gi || '',
                    meeseeks_api_key: credentials.meeseeks_api_key || '',
                    novelix_api_key: credentials.novelix_api_key || '',
                    novelix_affid: credentials.novelix_affid || '',
                    tigloo_username: credentials.tigloo_username || '',
                    tigloo_password: credentials.tigloo_password || '',
                    tigloo_api_key: credentials.tigloo_api_key || '',
                    tigloo_ai: credentials.tigloo_ai || '',
                    tigloo_ci: credentials.tigloo_ci || '',
                    tigloo_gi: credentials.tigloo_gi || '',
                    koi_api_key: credentials.koi_api_key || '',
                    pastile_username: credentials.pastile_username || '',
                    pastile_password: credentials.pastile_password || '',
                    pastile_api_key: credentials.pastile_api_key || '',
                    pastile_ai: credentials.pastile_ai || '',
                    pastile_ci: credentials.pastile_ci || '',
                    pastile_gi: credentials.pastile_gi || '',
                });
            }
        } catch (error) {
            console.error('Error loading existing credentials:', error);
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
            body: JSON.stringify({
                ...data,
                provider: activeTab  // Send the current active tab (provider name)
            }),
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // console.log('API credentials saved successfully');
                    Swal.fire({
                        title: 'Success!',
                        text: result.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    // console.error('Error saving API credentials:', result.errors);
                    Swal.fire({
                        title: 'Error!',
                        text: result.message,
                        icon: 'error',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    };

    const deleteAllCredentials = async () => {

        Swal.fire({
            title: 'Are you sure?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete all!',
            cancelButtonText: 'No, cancel!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await fetch(route('api.credentials.destroy'), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Reset all form data
                        reset();
                        // console.log('All API credentials deleted successfully');
                        Swal.fire({
                            title: 'Success!',
                            text: result.message,
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        // console.error('Error deleting credentials:', result.message);
                        Swal.fire({
                            title: 'Error!',
                            text: result.message,
                            icon: 'error',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            }
        });
    };

    if (loading) {
        return (
            <section className={className}>
                <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span className="ml-2 text-gray-600">Loading API credentials...</span>
                </div>
            </section>
        );
    }

    const apiProviders = [
        { key: 'aweber', name: 'AWeber', icon: '📧' },
        { key: 'dark', name: 'Connecto', icon: '🌑' },
        { key: 'electra', name: 'Electra', icon: '⚡' },
        { key: 'elps', name: 'ELPIS', icon: '🔧' },
        { key: 'meeseeks', name: 'MeeseeksMedia', icon: '👀' },
        { key: 'novelix', name: 'Novelix', icon: '📚' },
        { key: 'tigloo', name: 'Online Partners ED', icon: '🐅' },
        { key: 'koi', name: 'Koi', icon: '🐟' },
        { key: 'pastile', name: 'Pastile', icon: '💊' },
    ];

    const renderApiFields = () => {
        switch (activeTab) {
            case 'aweber':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="aweber_client_id" value="Client ID" />
                            <TextInput
                                id="aweber_client_id"
                                className="mt-1 block w-full"
                                value={data.aweber_client_id}
                                onChange={(e) => setData('aweber_client_id', e.target.value)}
                                placeholder="lvrj2RItD1E5CE5YGUyq6akFhehKrvzC"
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={errors.aweber_client_id} />
                        </div>
                        <div>
                            <InputLabel htmlFor="aweber_client_secret" value="Client Secret" />
                            <TextInput
                                id="aweber_client_secret"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.aweber_client_secret}
                                onChange={(e) => setData('aweber_client_secret', e.target.value)}
                                placeholder="aJ5ji1uZKkCFpGoeEeuNPRPMGDTGLf3y"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.aweber_client_secret} />
                        </div>
                        <div>
                            <InputLabel htmlFor="aweber_account_id" value="Account ID" />
                            <TextInput
                                id="aweber_account_id"
                                className="mt-1 block w-full"
                                value={data.aweber_account_id}
                                onChange={(e) => setData('aweber_account_id', e.target.value)}
                                placeholder="2342136"
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={errors.aweber_account_id} />
                        </div>
                        <div>
                            <InputLabel htmlFor="aweber_list_id" value="List ID" />
                            <TextInput
                                id="aweber_list_id"
                                className="mt-1 block w-full"
                                value={data.aweber_list_id}
                                onChange={(e) => setData('aweber_list_id', e.target.value)}
                                placeholder="6858148"
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={errors.aweber_list_id} />
                        </div>
                    </div>
                );

            case 'dark':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="dark_username" value="Trackbox Username" />
                            <TextInput
                                id="dark_username"
                                className="mt-1 block w-full"
                                value={data.dark_username}
                                onChange={(e) => setData('dark_username', e.target.value)}
                                placeholder="cfff"
                                autoComplete="username"
                            />
                            <InputError className="mt-2" message={errors.dark_username} />
                        </div>
                        <div>
                            <InputLabel htmlFor="dark_password" value="Trackbox Password" />
                            <TextInput
                                id="dark_password"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.dark_password}
                                onChange={(e) => setData('dark_password', e.target.value)}
                                placeholder="1YAnplgj!"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.dark_password} />
                        </div>
                        <div>
                            <InputLabel htmlFor="dark_api_key" value="API Key" />
                            <TextInput
                                id="dark_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.dark_api_key}
                                onChange={(e) => setData('dark_api_key', e.target.value)}
                                placeholder="2643889w34df345676ssdas323tgc738"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.dark_api_key} />
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <InputLabel htmlFor="dark_ai" value="AI Parameter" />
                                <TextInput
                                    id="dark_ai"
                                    className="mt-1 block w-full"
                                    value={data.dark_ai}
                                    onChange={(e) => setData('dark_ai', e.target.value)}
                                    placeholder="2958198"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.dark_ai} />
                            </div>
                            <div>
                                <InputLabel htmlFor="dark_ci" value="CI Parameter" />
                                <TextInput
                                    id="dark_ci"
                                    className="mt-1 block w-full"
                                    value={data.dark_ci}
                                    onChange={(e) => setData('dark_ci', e.target.value)}
                                    placeholder="1"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.dark_ci} />
                            </div>
                            <div>
                                <InputLabel htmlFor="dark_gi" value="GI Parameter" />
                                <TextInput
                                    id="dark_gi"
                                    className="mt-1 block w-full"
                                    value={data.dark_gi}
                                    onChange={(e) => setData('dark_gi', e.target.value)}
                                    placeholder="173"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.dark_gi} />
                            </div>
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p className="text-blue-800 text-sm">
                                <strong>Endpoint:</strong> https://tb.connnecto.com/api/signup/procform
                            </p>
                        </div>
                    </div>
                );

            case 'electra':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="electra_affid" value="Affiliate ID" />
                            <TextInput
                                id="electra_affid"
                                className="mt-1 block w-full"
                                value={data.electra_affid}
                                onChange={(e) => setData('electra_affid', e.target.value)}
                                placeholder="13"
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={errors.electra_affid} />
                        </div>
                        <div>
                            <InputLabel htmlFor="electra_api_key" value="API Key (Optional)" />
                            <TextInput
                                id="electra_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.electra_api_key}
                                onChange={(e) => setData('electra_api_key', e.target.value)}
                                placeholder="Enter API key if available"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.electra_api_key} />
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p className="text-blue-800 text-sm">
                                <strong>Endpoint:</strong> https://lcaapi.net/leads
                            </p>
                        </div>
                    </div>
                );

            case 'elps':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="elps_username" value="Trackbox Username" />
                            <TextInput
                                id="elps_username"
                                className="mt-1 block w-full"
                                value={data.elps_username}
                                onChange={(e) => setData('elps_username', e.target.value)}
                                placeholder="cfff"
                                autoComplete="username"
                            />
                            <InputError className="mt-2" message={errors.elps_username} />
                        </div>
                        <div>
                            <InputLabel htmlFor="elps_password" value="Trackbox Password" />
                            <TextInput
                                id="elps_password"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.elps_password}
                                onChange={(e) => setData('elps_password', e.target.value)}
                                placeholder="1YAnplgj!"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.elps_password} />
                        </div>
                        <div>
                            <InputLabel htmlFor="elps_api_key" value="API Key" />
                            <TextInput
                                id="elps_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.elps_api_key}
                                onChange={(e) => setData('elps_api_key', e.target.value)}
                                placeholder="2643889w34df345676ssdas323tgc738"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.elps_api_key} />
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <InputLabel htmlFor="elps_ai" value="AI Parameter" />
                                <TextInput
                                    id="elps_ai"
                                    className="mt-1 block w-full"
                                    value={data.elps_ai}
                                    onChange={(e) => setData('elps_ai', e.target.value)}
                                    placeholder="2958034"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.elps_ai} />
                            </div>
                            <div>
                                <InputLabel htmlFor="elps_ci" value="CI Parameter" />
                                <TextInput
                                    id="elps_ci"
                                    className="mt-1 block w-full"
                                    value={data.elps_ci}
                                    onChange={(e) => setData('elps_ci', e.target.value)}
                                    placeholder="1"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.elps_ci} />
                            </div>
                            <div>
                                <InputLabel htmlFor="elps_gi" value="GI Parameter" />
                                <TextInput
                                    id="elps_gi"
                                    className="mt-1 block w-full"
                                    value={data.elps_gi}
                                    onChange={(e) => setData('elps_gi', e.target.value)}
                                    placeholder="17"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.elps_gi} />
                            </div>
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p className="text-blue-800 text-sm">
                                <strong>Endpoint:</strong> https://ep.elpistrack.io/api/signup/procform
                            </p>
                        </div>
                    </div>
                );

            case 'meeseeks':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="meeseeks_api_key" value="API Key" />
                            <TextInput
                                id="meeseeks_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.meeseeks_api_key}
                                onChange={(e) => setData('meeseeks_api_key', e.target.value)}
                                placeholder="BA31CB52-2023-0F5E-26F1-17258C7B5CAA"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.meeseeks_api_key} />
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p className="text-blue-800 text-sm">
                                <strong>Endpoint:</strong> https://mskmd-api.com/api/v2/leads
                            </p>
                        </div>
                    </div>
                );

            case 'novelix':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="novelix_affid" value="Affiliate ID" />
                            <TextInput
                                id="novelix_affid"
                                className="mt-1 block w-full"
                                value={data.novelix_affid}
                                onChange={(e) => setData('novelix_affid', e.target.value)}
                                placeholder="16"
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={errors.novelix_affid} />
                        </div>
                        <div>
                            <InputLabel htmlFor="novelix_api_key" value="API Key (Optional)" />
                            <TextInput
                                id="novelix_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.novelix_api_key}
                                onChange={(e) => setData('novelix_api_key', e.target.value)}
                                placeholder="bANwHGbj4mxQFUdefk1i"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.novelix_api_key} />
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p className="text-blue-800 text-sm">
                                <strong>Endpoint:</strong> https://nexlapi.net/leads
                            </p>
                        </div>
                    </div>
                );

            case 'tigloo':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="tigloo_username" value="Trackbox Username" />
                            <TextInput
                                id="tigloo_username"
                                className="mt-1 block w-full"
                                value={data.tigloo_username}
                                onChange={(e) => setData('tigloo_username', e.target.value)}
                                placeholder="SECH"
                                autoComplete="username"
                            />
                            <InputError className="mt-2" message={errors.tigloo_username} />
                        </div>
                        <div>
                            <InputLabel htmlFor="tigloo_password" value="Trackbox Password" />
                            <TextInput
                                id="tigloo_password"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.tigloo_password}
                                onChange={(e) => setData('tigloo_password', e.target.value)}
                                placeholder="Ss1234@"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.tigloo_password} />
                        </div>
                        <div>
                            <InputLabel htmlFor="tigloo_api_key" value="API Key" />
                            <TextInput
                                id="tigloo_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.tigloo_api_key}
                                onChange={(e) => setData('tigloo_api_key', e.target.value)}
                                placeholder="2643889w34df345676ssdas323tgc738"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.tigloo_api_key} />
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <InputLabel htmlFor="tigloo_ai" value="AI Parameter" />
                                <TextInput
                                    id="tigloo_ai"
                                    className="mt-1 block w-full"
                                    value={data.tigloo_ai}
                                    onChange={(e) => setData('tigloo_ai', e.target.value)}
                                    placeholder="2958531"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.tigloo_ai} />
                            </div>
                            <div>
                                <InputLabel htmlFor="tigloo_ci" value="CI Parameter" />
                                <TextInput
                                    id="tigloo_ci"
                                    className="mt-1 block w-full"
                                    value={data.tigloo_ci}
                                    onChange={(e) => setData('tigloo_ci', e.target.value)}
                                    placeholder="821"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.tigloo_ci} />
                            </div>
                            <div>
                                <InputLabel htmlFor="tigloo_gi" value="GI Parameter" />
                                <TextInput
                                    id="tigloo_gi"
                                    className="mt-1 block w-full"
                                    value={data.tigloo_gi}
                                    onChange={(e) => setData('tigloo_gi', e.target.value)}
                                    placeholder="545"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.tigloo_gi} />
                            </div>
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p className="text-blue-800 text-sm">
                                <strong>Endpoint:</strong> https://platform.onlinepartnersed.com/api/signup/procform
                            </p>
                        </div>
                    </div>
                );

            case 'koi':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="koi_api_key" value="API Key" />
                            <TextInput
                                id="koi_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.koi_api_key}
                                onChange={(e) => setData('koi_api_key', e.target.value)}
                                placeholder="D39501B5-4872-3F35-3463-EC6B258BE52A"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.koi_api_key} />
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p className="text-blue-800 text-sm">
                                <strong>Endpoint:</strong> https://hannyaapi.com/api/v2/leads
                            </p>
                            <p className="text-blue-800 text-sm mt-1">
                                <strong>Note:</strong> This API requires IP whitelisting. Make sure your server IP is whitelisted.
                            </p>
                        </div>
                    </div>
                );

            case 'pastile':
                return (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="pastile_username" value="Trackbox Username" />
                            <TextInput
                                id="pastile_username"
                                className="mt-1 block w-full"
                                value={data.pastile_username}
                                onChange={(e) => setData('pastile_username', e.target.value)}
                                placeholder="CFmeeseeks"
                                autoComplete="username"
                            />
                            <InputError className="mt-2" message={errors.pastile_username} />
                        </div>
                        <div>
                            <InputLabel htmlFor="pastile_password" value="Trackbox Password" />
                            <TextInput
                                id="pastile_password"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.pastile_password}
                                onChange={(e) => setData('pastile_password', e.target.value)}
                                placeholder="3OxW)n(8_9"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.pastile_password} />
                        </div>
                        <div>
                            <InputLabel htmlFor="pastile_api_key" value="API Key" />
                            <TextInput
                                id="pastile_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={data.pastile_api_key}
                                onChange={(e) => setData('pastile_api_key', e.target.value)}
                                placeholder="2643889w34df345676ssdas323tgc738"
                                autoComplete="new-password"
                            />
                            <InputError className="mt-2" message={errors.pastile_api_key} />
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <InputLabel htmlFor="pastile_ai" value="AI Parameter" />
                                <TextInput
                                    id="pastile_ai"
                                    className="mt-1 block w-full"
                                    value={data.pastile_ai}
                                    onChange={(e) => setData('pastile_ai', e.target.value)}
                                    placeholder="2958073"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.pastile_ai} />
                            </div>
                            <div>
                                <InputLabel htmlFor="pastile_ci" value="CI Parameter" />
                                <TextInput
                                    id="pastile_ci"
                                    className="mt-1 block w-full"
                                    value={data.pastile_ci}
                                    onChange={(e) => setData('pastile_ci', e.target.value)}
                                    placeholder="1"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.pastile_ci} />
                            </div>
                            <div>
                                <InputLabel htmlFor="pastile_gi" value="GI Parameter" />
                                <TextInput
                                    id="pastile_gi"
                                    className="mt-1 block w-full"
                                    value={data.pastile_gi}
                                    onChange={(e) => setData('pastile_gi', e.target.value)}
                                    placeholder="55"
                                    autoComplete="off"
                                />
                                <InputError className="mt-2" message={errors.pastile_gi} />
                            </div>
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p className="text-blue-800 text-sm">
                                <strong>Endpoint:</strong> https://tb.pastile.net/api/signup/procform
                            </p>
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
                    API Credentials Management
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Configure your API credentials for various service providers. These credentials will be used for form integrations and data management.
                </p>
            </header>

            {/* Tab Navigation */}
            <div className="mt-6">
                <div className="border-b border-gray-200">
                    <nav className="-mb-px flex space-x-8 overflow-x-auto">
                        {apiProviders.map((provider) => (
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

            {/* API Configuration Form */}
            <form onSubmit={submit} className="mt-6">
                <div className="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 className="text-md font-medium text-gray-900 mb-4">
                        {apiProviders.find(p => p.key === activeTab)?.name} Configuration
                    </h3>

                    {renderApiFields()}
                </div>

                <div className="flex items-center gap-4 mt-6">
                    <PrimaryButton disabled={processing}>
                        Save {apiProviders.find(p => p.key === activeTab)?.name} Credentials
                    </PrimaryButton>

                    <button
                        type="button"
                        onClick={deleteAllCredentials}
                        className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        disabled={processing}
                    >
                        Delete All Credentials
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
