import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';

const DEFAULT_HERO_COLOR = '#3B27A8';

function SectionCard({ title, description, children }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div className="border-b border-gray-100 bg-gray-50/80 px-5 py-4">
                <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
                {description && (
                    <p className="mt-0.5 text-xs text-gray-500">{description}</p>
                )}
            </div>
            <div className="p-5">{children}</div>
        </div>
    );
}

function ImagePreview({ src, alt, onRemove, removeLabel, isHero = false }) {
    if (!src) return null;
    return (
        <div className="relative inline-block">
            <img
                src={src}
                alt={alt}
                className={
                    isHero
                        ? 'max-w-full w-full max-h-48 object-contain rounded-lg border border-gray-200 bg-gray-50'
                        : 'h-14 w-auto max-w-[200px] object-contain rounded-lg border border-gray-200 bg-gray-50 p-1'
                }
            />
            {onRemove && (
                <button
                    type="button"
                    onClick={onRemove}
                    className="absolute -top-2 -right-2 rounded-full bg-red-500 text-white p-1.5 shadow-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1"
                    title={removeLabel}
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            )}
        </div>
    );
}

function FileField({ id, label, hint, accept, onChange, error }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            {hint && <p className="mt-0.5 text-xs text-gray-500">{hint}</p>}
            <input
                id={id}
                type="file"
                accept={accept}
                className="mt-2 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 file:cursor-pointer cursor-pointer"
                onChange={(e) => onChange(e.target.files?.[0] ?? null)}
            />
            <InputError className="mt-1" message={error} />
        </div>
    );
}

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
        remove_logo: false,
        remove_profile_image: false,
        ...(isEdit ? { _method: 'PUT' } : {}),
    });

    const submit = (e) => {
        e.preventDefault();
        const options = { forceFormData: true };
        if (isEdit && pageId) {
            post(route('thank-you-pages.update', pageId), options);
            return;
        }
        post(route('thank-you-pages.store'), options);
    };

    const logoUrl = data.remove_logo ? null : (initialData.logo_url ?? null);
    const profileImageUrl = data.remove_profile_image ? null : (initialData.profile_image_url ?? null);
    const logoPreviewUrl = data.logo ? URL.createObjectURL(data.logo) : logoUrl;
    const profilePreviewUrl = data.profile_image ? URL.createObjectURL(data.profile_image) : profileImageUrl;

    const handleRemoveLogo = () => {
        setData({ ...data, remove_logo: true, logo: null });
    };
    const handleRemoveHeroImage = () => {
        setData({ ...data, remove_profile_image: true, profile_image: null });
    };

    return (
        <form onSubmit={submit} className="space-y-8 max-w-3xl">
            <SectionCard
                title="Page identity"
                description="Name and branding used in the thank you page."
            >
                <div className="space-y-5">
                    <div>
                        <InputLabel htmlFor="name" value="Page name *" />
                        <TextInput
                            id="name"
                            type="text"
                            className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. Main thank you page"
                        />
                        <InputError className="mt-1" message={errors.name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="logo" value={isEdit ? 'Logo' : 'Logo *'} />
                        {isEdit && (logoUrl || logoPreviewUrl) && (
                            <div className="mt-2 mb-3 flex items-center gap-4">
                                <ImagePreview
                                    src={logoPreviewUrl}
                                    alt="Logo"
                                    onRemove={isEdit ? handleRemoveLogo : null}
                                    removeLabel="Remove logo"
                                />
                                {data.remove_logo && (
                                    <span className="text-sm text-amber-600 font-medium">Logo will be removed on save</span>
                                )}
                            </div>
                        )}
                        <FileField
                            id="logo"
                            label={isEdit ? 'Replace logo (optional)' : 'Upload logo'}
                            hint={isEdit ? 'Leave empty to keep current. Use "Remove" above to clear.' : 'Recommended: PNG or JPG, max 5MB.'}
                            accept="image/*"
                            onChange={(file) => setData({ ...data, logo: file, remove_logo: false })}
                            error={errors.logo}
                        />
                    </div>
                </div>
            </SectionCard>

            <SectionCard
                title="Thank you message"
                description="Title and short description shown in the hero section."
            >
                <div className="space-y-5">
                    <div>
                        <InputLabel htmlFor="title_text" value="Title text *" />
                        <TextInput
                            id="title_text"
                            type="text"
                            className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                            className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows={4}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Short message shown below the title"
                        />
                        <InputError className="mt-1" message={errors.description} />
                    </div>
                </div>
            </SectionCard>

            <SectionCard
                title="Hero image"
                description="Image displayed in the thank you card (full width, not a profile avatar)."
            >
                <div className="space-y-5">
                    {(profileImageUrl || profilePreviewUrl) && (
                        <div className="space-y-2">
                            <InputLabel value="Current hero image" />
                            <div className="flex flex-col items-start gap-3">
                                <ImagePreview
                                    src={profilePreviewUrl}
                                    alt="Hero image"
                                    onRemove={isEdit ? handleRemoveHeroImage : null}
                                    removeLabel="Remove hero image"
                                    isHero
                                />
                                {data.remove_profile_image && (
                                    <span className="text-sm text-amber-600 font-medium">Hero image will be removed on save</span>
                                )}
                            </div>
                        </div>
                    )}
                    <FileField
                        id="profile_image"
                        label={isEdit ? 'Replace hero image (optional)' : 'Hero image (optional)'}
                        hint={isEdit ? 'Leave empty to keep current. Use "Remove" above to clear.' : 'Shown in the thank you card. Max 5MB.'}
                        accept="image/*"
                        onChange={(file) => setData({ ...data, profile_image: file, remove_profile_image: false })}
                        error={errors.profile_image}
                    />
                </div>
            </SectionCard>

            <SectionCard
                title="Appearance"
                description="Hero section background color (hex)."
            >
                <div>
                    <InputLabel htmlFor="hero_background_color" value="Hero background color *" />
                    <div className="mt-2 flex items-center gap-3">
                        <input
                            id="hero_color_swatch"
                            type="color"
                            className="h-11 w-16 cursor-pointer rounded-lg border border-gray-300"
                            value={data.hero_background_color}
                            onChange={(e) => setData('hero_background_color', e.target.value)}
                        />
                        <TextInput
                            id="hero_background_color"
                            type="text"
                            className="block w-36 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.hero_background_color}
                            onChange={(e) => setData('hero_background_color', e.target.value)}
                            placeholder="#3B27A8"
                        />
                    </div>
                    <InputError className="mt-1" message={errors.hero_background_color} />
                </div>
            </SectionCard>

            <div className="flex flex-wrap items-center gap-4 pt-2">
                <PrimaryButton type="submit" disabled={processing} className="rounded-lg px-6 py-2.5">
                    {processing ? (
                        <>
                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            {isEdit ? 'Saving...' : 'Creating...'}
                        </>
                    ) : (
                        isEdit ? 'Save changes' : 'Create thank you page'
                    )}
                </PrimaryButton>
                {backUrl && (
                    <a
                        href={backUrl}
                        className="text-sm font-medium text-gray-600 hover:text-gray-900"
                    >
                        Cancel
                    </a>
                )}
            </div>
        </form>
    );
}
