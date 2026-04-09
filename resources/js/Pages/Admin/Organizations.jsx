import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
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
import { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function Organizations() {
    const resourceName = { singular: 'Organization', plural: 'Organizations' };

    const pageOptions = [
        { label: '5', value: '5' },
        { label: '10', value: '10' },
        { label: '20', value: '20' },
        { label: '50', value: '50' },
        { label: '100', value: '100' },
    ];

    const sortOptions = [
        { label: 'Id', value: 'id desc', directionLabel: 'Descending' },
        { label: 'Id', value: 'id asc', directionLabel: 'Ascending' },
        { label: 'Name', value: 'name asc', directionLabel: 'Ascending' },
        { label: 'Name', value: 'name desc', directionLabel: 'Descending' },
        { label: 'Created', value: 'created_at desc', directionLabel: 'Descending' },
        { label: 'Created', value: 'created_at asc', directionLabel: 'Ascending' },
    ];

    const statusOptions = [
        { label: 'All', value: '' },
        { label: 'Active', value: 'active' },
        { label: 'Deactivated', value: 'deactivated' },
    ];

    const { mode, setMode } = useSetIndexFiltersMode();

    const [tableRows, setTableRows] = useState([]);

    const [pageCount, setPageCount] = useState('10');
    const [sortSelected, setSortSelected] = useState(['id desc']);
    const [statusSelected, setStatusSelected] = useState('');
    const [queryValue, setQueryValue] = useState('');
    const [loading, setLoading] = useState(false);
    const [updatingStatusId, setUpdatingStatusId] = useState(null);
    const [reload, setReload] = useState(true);
    const [currentCursor, setCurrentCursor] = useState(null);
    const [pagination, setPagination] = useState({
        path: route('admin.organizations.list'),
        next_cursor: null,
        next_page_url: null,
        prev_cursor: null,
        prev_page_url: null,
    });

    let timeout = null;

    const fetchData = useCallback(() => {
        let url = new URL(pagination.path);

        url.searchParams.set('page_count', pageCount);

        if (currentCursor) {
            url.searchParams.set('cursor', currentCursor);
        }

        if (queryValue !== '') {
            url.searchParams.set('q', queryValue);
        } else {
            url.searchParams.delete('q');
        }

        if (statusSelected !== '') {
            url.searchParams.set('status', statusSelected);
        } else {
            url.searchParams.delete('status');
        }

        if (sortSelected !== '') {
            url.searchParams.set('sort', sortSelected[0]);
        } else {
            url.searchParams.delete('sort');
        }

        setLoading(true);
        fetch(url.toString(), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((result) => {
                if (result.success && result.data) {
                    setTableRows(result.data.data || []);
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
            .catch(() => setLoading(false));
    }, [pagination.path, pageCount, currentCursor, queryValue, statusSelected, sortSelected]);

    useEffect(() => {
        fetchData();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reload]);

    useEffect(() => {
        setCurrentCursor(null);
        setReload(!reload);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sortSelected, statusSelected, pageCount]);

    const handleFiltersQueryChange = useCallback(
        (value) => {
            setQueryValue(value);
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                setCurrentCursor(null);
                setReload((v) => !v);
            }, 400);
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        []
    );

    const handleQueryValueRemove = useCallback(() => {
        setQueryValue('');
        setCurrentCursor(null);
        setReload((v) => !v);
    }, []);

    const appliedFilters = statusSelected
        ? [
              {
                  key: 'status',
                  label: `Status: ${statusSelected}`,
                  onRemove: () => setStatusSelected(''),
              },
          ]
        : [];

    const rowMarkup = tableRows.map((org, index) => (
        <IndexTable.Row
            id={String(org.id)}
            key={org.id}
            position={index}
        >
            <IndexTable.Cell>
                <div onClick={(e) => e.stopPropagation()}>{org.name}</div>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <div onClick={(e) => e.stopPropagation()}>{org.owner?.name || '-'}</div>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <div onClick={(e) => e.stopPropagation()}>{org.owner?.email || '-'}</div>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <div onClick={(e) => e.stopPropagation()}>{org.owner?.phone || '-'}</div>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <div onClick={(e) => e.stopPropagation()}>
                    <span
                        className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                            org.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }`}
                    >
                        {org.status === 'active' ? 'Active' : 'Deactivated'}
                    </span>
                </div>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <div onClick={(e) => e.stopPropagation()}>
                    {org.created_at
                        ? new Date(org.created_at).toLocaleDateString(undefined, {
                              year: 'numeric',
                              month: 'short',
                              day: '2-digit',
                          })
                        : '-'}
                </div>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <div className="flex gap-2 flex-wrap" onClick={(e) => e.stopPropagation()}>
                    <Link
                        href={route('admin.organizations.viewPage', org.id)}
                        className="px-2 py-1 text-xs rounded border border-indigo-300 text-indigo-700 hover:bg-indigo-50"
                    >
                        View
                    </Link>
                    <Link
                        href={route('admin.organizations.editPage', org.id)}
                        className="px-2 py-1 text-xs rounded border border-blue-300 text-blue-700 hover:bg-blue-50"
                    >
                        Edit
                    </Link>
                    <button
                        type="button"
                        onClick={() =>
                            handleStatusUpdate(org.id, org.status === 'active' ? 'deactivated' : 'active')
                        }
                        disabled={updatingStatusId === org.id}
                        className={`inline-flex items-center gap-1.5 px-2 py-1 text-xs rounded border disabled:opacity-60 disabled:cursor-not-allowed ${
                            org.status === 'active'
                                ? 'border-red-300 text-red-700 hover:bg-red-50'
                                : 'border-green-300 text-green-700 hover:bg-green-50'
                        }`}
                    >
                        {updatingStatusId === org.id ? (
                            <>
                                <svg
                                    className="animate-spin h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                Updating...
                            </>
                        ) : (
                            org.status === 'active' ? 'Deactivate' : 'Activate'
                        )}
                    </button>
                </div>
            </IndexTable.Cell>
        </IndexTable.Row>
    ));

    const handleStatusUpdate = async (id, status) => {
        const isActivating = status === 'active';
        const confirm = await Swal.fire({
            title: isActivating ? 'Activate organization?' : 'Deactivate organization?',
            text: isActivating
                ? 'This will set organization status to Active.'
                : 'This will set organization status to Deactivated.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: isActivating ? 'Yes, Activate' : 'Yes, Deactivate',
            cancelButtonText: 'Cancel',
        });

        if (!confirm.isConfirmed) {
            return;
        }

        try {
            setUpdatingStatusId(id);
            const headers = {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            };
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken.content;
            }

            const response = await fetch(route('admin.organizations.updateStatus', id), {
                method: 'PATCH',
                headers,
                body: JSON.stringify({ status }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                Swal.fire({
                    title: 'Error',
                    text: result.message || 'Failed to update status.',
                    icon: 'error',
                });
                return;
            }
            setReload((v) => !v);
            Swal.fire({
                title: 'Updated',
                text: `Organization status changed to ${status}.`,
                icon: 'success',
                timer: 1200,
                showConfirmButton: false,
            });
        } catch (e) {
            Swal.fire({
                title: 'Error',
                text: 'Failed to update organization status.',
                icon: 'error',
            });
        } finally {
            setUpdatingStatusId(null);
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Organizations</h2>}
        >
            <Head title="Organizations" />
            <AppProvider i18n={en}>
                <div className="py-8">
                    <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                        <Card>
                            <div className="p-4 space-y-3">
                                <div className="flex items-center gap-3 flex-wrap">
                                    <div className="min-w-[160px]">
                                        <ShopifySelect
                                            label="Status"
                                            options={statusOptions}
                                            value={statusSelected}
                                            onChange={setStatusSelected}
                                        />
                                    </div>
                                    <div className="min-w-[160px]">
                                        <ShopifySelect
                                            label="Rows"
                                            options={pageOptions}
                                            value={pageCount}
                                            onChange={setPageCount}
                                        />
                                    </div>
                                    <div className="ml-auto">
                                        <Link
                                            href={route('admin.organizations.create')}
                                            className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                                        >
                                            Create Organization
                                        </Link>
                                    </div>
                                </div>

                                <IndexFilters
                                    sortOptions={sortOptions}
                                    sortSelected={sortSelected}
                                    queryValue={queryValue}
                                    queryPlaceholder="Search by org name / owner email"
                                    onQueryChange={handleFiltersQueryChange}
                                    onQueryClear={handleQueryValueRemove}
                                    onSort={setSortSelected}
                                    cancelAction={{ onAction: () => {} }}
                                    tabs={[]}
                                    selected={0}
                                    onSelect={() => {}}
                                    mode={mode}
                                    setMode={setMode}
                                    filters={[]}
                                    appliedFilters={appliedFilters}
                                />

                                <IndexTable
                                    resourceName={resourceName}
                                    itemCount={tableRows.length}
                                    selectable={false}
                                    headings={[
                                        { title: 'Organization' },
                                        { title: 'Owner Name' },
                                        { title: 'Email' },
                                        { title: 'Contact' },
                                        { title: 'Status' },
                                        { title: 'Created' },
                                        { title: 'Actions' },
                                    ]}
                                    loading={loading}
                                >
                                    {rowMarkup}
                                </IndexTable>

                                <div className="flex items-center justify-end gap-3 pt-2">
                                    <Pagination
                                        hasPrevious={!!pagination.prev_cursor}
                                        onPrevious={() => {
                                            setCurrentCursor(pagination.prev_cursor);
                                            setReload((v) => !v);
                                        }}
                                        hasNext={!!pagination.next_cursor}
                                        onNext={() => {
                                            setCurrentCursor(pagination.next_cursor);
                                            setReload((v) => !v);
                                        }}
                                    />
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            </AppProvider>
        </AuthenticatedLayout>
    );
}

