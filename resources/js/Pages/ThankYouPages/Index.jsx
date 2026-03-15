import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Box, Button as MuiButton } from '@mui/material';
import {
    AppProvider,
    Button,
    Card,
    IndexTable,
    Text,
} from '@shopify/polaris';
import { DeleteIcon, EditIcon, ViewIcon } from '@shopify/polaris-icons';
import '@shopify/polaris/build/esm/styles.css';
import en from '@shopify/polaris/locales/en.json';
import Swal from 'sweetalert2';

export default function Index({ thankYouPages = [], status }) {
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

    const rowMarkup = thankYouPages.map((page, index) => (
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
            <IndexTable.Cell>
                <Button
                    variant="plain"
                    icon={ViewIcon}
                    onClick={() => window.open(previewUrl(page.id), '_blank')}
                    accessibilityLabel="Preview"
                />
                <span style={{ margin: '5px' }} />
                <Button
                    variant="plain"
                    icon={EditIcon}
                    onClick={() => router.get(route('thank-you-pages.edit', page.id))}
                    accessibilityLabel="Edit"
                />
                <span style={{ marginLeft: '10px' }} />
                <Button
                    variant="plain"
                    icon={DeleteIcon}
                    destructive
                    onClick={() => deleteHandler(page.id, page.name)}
                    accessibilityLabel="Delete"
                />
            </IndexTable.Cell>
        </IndexTable.Row>
    ));

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
            </AuthenticatedLayout>
        </AppProvider>
    );
}
