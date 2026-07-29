import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Box, Tooltip } from '@mui/material';

import {
    AppProvider,
    Button,
    Card,
    Checkbox,
    IndexFilters,
    IndexTable,
    Modal,
    Pagination,
    Select as ShopifySelect,
    Text,
    useIndexResourceState, useSetIndexFiltersMode
} from '@shopify/polaris';
import Select from 'react-select';
import { DeleteIcon, DuplicateIcon, EditIcon, LanguageIcon, PageDownIcon, ThemeEditIcon, ViewIcon, WrenchIcon } from '@shopify/polaris-icons';
import "@shopify/polaris/build/esm/styles.css";
import en from "@shopify/polaris/locales/en.json";
import { useCallback, useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';

export default function Dashboard() {

    const page = usePage().props;
    const organizationLandingPagesMode = Boolean(page.organization_landing_pages_mode);
    const userId = page.id;

    function convertISOToYMD(isoDateString) {
        var date = new Date(Date.parse(isoDateString));

        var year = date.getUTCFullYear();
        var month = String(date.getUTCMonth() + 1).padStart(2, '0'); // Months are zero-based
        var day = String(date.getUTCDate()).padStart(2, '0');

        var hours = String(date.getUTCHours()).padStart(2, '0');
        var minutes = String(date.getUTCMinutes()).padStart(2, '0');
        var seconds = String(date.getUTCSeconds()).padStart(2, '0');

        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    const [selected, setSelected] = useState(0);

    let timeout = null;

    const resourceName = {
        singular: 'Landing Page',
        plural: 'Landing Pages',
    };

    const pageOptions = [
        { label: '5', value: '5' },
        { label: '10', value: '10' },
        { label: '20', value: '20' },
        { label: '50', value: '50' },
        { label: '100', value: '100' },
    ];

    const [pageCount, setPageCount] = useState("10");

    const [tableRows, setTableRows] = useState([]);
    const { selectedResources, allResourcesSelected, handleSelectionChange } = useIndexResourceState(tableRows);

    const tabs = [].map((item, index) => ({
        content: item,
        index,
        onAction: () => { },
        id: `${item}-${index}`,
        isLocked: index === 0,
        actions: []
    }));

    const sortOptions = [
        { label: 'Id', value: 'id asc', directionLabel: 'Ascending' },
        { label: 'Id', value: 'id desc', directionLabel: 'Descending' },
    ];

    // Languages array similar to UserThemes
    const sourceLanguages = [
        { value: 'AR', label: 'Arabic' },
        { value: 'BG', label: 'Bulgarian' },
        { value: 'CS', label: 'Czech' },
        { value: 'DA', label: 'Danish' },
        { value: 'DE', label: 'German' },
        { value: 'EL', label: 'Greek' },
        { value: 'EN', label: 'English' },
        { value: 'ES', label: 'Spanish' },
        { value: 'ET', label: 'Estonian' },
        { value: 'FI', label: 'Finnish' },
        { value: 'FR', label: 'French' },
        { value: 'HE', label: 'Hebrew' },
        { value: 'HU', label: 'Hungarian' },
        { value: 'ID', label: 'Indonesian' },
        { value: 'IT', label: 'Italian' },
        { value: 'JA', label: 'Japanese' },
        { value: 'KO', label: 'Korean' },
        { value: 'LT', label: 'Lithuanian' },
        { value: 'LV', label: 'Latvian' },
        { value: 'NB', label: 'Norwegian Bokmål' },
        { value: 'NL', label: 'Dutch' },
        { value: 'PL', label: 'Polish' },
        { value: 'PT', label: 'Portuguese' },
        { value: 'RO', label: 'Romanian' },
        { value: 'RU', label: 'Russian' },
        { value: 'SK', label: 'Slovak' },
        { value: 'SL', label: 'Slovenian' },
        { value: 'SV', label: 'Swedish' },
        { value: 'TH', label: 'Thai' },
        { value: 'TR', label: 'Turkish' },
        { value: 'UK', label: 'Ukrainian' },
        { value: 'VI', label: 'Vietnamese' },
        { value: 'ZH', label: 'Chinese' },
    ];

    // Languages array similar to UserThemes
    const targetLanguages = [
        { value: 'AR', label: 'Arabic' },
        { value: 'BG', label: 'Bulgarian' },
        { value: 'CS', label: 'Czech' },
        { value: 'DA', label: 'Danish' },
        { value: 'DE', label: 'German' },
        { value: 'EL', label: 'Greek' },
        { value: 'EN-GB', label: 'English (British)' },
        { value: 'EN-US', label: 'English (American)' },
        { value: 'ES', label: 'Spanish' },
        { value: 'ES-419', label: 'Spanish (Latin America)' },
        { value: 'ET', label: 'Estonian' },
        { value: 'FI', label: 'Finnish' },
        { value: 'FR', label: 'French' },
        { value: 'HE', label: 'Hebrew' },
        { value: 'HU', label: 'Hungarian' },
        { value: 'ID', label: 'Indonesian' },
        { value: 'IT', label: 'Italian' },
        { value: 'JA', label: 'Japanese' },
        { value: 'KO', label: 'Korean' },
        { value: 'LT', label: 'Lithuanian' },
        { value: 'LV', label: 'Latvian' },
        { value: 'NB', label: 'Norwegian Bokmål' },
        { value: 'NL', label: 'Dutch' },
        { value: 'PL', label: 'Polish' },
        { value: 'PT-BR', label: 'Portuguese (Brazilian)' },
        { value: 'PT-PT', label: 'Portuguese (all Portuguese variants excluding Brazilian Portuguese)' },
        { value: 'RO', label: 'Romanian' },
        { value: 'RU', label: 'Russian' },
        { value: 'SK', label: 'Slovak' },
        { value: 'SL', label: 'Slovenian' },
        { value: 'SV', label: 'Swedish' },
        { value: 'TH', label: 'Thai' },
        { value: 'TR', label: 'Turkish' },
        { value: 'UK', label: 'Ukrainian' },
        { value: 'ZH', label: 'Chinese' },
        { value: 'ZH-HANS', label: 'Chinese (simplified)' },
        { value: 'ZH-HANT', label: 'Chinese (traditional)' }
    ];

    const [sortSelected, setSortSelected] = useState(['id asc']);
    const [queryValue, setQueryValue] = useState("");
    const { mode, setMode } = useSetIndexFiltersMode();
    const onHandleCancel = () => { };

    const [pagination, setPagination] = useState({
        path: organizationLandingPagesMode
            ? route('organization.landing-pages.list')
            : route('userThemes.list', userId),
        next_cursor: null,
        next_page_url: null,
        prev_cursor: null,
        prev_page_url: null,
    });
    const [currentCursor, setCurrentCursor] = useState(null);
    const [loading, setLoading] = useState(false);
    const [reload, setReload] = useState(true);

    // Translation state
    const [translateModalOpen, setTranslateModalOpen] = useState(false);
    const [translateActionModalOpen, setTranslateActionModalOpen] = useState(false);
    const [selectedLanguage, setSelectedLanguage] = useState('');
    const [currentAngleTemplateId, setCurrentAngleTemplateId] = useState(null);
    const [translating, setTranslating] = useState(false);

    // Translation options state
    const [splitSentences, setSplitSentences] = useState('1'); // Default: split sentences
    const [preserveFormatting, setPreserveFormatting] = useState('0'); // Default: preserve formatting

    // Export modal state
    const [exportModalOpen, setExportModalOpen] = useState(false);
    const [selectedExportAngleTemplateId, setSelectedExportAngleTemplateId] = useState(null);
    const [thankYouPages, setThankYouPages] = useState([]);
    const [selectedThankYouPageId, setSelectedThankYouPageId] = useState('');

    const [cloneLandingModalOpen, setCloneLandingModalOpen] = useState(false);
    const [cloneTemplateId, setCloneTemplateId] = useState(null);
    const [cloneTemplateName, setCloneTemplateName] = useState('');
    const [cloneTargetUser, setCloneTargetUser] = useState(null);
    const [cloneMemberOptions, setCloneMemberOptions] = useState([]);
    const [cloneMembersLoading, setCloneMembersLoading] = useState(false);
    const [cloneSubmitting, setCloneSubmitting] = useState(false);
    const [cloneLandingBulkMode, setCloneLandingBulkMode] = useState(false);
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [createSubmitting, setCreateSubmitting] = useState(false);
    const [createAngleOptions, setCreateAngleOptions] = useState([]);
    const [createThemeOptions, setCreateThemeOptions] = useState([]);
    const [selectedCreateAngle, setSelectedCreateAngle] = useState(null);
    const [selectedCreateTheme, setSelectedCreateTheme] = useState(null);
    const [createStructuredBd, setCreateStructuredBd] = useState(true);
    const emptyCreateBdContents = () => ({
        BD1: '',
        BD2: '',
        BD3: '',
        BD4: '',
        BD5: '',
    });
    const [createBdContents, setCreateBdContents] = useState(emptyCreateBdContents());
    const selectedCreateThemeSlots = useMemo(() => (
        Array.isArray(selectedCreateTheme?.bodySlots) ? selectedCreateTheme.bodySlots : []
    ), [selectedCreateTheme]);

    const [changeThemeModalOpen, setChangeThemeModalOpen] = useState(false);
    const [changeThemeSubmitting, setChangeThemeSubmitting] = useState(false);
    const [changeThemeOptions, setChangeThemeOptions] = useState([]);
    const [selectedChangeTheme, setSelectedChangeTheme] = useState(null);
    const [changeThemeTarget, setChangeThemeTarget] = useState(null);

    const loadCloneMemberOptions = useCallback(async () => {
        setCloneMembersLoading(true);
        try {
            const url = new URL(route('organization.team.members.index'));
            url.searchParams.set('page_count', '100');
            url.searchParams.set('archived', 'false');
            const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
            const result = await res.json();
            if (!result.success || !result.data?.members?.data) {
                setCloneMemberOptions([]);
                return;
            }
            const rows = result.data.members.data.filter((m) => String(m.membership_status || '') === 'active');
            setCloneMemberOptions(
                rows.map((m) => ({
                    value: String(m.user_id),
                    label: `${m.name || 'User'} (${m.email || m.user_id})`,
                })),
            );
        } catch {
            setCloneMemberOptions([]);
        } finally {
            setCloneMembersLoading(false);
        }
    }, []);

    const openCloneLandingModal = (row) => {
        setCloneLandingBulkMode(false);
        setCloneTemplateId(row.id);
        setCloneTemplateName(row.name || '');
        setCloneTargetUser(null);
        setCloneLandingModalOpen(true);
        loadCloneMemberOptions();
    };

    const openBulkCloneLandingModal = () => {
        if (allResourcesSelected) {
            Swal.fire('Selection required', 'Select specific landing pages only (not select-all).', 'warning');
            return;
        }
        if (selectedResources.length === 0) {
            Swal.fire('Selection required', 'Select at least one landing page.', 'warning');
            return;
        }
        setCloneLandingBulkMode(true);
        setCloneTemplateId(null);
        setCloneTemplateName(`${selectedResources.length} landing page${selectedResources.length === 1 ? '' : 's'}`);
        setCloneTargetUser(null);
        setCloneLandingModalOpen(true);
        loadCloneMemberOptions();
    };

    const submitCloneLandingToUser = async () => {
        if (!cloneTargetUser?.value) return;
        if (!cloneLandingBulkMode && !cloneTemplateId) return;
        if (cloneLandingBulkMode && selectedResources.length === 0) return;

        const isBulk = cloneLandingBulkMode;
        const url = isBulk
            ? route('organization.content.clone_angle_templates_to_user')
            : route('organization.content.clone_angle_template_to_user');
        const body = isBulk
            ? {
                angle_template_ids: selectedResources.map((id) => Number(id)),
                to_user_id: Number(cloneTargetUser.value),
            }
            : {
                angle_template_id: Number(cloneTemplateId),
                to_user_id: Number(cloneTargetUser.value),
            };

        try {
            setCloneSubmitting(true);
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(body),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Clone failed.');
            }
            setCloneLandingModalOpen(false);
            setCloneLandingBulkMode(false);
            handleSelectionChange('all', false);
            setReload(!reload);
            Swal.fire('Success!', result.message || (isBulk ? 'Landing pages cloned to user.' : 'Landing page cloned to user.'), 'success');
        } catch (e) {
            Swal.fire('Error', e?.message || 'Clone failed.', 'error');
        } finally {
            setCloneSubmitting(false);
        }
    };

    const orgLandingPromotedBulkActions = organizationLandingPagesMode
        ? [{ content: 'Clone to user', onAction: openBulkCloneLandingModal }]
        : [];

    const openCreateLandingModal = async () => {
        try {
            const response = await fetch(route('landing-pages.create-options'), {
                headers: { Accept: 'application/json' },
            });
            const result = await response.json();
            if (!response.ok || !result?.success) {
                throw new Error(result?.message || 'Could not load create options.');
            }
            setCreateAngleOptions((result?.data?.angles || []).map((angle) => ({
                value: String(angle.id),
                label: angle.name || `Angle #${angle.id}`,
            })));
            setCreateThemeOptions((result?.data?.templates || []).map((tpl) => ({
                value: String(tpl.id),
                label: tpl.name || `Theme #${tpl.id}`,
                bodySlots: tpl.body_slots || [],
            })));
            setSelectedCreateAngle(null);
            setSelectedCreateTheme(null);
            setCreateStructuredBd(true);
            setCreateBdContents(emptyCreateBdContents());
            setCreateModalOpen(true);
        } catch (e) {
            Swal.fire('Error', e?.message || 'Could not load options.', 'error');
        }
    };

    const openChangeThemeModal = async (row) => {
        try {
            const response = await fetch(route('landing-pages.create-options'), {
                headers: { Accept: 'application/json' },
            });
            const result = await response.json();
            if (!response.ok || !result?.success) {
                throw new Error(result?.message || 'Could not load themes.');
            }
            const currentTemplateId = row.template_id ? String(row.template_id) : null;
            setChangeThemeOptions(
                (result?.data?.templates || [])
                    .filter((tpl) => String(tpl.id) !== currentTemplateId)
                    .map((tpl) => ({
                        value: String(tpl.id),
                        label: tpl.name || `Theme #${tpl.id}`,
                        bodySlots: tpl.body_slots || [],
                    }))
            );
            setSelectedChangeTheme(null);
            setChangeThemeTarget(row);
            setChangeThemeModalOpen(true);
        } catch (e) {
            Swal.fire('Error', e?.message || 'Could not load themes.', 'error');
        }
    };

    const submitChangeTheme = async () => {
        if (!changeThemeTarget?.id || !selectedChangeTheme?.value) {
            Swal.fire('Selection required', 'Please select a theme.', 'warning');
            return;
        }

        const confirm = await Swal.fire({
            title: 'Change theme?',
            html: 'Your <strong>article content and images will stay the same</strong>. The page layout will use the new theme shell with your existing content inside it.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#51a70a',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Change theme',
        });

        if (!confirm.isConfirmed) {
            return;
        }

        try {
            setChangeThemeSubmitting(true);
            const response = await fetch(route('landing-pages.change-theme'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    angle_template_id: Number(changeThemeTarget.id),
                    template_id: Number(selectedChangeTheme.value),
                }),
            });
            const result = await response.json();
            if (!response.ok || !result?.success) {
                throw new Error(result?.message || 'Could not change theme.');
            }
            setChangeThemeModalOpen(false);
            setChangeThemeTarget(null);
            setReload(!reload);
            const requiresReview = result?.data?.user_action_required;
            await Swal.fire({
                title: requiresReview ? 'Review duplicated page' : 'Success',
                html: requiresReview
                    ? `${result?.message || 'Theme changed with safe content preservation.'}<br><br><strong>Next step:</strong> ${result?.data?.user_action_message || 'Please review the duplicated page.'}`
                    : (result?.message || 'Theme changed successfully.'),
                icon: requiresReview ? 'warning' : 'success',
            });
            if (result?.data?.angle_template_id) {
                router.get(route('previewAngleTemplate', { id: result.data.angle_template_id }));
            }
        } catch (e) {
            Swal.fire('Error', e?.message || 'Could not change theme.', 'error');
        } finally {
            setChangeThemeSubmitting(false);
        }
    };

    const submitCreateLandingPage = async () => {
        if (!selectedCreateAngle?.value || !selectedCreateTheme?.value) {
            Swal.fire('Selection required', 'Please select an angle and a theme.', 'warning');
            return;
        }
        const visibleCreateBdContents = selectedCreateThemeSlots.reduce((carry, slotKey) => ({
            ...carry,
            [slotKey]: createBdContents[slotKey] || '',
        }), {});

        if (createStructuredBd && selectedCreateThemeSlots.length === 0) {
            Swal.fire('Theme slots missing', 'The selected theme does not contain any BD placeholders. Please select a BD-compatible theme or use legacy mode.', 'warning');
            return;
        }

        try {
            setCreateSubmitting(true);
            const response = await fetch(route('landing-pages.create-from-angle-template'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    angle_id: Number(selectedCreateAngle.value),
                    template_id: Number(selectedCreateTheme.value),
                    content_mode: createStructuredBd ? 'structured_bd' : 'legacy',
                    bd_contents: createStructuredBd ? visibleCreateBdContents : undefined,
                }),
            });
            const result = await response.json();
            if (!response.ok || !result?.success) {
                throw new Error(result?.message || 'Could not create landing page.');
            }
            setCreateModalOpen(false);
            setReload(!reload);
            Swal.fire('Success', result?.message || 'Landing page created successfully.', 'success');
            if (result?.data?.angle_template_id) {
                router.get(route('previewAngleTemplate', { id: result.data.angle_template_id }));
            }
        } catch (e) {
            Swal.fire('Error', e?.message || 'Could not create landing page.', 'error');
        } finally {
            setCreateSubmitting(false);
        }
    };
    const handlePageCount = useCallback((value) => { setPageCount(value); setCurrentCursor(null); setReload(!reload); }, [tableRows]);

    useEffect(() => {

        let url = new URL(pagination.path);

        url.searchParams.set('page_count', pageCount);

        if (currentCursor) {
            url.searchParams.set('cursor', currentCursor);
        }

        if (queryValue != '') {
            url.searchParams.set('q', queryValue);
        } else {
            url.searchParams.delete('q');
        }

        if (sortSelected != "") {
            url.searchParams.set('sort', sortSelected[0])
        } else {
            url.searchParams.delete('sort');
        }

        url = url.toString();
        setLoading(true)
        fetch(url)
            .then((response) => response.json())
            .then((result) => {
                if (result.success) {
                    setTableRows(result.data.data);
                    setPagination({
                        path: result.data.path,
                        next_cursor: result.data.next_cursor,
                        next_page_url: result.data.next_page_url,
                        prev_cursor: result.data.prev_cursor,
                        prev_page_url: result.data.prev_page_url,
                    });
                }
                setLoading(false);
            })
            .catch((err) => {
                console.log(err);
                setLoading(false);
            });

    }, [reload])

    useEffect(() => {
        setReload(!reload);
    }, [sortSelected]);

    const handleFiltersQueryChange = useCallback(
        (value) => {
            setQueryValue(value)
            clearTimeout(timeout)
            timeout = setTimeout(() => {
                setCurrentCursor(null);
                setReload(!reload);
            }, 500);
        },
        [tableRows]
    );

    const handleQueryValueRemove = useCallback(() => { setQueryValue(""); setCurrentCursor(null); setReload(!reload); }, [tableRows]);

    const handleFiltersClearAll = useCallback(() => {
        handleQueryValueRemove();
    }, [
        handleQueryValueRemove,
    ]);

    const deleteAngleTemplateHandler = (deleteId) => {
        Swal.fire({
            title: "Are you sure?",
            text: "You won't be able to revert this!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#51a70a",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {

                fetch(route('delete.angleTemplate'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        angle_template_id: deleteId,
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        // console.log(data);
                        Swal.fire({
                            title: data.success ? "Deleted!" : "Error!",
                            text: data.message,
                            icon: data.success ? "success" : "error"
                        });
                        setReload(!reload);
                    })
            }
        });
    }

    const duplicateAngleTemplateHandler = (angleTemplateId) => {
        Swal.fire({
            title: "Are you sure?",
            text: "Do you want to duplicate this Landing Page?",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#51a70a",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, duplicate it!"
        }).then((result) => {
            if (result.isConfirmed) {

                fetch(route('duplicate.angleTemplate', angleTemplateId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({})
                })
                    .then(response => response.json())
                    .then(data => {
                        Swal.fire({
                            title: data.success ? "Duplicated!" : "Error!",
                            text: data.message,
                            icon: data.success ? "success" : "error"
                        });
                        setReload(!reload);
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: "Error!",
                            text: "An error occurred while duplicating the Landing Page.",
                            icon: "error"
                        });
                    });
            }
        });
    }

    const filters = [];

    const appliedFilters = [];

    const openLandingPreview = (templateId) => {
        const baseUrl = (window.appURL && !window.appURL.includes('localhost') && !window.appURL.includes('127.0.0.1'))
            ? window.appURL
            : window.location.origin;
        window.open(`${baseUrl}/angle-templates/preview/${templateId}/`, '_blank');
    };

    const rowMarkup = tableRows.map((value, index) => {
        const ownerName = value.user?.name ?? '—';
        const ownerUid = value.user?.id ? `U${value.user.id}` : null;
        const ownerLabel = ownerUid ? `${ownerName} (${ownerUid})` : ownerName;

        if (organizationLandingPagesMode) {
            return (
                <IndexTable.Row
                    id={String(value.id)}
                    key={value.id}
                    selected={selectedResources.includes(String(value.id))}
                    position={index}
                >
                    <IndexTable.Cell>
                        {`SP${value.id}`}
                    </IndexTable.Cell>
                    <IndexTable.Cell>
                        {value.name}
                    </IndexTable.Cell>
                    <IndexTable.Cell>
                        {ownerLabel}
                    </IndexTable.Cell>
                    <IndexTable.Cell>
                        {convertISOToYMD(value.created_at)}
                    </IndexTable.Cell>
                    <IndexTable.Cell>
                        <Tooltip title="Preview landing page" arrow>
                            <span><Button variant="plain" icon={ViewIcon} onClick={() => openLandingPreview(value.id)} accessibilityLabel="Preview landing page" /></span>
                        </Tooltip>
                        <span style={{ margin: '10px' }} />
                        <Tooltip title="Change theme" arrow>
                            <span><Button variant="plain" icon={ThemeEditIcon} onClick={() => openChangeThemeModal(value)} accessibilityLabel="Change theme" /></span>
                        </Tooltip>
                        <span style={{ margin: '10px' }} />
                        <Tooltip title="Export landing page" arrow>
                            <span><Button variant="plain" icon={PageDownIcon} onClick={() => openExportModal(value.id)} accessibilityLabel="Export landing page" /></span>
                        </Tooltip>
                        <span style={{ margin: '10px' }} />
                        <Tooltip title="Clone to user" arrow>
                            <span><Button variant="plain" icon={DuplicateIcon} onClick={() => openCloneLandingModal(value)} accessibilityLabel="Clone landing page to user" /></span>
                        </Tooltip>
                    </IndexTable.Cell>
                </IndexTable.Row>
            );
        }

        return (
            <IndexTable.Row
                id={String(value.id)}
                key={value.id}
                selected={selectedResources.includes(String(value.id))}
                position={index}
            >
                <IndexTable.Cell>
                    {`SP${value.id}`}
                </IndexTable.Cell>
                <IndexTable.Cell>
                    {value.name}
                </IndexTable.Cell>
                <IndexTable.Cell >
                    {convertISOToYMD(value.created_at)}
                </IndexTable.Cell>
                <IndexTable.Cell>
                    <Tooltip title="Export landing page" arrow>
                        <span><Button variant='plain' icon={PageDownIcon} onClick={() => openExportModal(value.id)} accessibilityLabel="Export landing page"></Button></span>
                    </Tooltip>
                    <span style={{ margin: "10px" }}></span>
                    <Tooltip title="Rename landing page" arrow>
                        <span><Button variant='plain' icon={WrenchIcon} onClick={() => openRenameModal(value.id, value.name)} accessibilityLabel="Rename landing page"></Button></span>
                    </Tooltip>
                    <span style={{ margin: "10px" }}></span>
                    <Tooltip title="Change theme" arrow>
                        <span><Button variant='plain' icon={ThemeEditIcon} onClick={() => openChangeThemeModal(value)} accessibilityLabel="Change theme"></Button></span>
                    </Tooltip>
                    <span style={{ margin: "10px" }}></span>
                    <Tooltip title="Open editor" arrow>
                        <span><Button variant='plain' icon={EditIcon} onClick={() => openLandingPreview(value.id)} accessibilityLabel="Open landing page editor"></Button></span>
                    </Tooltip>
                    <span style={{ margin: "10px" }}></span>
                    <Tooltip title="Preview landing page" arrow>
                        <span><Button variant='plain' icon={ViewIcon} onClick={() => openLandingPreview(value.id)} accessibilityLabel="Preview landing page"></Button></span>
                    </Tooltip>
                    <span style={{ margin: "10px" }}></span>
                    <Tooltip title="Translate landing page" arrow>
                        <span><Button variant='plain' icon={LanguageIcon} onClick={() => openTranslateModal(value.id)} accessibilityLabel="Translate landing page"></Button></span>
                    </Tooltip>
                    <span style={{ margin: "10px" }}></span>
                    <Tooltip title="Delete landing page" arrow>
                        <span><Button variant='plain' icon={DeleteIcon} onClick={() => deleteAngleTemplateHandler(value.id)} accessibilityLabel="Delete landing page"></Button></span>
                    </Tooltip>
                    <span style={{ margin: "10px" }}></span>
                    <Tooltip title="Duplicate landing page" arrow>
                        <span><Button variant='plain' icon={DuplicateIcon} onClick={() => duplicateAngleTemplateHandler(value.id)} accessibilityLabel="Duplicate landing page"></Button></span>
                    </Tooltip>
                </IndexTable.Cell>
            </IndexTable.Row >
        );
    });

    const openTranslateModal = (angleTemplateId) => {
        setCurrentAngleTemplateId(angleTemplateId);
        setTranslateModalOpen(true);
    };

    // Export modal functions
    const openExportModal = (angleTemplateId) => {
        setSelectedExportAngleTemplateId(angleTemplateId);
        setExportModalOpen(true);

        // Lazy-load thank you pages list for dropdown
        if (thankYouPages.length === 0) {
            fetch(route('thank-you-pages.api-index'))
                .then((response) => response.json())
                .then((result) => {
                    if (result && result.success && Array.isArray(result.data)) {
                        setThankYouPages(result.data);
                    }
                })
                .catch((err) => {
                    console.error('Failed to load thank you pages:', err);
                });
        }
    };

    const handleExport = () => {
        setExportModalOpen(false);
        if (selectedExportAngleTemplateId) {
            const baseUrl = (window.appURL && !window.appURL.includes('localhost') && !window.appURL.includes('127.0.0.1')) 
                ? window.appURL 
                : window.location.origin;
            const tyId = selectedThankYouPageId || '';
            const url = `${baseUrl}/download?angle_template_id=${selectedExportAngleTemplateId}` + (tyId !== '' ? `&thank_you_page_id=${encodeURIComponent(tyId)}` : '');
            window.open(url, "_blank");
        }
    };

    const handleLanguageSelect = () => {
        if (!selectedLanguage) {
            alert('Please select a language');
            return;
        }
        setTranslateModalOpen(false);
        setTranslateActionModalOpen(true);
    };

    const handleTranslateAction = (shouldDuplicate) => {
        setTranslateActionModalOpen(false);
        setTranslating(true);

        // Show loading overlay
        Swal.fire({
            title: 'Translating...',
            html: 'Please wait while we translate the page. This may take a few moments.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        if (shouldDuplicate) {
            // First duplicate, then translate
            fetch(route('duplicate.angleTemplate', currentAngleTemplateId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({})
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Now translate the duplicated template
                        translateAngleTemplate(data.data.angleTemplate.id);
                    } else {
                        throw new Error(data.message || 'Duplication failed');
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    Swal.close();
                    Swal.fire({
                        title: "Error!",
                        text: "An error occurred while duplicating the Landing Page.",
                        icon: "error"
                    });
                    setTranslating(false);
                });
        } else {
            // Just translate the original
            translateAngleTemplate(currentAngleTemplateId);
        }
    };

    const translateAngleTemplate = (angleTemplateId) => {
        fetch(route('translate.angleTemplate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                angle_template_id: angleTemplateId,
                target_language: selectedLanguage,
                split_sentences: splitSentences,
                preserve_formatting: preserveFormatting
            })
        })
            .then(response => response.json())
            .then(data => {
                setTranslating(false);
                Swal.close(); // Close loading overlay
                Swal.fire({
                    title: data.success ? "Translated!" : "Error!",
                    text: data.message,
                    icon: data.success ? "success" : "error"
                });
                if (data.success) {
                    setReload(!reload);
                }
                // Reset states
                setSelectedLanguage('');
                setCurrentAngleTemplateId(null);
                setSplitSentences('1');
                setPreserveFormatting('0');
            })
            .catch((error) => {
                console.error('Error:', error);
                setTranslating(false);
                Swal.close(); // Close loading overlay
                Swal.fire({
                    title: "Error!",
                    text: "An error occurred while translating the Landing Page.",
                    icon: "error"
                });
                // Reset states
                setSelectedLanguage('');
                setCurrentAngleTemplateId(null);
                setSplitSentences('1');
                setPreserveFormatting('0');
            });
    };

    const openRenameModal = (angleTemplateId, currentName) => {
        Swal.fire({
            title: 'Rename Landing Page',
            input: 'text',
            inputValue: currentName,
            showCancelButton: true,
            cancelButtonColor: "#d33",
            confirmButtonText: 'Done',
            confirmButtonColor: "#51a70a",
            customClass: {
                title: 'swal-title-left'
            },
            preConfirm: (newName) => {
                if (!newName || newName.trim() === '') {
                    Swal.showValidationMessage('Name cannot be empty');
                    return false;
                }
                return fetch(route('rename.angleTemplate'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ angle_template_id: angleTemplateId, name: newName })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) throw new Error(data.message || 'Rename failed');
                        return data;
                    })
                    .catch(err => {
                        Swal.showValidationMessage(`Request failed: ${err.message}`);
                    });
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    title: 'Renamed!',
                    text: result.value.message || 'Landing Page renamed successfully.',
                    icon: 'success'
                });
                setReload(!reload);
            }
        });
    }

    return (
        <AppProvider i18n={en}>
            <AuthenticatedLayout
                header={
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Landing Pages
                        </h2>
                        {organizationLandingPagesMode && (
                            <Text as="p" variant="bodySm" tone="subdued">
                                All landing pages in your organization. Clone a copy to any active member.
                            </Text>
                        )}
                    </div>
                }
            >
                <Head title="Landing Pages" />

                <div className="py-16">
                    {/* sm:px-6 lg:px-8 */}
                    <div className="mx-auto max-w-7xl">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6 text-gray-900">
                                <Box>
                                    <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "15px" }}>
                                        <div>
                                            {!organizationLandingPagesMode && (
                                                <Button onClick={openCreateLandingModal} variant="primary">
                                                    Create Landing Page
                                                </Button>
                                            )}
                                        </div>
                                        <ShopifySelect
                                            labelInline
                                            label="Rows:"
                                            options={pageOptions}
                                            value={pageCount}
                                            onChange={handlePageCount}
                                        />
                                    </div>
                                    <Card>
                                        <div>
                                            <IndexFilters
                                                sortOptions={sortOptions}
                                                sortSelected={sortSelected}
                                                queryValue={queryValue}
                                                queryPlaceholder={organizationLandingPagesMode ? 'Search landing pages…' : 'Search User Themes...'}
                                                onQueryChange={handleFiltersQueryChange}
                                                onQueryClear={handleQueryValueRemove}
                                                onSort={setSortSelected}
                                                cancelAction={{
                                                    onAction: onHandleCancel,
                                                    disabled: false,
                                                    loading: false,
                                                }}
                                                tabs={tabs}
                                                selected={selected}
                                                onSelect={setSelected}
                                                canCreateNewView={false}
                                                filters={filters}
                                                appliedFilters={appliedFilters}
                                                onClearAll={handleFiltersClearAll}
                                                mode={mode}
                                                setMode={setMode}
                                                loading={loading}
                                            />
                                        </div>
                                        <IndexTable
                                            resourceName={resourceName}
                                            itemCount={tableRows.length}
                                            selectedItemsCount={
                                                allResourcesSelected ? 'All ' : selectedResources.length
                                            }
                                            onSelectionChange={handleSelectionChange}
                                            promotedBulkActions={orgLandingPromotedBulkActions}
                                            headings={organizationLandingPagesMode ? [
                                                { title: 'ID' },
                                                { title: 'Name' },
                                                { title: 'Page owner' },
                                                { title: 'Date added' },
                                                { title: 'Actions' },
                                            ] : [
                                                { title: 'ID' },
                                                { title: 'Name' },
                                                { title: 'Date Added' },
                                                { title: 'Action' },
                                            ]}
                                            hasMoreItems
                                            selectable={organizationLandingPagesMode}
                                        >
                                            {rowMarkup}
                                        </IndexTable>
                                    </Card>
                                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', paddingTop: '22px', paddingBottom: '22px' }}>
                                        <Pagination hasNext={pagination.next_cursor ? true : false} hasPrevious={pagination.prev_cursor ? true : false} onNext={() => {
                                            setPagination({
                                                ...pagination,
                                                path: pagination.next_page_url
                                            })
                                            setCurrentCursor(pagination.next_cursor);
                                            setReload(!reload);
                                        }} onPrevious={() => {
                                            setPagination({
                                                ...pagination,
                                                path: pagination.prev_page_url
                                            })
                                            setCurrentCursor(pagination.prev_cursor);
                                            setReload(!reload);
                                        }} />
                                    </div>
                                </Box>
                            </div>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>

            <Modal
                open={changeThemeModalOpen}
                onClose={() => setChangeThemeModalOpen(false)}
                title="Change Theme"
                primaryAction={{
                    content: changeThemeSubmitting ? 'Changing...' : 'Change theme',
                    onAction: submitChangeTheme,
                    disabled: changeThemeSubmitting || !selectedChangeTheme?.value,
                }}
                secondaryActions={[
                    {
                        content: 'Cancel',
                        onAction: () => setChangeThemeModalOpen(false),
                    },
                ]}
            >
                <Modal.Section>
                    <div style={{ marginBottom: '14px' }}>
                        <Text as="p" variant="bodyMd">
                            {changeThemeTarget?.name ? (
                                <>Landing page: <strong>{changeThemeTarget.name}</strong></>
                            ) : (
                                'Select a new theme for this landing page.'
                            )}
                        </Text>
                        {changeThemeTarget?.template?.name && (
                            <Text as="p" variant="bodySm" tone="subdued">
                                Current theme: {changeThemeTarget.template.name}
                            </Text>
                        )}
                        <Text as="p" variant="bodySm" tone="subdued">
                            Article content and images are kept. Layout updates to the new theme shell.
                        </Text>
                    </div>
                    <Select
                        menuPortalTarget={document.body}
                        styles={{ menuPortal: (base) => ({ ...base, zIndex: 9999 }) }}
                        placeholder="Select new theme..."
                        options={changeThemeOptions}
                        value={selectedChangeTheme}
                        onChange={setSelectedChangeTheme}
                    />
                </Modal.Section>
            </Modal>

            <Modal
                open={createModalOpen}
                onClose={() => setCreateModalOpen(false)}
                title="Create Landing Page"
                primaryAction={{
                    content: createSubmitting ? 'Creating...' : 'Create',
                    onAction: submitCreateLandingPage,
                    disabled: createSubmitting || !selectedCreateAngle?.value || !selectedCreateTheme?.value,
                }}
                secondaryActions={[
                    {
                        content: 'Cancel',
                        onAction: () => setCreateModalOpen(false),
                    },
                ]}
            >
                <Modal.Section>
                    <div style={{ marginBottom: '14px' }}>
                        <Text as="p" variant="bodyMd">Select an Angle and a Theme to generate a landing page.</Text>
                    </div>
                    <div style={{ marginBottom: '14px' }}>
                        <Select
                            menuPortalTarget={document.body}
                            styles={{ menuPortal: (base) => ({ ...base, zIndex: 9999 }) }}
                            placeholder="Select Angle..."
                            options={createAngleOptions}
                            value={selectedCreateAngle}
                            onChange={setSelectedCreateAngle}
                        />
                    </div>
                    <div>
                        <Select
                            menuPortalTarget={document.body}
                            styles={{ menuPortal: (base) => ({ ...base, zIndex: 9999 }) }}
                            placeholder="Select Theme..."
                            options={createThemeOptions}
                            value={selectedCreateTheme}
                            onChange={(option) => {
                                setSelectedCreateTheme(option);
                                setCreateBdContents({});
                            }}
                        />
                    </div>
                    <div style={{ marginTop: '14px' }}>
                        <Checkbox
                            label="Create with structured BD content"
                            checked={createStructuredBd}
                            onChange={setCreateStructuredBd}
                            helpText="Recommended for all new landing pages. Fields are based on the selected theme's BD placeholders."
                        />
                    </div>
                    {createStructuredBd && (
                        <div style={{ marginTop: '16px' }}>
                            <Text as="p" variant="bodySm" tone="subdued">
                                Optional: content entered here will be added after the selected Angle's saved content for the matching slot.
                            </Text>
                            {selectedCreateTheme && selectedCreateThemeSlots.length === 0 && (
                                <div style={{ marginTop: '12px' }}>
                                    <Text as="p" variant="bodySm" tone="critical">
                                        This theme does not contain BD placeholders. Structured BD content cannot be rendered by this theme.
                                    </Text>
                                </div>
                            )}
                            {selectedCreateThemeSlots.map((slotKey) => (
                                <div key={slotKey} style={{ marginTop: '12px' }}>
                                    <Text as="p" variant="bodySm" fontWeight="semibold">{slotKey}</Text>
                                    <textarea
                                        value={createBdContents[slotKey] || ''}
                                        onChange={(event) => {
                                            setCreateBdContents((prev) => ({
                                                ...prev,
                                                [slotKey]: event.target.value,
                                            }));
                                        }}
                                        placeholder={`Enter ${slotKey} content...`}
                                        rows={3}
                                        style={{
                                            width: '100%',
                                            border: '1px solid #c9cccf',
                                            borderRadius: '6px',
                                            padding: '10px',
                                            marginTop: '6px',
                                            resize: 'vertical',
                                        }}
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                </Modal.Section>
            </Modal>

            {/* Language Selection Modal */}
            <Modal
                open={translateModalOpen}
                onClose={() => setTranslateModalOpen(false)}
                title="Translation Settings"
                primaryAction={{
                    content: 'Next',
                    onAction: handleLanguageSelect,
                }}
                secondaryActions={[
                    {
                        content: 'Cancel',
                        onAction: () => setTranslateModalOpen(false),
                    },
                ]}
            >
                <Modal.Section>
                    <ShopifySelect
                        label="Target Language"
                        options={targetLanguages}
                        value={selectedLanguage}
                        onChange={(value) => setSelectedLanguage(value)}
                        placeholder="Select a language"
                    />
                </Modal.Section>

                <Modal.Section>
                    {/* <Text variant="headingMd" as="h6">Translation Options</Text>

                    <div style={{ marginTop: '16px' }}>
                        <ShopifySelect
                            label="Split Sentences"
                            options={[
                                { label: 'No splitting (0)', value: '0' },
                                { label: 'Split into sentences (1)', value: '1' },
                                { label: 'Split but no new lines (nonewlines)', value: 'nonewlines' }
                            ]}
                            value={splitSentences}
                            onChange={(value) => setSplitSentences(value)}
                            helpText="Controls how sentences are handled during translation. Default: Split into sentences for better accuracy."
                        />
                    </div> */}

                    <div style={{ marginTop: '5px' }}>
                        <ShopifySelect
                            label="Preserve Formatting"
                            options={[
                                { label: 'No formatting preservation (0)', value: '0' },
                                { label: 'Preserve formatting (1)', value: '1' }
                            ]}
                            value={preserveFormatting}
                            onChange={(value) => setPreserveFormatting(value)}
                            helpText="Whether to preserve the original text's formatting (line breaks, spaces). Default: Preserve formatting."
                        />
                    </div>
                </Modal.Section>
            </Modal>

            {/* Translation Action Modal */}
            <Modal
                open={translateActionModalOpen}
                onClose={() => setTranslateActionModalOpen(false)}
                title="Translation Options"
                primaryAction={{
                    content: 'Translate Only',
                    onAction: () => handleTranslateAction(false),
                    loading: translating,
                }}
                secondaryActions={[
                    {
                        content: 'Duplicate & Translate',
                        onAction: () => handleTranslateAction(true),
                        loading: translating,
                    },
                    {
                        content: 'Cancel',
                        onAction: () => setTranslateActionModalOpen(false),
                    },
                ]}
            >
                <Modal.Section>
                    <p>Choose how you want to proceed with the translation:</p>
                    <ul style={{ marginTop: '10px', paddingLeft: '20px' }}>
                        <li><strong>Translate Only:</strong> Translate the current landing page directly</li>
                        <li><strong>Duplicate & Translate:</strong> Create a copy first, then translate the copy</li>
                    </ul>
                </Modal.Section>
            </Modal>

            {/* Export Modal */}
            <Modal
                open={exportModalOpen}
                onClose={() => setExportModalOpen(false)}
                title="Export Options"
                primaryAction={{
                    content: 'Export',
                    onAction: handleExport,
                }}
                secondaryActions={[
                    {
                        content: 'Cancel',
                        onAction: () => setExportModalOpen(false),
                    },
                ]}
            >
                <Modal.Section>
                    <p className="mb-3">You are going to export this landing page.</p>
                    <div className="space-y-2">
                        <label className="block text-sm font-medium text-gray-700">
                            Thank You Page
                        </label>
                        <select
                            className="mt-1 block w-full rounded-md border border-gray-300 bg-white py-2 px-3 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            value={selectedThankYouPageId}
                            onChange={(e) => setSelectedThankYouPageId(e.target.value)}
                        >
                            <option value="">Default thank you page</option>
                            {thankYouPages.map((page) => (
                                <option key={page.id} value={page.id}>
                                    {page.name}
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-gray-500">
                            Choose a custom thank you page for this export, or keep the default.
                        </p>
                    </div>
                </Modal.Section>
            </Modal>

            <Modal
                open={cloneLandingModalOpen}
                onClose={() => {
                    setCloneLandingModalOpen(false);
                    setCloneLandingBulkMode(false);
                }}
                title={cloneLandingBulkMode ? 'Clone landing pages to user' : 'Clone landing page to user'}
                primaryAction={{
                    content: cloneSubmitting ? 'Cloning…' : 'Clone',
                    onAction: submitCloneLandingToUser,
                    disabled: cloneSubmitting || cloneMembersLoading || !cloneTargetUser?.value,
                }}
                secondaryActions={[{
                    content: 'Cancel',
                    onAction: () => {
                        setCloneLandingModalOpen(false);
                        setCloneLandingBulkMode(false);
                    },
                }]}
            >
                <Modal.Section>
                    <Text as="p" variant="bodyMd">
                        {cloneLandingBulkMode ? (
                            <>
                                Duplicate <strong>{cloneTemplateName}</strong> into another organization member&apos;s account.
                                Each page is cloned as an independent copy.
                            </>
                        ) : (
                            <>
                                Duplicate <strong>{cloneTemplateName}</strong> into another organization member&apos;s account.
                                They receive an independent copy.
                            </>
                        )}
                    </Text>
                    <div style={{ marginTop: '16px' }}>
                        <Select
                            menuPortalTarget={document.body}
                            styles={{
                                menuPortal: (base) => ({ ...base, zIndex: 9999 }),
                            }}
                            isLoading={cloneMembersLoading}
                            placeholder={cloneMembersLoading ? 'Loading members…' : 'Select organization user…'}
                            options={cloneMemberOptions}
                            value={cloneTargetUser}
                            onChange={(v) => setCloneTargetUser(v)}
                        />
                    </div>
                </Modal.Section>
            </Modal>

        </AppProvider>
    );
}
