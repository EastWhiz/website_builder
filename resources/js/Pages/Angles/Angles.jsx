import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Box, Button as MuiButton } from '@mui/material';
import {
    AppProvider, Button,
    Card,
    IndexFilters,
    IndexTable,
    Modal,
    Pagination,
    Select as ShopifySelect,
    Text,
    useIndexResourceState, useSetIndexFiltersMode
} from '@shopify/polaris';
import { DeleteIcon, DuplicateIcon, EditIcon, LanguageIcon, ViewIcon } from '@shopify/polaris-icons';
import "@shopify/polaris/build/esm/styles.css";
import en from "@shopify/polaris/locales/en.json";
import { useCallback, useEffect, useState } from 'react';
import Select from 'react-select';
import Swal from 'sweetalert2';


export default function Dashboard() {

    const page = usePage().props;
    const roleId = page?.auth?.user?.role_id;
    const permissions = page?.auth?.permissions || {};
    const canTransferInOrg = Boolean(permissions.content_transfer_in_org);
    const canCloneCrossOrg = Boolean(permissions.content_clone_cross_org);
    // console.log(roleId);

    const [selected, setSelected] = useState(0);

    let timeout = null;

    const resourceName = {
        singular: 'Angle',
        plural: 'Angles',
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

    const [sortSelected, setSortSelected] = useState(['id asc']);
    const [queryValue, setQueryValue] = useState("");
    const { mode, setMode } = useSetIndexFiltersMode();
    const onHandleCancel = () => { };

    const [pagination, setPagination] = useState({
        path: route("angles.list"),
        next_cursor: null,
        next_page_url: null,
        prev_cursor: null,
        prev_page_url: null,
    });
    const [currentCursor, setCurrentCursor] = useState(null);
    const [loading, setLoading] = useState(false);
    const [myUrl, setMyUrl] = useState("");
    const [reload, setReload] = useState(true);
    const { selectedResources, allResourcesSelected, handleSelectionChange } = useIndexResourceState(tableRows);
    const handlePageCount = useCallback((value) => { setPageCount(value); setCurrentCursor(null); setReload(!reload); }, [tableRows]);

    const [templateOptions, setTemplateOptions] = useState([]);
    const [selectedTemplateOptions, setSelectedTemplateOptions] = useState([]);
    const [active, setActive] = useState(false);

    const [usersOptions, setUsersOptions] = useState([]);
    const [activeTwo, setActiveTwo] = useState(false);
    const [selectedUsersOption, setSelectedUsersOption] = useState([]);
    const [assignOrgModalOpen, setAssignOrgModalOpen] = useState(false);
    const [assignOrgSubmitting, setAssignOrgSubmitting] = useState(false);
    const [assignOrgId, setAssignOrgId] = useState('');
    const [inOrgAssignModalOpen, setInOrgAssignModalOpen] = useState(false);
    const [inOrgAssignSubmitting, setInOrgAssignSubmitting] = useState(false);
    const [inOrgTargetUser, setInOrgTargetUser] = useState(null);
    const [cloneModalOpen, setCloneModalOpen] = useState(false);
    const [cloneSubmitting, setCloneSubmitting] = useState(false);
    const [cloneSourceOrgId, setCloneSourceOrgId] = useState('');
    const [cloneTargetOrgId, setCloneTargetOrgId] = useState('');
    const [cloneTargetUserId, setCloneTargetUserId] = useState('');
    const [organizations, setOrganizations] = useState([]);
    const [allUsers, setAllUsers] = useState([]);

    // Translation state
    const [translateModalOpen, setTranslateModalOpen] = useState(false);
    const [translateActionModalOpen, setTranslateActionModalOpen] = useState(false);
    const [selectedLanguage, setSelectedLanguage] = useState('');
    const [currentAngleId, setCurrentAngleId] = useState(null);
    const [translating, setTranslating] = useState(false);

    // Translation options state
    const [splitSentences, setSplitSentences] = useState('1'); // Default: split sentences
    const [preserveFormatting, setPreserveFormatting] = useState('0'); // Default: preserve formatting

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

        setMyUrl(url);
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
                    setTemplateOptions(result.data2.map((value, index) => {
                        return { value: value.id, label: value.name }
                    }))

                    setUsersOptions(result.data3.map((value, index) => {
                        return { value: value.id, label: value.name }
                    }));
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

    const selectThemesHandler = () => {
        const formData = new FormData();
        formData.append('angles_ids', JSON.stringify(selectedResources));
        formData.append('all_check', allResourcesSelected);
        formData.append('search_query', JSON.stringify(myUrl.search));
        formData.append('selected_templates', JSON.stringify(selectedTemplateOptions));

        fetch(route('angles.applying'), {
            method: 'POST',
            body: formData,
        })
            .then((response) => response.json())
            .then((data) => {
                console.log(data);
                if (data.success == true) {
                    handleSelectionChange('all', false)
                    setReload(!reload);
                    setActive(false);
                    Swal.fire("Success!", data.message, "success");
                }
            })
            .catch((error) => {

                console.error(error);
            });
    }

    const assignToOtherUsersHandler = () => {
        const formData = new FormData();
        formData.append('angles_ids', JSON.stringify(selectedResources));
        formData.append('all_check', allResourcesSelected);
        formData.append('search_query', JSON.stringify(myUrl.search));
        formData.append('selected_user', JSON.stringify(selectedUsersOption));
        fetch(route('assign.to.users'), {
            method: 'POST',
            body: formData,
        })
            .then((response) => response.json())
            .then((data) => {
                console.log(data);
                if (data.success == true) {
                    handleSelectionChange('all', false)
                    setReload(!reload);
                    setActiveTwo(false);
                    Swal.fire("Success!", data.message, "success");
                }
            })
            .catch((error) => {

                console.error(error);
            });
    }

    const duplicateAnglesHandler = () => {
        const formData = new FormData();
        formData.append('angles_ids', JSON.stringify(selectedResources));
        formData.append('all_check', allResourcesSelected);
        formData.append('search_query', JSON.stringify(myUrl.search));
        fetch(route('duplicate.angles'), {
            method: 'POST',
            body: formData,
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    handleSelectionChange('all', false);
                    setReload(!reload);
                    Swal.fire("Success!", data.message, "success");
                } else {
                    Swal.fire("Error!", data.message, "error");
                }
            })
            .catch((error) => {
                Swal.fire("Error!", error.message, "error");
            });
    }

    const getSelectedRows = () => tableRows.filter((row) => selectedResources.includes(row.id));

    const ensureExplicitSelection = () => {
        if (allResourcesSelected) {
            Swal.fire("Selection required", "Please select specific rows only for this action.", "warning");
            return false;
        }
        if (selectedResources.length === 0) {
            Swal.fire("Selection required", "Please select at least one angle.", "warning");
            return false;
        }
        return true;
    };

    const fetchOrganizations = async () => {
        const url = new URL(route('admin.organizations.list'));
        url.searchParams.set('page_count', '500');
        const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Could not load organizations.');
        }
        return result?.data?.data || [];
    };

    const fetchAllUsers = async () => {
        const url = new URL(route('users.list'));
        url.searchParams.set('page_count', '500');
        const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Could not load users.');
        }
        return result?.data?.data || [];
    };

    const openAssignOrgModal = async () => {
        if (!ensureExplicitSelection()) return;
        try {
            if (organizations.length === 0) {
                const orgRows = await fetchOrganizations();
                setOrganizations(orgRows);
            }
            setAssignOrgId('');
            setAssignOrgModalOpen(true);
        } catch (error) {
            Swal.fire("Error!", error?.message || 'Could not open organization assignment.', "error");
        }
    };

    const submitAssignOrg = async () => {
        if (!assignOrgId) return;
        const rows = getSelectedRows();
        if (rows.length === 0) {
            Swal.fire("Invalid selection", "Please reselect rows and try again.", "error");
            return;
        }

        try {
            setAssignOrgSubmitting(true);
            const response = await fetch(route('admin.organizations.assignContent'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '',
                },
                body: JSON.stringify({
                    organization_id: Number(assignOrgId),
                    items: rows.map((row) => ({ type: 'angle', id: Number(row.id) })),
                }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Organization assignment failed.');
            }

            setAssignOrgModalOpen(false);
            handleSelectionChange('all', false);
            setReload(!reload);
            Swal.fire("Success!", result.message || 'Angles assigned to organization.', "success");
        } catch (error) {
            Swal.fire("Error!", error?.message || 'Organization assignment failed.', "error");
        } finally {
            setAssignOrgSubmitting(false);
        }
    };

    const openInOrgAssignModal = () => {
        if (!ensureExplicitSelection()) return;
        setInOrgTargetUser(null);
        setInOrgAssignModalOpen(true);
    };

    const submitInOrgAssign = async () => {
        if (!inOrgTargetUser?.value) return;
        const rows = getSelectedRows();
        if (rows.length === 0) {
            Swal.fire("Invalid selection", "Please reselect rows and try again.", "error");
            return;
        }

        try {
            setInOrgAssignSubmitting(true);
            const response = await fetch(route('organization.content.assign_to_user'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '',
                },
                body: JSON.stringify({
                    organization_id: null,
                    to_user_id: Number(inOrgTargetUser.value),
                    items: rows.map((row) => ({ type: 'angle', id: Number(row.id) })),
                }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'In-org user assignment failed.');
            }

            setInOrgAssignModalOpen(false);
            handleSelectionChange('all', false);
            setReload(!reload);
            Swal.fire("Success!", result.message || 'Angles assigned to user.', "success");
        } catch (error) {
            Swal.fire("Error!", error?.message || 'In-org user assignment failed.', "error");
        } finally {
            setInOrgAssignSubmitting(false);
        }
    };

    const openCloneModal = async () => {
        if (!ensureExplicitSelection()) return;
        try {
            if (organizations.length === 0) {
                const orgRows = await fetchOrganizations();
                setOrganizations(orgRows);
            }
            if (allUsers.length === 0) {
                const userRows = await fetchAllUsers();
                setAllUsers(userRows);
            }
            const selectedOrgIds = [...new Set(getSelectedRows().map((row) => Number(row.organization_id || 0)).filter((v) => v > 0))];
            if (selectedOrgIds.length !== 1) {
                Swal.fire("Invalid selection", "Selected rows must belong to exactly one source organization for clone.", "error");
                return;
            }
            setCloneSourceOrgId(String(selectedOrgIds[0]));
            setCloneTargetOrgId('');
            setCloneTargetUserId('');
            setCloneModalOpen(true);
        } catch (error) {
            Swal.fire("Error!", error?.message || 'Could not open clone modal.', "error");
        }
    };

    const submitClone = async () => {
        if (!cloneSourceOrgId || !cloneTargetOrgId || !cloneTargetUserId) return;
        const rows = getSelectedRows();
        if (rows.length === 0) {
            Swal.fire("Invalid selection", "Please reselect rows and try again.", "error");
            return;
        }

        try {
            setCloneSubmitting(true);
            const response = await fetch(route('admin.organizations.cloneContent'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '',
                },
                body: JSON.stringify({
                    source_organization_id: Number(cloneSourceOrgId),
                    target_organization_id: Number(cloneTargetOrgId),
                    target_user_id: Number(cloneTargetUserId),
                    items: rows.map((row) => ({ type: 'angle', id: Number(row.id) })),
                }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Cross-org clone failed.');
            }

            setCloneModalOpen(false);
            handleSelectionChange('all', false);
            setReload(!reload);
            Swal.fire("Success!", result.message || 'Angles cloned successfully.', "success");
        } catch (error) {
            Swal.fire("Error!", error?.message || 'Cross-org clone failed.', "error");
        } finally {
            setCloneSubmitting(false);
        }
    };

    const promotedBulkActions = [
        {
            content: 'Select Themes',
            onAction: () => { setActive(true) },
        },
        {
            content: 'Duplicate Angles',
            onAction: duplicateAnglesHandler,
        },
        ...(roleId && roleId == 1 ? [{
            content: 'Assign to User',
            onAction: () => { setActiveTwo(true) },
        }] : []),
        ...(canTransferInOrg && roleId == 1 ? [{
            content: 'Assign to Organization',
            onAction: openAssignOrgModal,
        }] : []),
        ...(canTransferInOrg ? [{
            content: 'Assign to Org User',
            onAction: openInOrgAssignModal,
        }] : []),
        ...(canCloneCrossOrg && roleId == 1 ? [{
            content: 'Clone Cross Org',
            onAction: openCloneModal,
        }] : [])
    ];


    const handleQueryValueRemove = useCallback(() => { setQueryValue(""); setCurrentCursor(null); setReload(!reload); }, [tableRows]);

    const handleFiltersClearAll = useCallback(() => {
        handleQueryValueRemove();
    }, [
        handleQueryValueRemove,
    ]);

    const deleteAngleHandler = (deleteId) => {
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

                fetch(route('delete.angle'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        angle_id: deleteId,
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

    const duplicateAngleHandler = (angleId) => {
        fetch(route('duplicate.angle', angleId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
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
            .catch(error => {
                Swal.fire({
                    title: "Error!",
                    text: error.message,
                    icon: "error"
                });
            });
    }

    const openTranslateModal = (angleId) => {
        setCurrentAngleId(angleId);
        setTranslateModalOpen(true);
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
            fetch(route('duplicate.angle', currentAngleId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({})
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Now translate the duplicated angle
                        translateAngle(data.data.angle.id);
                    } else {
                        throw new Error(data.message || 'Duplication failed');
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    Swal.close();
                    Swal.fire({
                        title: "Error!",
                        text: "An error occurred while duplicating the Angle.",
                        icon: "error"
                    });
                    setTranslating(false);
                });
        } else {
            // Just translate the original
            translateAngle(currentAngleId);
        }
    };

    const translateAngle = (angleId) => {
        fetch(route('translate.angle'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                angle_id: angleId,
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
                setCurrentAngleId(null);
                setSplitSentences('1');
                setPreserveFormatting('0');
            })
            .catch((error) => {
                console.error('Error:', error);
                setTranslating(false);
                Swal.close(); // Close loading overlay
                Swal.fire({
                    title: "Error!",
                    text: "An error occurred while translating the Angle.",
                    icon: "error"
                });
                // Reset states
                setSelectedLanguage('');
                setCurrentAngleId(null);
                setSplitSentences('1');
                setPreserveFormatting('0');
            });
    };

    const filters = [];

    const appliedFilters = [];

    const rowMarkup = tableRows.map((value, index) => (
        <IndexTable.Row
            id={value.id}
            key={value.id}
            selected={selectedResources.includes(value.id)}
            position={index}
        >
            <IndexTable.Cell>
                {`A${value.id}`}
            </IndexTable.Cell>
            <IndexTable.Cell>
                {value.name}
            </IndexTable.Cell>
            {roleId && roleId == 1 &&
                <IndexTable.Cell>
                    {`(ID: U${value.user.id}) ${value.user.name}`}
                </IndexTable.Cell>
            }
            <IndexTable.Cell >
                <Text as="span" alignment="center">
                    <Box component="span" sx={{ borderRadius: "50px", padding: "5px 10px", backgroundColor: "#d50000", color: "white" }}>
                        {value.contents.filter(iter => iter.type == "html").length}
                    </Box>
                </Text>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <Text as="span" alignment="center">
                    <Box component="span" sx={{ borderRadius: "50px", padding: "5px 10px", backgroundColor: "blue", color: "white" }}>
                        {value.contents.filter(iter => iter.type == "css").length}
                    </Box>
                </Text>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <Text as="span" alignment="center">
                    <Box component="span" sx={{ borderRadius: "50px", padding: "5px 10px", backgroundColor: "green", color: "white" }}>
                        {value.contents.filter(iter => iter.type == "js").length}
                    </Box>
                </Text>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <Text as="span" alignment="center">
                    <Box component="span" sx={{ borderRadius: "50px", padding: "5px 10px", backgroundColor: "purple", color: "white" }}>
                        {value.contents.filter(iter => iter.type == "image").length}
                    </Box>
                </Text>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <Text as="span" alignment="center">
                    <Box component="span" sx={{ borderRadius: "50px", padding: "5px 10px", backgroundColor: "#e99600", color: "white" }}>
                        {value.contents.filter(iter => iter.type == "font").length}
                    </Box>
                </Text>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <Button variant='plain' icon={ViewIcon} onClick={() => {
                    const baseUrl = (window.appURL && !window.appURL.includes('localhost') && !window.appURL.includes('127.0.0.1')) 
                        ? window.appURL 
                        : window.location.origin;
                    window.open(`${baseUrl}/angles/preview/${value.id}/`, "_blank");
                }}></Button>
                <span style={{ margin: "5px" }}></span>
                <Button variant='plain' icon={EditIcon} onClick={() => router.get(route('editAngle', value.id))}></Button>
                <span style={{ marginLeft: "10px" }}></span>
                <Button variant='plain' icon={LanguageIcon} onClick={() => openTranslateModal(value.id)}></Button>
                <span style={{ marginLeft: "10px" }}></span>
                <Button variant='plain' icon={DeleteIcon} onClick={() => deleteAngleHandler(value.id)}></Button>
                <span style={{ marginLeft: "10px" }}></span>
                <Button variant='plain' icon={DuplicateIcon} onClick={() => duplicateAngleHandler(value.id)}></Button>
            </IndexTable.Cell>
        </IndexTable.Row >
    ));

    const cloneTargetUsers = allUsers.filter((u) => Number(u.current_organization_id || 0) === Number(cloneTargetOrgId || 0));

    return (
        <AppProvider i18n={en}>
            <AuthenticatedLayout
                header={
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Angles
                    </h2>
                }
            >
                <Head title="Angles" />
                <Modal
                    open={active}
                    size='fullScreen'
                    onClose={() => setActive(false)}
                    title="Themes List"
                    primaryAction={{
                        content: 'Done',
                        onAction: () => selectThemesHandler(),
                    }}
                    secondaryActions={[
                        {
                            content: 'Cancel',
                            onAction: () => setActive(false),
                        },
                    ]}
                >
                    <Modal.Section>
                        <Select
                            menuPortalTarget={document.body}
                            styles={{
                                menuPortal: base => ({ ...base, zIndex: 9999 }),
                            }}
                            placeholder="Select Themes..."
                            options={templateOptions}
                            value={selectedTemplateOptions}
                            onChange={(e) => setSelectedTemplateOptions(e)}
                            isMulti
                            closeMenuOnSelect={false}
                        />
                    </Modal.Section>
                </Modal>
                <Modal
                    open={activeTwo}
                    size='fullScreen'
                    onClose={() => setActiveTwo(false)}
                    title="Users List"
                    primaryAction={{
                        content: 'Done',
                        onAction: () => assignToOtherUsersHandler(),
                    }}
                    secondaryActions={[
                        {
                            content: 'Cancel',
                            onAction: () => setActiveTwo(false),
                        },
                    ]}
                >
                    <Modal.Section>
                        <Select
                            menuPortalTarget={document.body}
                            styles={{
                                menuPortal: base => ({ ...base, zIndex: 9999 }),
                            }}
                            placeholder="Select User..."
                            options={usersOptions}
                            value={selectedUsersOption}
                            onChange={(e) => setSelectedUsersOption(e)}
                        />
                    </Modal.Section>
                </Modal>
                <Modal
                    open={assignOrgModalOpen}
                    onClose={() => setAssignOrgModalOpen(false)}
                    title="Assign Angles To Organization"
                    primaryAction={{
                        content: assignOrgSubmitting ? 'Assigning...' : 'Confirm Assign',
                        onAction: submitAssignOrg,
                        disabled: assignOrgSubmitting || !assignOrgId,
                    }}
                    secondaryActions={[{ content: 'Cancel', onAction: () => setAssignOrgModalOpen(false) }]}
                >
                    <Modal.Section>
                        <p style={{ marginBottom: '12px' }}>Selected angles: <strong>{selectedResources.length}</strong></p>
                        <ShopifySelect
                            label="Target Organization"
                            options={[
                                { label: 'Select organization...', value: '' },
                                ...organizations.map((org) => ({ label: org.name, value: String(org.id) })),
                            ]}
                            value={assignOrgId}
                            onChange={setAssignOrgId}
                        />
                    </Modal.Section>
                </Modal>
                <Modal
                    open={inOrgAssignModalOpen}
                    onClose={() => setInOrgAssignModalOpen(false)}
                    title="Assign Angles To User (In Organization)"
                    primaryAction={{
                        content: inOrgAssignSubmitting ? 'Assigning...' : 'Confirm Assign',
                        onAction: submitInOrgAssign,
                        disabled: inOrgAssignSubmitting || !inOrgTargetUser?.value,
                    }}
                    secondaryActions={[{ content: 'Cancel', onAction: () => setInOrgAssignModalOpen(false) }]}
                >
                    <Modal.Section>
                        <p style={{ marginBottom: '12px' }}>Selected angles: <strong>{selectedResources.length}</strong></p>
                        <Select
                            menuPortalTarget={document.body}
                            styles={{ menuPortal: base => ({ ...base, zIndex: 9999 }) }}
                            placeholder="Select organization user..."
                            options={usersOptions}
                            value={inOrgTargetUser}
                            onChange={(value) => setInOrgTargetUser(value)}
                        />
                    </Modal.Section>
                </Modal>
                <Modal
                    open={cloneModalOpen}
                    onClose={() => setCloneModalOpen(false)}
                    title="Clone Angles Across Organizations"
                    primaryAction={{
                        content: cloneSubmitting ? 'Cloning...' : 'Confirm Clone',
                        onAction: submitClone,
                        disabled: cloneSubmitting || !cloneSourceOrgId || !cloneTargetOrgId || !cloneTargetUserId,
                    }}
                    secondaryActions={[{ content: 'Cancel', onAction: () => setCloneModalOpen(false) }]}
                >
                    <Modal.Section>
                        <p style={{ marginBottom: '12px' }}>Selected angles: <strong>{selectedResources.length}</strong></p>
                        <ShopifySelect
                            label="Source Organization"
                            options={[
                                { label: 'Select source organization...', value: '' },
                                ...organizations.map((org) => ({ label: org.name, value: String(org.id) })),
                            ]}
                            value={cloneSourceOrgId}
                            onChange={setCloneSourceOrgId}
                            disabled
                        />
                        <div style={{ marginTop: '12px' }}>
                            <ShopifySelect
                                label="Target Organization"
                                options={[
                                    { label: 'Select target organization...', value: '' },
                                    ...organizations
                                        .filter((org) => String(org.id) !== String(cloneSourceOrgId))
                                        .map((org) => ({ label: org.name, value: String(org.id) })),
                                ]}
                                value={cloneTargetOrgId}
                                onChange={setCloneTargetOrgId}
                            />
                        </div>
                        <div style={{ marginTop: '12px' }}>
                            <ShopifySelect
                                label="Target User"
                                options={[
                                    { label: 'Select target user...', value: '' },
                                    ...cloneTargetUsers.map((user) => ({
                                        label: `${user.name} (U${user.id})`,
                                        value: String(user.id),
                                    })),
                                ]}
                                value={cloneTargetUserId}
                                onChange={setCloneTargetUserId}
                            />
                        </div>
                    </Modal.Section>
                </Modal>
                <div className="py-16">
                    {/* sm:px-6 lg:px-8 */}
                    <div className="mx-auto max-w-7xl">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6 text-gray-900">
                                <Box>
                                    <div style={{ display: "flex", justifyContent: "right", marginBottom: "15px" }}>
                                        <ShopifySelect
                                            labelInline
                                            label="Rows:"
                                            options={pageOptions}
                                            value={pageCount}
                                            onChange={handlePageCount}
                                        />
                                        {/* {page && page.auth.user.role.name == "admin" &&
                                            <> */}
                                        <span style={{ marginRight: "10px" }}></span>
                                        <MuiButton variant='contained' color='primary' onClick={() => router.get(route('addAngle'))} sx={{ textTransform: "capitalize", height: "31px" }}>Add</MuiButton>
                                        {/* </>
                                        } */}
                                    </div>
                                    <Card>
                                        <div>
                                            <IndexFilters
                                                sortOptions={sortOptions}
                                                sortSelected={sortSelected}
                                                queryValue={queryValue}
                                                queryPlaceholder="Search Angles..."
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
                                            headings={[
                                                { title: 'ID' },
                                                { title: 'Title' },
                                                ...(roleId && roleId == 1 ? [{ title: 'Assigned to User' }] : []),
                                                { title: 'Body Count', alignment: 'center' },
                                                { title: 'CSS Count', alignment: 'center' },
                                                { title: 'JS Count', alignment: 'center' },
                                                { title: 'Image Count', alignment: 'center' },
                                                { title: 'Font Count', alignment: 'center' },
                                                { title: 'Action' }
                                            ]}
                                            hasMoreItems
                                            selectable={true}
                                            promotedBulkActions={promotedBulkActions}
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
                        <li><strong>Translate Only:</strong> Translate the current angle directly (all bodies will be translated)</li>
                        <li><strong>Duplicate & Translate:</strong> Create a copy first, then translate the copy</li>
                    </ul>
                </Modal.Section>
            </Modal>

        </AppProvider>
    );
}
