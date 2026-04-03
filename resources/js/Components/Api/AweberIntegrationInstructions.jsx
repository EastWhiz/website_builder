import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

const OAUTH_CB_PRODUCTION = 'https://crm.diy/oauth/aweber/callback';
const OAUTH_CB_STAGING = 'https://129.212.182.198/oauth/aweber/callback';

function resolveOauthRedirectUrlForCurrentHost() {
    if (typeof window === 'undefined') {
        return OAUTH_CB_PRODUCTION;
    }
    const host = window.location.hostname;
    if (
        host === 'localhost' ||
        host === '127.0.0.1' ||
        host === '129.212.182.198' ||
        host === 'https://phpstack-1554373-6246482.cloudwaysapps.com/'
    ) {
        return OAUTH_CB_STAGING;
    }
    return OAUTH_CB_PRODUCTION;
}

/**
 * Link + modal with AWeber OAuth / list ID setup steps (Profile API instance modals).
 * Renders the modal via a portal to document.body to avoid nested scrolling inside the Add/Edit API modals.
 */
export default function AweberIntegrationInstructions() {
    const [open, setOpen] = useState(false);
    const [oauthRedirectUrl, setOauthRedirectUrl] = useState(OAUTH_CB_PRODUCTION);

    useEffect(() => {
        setOauthRedirectUrl(resolveOauthRedirectUrlForCurrentHost());
    }, []);

    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = prevOverflow;
        };
    }, [open]);

    const modal =
        open &&
        createPortal(
            <div
                className="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="aweber-instructions-title"
            >
                <div
                    className="absolute inset-0 bg-gray-500 bg-opacity-75"
                    onClick={() => setOpen(false)}
                    onWheel={(e) => e.stopPropagation()}
                    role="presentation"
                />
                <div className="relative z-[1] flex min-h-0 max-h-[min(92vh,900px)] w-full max-w-xl flex-col overflow-hidden rounded-lg bg-white p-6 shadow-xl">
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        className="absolute top-3 right-3 z-[2] text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded p-1"
                        aria-label="Close"
                    >
                        <span className="text-2xl leading-none">&times;</span>
                    </button>
                    <h3
                        id="aweber-instructions-title"
                        className="shrink-0 text-lg font-semibold text-gray-900 pr-10 mb-4"
                    >
                        AWeber Integration Setup Instructions
                    </h3>
                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain pr-2 text-sm text-gray-700 [-webkit-overflow-scrolling:touch]">
                        <section className="space-y-2 pb-2">
                            <p className="font-semibold text-gray-900">
                                1. Obtain Client ID and Client Secret
                            </p>
                            <p>
                                Go to the AWeber Labs login page:{' '}
                                <a
                                    href="https://labs.aweber.com/auth/login"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-indigo-600 hover:text-indigo-800 break-all"
                                >
                                    https://labs.aweber.com/auth/login
                                </a>
                            </p>
                            <ul className="list-disc pl-5 space-y-1">
                                <li>Log in to your account.</li>
                                <li>Click on Create New App.</li>
                                <li>Enter the required details for your application.</li>
                                <li>
                                    Use this OAuth Redirect URL when configuring your AWeber app:
                                    <div className="mt-2 break-all text-gray-900 select-all">
                                        {oauthRedirectUrl}
                                    </div>
                                </li>
                            </ul>
                            <p>
                                After creating the app, you will be provided with a <strong>Client ID</strong> and{' '}
                                <strong>Client Secret</strong>. Copy these credentials and paste them into their
                                respective fields in your system.
                            </p>
                        </section>
                        <section className="space-y-2 pt-4 border-t border-gray-100">
                            <p className="font-semibold text-gray-900">2. Retrieve List ID</p>
                            <p>
                                Log in to your AWeber account:{' '}
                                <a
                                    href="https://www.aweber.com/"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-indigo-600 hover:text-indigo-800 break-all"
                                >
                                    https://www.aweber.com/
                                </a>
                            </p>
                            <p>From the left sidebar, navigate to:</p>
                            <p className="pl-2">
                                <strong>List Options → List Settings</strong>
                            </p>
                            <p>Locate the List ID.</p>
                            <p>Copy only the numeric portion of the List ID.</p>
                            <p>Paste this value into the List ID field on the API Instance page.</p>
                            <div className="pt-4 mt-4 border-t border-gray-200 space-y-2">
                                <p className="font-semibold text-gray-900">Additional Setup (Custom Fields):</p>
                                <p>Once the list is created, go to:</p>
                                <p className="pl-2">
                                    <strong>List Options → Custom Fields</strong>
                                </p>
                                <p>
                                    Create the following fields{' '}
                                    <span className="text-red-600 font-medium">
                                        (copy each field exactly as written)
                                    </span>
                                    :
                                </p>
                                <ul className="list-disc pl-5 space-y-1 text-gray-900">
                                    <li>First Name</li>
                                    <li>Last Name</li>
                                    <li>Email</li>
                                    <li>Broker URL</li>
                                </ul>
                            </div>
                        </section>
                    </div>
                    <div className="mt-4 flex shrink-0 justify-end border-t border-gray-200 pt-4">
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>,
            document.body
        );

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="text-sm font-medium text-indigo-600 hover:text-indigo-800 underline decoration-indigo-400/60 underline-offset-2 text-left"
            >
                AWeber Integration Setup Instructions
            </button>
            {modal}
        </>
    );
}
