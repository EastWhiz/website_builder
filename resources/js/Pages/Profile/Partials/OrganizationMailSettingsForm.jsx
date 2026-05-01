import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
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

    const [showGmailHelp, setShowGmailHelp] = useState(false);

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
            <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="text-lg font-medium text-gray-900">Organization email settings</h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Configure SMTP for your organization&apos;s email settings so invitations can be sent out.{' '}
                        <br />
                        For Gmail, use an app password and typically SMTP host{' '}
                        <code className="text-xs bg-gray-100 px-1 rounded">smtp.gmail.com</code>, port{' '}
                        <code className="text-xs bg-gray-100 px-1 rounded">587</code>, and TLS.
                    </p>
                </div>
                <PrimaryButton
                    type="button"
                    className="w-full shrink-0 self-stretch sm:w-auto sm:self-start !bg-indigo-600 !px-5 !py-3 !text-sm !font-semibold !normal-case !tracking-normal shadow-md hover:!bg-indigo-700 focus:!bg-indigo-700 focus:!ring-indigo-400 active:!bg-indigo-800"
                    onClick={() => setShowGmailHelp(true)}
                >
                    How to use Gmail
                </PrimaryButton>
            </header>

            <Modal show={showGmailHelp} onClose={() => setShowGmailHelp(false)} maxWidth="2xl">
                <div className="max-h-[min(32rem,85vh)] overflow-y-auto p-6">
                    <h3 className="text-lg font-medium text-gray-900">Using Gmail with SMTP</h3>
                    <p className="mt-2 text-sm text-gray-600">
                        Google does not allow regular account passwords for SMTP in most cases. Turn on{' '}
                        <strong>2-Step Verification</strong>, then create an <strong>App password</strong> and paste it
                        into the App password field below.
                    </p>

                    <div className="mt-6 space-y-6">
                        <div>
                            <h4 className="text-base font-semibold text-gray-900">1. Turn on 2-Step Verification</h4>
                            <ol className="mt-2 list-decimal space-y-2 pl-5 text-sm text-gray-700">
                                <li>
                                    Open your{' '}
                                    <a
                                        href="https://myaccount.google.com/security"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="font-medium text-indigo-600 underline hover:text-indigo-800"
                                    >
                                        Google Account - Security
                                    </a>{' '}
                                    page (while signed in as the Gmail address you will use for SMTP).
                                </li>
                                <li>
                                    Under <strong>How you sign in to Google</strong>, select{' '}
                                    <strong>2-Step Verification</strong> and follow the steps to enable it (phone prompt,
                                    authenticator app, etc.).
                                </li>
                                <li>Finish the wizard until 2-Step Verification shows as <strong>On</strong>.</li>
                            </ol>
                            <p className="mt-2 text-xs text-gray-500">
                                Official guide:{' '}
                                <a
                                    href="https://support.google.com/accounts/answer/185839"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-indigo-600 underline hover:text-indigo-800"
                                >
                                    https://support.google.com/accounts/answer/185839
                                </a>
                            </p>
                        </div>

                        <div>
                            <h4 className="text-base font-semibold text-gray-900">2. Create an App password</h4>
                            <ol className="mt-2 list-decimal space-y-2 pl-5 text-sm text-gray-700">
                                <li>
                                    Stay in{' '}
                                    <a
                                        href="https://myaccount.google.com/security"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="font-medium text-indigo-600 underline hover:text-indigo-800"
                                    >
                                        Google Account - Security
                                    </a>
                                    . With 2-Step Verification on, open <strong>2-Step Verification</strong> again.
                                </li>
                                <li>
                                    Scroll to <strong>App passwords</strong> (you may need to sign in again). If you do not
                                    see App passwords, your administrator or account type may not allow them; use a
                                    consumer Google account or ask your admin.
                                </li>
                                <li>
                                    For personal Google accounts, you can open App passwords directly here:{' '}
                                    <a
                                        href="https://myaccount.google.com/apppasswords"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="font-medium text-indigo-600 underline hover:text-indigo-800 break-all"
                                    >
                                        https://myaccount.google.com/apppasswords
                                    </a>
                                    .
                                </li>
                                <li>
                                    Create a new app password: choose app <strong>Mail</strong> and device{' '}
                                    <strong>Other</strong> (name it e.g. &quot;Website Builder&quot;), then generate.
                                </li>
                                <li>
                                    Google shows a <strong>16-character password</strong> (often in groups). Copy it and
                                    paste it into <strong>App password</strong> in this form (spaces are optional).
                                </li>
                                <li>
                                    Use your full <strong>Gmail address</strong> as <strong>SMTP username (email)</strong>{' '}
                                    and the same or an allowed alias as <strong>From email</strong> if required by your
                                    workspace.
                                </li>
                            </ol>
                            <p className="mt-2 text-xs text-gray-500">
                                Official guide:{' '}
                                <a
                                    href="https://support.google.com/accounts/answer/185833"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-indigo-600 underline hover:text-indigo-800"
                                >
                                    https://support.google.com/accounts/answer/185833
                                </a>
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end border-t border-gray-200 pt-4">
                        <SecondaryButton type="button" onClick={() => setShowGmailHelp(false)}>
                            Close
                        </SecondaryButton>
                    </div>
                </div>
            </Modal>

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


