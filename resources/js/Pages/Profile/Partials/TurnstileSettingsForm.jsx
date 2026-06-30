import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useEffect, useState } from 'react';
import Swal from 'sweetalert2';

const defaultSettings = {
    enabled: false,
    auto_provision_enabled: false,
    cloudflare_account_id: '',
    cloudflare_api_token_exists: false,
    default_widget_mode: 'managed',
    widget_scope: 'shared',
};

export default function TurnstileSettingsForm({ className = '' }) {
    const [settings, setSettings] = useState(defaultSettings);
    const [apiToken, setApiToken] = useState('');
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const headers = () => ({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    });

    const fetchSettings = async () => {
        setLoading(true);
        try {
            const response = await fetch(route('organization.turnstile-settings.index'), {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Failed to load Turnstile settings.');
            }
            setSettings({ ...defaultSettings, ...data });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to load Turnstile settings.',
            });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchSettings();
    }, []);

    const setField = (field, value) => {
        setSettings((current) => ({ ...current, [field]: value }));
        setErrors((current) => ({ ...current, [field]: undefined }));
    };

    const saveSettings = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const response = await fetch(route('organization.turnstile-settings.update'), {
                method: 'PUT',
                headers: headers(),
                body: JSON.stringify({
                    enabled: settings.enabled,
                    auto_provision_enabled: settings.auto_provision_enabled,
                    cloudflare_account_id: settings.cloudflare_account_id,
                    cloudflare_api_token: apiToken,
                    default_widget_mode: settings.default_widget_mode,
                    widget_scope: settings.widget_scope,
                }),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                setErrors(data.errors || {});
                throw new Error(data.message || Object.values(data.errors || {})?.[0]?.[0] || 'Failed to save settings.');
            }

            setSettings({ ...defaultSettings, ...(data.settings || {}) });
            setApiToken('');
            Swal.fire({
                icon: 'success',
                title: 'Saved',
                text: data.message || 'Turnstile settings saved.',
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to save settings.',
            });
        } finally {
            setSaving(false);
        }
    };

    const testConnection = async () => {
        setTesting(true);
        try {
            const response = await fetch(route('organization.turnstile-settings.test-connection'), {
                method: 'POST',
                headers: headers(),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || 'Cloudflare Turnstile connection test failed.');
            }

            Swal.fire({
                icon: 'success',
                title: 'Connection OK',
                text: data.message || 'Cloudflare Turnstile connection test passed.',
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Connection failed',
                text: error.message || 'Cloudflare Turnstile connection test failed.',
            });
        } finally {
            setTesting(false);
        }
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">Cloudflare Turnstile settings</h2>
                <p className="mt-1 text-sm text-gray-600">
                    Configure automatic Turnstile widget creation and hostname registration for exported landing-page forms.
                </p>
            </header>

            <form onSubmit={saveSettings} className="mt-6 space-y-5">
                <label className="flex items-start gap-3">
                    <input
                        type="checkbox"
                        checked={settings.enabled}
                        onChange={(event) => setField('enabled', event.target.checked)}
                        className="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        disabled={loading}
                    />
                    <span>
                        <span className="block text-sm font-medium text-gray-800">Enable Turnstile</span>
                        <span className="block text-sm text-gray-500">Allow forms to use Turnstile protection when enabled per form.</span>
                    </span>
                </label>

                <label className="flex items-start gap-3">
                    <input
                        type="checkbox"
                        checked={settings.auto_provision_enabled}
                        onChange={(event) => setField('auto_provision_enabled', event.target.checked)}
                        className="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        disabled={loading}
                    />
                    <span>
                        <span className="block text-sm font-medium text-gray-800">Auto-provision Cloudflare widgets</span>
                        <span className="block text-sm text-gray-500">Builder will create/update widgets and register hostnames through Cloudflare API.</span>
                    </span>
                </label>

                <div>
                    <InputLabel htmlFor="cloudflare_account_id" value="Cloudflare Account ID" />
                    <TextInput
                        id="cloudflare_account_id"
                        type="text"
                        className="mt-1 block w-full"
                        value={settings.cloudflare_account_id || ''}
                        onChange={(event) => setField('cloudflare_account_id', event.target.value)}
                        disabled={loading}
                        autoComplete="off"
                    />
                    <InputError message={errors.cloudflare_account_id?.[0]} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="cloudflare_api_token" value="Cloudflare API token/access key" />
                    <TextInput
                        id="cloudflare_api_token"
                        type="password"
                        className="mt-1 block w-full"
                        value={apiToken}
                        onChange={(event) => {
                            setApiToken(event.target.value);
                            setErrors((current) => ({ ...current, cloudflare_api_token: undefined }));
                        }}
                        disabled={loading}
                        autoComplete="new-password"
                        placeholder={settings.cloudflare_api_token_exists ? 'Token saved. Enter a new token to replace it.' : ''}
                    />
                    <InputError message={errors.cloudflare_api_token?.[0]} className="mt-2" />
                    <p className="mt-1 text-xs text-gray-500">
                        This value is write-only. Saved tokens are encrypted and never shown again.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="default_widget_mode" value="Default widget mode" />
                        <select
                            id="default_widget_mode"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={settings.default_widget_mode || 'managed'}
                            onChange={(event) => setField('default_widget_mode', event.target.value)}
                            disabled={loading}
                        >
                            <option value="managed">Managed</option>
                            <option value="non-interactive">Non-interactive</option>
                            <option value="invisible">Invisible</option>
                        </select>
                        <InputError message={errors.default_widget_mode?.[0]} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="widget_scope" value="Widget scope" />
                        <select
                            id="widget_scope"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={settings.widget_scope || 'shared'}
                            onChange={(event) => setField('widget_scope', event.target.value)}
                            disabled={loading}
                        >
                            <option value="shared">Shared widget</option>
                            <option value="per_hostname">Per hostname</option>
                        </select>
                        <InputError message={errors.widget_scope?.[0]} className="mt-2" />
                    </div>
                </div>

                <div className="flex flex-col gap-3 pt-2 sm:flex-row">
                    <PrimaryButton type="submit" disabled={loading || saving}>
                        {saving ? 'Saving...' : 'Save Turnstile settings'}
                    </PrimaryButton>
                    <SecondaryButton type="button" onClick={testConnection} disabled={loading || testing || saving}>
                        {testing ? 'Testing...' : 'Test Cloudflare connection'}
                    </SecondaryButton>
                </div>
            </form>
        </section>
    );
}
