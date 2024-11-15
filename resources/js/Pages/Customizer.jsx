import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Button } from '@mui/material';
import GridLayout from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';

export default function Dashboard() {

    const layout = [
        { i: '1', x: 0, y: 0, w: 1, h: 2 },
        { i: '2', x: 1, y: 0, w: 1, h: 2, isResizable: false },
        { i: '3', x: 2, y: 0, w: 1, h: 2 },
        { i: '4', x: 3, y: 0, w: 1, h: 2, static: true },
        { i: '5', x: 4, y: 0, w: 1, h: 2 },
        { i: '6', x: 5, y: 0, w: 1, h: 2 },
        { i: '7', x: 6, y: 0, w: 1, h: 2 },
        { i: '8', x: 7, y: 0, w: 1, h: 2 },
        { i: '9', x: 8, y: 0, w: 1, h: 2 },
        { i: '10', x: 9, y: 0, w: 1, h: 2 },
        { i: '11', x: 10, y: 0, w: 1, h: 2 },
        { i: '12', x: 11, y: 0, w: 1, h: 2 },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Customizer
                </h2>
            }
        >
            <Head title="Customizer" />

            <div className="py-16">
                {/* sm:px-6 lg:px-8 */}
                <div className="mx-auto max-w-7xl">
                    {/* p-6  */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lgtext-gray-900">
                        {/* <Button variant='contained' color='secondary'>Button</Button> */}
                        <GridLayout
                            className="layout"
                            layout={layout}
                            cols={12}
                            rowHeight={40}
                            width={1280}
                            compactType='none'
                        >
                            <div key="1" style={{ background: '#4CAF50' }}>Item 1</div>
                            <div key="2" style={{ background: '#2196F3' }}>Item 2</div>
                            <div key="3" style={{ background: '#FF5722' }}>Item 3</div>
                            <div key="4" style={{ background: '#4CAF50' }}>Item 4</div>
                            <div key="5" style={{ background: '#2196F3' }}>Item 5</div>
                            <div key="6" style={{ background: '#FF5722' }}>Item 6</div>
                            <div key="7" style={{ background: '#4CAF50' }}>Item 7</div>
                            <div key="8" style={{ background: '#2196F3' }}>Item 8</div>
                            <div key="9" style={{ background: '#FF5722' }}>Item 9</div>
                            <div key="10" style={{ background: '#4CAF50' }}>Item 10</div>
                            <div key="11" style={{ background: '#2196F3' }}>Item 11</div>
                            <div key="12" style={{ background: '#FF5722' }}>Item 12</div>
                        </GridLayout>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
