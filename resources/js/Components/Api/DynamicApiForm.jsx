import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import ApiField from './ApiField';

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
}) {
    const getFieldError = (fieldName) => {
        const msg = errors[`values.${fieldName}`] ?? errors[fieldName];
        return Array.isArray(msg) ? msg[0] : msg;
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
                <ApiField
                    key={field.id}
                    field={field}
                    value={values[field.name]}
                    onChange={onValueChange}
                    error={getFieldError(field.name)}
                />
            ))}
        </div>
    );
}
