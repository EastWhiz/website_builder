import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

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
    const [saveError, setSaveError] = useState('');
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        name: initialData.name ?? '',
        template_type: initialData.template_type ?? (isEdit ? 'legacy' : 'geo_aware_v2'),
        title_text: initialData.title_text ?? '',
        description: initialData.description ?? '',
        v2_page_title: initialData.v2_content?.v2_page_title ?? '',
        v2_top_strip_text: initialData.v2_content?.v2_top_strip_text ?? '',
        v2_banner_limited_text: initialData.v2_content?.v2_banner_limited_text ?? '',
        v2_banner_heading: initialData.v2_content?.v2_banner_heading ?? '',
        v2_banner_text_1: initialData.v2_content?.v2_banner_text_1 ?? '',
        v2_banner_text_2: initialData.v2_content?.v2_banner_text_2 ?? '',
        v2_call_scheduled_text: initialData.v2_content?.v2_call_scheduled_text ?? '',
        v2_call_setup_text: initialData.v2_content?.v2_call_setup_text ?? '',
        v2_geo_cutoff_hour: initialData.v2_content?.v2_geo_cutoff_hour ?? 19,
        v2_geo_skip_weekends: initialData.v2_content?.v2_geo_skip_weekends ?? true,
        v2_geo_sunday_cutoff_hour: initialData.v2_content?.v2_geo_sunday_cutoff_hour ?? 17,
        v2_geo_default_visitor_tz: initialData.v2_content?.v2_geo_default_visitor_tz ?? 'UTC',
        v2_geo_country_overrides_json: initialData.v2_content?.v2_geo_country_overrides_json ?? '',
        logo: null,
        profile_image: null,
        hero_background_color: initialData.hero_background_color ?? DEFAULT_HERO_COLOR,
        remove_logo: false,
        remove_profile_image: false,
        ...(isEdit ? { _method: 'PUT' } : {}),
    });

    const submit = (e) => {
        e.preventDefault();
        setSaveError('');
        const options = {
            forceFormData: true,
            onError: () => setSaveError('The page could not be saved. Please review the highlighted fields and try again.'),
        };
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
    const isLegacy = data.template_type === 'legacy';

    const handleRemoveLogo = () => {
        setData({ ...data, remove_logo: true, logo: null });
    };
    const handleRemoveHeroImage = () => {
        setData({ ...data, remove_profile_image: true, profile_image: null });
    };

    return (
        <form onSubmit={submit} className="space-y-8 max-w-3xl">
            {saveError && (
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {saveError}
                </div>
            )}
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

                    <input type="hidden" name="template_type" value={data.template_type} />

                    {isLegacy && <div>
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
                    </div>}
                </div>
            </SectionCard>

            {isLegacy ? (
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
            ) : (
                <SectionCard
                    title="Dynamic Content Settings"
                    description="These fields control the new thank-you page copy."
                >
                    <div className="space-y-5">
                        <div><InputLabel htmlFor="v2_top_strip_text" value="Top strip text" /><TextInput id="v2_top_strip_text" type="text" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.v2_top_strip_text} onChange={(e) => setData('v2_top_strip_text', e.target.value)} /><InputError className="mt-1" message={errors.v2_top_strip_text} /></div>
                        <div><InputLabel htmlFor="v2_banner_limited_text" value="Banner limited text" /><TextInput id="v2_banner_limited_text" type="text" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.v2_banner_limited_text} onChange={(e) => setData('v2_banner_limited_text', e.target.value)} /><InputError className="mt-1" message={errors.v2_banner_limited_text} /></div>
                        <div><InputLabel htmlFor="v2_banner_heading" value="Banner heading" /><TextInput id="v2_banner_heading" type="text" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.v2_banner_heading} onChange={(e) => setData('v2_banner_heading', e.target.value)} /><InputError className="mt-1" message={errors.v2_banner_heading} /></div>
                        <div><InputLabel htmlFor="v2_banner_text_1" value="Banner text 1" /><textarea id="v2_banner_text_1" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows={3} value={data.v2_banner_text_1} onChange={(e) => setData('v2_banner_text_1', e.target.value)} /><InputError className="mt-1" message={errors.v2_banner_text_1} /></div>
                        <div><InputLabel htmlFor="v2_banner_text_2" value="Banner text 2" /><textarea id="v2_banner_text_2" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows={3} value={data.v2_banner_text_2} onChange={(e) => setData('v2_banner_text_2', e.target.value)} /><InputError className="mt-1" message={errors.v2_banner_text_2} /></div>
                        <div><InputLabel htmlFor="v2_call_scheduled_text" value="Call scheduled text" /><TextInput id="v2_call_scheduled_text" type="text" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.v2_call_scheduled_text} onChange={(e) => setData('v2_call_scheduled_text', e.target.value)} /><InputError className="mt-1" message={errors.v2_call_scheduled_text} /></div>
                        <div><InputLabel htmlFor="v2_call_setup_text" value="Call setup text" /><textarea id="v2_call_setup_text" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows={3} value={data.v2_call_setup_text} onChange={(e) => setData('v2_call_setup_text', e.target.value)} /><InputError className="mt-1" message={errors.v2_call_setup_text} /></div>
                        <div className="border-t border-gray-200 pt-4">
                            <p className="text-sm font-semibold text-gray-900 mb-3">Geo/Timezone call settings</p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div><InputLabel htmlFor="v2_geo_cutoff_hour" value="Default cutoff hour (0-23)" /><TextInput id="v2_geo_cutoff_hour" type="number" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.v2_geo_cutoff_hour} onChange={(e) => setData('v2_geo_cutoff_hour', e.target.value)} /><InputError className="mt-1" message={errors.v2_geo_cutoff_hour} /></div>
                                <div><InputLabel htmlFor="v2_geo_sunday_cutoff_hour" value="Sunday cutoff hour (0-23)" /><TextInput id="v2_geo_sunday_cutoff_hour" type="number" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.v2_geo_sunday_cutoff_hour} onChange={(e) => setData('v2_geo_sunday_cutoff_hour', e.target.value)} /><InputError className="mt-1" message={errors.v2_geo_sunday_cutoff_hour} /></div>
                                <div><InputLabel htmlFor="v2_geo_default_visitor_tz" value="Default visitor timezone" /><TextInput id="v2_geo_default_visitor_tz" type="text" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.v2_geo_default_visitor_tz} onChange={(e) => setData('v2_geo_default_visitor_tz', e.target.value)} placeholder="e.g. UTC or Europe/Berlin" /><InputError className="mt-1" message={errors.v2_geo_default_visitor_tz} /></div>
                                <div className="flex items-center gap-2 pt-8">
                                    <input id="v2_geo_skip_weekends" type="checkbox" checked={Boolean(data.v2_geo_skip_weekends)} onChange={(e) => setData('v2_geo_skip_weekends', e.target.checked)} />
                                    <InputLabel htmlFor="v2_geo_skip_weekends" value="Skip weekends (roll to Monday)" />
                                </div>
                            </div>
                            <div className="mt-4">
                                <InputLabel htmlFor="v2_geo_country_overrides_json" value="Country overrides JSON (optional)" />
                                <textarea id="v2_geo_country_overrides_json" className="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs" rows={6} value={data.v2_geo_country_overrides_json} onChange={(e) => setData('v2_geo_country_overrides_json', e.target.value)} placeholder='{"DE":{"cutoff_hour":19,"skip_weekends":true,"sunday_cutoff_hour":17,"visitor_tz":"Europe/Berlin"}}' />
                                <InputError className="mt-1" message={errors.v2_geo_country_overrides_json} />
                            </div>
                        </div>
                    </div>
                </SectionCard>
            )}

            {isLegacy && <SectionCard
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
            </SectionCard>}

            {isLegacy && <SectionCard
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
            </SectionCard>}

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
