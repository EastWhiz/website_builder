import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { Box } from '@mui/material';

import {
    AppProvider,
    Button,
    Card,
    IndexFilters,
    IndexTable,
    Modal,
    Pagination,
    Select as ShopifySelect,
    Text,
    useIndexResourceState, useSetIndexFiltersMode
} from '@shopify/polaris';
import { DeleteIcon, DuplicateIcon, EditIcon, LanguageIcon, PageDownIcon, ViewIcon, WrenchIcon } from '@shopify/polaris-icons';
import "@shopify/polaris/build/esm/styles.css";
import en from "@shopify/polaris/locales/en.json";
import { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function Dashboard() {

    const page = usePage().props;
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
        singular: 'Sales Page',
        plural: 'Sales Pages',
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
        path: route("userThemes.list", userId),
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
    const [isSelfHosted, setIsSelfHosted] = useState(false);

    const { selectedResources, allResourcesSelected, handleSelectionChange } = useIndexResourceState(tableRows);
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
            text: "Do you want to duplicate this Sales Page?",
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
                            text: "An error occurred while duplicating the Sales Page.",
                            icon: "error"
                        });
                    });
            }
        });
    }

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
                {`SP${value.id}`}
            </IndexTable.Cell>
            <IndexTable.Cell>
                {value.name}
            </IndexTable.Cell>
            <IndexTable.Cell >
                {convertISOToYMD(value.created_at)}
            </IndexTable.Cell>
            <IndexTable.Cell>
                <Button variant='plain' icon={PageDownIcon} onClick={() => openExportModal(value.id)}></Button>
                <span style={{ margin: "10px" }}></span>
                <Button variant='plain' icon={WrenchIcon} onClick={() => openRenameModal(value.id, value.name)}></Button>
                <span style={{ margin: "10px" }}></span>
                <Button variant='plain' icon={EditIcon} onClick={() => {
                    const baseUrl = (window.appURL && !window.appURL.includes('localhost') && !window.appURL.includes('127.0.0.1')) 
                        ? window.appURL 
                        : window.location.origin;
                    window.open(`${baseUrl}/angle-templates/preview/${value.id}/`, "_blank");
                }}></Button>
                <span style={{ margin: "10px" }}></span>
                <Button variant='plain' icon={ViewIcon} onClick={() => {
                    const baseUrl = (window.appURL && !window.appURL.includes('localhost') && !window.appURL.includes('127.0.0.1')) 
                        ? window.appURL 
                        : window.location.origin;
                    window.open(`${baseUrl}/angle-templates/preview/${value.id}/`, "_blank");
                }}></Button>
                <span style={{ margin: "10px" }}></span>
                <Button variant='plain' icon={LanguageIcon} onClick={() => openTranslateModal(value.id)}></Button>
                <span style={{ margin: "10px" }}></span>
                <Button variant='plain' icon={DeleteIcon} onClick={() => deleteAngleTemplateHandler(value.id)}></Button>
                <span style={{ margin: "10px" }}></span>
                <Button variant='plain' icon={DuplicateIcon} onClick={() => duplicateAngleTemplateHandler(value.id)}></Button>
            </IndexTable.Cell>
        </IndexTable.Row >
    ));

    const openTranslateModal = (angleTemplateId) => {
        setCurrentAngleTemplateId(angleTemplateId);
        setTranslateModalOpen(true);
    };

    // Export modal functions
    const openExportModal = (angleTemplateId) => {
        setSelectedExportAngleTemplateId(angleTemplateId);
        setExportModalOpen(true);
        setIsSelfHosted(false); // Reset to default
    };

    const handleExport = () => {
        setExportModalOpen(false);
        if (selectedExportAngleTemplateId) {
            const isSelfHostedParam = isSelfHosted ? '&is_self_hosted=true' : '&is_self_hosted=false';
            const baseUrl = (window.appURL && !window.appURL.includes('localhost') && !window.appURL.includes('127.0.0.1')) 
                ? window.appURL 
                : window.location.origin;
            window.open(`${baseUrl}/download?angle_template_id=${selectedExportAngleTemplateId}${isSelfHostedParam}`, "_blank");
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
                    Swal.fire({
                        title: "Error!",
                        text: "An error occurred while duplicating the Sales Page.",
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
                Swal.fire({
                    title: "Error!",
                    text: "An error occurred while translating the Sales Page.",
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
            title: 'Rename Sales Page',
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
                    text: result.value.message || 'Sales Page renamed successfully.',
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
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Sales Pages
                    </h2>
                }
            >
                <Head title="Sales Pages" />

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
                                    </div>
                                    <Card>
                                        <div>
                                            <IndexFilters
                                                sortOptions={sortOptions}
                                                sortSelected={sortSelected}
                                                queryValue={queryValue}
                                                queryPlaceholder="Search User Themes..."
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
                                                { title: 'Name' },
                                                { title: 'Date Added' },
                                                { title: 'Action' },
                                            ]}
                                            hasMoreItems
                                            selectable={false}
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
                        <li><strong>Translate Only:</strong> Translate the current sales page directly</li>
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
                    <p>Choose how you want to export this sales page:</p>
                    <div style={{ marginTop: '20px' }}>
                        <label style={{ display: 'flex', alignItems: 'center', gap: '10px', cursor: 'pointer' }}>
                            <input
                                type="checkbox"
                                checked={isSelfHosted}
                                onChange={(e) => setIsSelfHosted(e.target.checked)}
                            />
                            <Text as="span">
                                <strong>Self-hosted mode</strong> - Skip external API calls and send form data directly to CRM
                            </Text>
                        </label>
                    </div>
                    <div style={{ marginTop: '15px', fontSize: '14px', color: '#666' }}>
                        <ul style={{ paddingLeft: '20px', margin: 0 }}>
                            <li><strong>Self-hosted enabled:</strong> Forms will send data directly to your CRM without external API calls</li>
                            <li><strong>Self-hosted disabled:</strong> Forms will use the current API flow as configured</li>
                        </ul>
                    </div>
                </Modal.Section>
            </Modal>

        </AppProvider>
    );
}
