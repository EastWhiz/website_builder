import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';

const DEFAULT_HERO_COLOR = '#3B27A8';

export default function ThankYouPageForm({
    initialData = {},
    isEdit = false,
    pageId = null,
    backUrl,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        name: initialData.name ?? '',
        title_text: initialData.title_text ?? '',
        description: initialData.description ?? '',
        logo: null,
        profile_image: null,
        hero_background_color: initialData.hero_background_color ?? DEFAULT_HERO_COLOR,
        // Method spoofing: POST with _method=PUT so Laravel parses FormData (PHP does not parse PUT body)
        ...(isEdit ? { _method: 'PUT' } : {}),
    });

    const submit = (e) => {
        e.preventDefault();
        const options = { forceFormData: true };
        if (isEdit && pageId) {
            // POST + _method=PUT so Laravel parses FormData and still routes to update()
            post(route('thank-you-pages.update', pageId), options);
            return;
        }
        post(route('thank-you-pages.store'), options);
    };

    const logoUrl = initialData.logo_url ?? null;
    const profileImageUrl = initialData.profile_image_url ?? null;

    return (
        <form onSubmit={submit} className="space-y-6 max-w-2xl">
            <div>
                <InputLabel htmlFor="name" value="Page name" />
                <TextInput
                    id="name"
                    type="text"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="e.g. Main thank you page"
                />
                <InputError className="mt-1" message={errors.name} />
            </div>

            <div>
                <InputLabel htmlFor="logo" value={isEdit ? 'Logo (leave empty to keep current)' : 'Logo *'} />
                {isEdit && logoUrl && (
                    <div className="mt-1 mb-2 flex items-center gap-3">
                        <img src={logoUrl} alt="Current logo" className="h-12 w-auto object-contain border rounded p-1 bg-gray-50" />
                        <span className="text-sm text-gray-500">Current logo</span>
                    </div>
                )}
                <input
                    id="logo"
                    type="file"
                    accept="image/*"
                    className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    onChange={(e) => setData('logo', e.target.files?.[0] ?? null)}
                />
                <InputError className="mt-1" message={errors.logo} />
            </div>

            <div>
                <InputLabel htmlFor="title_text" value="Title text" />
                <TextInput
                    id="title_text"
                    type="text"
                    className="mt-1 block w-full"
                    value={data.title_text}
                    onChange={(e) => setData('title_text', e.target.value)}
                    placeholder="e.g. Thank you for signing up!"
                />
                <InputError className="mt-1" message={errors.title_text} />
            </div>

            <div>
                <InputLabel htmlFor="description" value="Description (optional)" />
                <textarea
                    id="description"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={4}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    placeholder="Short message shown below the title"
                />
                <InputError className="mt-1" message={errors.description} />
            </div>

            <div>
                <InputLabel htmlFor="profile_image" value={isEdit ? 'Profile image (leave empty to keep current)' : 'Profile image (optional)'} />
                {isEdit && profileImageUrl && (
                    <div className="mt-1 mb-2 flex items-center gap-3">
                        <img src={profileImageUrl} alt="Current profile" className="h-16 w-16 object-cover rounded-full border" />
                        <span className="text-sm text-gray-500">Current profile image</span>
                    </div>
                )}
                <input
                    id="profile_image"
                    type="file"
                    accept="image/*"
                    className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    onChange={(e) => setData('profile_image', e.target.files?.[0] ?? null)}
                />
                <InputError className="mt-1" message={errors.profile_image} />
            </div>

            <div>
                <InputLabel htmlFor="hero_background_color" value="Hero background color" />
                <div className="mt-1 flex items-center gap-2">
                    <input
                        id="hero_color_swatch"
                        type="color"
                        className="h-10 w-14 cursor-pointer rounded border border-gray-300"
                        value={data.hero_background_color}
                        onChange={(e) => setData('hero_background_color', e.target.value)}
                    />
                    <TextInput
                        id="hero_background_color"
                        type="text"
                        className="block w-32"
                        value={data.hero_background_color}
                        onChange={(e) => setData('hero_background_color', e.target.value)}
                        placeholder="#3B27A8"
                    />
                </div>
                <InputError className="mt-1" message={errors.hero_background_color} />
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton type="submit" disabled={processing}>
                    {processing ? (
                        <>
                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            {isEdit ? 'Updating...' : 'Creating...'}
                        </>
                    ) : (
                        isEdit ? 'Update' : 'Create'
                    )}
                </PrimaryButton>
                {backUrl && (
                    <a
                        href={backUrl}
                        className="text-sm text-indigo-600 hover:text-indigo-900"
                    >
                        Cancel
                    </a>
                )}
            </div>
        </form>
    );
}
