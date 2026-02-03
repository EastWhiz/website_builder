import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Box, Button as MuiButton } from '@mui/material';

import {
    AppProvider, Button, Card, IndexFilters,
    IndexTable,
    Pagination,
    Select as ShopifySelect, Text, useIndexResourceState, useSetIndexFiltersMode
} from '@shopify/polaris';
import { DeleteIcon, EditIcon, WrenchIcon } from '@shopify/polaris-icons';
import "@shopify/polaris/build/esm/styles.css";
import en from "@shopify/polaris/locales/en.json";
import { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';


export default function Dashboard() {

    const page = usePage().props;
    const roleId = page?.auth?.user?.role_id;
    // console.log(roleId);

    const [selected, setSelected] = useState(0);

    let timeout = null;

    const resourceName = {
        singular: 'Theme',
        plural: 'Themes',
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
        path: route("templates.list"),
        next_cursor: null,
        next_page_url: null,
        prev_cursor: null,
        prev_page_url: null,
    });
    const [currentCursor, setCurrentCursor] = useState(null);
    const [loading, setLoading] = useState(false);
    const [reload, setReload] = useState(true);
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

    const deleteTemplateHandler = (deleteId) => {
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

                fetch(route('delete.template'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        template_id: deleteId,
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

    const renameTemplateHandler = (template) => {
        Swal.fire({
            title: 'Rename Theme',
            input: 'text',
            inputLabel: 'New name',
            inputValue: template.name,
            showCancelButton: true,
            confirmButtonColor: '#51a70a',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Rename',
            inputValidator: (value) => {
                if (!value || value.trim() === '') {
                    return 'Please enter a name!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(route('rename.template'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        template_id: template.id,
                        name: result.value
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        Swal.fire({
                            title: data.success ? 'Renamed!' : 'Error!',
                            text: data.message,
                            icon: data.success ? 'success' : 'error'
                        });
                        setReload(!reload);
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire({ title: 'Error', text: 'Unable to rename', icon: 'error' });
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
                {`P${value.id}`}
            </IndexTable.Cell>
            <IndexTable.Cell>
                {value.name}
            </IndexTable.Cell>
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
            {roleId == 1 &&
                <IndexTable.Cell>
                    <Button variant='plain' icon={WrenchIcon} onClick={() => renameTemplateHandler(value)}></Button>
                    <span style={{ marginLeft: "10px" }}></span>
                    <Button variant='plain' icon={EditIcon} onClick={() => router.get(route('editTemplate', value.id))}></Button>
                    <span style={{ marginLeft: "10px" }}></span>
                    <Button variant='plain' icon={DeleteIcon} onClick={() => deleteTemplateHandler(value.id)}></Button>
                </IndexTable.Cell>
            }
        </IndexTable.Row >
    ));

    return (
        <AppProvider i18n={en}>
            <AuthenticatedLayout
                header={
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Themes
                    </h2>
                }
            >
                <Head title="Themes" />

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
                                        {page && page.auth.user.role.name == "admin" &&
                                            <>
                                                <span style={{ marginRight: "10px" }}></span>
                                                <MuiButton variant='contained' color='primary' onClick={() => router.get(route('addTemplate'))} sx={{ textTransform: "capitalize", height: "31px" }}>Add</MuiButton>
                                            </>
                                        }
                                    </div>
                                    <Card>
                                        <div>
                                            <IndexFilters
                                                sortOptions={sortOptions}
                                                sortSelected={sortSelected}
                                                queryValue={queryValue}
                                                queryPlaceholder="Search Themes..."
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
                                                { title: 'Body Count', alignment: 'center' },
                                                { title: 'CSS Count', alignment: 'center' },
                                                { title: 'JS Count', alignment: 'center' },
                                                { title: 'Image Count', alignment: 'center' },
                                                { title: 'Font Count', alignment: 'center' },
                                                ...(roleId == 1 ? [{ title: 'Action' }] : []),
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
        </AppProvider>
    );
}
