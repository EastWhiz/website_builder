import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import Swal from 'sweetalert2';

export default function OrganizationMailSettingsForm({
    organizationMailSettings,
    className = '',
}) {
    const {
        smtp_host = 'smtp.gmail.com',
        smtp_port = 587,
        smtp_encryption = 'tls',
        smtp_username = '',
        smtp_password = '',
        mail_from_address = '',
        mail_from_name = '',
        smtp_password_set = false,
    } = organizationMailSettings || {};

    const { data, setData, put, errors, processing, recentlySuccessful } = useForm({
        smtp_host,
        smtp_port: smtp_port ?? 587,
        smtp_encryption,
        smtp_username,
        smtp_password,
        mail_from_address,
        mail_from_name,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('profile.organization-mail-settings.update'), {
            onSuccess: () => {
                if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                    Swal.fire('Success!', 'Organization email settings saved.', 'success');
                }
            },
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">Organization email settings</h2>
                <p className="mt-1 text-sm text-gray-600">
                    Configure SMTP for your current organization so invitations and other outbound mail use this
                    account instead of the global application defaults. For Gmail, use an app password and typically
                    SMTP host <code className="text-xs bg-gray-100 px-1 rounded">smtp.gmail.com</code>, port{' '}
                    <code className="text-xs bg-gray-100 px-1 rounded">587</code>, and TLS.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div className="grid gap-6 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="org_smtp_host" value="SMTP host" />
                        <TextInput
                            id="org_smtp_host"
                            type="text"
                            className="mt-1 block w-full"
                            value={data.smtp_host}
                            onChange={(e) => setData('smtp_host', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError className="mt-2" message={errors.smtp_host} />
                    </div>
                    <div>
                        <InputLabel htmlFor="org_smtp_port" value="SMTP port" />
                        <TextInput
                            id="org_smtp_port"
                            type="number"
                            min={1}
                            max={65535}
                            className="mt-1 block w-full"
                            value={data.smtp_port}
                            onChange={(e) => setData('smtp_port', e.target.value === '' ? '' : parseInt(e.target.value, 10))}
                            autoComplete="off"
                        />
                        <InputError className="mt-2" message={errors.smtp_port} />
                    </div>
                    <div>
                        <InputLabel htmlFor="org_smtp_encryption" value="Encryption" />
                        <select
                            id="org_smtp_encryption"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.smtp_encryption}
                            onChange={(e) => setData('smtp_encryption', e.target.value)}
                        >
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">None</option>
                        </select>
                        <InputError className="mt-2" message={errors.smtp_encryption} />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="org_smtp_username" value="SMTP username (email)" />
                    <TextInput
                        id="org_smtp_username"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.smtp_username}
                        onChange={(e) => setData('smtp_username', e.target.value)}
                        autoComplete="off"
                    />
                    <InputError className="mt-2" message={errors.smtp_username} />
                </div>

                <div>
                    <InputLabel htmlFor="org_smtp_password" value="App password" />
                    <TextInput
                        id="org_smtp_password"
                        type="password"
                        className="mt-1 block w-full"
                        value={data.smtp_password}
                        onChange={(e) => setData('smtp_password', e.target.value)}
                        autoComplete="new-password"
                    />
                    {smtp_password_set ? (
                        <p className="mt-1 text-xs text-gray-500">
                            A password is already stored. Enter the app password again to save changes (required each
                            time you update these settings).
                        </p>
                    ) : null}
                    <InputError className="mt-2" message={errors.smtp_password} />
                </div>

                <div>
                    <InputLabel htmlFor="org_mail_from_address" value="From email" />
                    <TextInput
                        id="org_mail_from_address"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.mail_from_address}
                        onChange={(e) => setData('mail_from_address', e.target.value)}
                        autoComplete="off"
                    />
                    <InputError className="mt-2" message={errors.mail_from_address} />
                </div>

                <div>
                    <InputLabel htmlFor="org_mail_from_name" value="From name (optional)" />
                    <TextInput
                        id="org_mail_from_name"
                        type="text"
                        className="mt-1 block w-full"
                        value={data.mail_from_name}
                        onChange={(e) => setData('mail_from_name', e.target.value)}
                        autoComplete="off"
                    />
                    <InputError className="mt-2" message={errors.mail_from_name} />
                </div>

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>
                        {processing ? (
                            <>
                                <svg
                                    className="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle
                                        className="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        strokeWidth="4"
                                    />
                                    <path
                                        className="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                Saving...
                            </>
                        ) : (
                            'Save organization email settings'
                        )}
                    </PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out duration-200"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="transition ease-in-out duration-200"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm font-medium text-green-600">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
