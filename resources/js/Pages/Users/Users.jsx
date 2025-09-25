import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Backdrop, Box, Fade, Modal, Button as MuiButton, TextField } from '@mui/material';
import { PhoneInput } from 'react-international-phone';
import 'react-international-phone/style.css';

import LockResetIcon from '@mui/icons-material/LockReset';
import VisibilityIcon from '@mui/icons-material/Visibility';
import DeleteIcon from '@mui/icons-material/Delete';
import {
    AppProvider, Card,
    IndexFilters,
    IndexTable,
    Pagination,
    Select as ShopifySelect,
    useIndexResourceState, useSetIndexFiltersMode
} from '@shopify/polaris';
import "@shopify/polaris/build/esm/styles.css";
import en from "@shopify/polaris/locales/en.json";
import { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';

export default function Dashboard() {

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
        singular: 'User',
        plural: 'Users',
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
        path: route("users.list"),
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

    const filters = [];

    const appliedFilters = [];

    const handleResetPassword = async (userId) => {
        try {
            const response = await fetch(route('resetPassword'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId })
            });
            const result = await response.json();
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Password Reset',
                    text: 'Password has been reset to "Reset@321"'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to reset password.'
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to reset password.'
            });
        }
    };

    const handleDeleteUser = async (userId) => {
        try {
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: 'This will permanently delete the user and all their associated data. This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete user!',
                cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
                const response = await fetch(route('deleteUser'), {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: userId })
                });
                const deleteResult = await response.json();
                if (deleteResult.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'User Deleted',
                        text: 'User has been deleted successfully!'
                    });
                    setReload(r => !r); // Refresh the table
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: deleteResult.message || 'Failed to delete user.'
                    });
                }
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to delete user.'
            });
        }
    };

    const rowMarkup = tableRows.map((value, index) => (
        <IndexTable.Row
            id={value.id}
            key={value.id}
            selected={selectedResources.includes(value.id)}
            position={index}
        >
            <IndexTable.Cell>
                {`U${value.id}`}
            </IndexTable.Cell>
            <IndexTable.Cell>
                {value.name}
            </IndexTable.Cell>
            <IndexTable.Cell >
                {convertISOToYMD(value.created_at)}
            </IndexTable.Cell>
            <IndexTable.Cell>
                <MuiButton size='small' variant='contained' color='secondary' className='cptlz' onClick={() => {
                    router.get(route('userThemes', { id: value.id }));
                }}> <VisibilityIcon sx={{ fontSize: "16px", mr: 1 }} />Themes</MuiButton>
                <MuiButton size='small' variant='contained' color='warning' className='cptlz' sx={{ ml: 1 }} onClick={() => handleResetPassword(value.id)}>
                    <LockResetIcon sx={{ fontSize: "16px", mr: 1 }} /> Reset Password
                </MuiButton>
                <MuiButton size='small' variant='contained' color='error' className='cptlz' sx={{ ml: 1 }} onClick={() => handleDeleteUser(value.id)}>
                    <DeleteIcon sx={{ fontSize: "16px", mr: 1 }} /> Delete
                </MuiButton>
            </IndexTable.Cell>
        </IndexTable.Row >
    ));

    const [addUserModalOpen, setAddUserModalOpen] = useState(false);
    const [newUser, setNewUser] = useState({ name: '', email: '', phone: '', password: '' });
    const [creatingUser, setCreatingUser] = useState(false);

    const modalStyle = {
        position: 'absolute',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
        width: { xs: '95%', sm: '80%', md: '50%', lg: '40%', xl: '35%' },
        bgcolor: 'background.paper',
        boxShadow: 24,
        p: 4,
        pt: 4,
        minWidth: 400,
        minHeight: 250,
        borderRadius: 2,
        display: 'flex',
        flexDirection: 'column',
        gap: 2,
    };

    const handleAddUser = async () => {
        setCreatingUser(true);
        try {
            const response = await fetch(route('createUser'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(newUser)
            });
            const result = await response.json();
            if (result.success) {
                setAddUserModalOpen(false);
                setNewUser({ name: '', email: '', phone: '', password: '' });
                Swal.fire({
                    icon: 'success',
                    title: 'User Created',
                    text: 'The user has been created successfully!'
                });
                setReload(r => !r);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to create user.'
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to create user.'
            });
        }
        setCreatingUser(false);
    };

    return (
        <AppProvider i18n={en}>
            <AuthenticatedLayout
                header={
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Users
                    </h2>
                }
            >
                <Head title="Users" />
                <div className="py-16">
                    <div className="mx-auto max-w-7xl">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6 text-gray-900">
                                <Box>
                                    <div style={{ display: "flex", justifyContent: "flex-end", marginBottom: "15px" }}>
                                        <ShopifySelect
                                            labelInline
                                            label="Rows:"
                                            options={pageOptions}
                                            value={pageCount}
                                            onChange={handlePageCount}
                                        />
                                        <MuiButton sx={{ marginLeft: "10px", height: "31px" }} variant="contained" color="primary" className="cptlz" onClick={() => setAddUserModalOpen(true)}>
                                            Add
                                        </MuiButton>
                                    </div>
                                    <Card>
                                        <div>
                                            <IndexFilters
                                                sortOptions={sortOptions}
                                                sortSelected={sortSelected}
                                                queryValue={queryValue}
                                                queryPlaceholder="Search Users..."
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
                                                { title: 'User Added' },
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
                {/* Add User Modal using MUI Modal */}
                <Modal
                    aria-labelledby="add-user-modal-title"
                    aria-describedby="add-user-modal-description"
                    open={addUserModalOpen}
                    onClose={() => setAddUserModalOpen(false)}
                    closeAfterTransition
                    slots={{ backdrop: Backdrop }}
                    slotProps={{ backdrop: { timeout: 100 } }}
                >
                    <Fade in={addUserModalOpen}>
                        <Box sx={modalStyle}>
                            <h3 id="add-user-modal-title" style={{ marginBottom: "10px", fontSize: 22 }}>Add New User</h3>
                            <TextField
                                label="Name"
                                value={newUser.name}
                                onChange={e => setNewUser(u => ({ ...u, name: e.target.value }))}
                                fullWidth
                                size="small"
                            />
                            <TextField
                                label="Email"
                                type="email"
                                value={newUser.email}
                                onChange={e => setNewUser(u => ({ ...u, email: e.target.value }))}
                                fullWidth
                                size="small"
                            />
                            <Box>
                                <PhoneInput
                                    defaultCountry="us"
                                    value={newUser.phone}
                                    onChange={(phone) => setNewUser(u => ({ ...u, phone }))}
                                    inputProps={{
                                        name: 'phone',
                                        required: true,
                                        autoComplete: 'tel',
                                        // className: 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm pl-16',
                                    }}
                                    inputStyle={{
                                        width: '100%',
                                        padding: '1.2rem 0.75rem',
                                        // paddingLeft: '4rem',
                                        // borderRadius: '0.375rem',
                                        fontSize: '0.875rem',
                                    }}
                                    countrySelectorStyleProps={{
                                        buttonStyle: {
                                            borderRadius: '0.375rem 0 0 0.375rem',
                                            border: '1px solid #d1d5db',
                                            borderRight: 'none',
                                            padding: '1.2rem 0.75rem',
                                        }
                                    }}
                                />
                            </Box>
                            <TextField
                                label="Password"
                                type="password"
                                value={newUser.password}
                                onChange={e => setNewUser(u => ({ ...u, password: e.target.value }))}
                                fullWidth
                                size="small"
                            />
                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 2 }}>
                                <MuiButton onClick={() => setAddUserModalOpen(false)} sx={{ mr: 1 }} className="cptlz" disabled={creatingUser}>
                                    Cancel
                                </MuiButton>
                                <MuiButton variant="contained" color="primary" className="cptlz" onClick={handleAddUser} disabled={creatingUser || !newUser.name || !newUser.email || !newUser.phone || !newUser.password}>
                                    {creatingUser ? 'Creating...' : 'Create User'}
                                </MuiButton>
                            </Box>
                        </Box>
                    </Fade>
                </Modal>
            </AuthenticatedLayout>
        </AppProvider >
    );
}
