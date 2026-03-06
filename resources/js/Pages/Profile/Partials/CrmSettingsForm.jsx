import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Swal from 'sweetalert2';

const ADMIN_EMAIL = 'admin@gmail.com';

export default function CrmSettingsForm({ crmSettings, className = '' }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const [crmMode, setCrmMode] = useState('production');
    const [urlProduction, setUrlProduction] = useState('');
    const [urlDev, setUrlDev] = useState('');
    const [verifySsl, setVerifySsl] = useState(true);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (crmSettings) {
            setCrmMode(crmSettings.crm_mode || 'production');
            setUrlProduction(crmSettings.crm_url_production || '');
            setUrlDev(crmSettings.crm_url_dev || '');
            setVerifySsl(crmSettings.crm_verify_ssl !== '0' && crmSettings.crm_verify_ssl !== false);
        }
    }, [crmSettings]);

    const isAdmin = user?.email === ADMIN_EMAIL;
    if (!isAdmin) return null;

    const getHeaders = () => {
        const h = { 'Content-Type': 'application/json', Accept: 'application/json' };
        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf) h['X-CSRF-TOKEN'] = csrf.content;
        return h;
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            const res = await fetch(route('crm.settings.update'), {
                method: 'PUT',
                headers: getHeaders(),
                body: JSON.stringify({
                    crm_mode: crmMode,
                    crm_url_production: urlProduction,
                    crm_url_dev: urlDev,
                    crm_verify_ssl: verifySsl,
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Saved', text: data.message || 'CRM settings saved.' });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to save.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message || 'Failed to save.' });
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">CRM Server Settings (Admin only)</h2>
                <p className="mt-1 text-sm text-gray-600">
                    Switch between Production and Dev CRM and set base URLs. Only visible to {ADMIN_EMAIL}.
                </p>
            </header>

            <div className="mt-6 space-y-4">
                <div>
                    <InputLabel value="CRM environment" />
                    <div className="mt-2 flex gap-4">
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                name="crm_mode"
                                checked={crmMode === 'production'}
                                onChange={() => setCrmMode('production')}
                                className="rounded border-gray-300"
                            />
                            <span>Production</span>
                        </label>
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                name="crm_mode"
                                checked={crmMode === 'dev'}
                                onChange={() => setCrmMode('dev')}
                                className="rounded border-gray-300"
                            />
                            <span>Dev</span>
                        </label>
                    </div>
                </div>

                <div>
                    <InputLabel value="Production CRM URL" />
                    <TextInput
                        type="url"
                        className="mt-1 block w-full"
                        value={urlProduction}
                        onChange={(e) => setUrlProduction(e.target.value)}
                        placeholder="https://crm.diy"
                    />
                </div>

                <div>
                    <InputLabel value="Dev CRM URL" />
                    <TextInput
                        type="url"
                        className="mt-1 block w-full"
                        value={urlDev}
                        onChange={(e) => setUrlDev(e.target.value)}
                        placeholder="https://dev-crm.example.com"
                    />
                </div>

                <div>
                    <label className="inline-flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={verifySsl}
                            onChange={(e) => setVerifySsl(e.target.checked)}
                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        <span className="text-sm text-gray-700">Verify SSL certificate</span>
                    </label>
                    <p className="mt-1 text-xs text-gray-500">
                        Uncheck only for dev CRM with self-signed or expired certificates.
                    </p>
                </div>

                <div className="pt-2">
                    <PrimaryButton onClick={handleSave} disabled={saving}>
                        {saving ? 'Saving...' : 'Save CRM settings'}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    );
}
