import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { Box } from '@mui/material';
import {
    AppProvider,
    Card,
    IndexFilters,
    IndexTable,
    Pagination,
    Select as ShopifySelect,
    Text,
    useSetIndexFiltersMode,
} from '@shopify/polaris';
import '@shopify/polaris/build/esm/styles.css';
import en from '@shopify/polaris/locales/en.json';
import { useCallback, useEffect, useMemo, useState } from 'react';

export default function AuditLogs() {
    const page = usePage().props;
    const permissions = page?.auth?.permissions || {};
    const canViewLogs = Boolean(permissions.audit_view_org || permissions.audit_view_cross_org);

    const [selected, setSelected] = useState(0);
    const [queryValue, setQueryValue] = useState('');
    const [actionFilter, setActionFilter] = useState('');
    const [sortSelected, setSortSelected] = useState(['created_at desc']);
    const [loading, setLoading] = useState(false);
    const [rows, setRows] = useState([]);
    const [actionOptions, setActionOptions] = useState([]);
    const [pageCount, setPageCount] = useState('20');
    const [currentCursor, setCurrentCursor] = useState(null);
    const [reload, setReload] = useState(true);
    const [pagination, setPagination] = useState({
        path: route('organization.audit.logs.index'),
        next_cursor: null,
        next_page_url: null,
        prev_cursor: null,
        prev_page_url: null,
    });

    let timeout = null;
    const { mode, setMode } = useSetIndexFiltersMode();

    const resourceName = useMemo(() => ({
        singular: 'Audit log',
        plural: 'Audit logs',
    }), []);

    useEffect(() => {
        if (!canViewLogs) {
            return;
        }
        let url = new URL(pagination.path);
        url.searchParams.set('page_count', pageCount);
        if (currentCursor) {
            url.searchParams.set('cursor', currentCursor);
        }
        if (queryValue) {
            url.searchParams.set('q', queryValue);
        } else {
            url.searchParams.delete('q');
        }
        if (actionFilter) {
            url.searchParams.set('action', actionFilter);
        } else {
            url.searchParams.delete('action');
        }
        if (sortSelected[0]) {
            url.searchParams.set('sort', sortSelected[0]);
        }

        setLoading(true);
        fetch(url.toString())
            .then((response) => response.json())
            .then((result) => {
                if (result.success) {
                    setRows(result?.data?.data || []);
                    setPagination({
                        path: result.data.path,
                        next_cursor: result.data.next_cursor,
                        next_page_url: result.data.next_page_url,
                        prev_cursor: result.data.prev_cursor,
                        prev_page_url: result.data.prev_page_url,
                    });
                    setActionOptions(result.data2 || []);
                } else {
                    setRows([]);
                }
            })
            .finally(() => setLoading(false));
    }, [reload, canViewLogs]);

    useEffect(() => {
        setCurrentCursor(null);
        setReload((v) => !v);
    }, [sortSelected, pageCount, actionFilter]);

    const handleFiltersQueryChange = useCallback((value) => {
        setQueryValue(value);
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            setCurrentCursor(null);
            setReload((v) => !v);
        }, 400);
    }, []);

    const rowMarkup = rows.map((value, index) => (
        <IndexTable.Row id={value.id} key={value.id} position={index}>
            <IndexTable.Cell>{value.id}</IndexTable.Cell>
            <IndexTable.Cell>{value.action}</IndexTable.Cell>
            <IndexTable.Cell>{value.organization?.name || '-'}</IndexTable.Cell>
            <IndexTable.Cell>{value.actor?.name || '-'}</IndexTable.Cell>
            <IndexTable.Cell>{new Date(value.created_at).toLocaleString()}</IndexTable.Cell>
            <IndexTable.Cell>
                <Text as="span" tone="subdued">{JSON.stringify(value.metadata || {})}</Text>
            </IndexTable.Cell>
        </IndexTable.Row>
    ));

    return (
        <AppProvider i18n={en}>
            <AuthenticatedLayout
                header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Audit Logs</h2>}
            >
                <Head title="Audit Logs" />
                <div className="py-16">
                    <div className="mx-auto max-w-7xl">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6 text-gray-900">
                                {!canViewLogs ? (
                                    <p>You do not have permission to view audit logs.</p>
                                ) : (
                                    <Box>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '15px', gap: '10px' }}>
                                            <ShopifySelect
                                                labelInline
                                                label="Rows:"
                                                options={[
                                                    { label: '10', value: '10' },
                                                    { label: '20', value: '20' },
                                                    { label: '50', value: '50' },
                                                    { label: '100', value: '100' },
                                                ]}
                                                value={pageCount}
                                                onChange={setPageCount}
                                            />
                                            <ShopifySelect
                                                labelInline
                                                label="Action:"
                                                options={[
                                                    { label: 'All actions', value: '' },
                                                    ...actionOptions.map((action) => ({ label: action, value: action })),
                                                ]}
                                                value={actionFilter}
                                                onChange={setActionFilter}
                                            />
                                        </div>
                                        <Card>
                                            <IndexFilters
                                                sortOptions={[
                                                    { label: 'Created', value: 'created_at desc', directionLabel: 'Newest first' },
                                                    { label: 'Created', value: 'created_at asc', directionLabel: 'Oldest first' },
                                                    { label: 'Action', value: 'action asc', directionLabel: 'Ascending' },
                                                    { label: 'Action', value: 'action desc', directionLabel: 'Descending' },
                                                ]}
                                                sortSelected={sortSelected}
                                                queryValue={queryValue}
                                                queryPlaceholder="Search action, org, or actor..."
                                                onQueryChange={handleFiltersQueryChange}
                                                onQueryClear={() => {
                                                    setQueryValue('');
                                                    setCurrentCursor(null);
                                                    setReload((v) => !v);
                                                }}
                                                onSort={setSortSelected}
                                                cancelAction={{ onAction: () => {}, disabled: false, loading: false }}
                                                tabs={[]}
                                                selected={selected}
                                                onSelect={setSelected}
                                                canCreateNewView={false}
                                                filters={[]}
                                                appliedFilters={[]}
                                                onClearAll={() => {
                                                    setQueryValue('');
                                                    setActionFilter('');
                                                    setCurrentCursor(null);
                                                    setReload((v) => !v);
                                                }}
                                                mode={mode}
                                                setMode={setMode}
                                                loading={loading}
                                            />
                                            <IndexTable
                                                resourceName={resourceName}
                                                itemCount={rows.length}
                                                selectedItemsCount={0}
                                                onSelectionChange={() => {}}
                                                selectable={false}
                                                headings={[
                                                    { title: 'ID' },
                                                    { title: 'Action' },
                                                    { title: 'Organization' },
                                                    { title: 'Actor' },
                                                    { title: 'Created At' },
                                                    { title: 'Metadata' },
                                                ]}
                                            >
                                                {rowMarkup}
                                            </IndexTable>
                                        </Card>
                                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', paddingTop: '22px', paddingBottom: '22px' }}>
                                            <Pagination
                                                hasNext={Boolean(pagination.next_cursor)}
                                                hasPrevious={Boolean(pagination.prev_cursor)}
                                                onNext={() => {
                                                    setPagination((prev) => ({ ...prev, path: prev.next_page_url }));
                                                    setCurrentCursor(pagination.next_cursor);
                                                    setReload((v) => !v);
                                                }}
                                                onPrevious={() => {
                                                    setPagination((prev) => ({ ...prev, path: prev.prev_page_url }));
                                                    setCurrentCursor(pagination.prev_cursor);
                                                    setReload((v) => !v);
                                                }}
                                            />
                                        </div>
                                    </Box>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        </AppProvider>
    );
}
