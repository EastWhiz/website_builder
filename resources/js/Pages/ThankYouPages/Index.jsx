import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Box, Button as MuiButton } from '@mui/material';
import {
    AppProvider,
    Button,
    Card,
    IndexTable,
    Modal,
    Text,
} from '@shopify/polaris';
import { DeleteIcon, DuplicateIcon, EditIcon, ViewIcon } from '@shopify/polaris-icons';
import '@shopify/polaris/build/esm/styles.css';
import en from '@shopify/polaris/locales/en.json';
import { useCallback, useState } from 'react';
import Select from 'react-select';
import Swal from 'sweetalert2';

export default function Index({ thankYouPages = [], status, showThankYouPageOwnerColumn = false }) {
    const pageProps = usePage().props;
    const permissions = pageProps?.auth?.permissions || {};
    const currentUserId = Number(pageProps?.auth?.user?.id ?? 0);
    const canCloneThankYouToOrgUser = Boolean(permissions.can_clone_thank_you_to_org_user);

    const [cloneModalOpen, setCloneModalOpen] = useState(false);
    const [clonePageId, setClonePageId] = useState(null);
    const [clonePageName, setClonePageName] = useState('');
    const [cloneTargetUser, setCloneTargetUser] = useState(null);
    const [memberOptions, setMemberOptions] = useState([]);
    const [cloneMembersLoading, setCloneMembersLoading] = useState(false);
    const [cloneSubmitting, setCloneSubmitting] = useState(false);

    const loadActiveMembers = useCallback(async () => {
        setCloneMembersLoading(true);
        try {
            const url = new URL(route('organization.team.members.index'));
            url.searchParams.set('page_count', '100');
            url.searchParams.set('archived', 'false');
            const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
            const result = await res.json();
            if (!result.success || !result.data?.members?.data) {
                setMemberOptions([]);
                return;
            }
            const rows = result.data.members.data.filter((m) => String(m.membership_status || '') === 'active');
            setMemberOptions(
                rows.map((m) => ({
                    value: String(m.user_id),
                    label: `${m.name || 'User'} (${m.email || m.user_id})`,
                })),
            );
        } catch {
            setMemberOptions([]);
        } finally {
            setCloneMembersLoading(false);
        }
    }, []);

    const openCloneModal = (page) => {
        setClonePageId(page.id);
        setClonePageName(page.name || '');
        setCloneTargetUser(null);
        setCloneModalOpen(true);
        loadActiveMembers();
    };

    const submitCloneToUser = async () => {
        if (!clonePageId || !cloneTargetUser?.value) return;
        try {
            setCloneSubmitting(true);
            const response = await fetch(route('organization.content.clone_thank_you_page_to_user'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    thank_you_page_id: Number(clonePageId),
                    to_user_id: Number(cloneTargetUser.value),
                }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Clone failed.');
            }
            setCloneModalOpen(false);
            router.reload({ preserveScroll: true });
            Swal.fire('Success!', result.message || 'Thank you page cloned to user.', 'success');
        } catch (e) {
            Swal.fire('Error', e?.message || 'Clone failed.', 'error');
        } finally {
            setCloneSubmitting(false);
        }
    };
    const resourceName = {
        singular: 'Thank You Page',
        plural: 'Thank You Pages',
    };

    const deleteHandler = (id, name) => {
        Swal.fire({
            title: 'Are you sure?',
            text: `Delete "${name}"? You won't be able to revert this!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#51a70a',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!',
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(route('thank-you-pages.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        if (typeof Swal !== 'undefined' && Swal.fire) {
                            Swal.fire('Deleted!', 'Thank you page has been deleted.', 'success');
                        }
                    },
                    onError: () => {
                        if (typeof Swal !== 'undefined' && Swal.fire) {
                            Swal.fire('Error', 'Something went wrong.', 'error');
                        }
                    },
                });
            }
        });
    };

    const previewUrl = (id) => {
        const base = window.location.origin;
        return `${base}/thank-you-pages/${id}/preview`;
    };

    const rowMarkup = thankYouPages.map((page, index) => {
        const ownerUserId = Number(page.user_id ?? 0);
        const isRowOwner = ownerUserId > 0 && ownerUserId === currentUserId;

        return (
            <IndexTable.Row id={String(page.id)} key={page.id} position={index}>
                <IndexTable.Cell>
                    <Text as="span" fontWeight="semibold">
                        {page.name}
                    </Text>
                </IndexTable.Cell>
                <IndexTable.Cell>
                    <Text as="span" variant="bodyMd" tone="subdued">
                        {page.title_text || '—'}
                    </Text>
                </IndexTable.Cell>
                {showThankYouPageOwnerColumn && (
                    <IndexTable.Cell>
                        <Text as="span" variant="bodyMd">
                            {page.owner_name || '—'}
                        </Text>
                    </IndexTable.Cell>
                )}
                <IndexTable.Cell>
                    <Button
                        variant="plain"
                        icon={ViewIcon}
                        onClick={() => window.open(previewUrl(page.id), '_blank')}
                        accessibilityLabel="Preview"
                    />
                    {isRowOwner && (
                        <>
                            <span style={{ margin: '5px' }} />
                            <Button
                                variant="plain"
                                icon={EditIcon}
                                onClick={() => router.get(route('thank-you-pages.edit', page.id))}
                                accessibilityLabel="Edit"
                            />
                        </>
                    )}
                    {canCloneThankYouToOrgUser && (
                        <>
                            <span style={{ margin: '5px' }} />
                            <Button
                                variant="plain"
                                icon={DuplicateIcon}
                                onClick={() => openCloneModal(page)}
                                accessibilityLabel="Clone to user"
                            />
                        </>
                    )}
                    {isRowOwner && (
                        <>
                            <span style={{ marginLeft: '10px' }} />
                            <Button
                                variant="plain"
                                icon={DeleteIcon}
                                destructive
                                onClick={() => deleteHandler(page.id, page.name)}
                                accessibilityLabel="Delete"
                            />
                        </>
                    )}
                </IndexTable.Cell>
            </IndexTable.Row>
        );
    });

    return (
        <AppProvider i18n={en}>
            <AuthenticatedLayout
                header={
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Thank You Pages
                    </h2>
                }
            >
                <Head title="Thank You Pages" />
                <div className="py-16">
                    <div className="mx-auto max-w-7xl">
                        {status && (
                            <div className="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">
                                {status}
                            </div>
                        )}
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6 text-gray-900">
                                <Box>
                                    <div
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'right',
                                            marginBottom: '15px',
                                        }}
                                    >
                                        <MuiButton
                                            variant="contained"
                                            color="primary"
                                            onClick={() => router.get(route('thank-you-pages.create'))}
                                            sx={{ textTransform: 'capitalize', height: '31px' }}
                                        >
                                            Add Thank You Page
                                        </MuiButton>
                                    </div>
                                    <Card>
                                        <IndexTable
                                            resourceName={resourceName}
                                            itemCount={thankYouPages.length}
                                            headings={[
                                                { title: 'Name' },
                                                { title: 'Title' },
                                                ...(showThankYouPageOwnerColumn ? [{ title: 'Page owner' }] : []),
                                                { title: 'Actions' },
                                            ]}
                                            selectable={false}
                                            loading={false}
                                        >
                                            {rowMarkup}
                                        </IndexTable>
                                        {thankYouPages.length === 0 && (
                                            <div className="p-6 text-center text-gray-500">
                                                No thank you pages yet. Create one to use with your sale page exports.
                                            </div>
                                        )}
                                    </Card>
                                </Box>
                            </div>
                        </div>
                    </div>
                </div>
                <Modal
                    open={cloneModalOpen}
                    onClose={() => setCloneModalOpen(false)}
                    title="Clone thank you page to user"
                    primaryAction={{
                        content: cloneSubmitting ? 'Cloning…' : 'Clone',
                        onAction: submitCloneToUser,
                        disabled: cloneSubmitting || cloneMembersLoading || !cloneTargetUser?.value,
                    }}
                    secondaryActions={[{ content: 'Cancel', onAction: () => setCloneModalOpen(false) }]}
                >
                    <Modal.Section>
                        <Text as="p" variant="bodyMd">
                            Duplicate <strong>{clonePageName}</strong> into another organization member&apos;s account.
                            Their copy is independent; edits do not affect the original.
                        </Text>
                        <div style={{ marginTop: '16px' }}>
                            <Select
                                menuPortalTarget={document.body}
                                styles={{
                                    menuPortal: (base) => ({ ...base, zIndex: 9999 }),
                                }}
                                isLoading={cloneMembersLoading}
                                placeholder={cloneMembersLoading ? 'Loading members…' : 'Select organization user…'}
                                options={memberOptions}
                                value={cloneTargetUser}
                                onChange={(v) => setCloneTargetUser(v)}
                            />
                        </div>
                    </Modal.Section>
                </Modal>
            </AuthenticatedLayout>
        </AppProvider>
    );
}
