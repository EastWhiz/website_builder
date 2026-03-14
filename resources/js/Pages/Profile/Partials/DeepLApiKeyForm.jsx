import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import Swal from 'sweetalert2';

export default function DeepLApiKeyForm({ deepl_api_key = '', className = '' }) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        deepl_api_key: deepl_api_key || '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update-deepl-api-key'), {
            onSuccess: () => {
                if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                    Swal.fire('Success!', 'DeepL API key saved successfully.', 'success');
                }
            },
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    DeepL API Key
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Manage your personal DeepL API key for translation services. Required to use DeepL translation for angles and templates. Add your key from the DeepL API dashboard.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="deepl_api_key" value="DeepL API Key" />

                    <TextInput
                        id="deepl_api_key"
                        type="text"
                        className="mt-1 block w-full"
                        value={data.deepl_api_key}
                        onChange={(e) => setData('deepl_api_key', e.target.value)}
                        autoComplete="off"
                        placeholder="Your DeepL API key"
                    />

                    <InputError className="mt-2" message={errors.deepl_api_key} />
                </div>

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>
                        {processing ? (
                            <>
                                <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                Saving...
                            </>
                        ) : (
                            'Save'
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
                        <p className="text-sm font-medium text-green-600">
                            DeepL API key saved successfully.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
