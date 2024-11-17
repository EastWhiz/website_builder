import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Box, Typography } from '@mui/material';
import GridLayout from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';

import AssignmentOutlinedIcon from '@mui/icons-material/AssignmentOutlined';
import CloseIcon from '@mui/icons-material/Close';
import ExpandOutlinedIcon from '@mui/icons-material/ExpandOutlined';
import HtmlOutlinedIcon from '@mui/icons-material/HtmlOutlined';
import ImageOutlinedIcon from '@mui/icons-material/ImageOutlined';
import InsertEmoticonOutlinedIcon from '@mui/icons-material/InsertEmoticonOutlined';
import PsychologyIcon from '@mui/icons-material/PsychologyAltOutlined';
import RemoveOutlinedIcon from '@mui/icons-material/RemoveOutlined';
import SmartButtonOutlinedIcon from '@mui/icons-material/SmartButtonOutlined';
import TextSnippetOutlinedIcon from '@mui/icons-material/TextSnippetOutlined';
import TranslateIcon from '@mui/icons-material/TranslateOutlined';
import VideocamOutlinedIcon from '@mui/icons-material/VideocamOutlined';
import WidgetsIcon from '@mui/icons-material/WidgetsOutlined';
import CommentOutlinedIcon from '@mui/icons-material/CommentOutlined';
import { useState } from 'react';

export default function Dashboard() {

    const [layout, setLayout] = useState([]);
    const [blockCount, setBlockCount] = useState(0);
    const addNewBlock = () => {
        const newBlock = {
            i: `${blockCount + 1}`,
            x: blockCount % 12,
            y: 0,
            w: 1,
            h: 2,
        };
        setLayout([...layout, newBlock]);
        setBlockCount(blockCount + 1);
    };

    const [tabs, setTabs] = useState([
        { name: "Widgets", value: "widgets", selected: true, icon: <WidgetsIcon /> },
        { name: "Translate", value: "translate", selected: false, icon: <TranslateIcon /> },
        { name: "Writing Assistant", value: "writing_assistant", selected: false, icon: <PsychologyIcon /> },
    ]);

    const widgets = [
        { name: "Text", value: "text", icon: <TextSnippetOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
        { name: "Image", value: "image", icon: <ImageOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
        { name: "Video", value: "video", icon: <VideocamOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
        { name: "Image + Text", value: "image_text", icon: <><ImageOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> <TextSnippetOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /></> },
        { name: "Video + Text", value: "video_text", icon: <><VideocamOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> <TextSnippetOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /></> },
        { name: "Form", value: "form", icon: <AssignmentOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
        { name: "Button", value: "button", icon: <SmartButtonOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
        { name: "Spacer", value: "spacer", icon: <ExpandOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
        { name: "Icons", value: "icons", icon: <InsertEmoticonOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
        { name: "Line", value: "line", icon: <RemoveOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
        { name: "Custom HTML", value: "custom_html", icon: <HtmlOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
        { name: "Comments", value: "comments", icon: <CommentOutlinedIcon sx={{ fontSize: "30px", color: "#1677d7" }} /> },
    ];

    const unselectAllTabsHandler = () => {
        setTabs(tabs.map(tab => ({
            ...tab,
            selected: false,
        })));
    }

    const addNewWidgetHandler = () => {
        addNewBlock();
    }

    let anyTabSelected = tabs.find(value => value.selected);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Customizer</h2>} >
            <Head title="Customizer" />

            {/* py-16 */}
            <div className="">
                {/* sm:px-6 lg:px-8 */}
                {/* max-w-7xl */}
                <div className="mx-auto">
                    {/* p-6  */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lgtext-gray-900">
                        <Box sx={{ height: "100vh", display: "flex" }}>
                            {/* SIDE BAR */}
                            <Box sx={{ width: "80px", bgcolor: "#ebf5ff", textAlign: "center", height: "100%" }}>
                                {tabs.map((value, index) => (
                                    <Box
                                        key={index}
                                        sx={{
                                            py: 1,
                                            cursor: "pointer",
                                            bgcolor: anyTabSelected && anyTabSelected.value === value.value ? "#3f93ff" : "transparent",
                                            color: anyTabSelected && anyTabSelected.value === value.value ? "white" : "black",
                                        }}
                                        onClick={() => {
                                            let temp = [...tabs];
                                            temp.forEach((tab, indexInside) => {
                                                if (index == indexInside)
                                                    temp[indexInside] = { ...temp[indexInside], selected: !tab.selected }
                                                else
                                                    temp[indexInside] = { ...temp[indexInside], selected: false }
                                            });
                                            setTabs(temp);
                                        }}
                                    >
                                        {value.icon}
                                        <Typography variant="subtitle2" component="h2"> {value.name}</Typography>
                                    </Box>
                                ))}
                            </Box>

                            {/* RIGHT SIDE CONTENT */}
                            {anyTabSelected &&
                                <Box sx={{ p: 3, bgcolor: "#ebf5ff", width: "300px", borderLeft: "1px solid #7bb6f0" }}>
                                    <Box>
                                        <CloseIcon sx={{ float: 'right', cursor: "pointer" }} onClick={unselectAllTabsHandler} />
                                        <Typography variant="usbtitle1">
                                            {anyTabSelected.name}
                                        </Typography>
                                        {anyTabSelected && anyTabSelected.value == "widgets" &&
                                            < Box sx={{ py: 3, textAlign: "center", display: "flex", flexWrap: "wrap" }}>
                                                {widgets.map((value, index) => (
                                                    <Box key={index} sx={{ cursor: "pointer", width: "48%", border: "2px solid #7bb6f0", borderRadius: "5px", m: 0.3, p: 1, py: 1.5 }} onClick={addNewWidgetHandler}>
                                                        {value.icon}
                                                        <Typography variant="subtitle2" sx={{ color: "#1677d7", mt: 0.5, fontSize: "12px" }}>
                                                            {value.name}
                                                        </Typography>
                                                    </Box>
                                                ))}
                                            </Box>
                                        }
                                    </Box>
                                </Box>
                            }

                            <GridLayout
                                className="layout"
                                layout={layout}
                                cols={12}
                                rowHeight={32}
                                width={anyTabSelected ? 1060 : 1360}
                                compactType='none'
                            >
                                {layout.map((block) => (
                                    <div key={block.i} style={{ background: '#4CAF50' }}>
                                        Item {block.i}
                                    </div>
                                ))}
                            </GridLayout>
                        </Box>
                    </div>
                </div>
            </div >
        </AuthenticatedLayout >
    );
}
