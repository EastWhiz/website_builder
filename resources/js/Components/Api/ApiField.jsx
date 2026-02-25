import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

/**
 * Renders a single API instance field by type (text, password, email, url, number, textarea).
 * Handles label, value, onChange, error, and placeholder. Password fields are masked.
 */
export default function ApiField({ field, value, onChange, error }) {
    const id = `api_field_${field.name}`;
    const common = {
        id,
        className: 'mt-1 block w-full',
        value: value ?? '',
        onChange: (e) => onChange(field.name, e.target.value),
        placeholder: field.placeholder || '',
        autoComplete: field.type === 'password' ? 'new-password' : 'off',
    };

    if (field.type === 'textarea') {
        return (
            <div>
                <InputLabel htmlFor={id} value={field.label} />
                <textarea
                    {...common}
                    rows={3}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError className="mt-2" message={error} />
            </div>
        );
    }

    const typeMap = {
        password: 'password',
        email: 'email',
        url: 'url',
        number: 'number',
        text: 'text',
    };
    const type = typeMap[field.type] || 'text';

    return (
        <div>
            <InputLabel htmlFor={id} value={field.label} />
            <TextInput {...common} type={type} />
            <InputError className="mt-2" message={error} />
        </div>
    );
}
