import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import ApiField from './ApiField';

/** Trackbox-only: global API key caption and value for the copy button. */
const TRACKBOX_GLOBAL_API_KEY_LABEL = 'As of Jan 2026 Global API Key Is 2643889w34df345676ssdas323tgc738';
const TRACKBOX_GLOBAL_API_KEY_VALUE = '2643889w34df345676ssdas323tgc738';

/**
 * Dynamic form for API instance: instance name (optional) + one input per category field.
 * Accepts category fields, values, errors, and change callbacks. Renders fields by type (text, password, email, url, number, textarea).
 * Password fields are masked (type="password").
 */
export default function DynamicApiForm({
    fields = [],
    values = {},
    errors = {},
    onValueChange,
    name,
    onNameChange,
    nameError,
    nameLabel = 'Instance name',
    nameId = 'dynamic_api_instance_name',
    categoryName = null,
}) {
    const getFieldError = (fieldName) => {
        const msg = errors[`values.${fieldName}`] ?? errors[fieldName];
        return Array.isArray(msg) ? msg[0] : msg;
    };

    const handleCopyTrackboxGlobalKey = () => {
        navigator.clipboard.writeText(TRACKBOX_GLOBAL_API_KEY_VALUE).catch(() => {});
    };

    return (
        <div className="space-y-4">
            {(onNameChange !== undefined || name !== undefined) && (
                <div>
                    <InputLabel htmlFor={nameId} value={nameLabel} />
                    <TextInput
                        id={nameId}
                        className="mt-1 block w-full"
                        value={name ?? ''}
                        onChange={(e) => onNameChange?.(e.target.value)}
                        required
                    />
                    <InputError className="mt-2" message={Array.isArray(nameError) ? nameError[0] : nameError} />
                </div>
            )}
            {fields.map((field) => (
                <div key={field.id}>
                    <ApiField
                        field={field}
                        value={values[field.name]}
                        onChange={onValueChange}
                        error={getFieldError(field.name)}
                    />
                    {field.name === 'api_key' && categoryName === 'Trackbox' && (
                        <div className="mt-2 flex items-center gap-2 flex-wrap">
                            <span className="text-[12px] text-red-600">{TRACKBOX_GLOBAL_API_KEY_LABEL}</span>
                            <button
                                type="button"
                                onClick={handleCopyTrackboxGlobalKey}
                                className="inline-flex items-center px-2 py-1 text-sm font-medium text-indigo-600 hover:text-indigo-800 border border-indigo-300 rounded hover:bg-indigo-50"
                            >
                                Copy
                            </button>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
