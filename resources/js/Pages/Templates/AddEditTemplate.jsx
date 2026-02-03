
import Doc1 from "@/Assets/document1.png";
import Doc2 from "@/Assets/document2.png";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import AddIcon from '@mui/icons-material/Add';
import ClearIcon from '@mui/icons-material/Clear';
import SortIcon from '@mui/icons-material/Sort';
import { Box, Button, Card, CardContent, TextField, Typography } from '@mui/material';
import Backdrop from '@mui/material/Backdrop';
import Fade from '@mui/material/Fade';
import Modal from '@mui/material/Modal';
import Step from '@mui/material/Step';
import StepLabel from '@mui/material/StepLabel';
import Stepper from '@mui/material/Stepper';
import { AgGridReact } from "ag-grid-react";
import { useCallback, useEffect, useState } from 'react';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import Swal from "sweetalert2";

export default function Dashboard() {

    const page = usePage().props;

    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    const style = {
        position: 'absolute',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
        width: { xs: '90%', sm: '70%', md: '60%', lg: '50%', xl: '50%' },
        bgcolor: 'background.paper',
        // border: '2px solid #000',
        boxShadow: 24,
        p: 4,
        pt: 3.5,
        height: "395px",
        overflow: "auto"
    };

    const [open, setOpen] = useState(false);
    const handleOpen = () => setOpen(true);
    const handleClose = () => setOpen(false);

    const [templateUUID, setTemplateUUID] = useState(false);
    // const [template, setTemplate] = useState({
    //     name: '',
    //     head: '',
    //     body: '',
    //     html: [{ name: '', content: '', }],
    //     css: [{ name: '', content: '', }],
    //     js: [{ name: '', content: '', }],
    //     fonts: [{ alreadyUploaded: "", name: "", size: "", file: "" }],
    //     images: [{ alreadyUploaded: "", name: "", size: "", file: "" }]
    // });

    const [template, setTemplate] = useState({
        name: '',
        head: '',
        body: '',
        html: [],
        css: [],
        js: [],
        fonts: [],
        images: []
    });

    const [currentThing, setCurrentThing] = useState('');

    const steps = ['Theme', 'Contents', 'Files'];

    const [activeStep, setActiveStep] = useState(0);
    const [skipped, setSkipped] = useState(new Set());

    const [rowData, setRowData] = useState([
        { make: 'Toyota', model: 'Celica', price: 35000 },
        { make: 'Ford', model: 'Mondeo', price: 32000 },
        { make: 'Porsche', model: 'Boxter', price: 72000 }
    ]);

    const [gridApi, setGridApi] = useState(null);

    const onGridReady = useCallback((params) => {
        setGridApi(params.api);
    }, []);

    useEffect(() => {
        if (page.template) {
            let htmls = page.template.contents.filter(html => html.type == "html");
            let csss = page.template.contents.filter(css => css.type == "css");
            let jss = page.template.contents.filter(js => js.type == "js");
            let fonts = page.template.contents.filter(js => js.type == "font");
            let images = page.template.contents.filter(js => js.type == "image");
            setTemplateUUID(page.template.uuid);
            setTemplate({
                name: page.template.name,
                head: page.template.head,
                body: page.template.index,
                html: htmls.map((html, index) => {
                    return ({ name: html.name, content: html.content })
                }),
                css: csss.map((css, index) => {
                    return ({ name: css.name, content: css.content })
                }),
                js: jss.map((js, index) => {
                    return ({ name: js.name, content: js.content })
                }),
                fonts: fonts.map((font, index) => {
                    return ({ alreadyUploaded: font.name, name: "", size: "", file: "" })
                }),
                images: images.map((image, index) => {
                    return ({ alreadyUploaded: image.name, name: "", size: "", file: "" })
                })
            });
        }
    }, []);

    const isStepSkipped = (step) => {
        return skipped.has(step);
    };

    const chunkArray = (array, size) => {
        const result = [];
        for (let i = 0; i < array.length; i += size) {
            result.push(array.slice(i, i + size));
        }
        return result;
    };

    let abortController = null;

    const submitTemplateHandler = async () => {
        try {
            const CHUNK_SIZE = 10; // Adjust chunk size as needed
            const fontChunks = chunkArray(template.fonts, CHUNK_SIZE);
            const imageChunks = chunkArray(template.images, CHUNK_SIZE);

            let uploadedFiles = 0;
            const totalFiles = template.fonts.length + template.images.length;

            Swal.fire({
                title: 'Uploading...',
                html: '<b>0%</b>',
                allowOutsideClick: false,
                showConfirmButton: false, // Hide the default OK button
                showDenyButton: true,
                denyButtonText: `Don't Save`
            }).then((result) => {
                if (result.isDenied) {
                    if (abortController) {
                        abortController.abort();
                    }
                }
            });

            const uuid = generateUUID();
            const assetUUID = generateUUID();

            const chunks = [...fontChunks, ...imageChunks];
            if (chunks.length == 0)
                chunks.push(1);

            for (const [chunkIndex, chunk] of chunks.entries()) {

                const isLastIteration = chunkIndex === chunks.length - 1 ? true : false;

                const formData = new FormData();
                formData.append("name", template.name);
                formData.append("head", template.head);
                formData.append("index", template.body);
                formData.append("html", JSON.stringify(template.html));
                formData.append("css", JSON.stringify(template.css));
                formData.append("js", JSON.stringify(template.js));
                formData.append("last_iteration", isLastIteration);
                formData.append("uuid", templateUUID ? templateUUID : uuid);
                formData.append("chunk_count", chunk == 1 ? 0 : chunk.length);
                formData.append("edit_template_uuid", templateUUID);
                formData.append("asset_unique_uuid", assetUUID);

                if (chunk != 1) {
                    chunk.forEach((item, index) => {
                        const isFont = template.fonts.includes(item);
                        formData.append(`${isFont ? 'font' : 'image'}${index}`, item.file);
                        if (item.alreadyUploaded)
                            formData.append(`${isFont ? 'font' : 'image'}${index}Done`, item.alreadyUploaded);
                    });
                }

                // Create a new AbortController for each chunk
                abortController = new AbortController();

                try {
                    let response = await fetch(route('templates.addEdit'), {
                        method: "POST",
                        body: formData,
                        signal: abortController.signal, // Attach abort signal
                    });

                    const result = await response.json();
                    if (!result.success) {
                        Swal.fire("Error!", result.message, "error");
                        return;
                    }

                    uploadedFiles += chunk.length;
                    const progress = Math.round((uploadedFiles / totalFiles) * 100);

                    // Smoothly update the existing Swal modal with progress
                    // ${progress}%
                    Swal.update({
                        html: `<b>${progress}%</b>`,
                        title: `Uploading...`,
                    });

                } catch (error) {
                    if (error.name === 'AbortError') {
                        Swal.fire("Cancelled", "Upload has been cancelled.", "info");
                        return;
                    } else {
                        Swal.fire("Error!", error.toString(), "error");
                        return;
                    }
                }
            }

            Swal.fire("Success!", "All files uploaded successfully!", "success");
            router.get(route('templates'))

        } catch (error) {
            if (error.name === 'AbortError') {
                Swal.fire("Cancelled", "Upload has been cancelled.", "info");
                return;
            } else {
                Swal.fire("Error!", error.toString(), "error");
                return;
            }
        }
    };

    const handleNext = async () => {
        if (activeStep === steps.length - 1) {
            Swal.fire({
                title: 'Are you sure?',
                text: "Your data is getting saved!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Submit!',
                cancelButtonText: 'No, Cancel!',
                // reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    submitTemplateHandler();
                }
            })
        }
        else {
            if (activeStep == 0) {
                let errors = false;
                if (!errors)
                    handleNextDone();
            }

            if (activeStep == 1) {
                handleNextDone();
            }

            if (activeStep == 2) {
                let errors = false;

                if (!errors)
                    handleNextDone();
            }
        }
    };

    const handleNextDone = () => {
        let newSkipped = skipped;
        if (isStepSkipped(activeStep)) {
            newSkipped = new Set(newSkipped.values());
            newSkipped.delete(activeStep);
        }

        setActiveStep((prevActiveStep) => prevActiveStep + 1);
        setSkipped(newSkipped);
    };

    const handleBack = () => {
        setActiveStep((prevActiveStep) => prevActiveStep - 1);
    };

    const columnDefs = [
        { field: 'Sort', width: 100, rowDrag: true, sortable: false, menuTabs: [] },
        { field: 'name', width: 480, sortable: false, menuTabs: [] }
    ]

    return (
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
                            <Modal
                                aria-labelledby="transition-modal-title"
                                aria-describedby="transition-modal-description"
                                open={open}
                                onClose={handleClose}
                                closeAfterTransition
                                slots={{ backdrop: Backdrop }}
                                slotProps={{
                                    backdrop: {
                                        timeout: 100,
                                    },
                                }}
                            >
                                <Fade in={open}>
                                    <Box sx={style}>
                                        <Box>
                                            <Box sx={{ display: "flex", justifyContent: "space-between" }}>
                                                <Typography variant="h5" component="div" pt={0.5} sx={{ fontSize: { xs: '20px', sm: '20px', md: '20px', lg: '22px', xl: '22px' } }}>
                                                    Sort Items
                                                </Typography>
                                                <Button variant="contained" onClick={() => {
                                                    const rows = [];
                                                    gridApi.forEachNodeAfterFilterAndSort((node) => rows.push(node.data));
                                                    // console.log(rows);
                                                    let temp = { ...template };
                                                    if (currentThing == "html") {
                                                        temp.html = rows;
                                                    } else if (currentThing == "css") {
                                                        temp.css = rows;
                                                    } else if (currentThing == "js") {
                                                        temp.js = rows;
                                                    } else if (currentThing == "fonts") {
                                                        temp.fonts = rows;
                                                    } else if (currentThing == "images") {
                                                        temp.images = rows;
                                                    }
                                                    setTemplate(temp);
                                                    handleClose();
                                                }}>Sort</Button>
                                            </Box>
                                            <div className="ag-theme-alpine" style={{ marginTop: '20px', height: '275px', width: '100%' }}>
                                                <AgGridReact
                                                    rowData={rowData}
                                                    columnDefs={columnDefs}
                                                    defaultColDef={{
                                                        width: 194,
                                                        sortable: true,
                                                        filter: true,
                                                    }}
                                                    rowDragManaged={true}
                                                    rowDragMultiRow={true}
                                                    rowSelection={'multiple'}
                                                    animateRows={true}
                                                    onGridReady={onGridReady}
                                                    sortable={false}
                                                />
                                            </div>
                                        </Box>
                                    </Box>
                                </Fade>
                            </Modal>
                            <Box>
                                <Box sx={{ background: "white", height: "70px" }}>
                                    <Box sx={{ width: '100%' }}>
                                        <Box sx={{
                                            paddingTop: "22px",
                                            paddingLeft: { xs: '50px', sm: '100px', md: '150px', lg: '200px', xl: '200px' },
                                            paddingRight: { xs: '50px', sm: '100px', md: '150px', lg: '200px', xl: '200px' },
                                        }}>
                                            <Stepper activeStep={activeStep}>
                                                {steps.map((label, index) => {
                                                    const stepProps = {};
                                                    const labelProps = {};
                                                    if (isStepSkipped(index)) {
                                                        stepProps.completed = false;
                                                    }
                                                    return (
                                                        <Step key={label} {...stepProps}>
                                                            <StepLabel {...labelProps}>{label}</StepLabel>
                                                        </Step>
                                                    );
                                                })}
                                            </Stepper>
                                        </Box>
                                    </Box>
                                </Box>

                                {activeStep == 0 ?
                                    <Box
                                        sx={{
                                            padding: { xs: '20px', sm: '20px', md: '80px', lg: '80px', xl: '80px' },
                                            paddingTop: { xs: '20px', sm: '20px', md: '20px', lg: '20px', xl: '20px' },
                                            paddingBottom: { xs: '60px', sm: '60px', md: '60px', lg: '60px', xl: '60px' },
                                        }}>

                                        <Box>
                                            <Box sx={{ mb: 0, background: "#707070", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center" }}>
                                                <Box mt={3} mb={2}><img src={Doc1}></img></Box>
                                                <Typography variant="h3" component="div" color="white" mb={1} sx={{ fontSize: { xs: '30px', sm: '30px', md: '40px', lg: '40px', xl: '40px' } }}>
                                                    Insert Theme
                                                </Typography>
                                                <Typography variant="body" color="white" mb={3}>
                                                    Insert head, body content below
                                                </Typography>
                                            </Box>
                                            <Card sx={{ minWidth: 275 }}>
                                                <CardContent>
                                                    <Box>
                                                        <Typography variant="h5" component="div" mt={2} sx={{ fontSize: { xs: '18px', sm: '18px', md: '20px', lg: '24px', xl: '24px' }, textAlign: "center" }}>
                                                            Insert the Head and Index code below
                                                        </Typography>
                                                        <Box sx={{
                                                            marginTop: "40px",
                                                            paddingLeft: { xs: '10px', sm: '50px', md: '100px', lg: '150px', xl: '150px' },
                                                            paddingRight: { xs: '10px', sm: '50px', md: '100px', lg: '150px', xl: '150px' },
                                                        }}>
                                                            <TextField
                                                                sx={{
                                                                    width: "100%",
                                                                    marginBottom: 2,
                                                                    "& .MuiInputBase-input:focus": {
                                                                        outline: "none", // Remove input focus outline
                                                                        boxShadow: "none", // Remove any remaining shadow
                                                                    },

                                                                }}
                                                                fullWidth
                                                                placeholder="Enter Theme Name..."
                                                                size="small"
                                                                value={template.name}
                                                                onChange={(e) => {
                                                                    let temp = { ...template };
                                                                    temp.name = e.target.value;
                                                                    setTemplate(temp);
                                                                }}
                                                            />
                                                            <TextField
                                                                fullWidth
                                                                sx={{
                                                                    "& .MuiInputBase-input:focus": {
                                                                        outline: "none", // Remove input focus outline
                                                                        boxShadow: "none", // Remove any remaining shadow
                                                                    },

                                                                }}
                                                                multiline
                                                                rows={6}
                                                                placeholder="Enter <Head> content..."
                                                                onChange={(e) => {
                                                                    let temp = { ...template };
                                                                    temp = { ...temp, head: e.target.value }
                                                                    setTemplate(temp);
                                                                }}
                                                                value={template.head}
                                                            />

                                                            <TextField
                                                                fullWidth
                                                                sx={{
                                                                    marginTop: 2,
                                                                    "& .MuiInputBase-input:focus": {
                                                                        outline: "none", // Remove input focus outline
                                                                        boxShadow: "none", // Remove any remaining shadow
                                                                    },

                                                                }}
                                                                multiline
                                                                rows={6}
                                                                placeholder="Enter <body> content..."
                                                                onChange={(e) => {
                                                                    let temp = { ...template };
                                                                    temp = { ...temp, body: e.target.value }
                                                                    setTemplate(temp);
                                                                }}
                                                                value={template.body}
                                                            />
                                                        </Box>
                                                    </Box>
                                                </CardContent>
                                            </Card>
                                        </Box>
                                    </Box> : null}
                                {activeStep == 1 ?
                                    <Box
                                        sx={{
                                            padding: { xs: '20px', sm: '20px', md: '80px', lg: '80px', xl: '80px' },
                                            paddingTop: { xs: '20px', sm: '20px', md: '20px', lg: '20px', xl: '20px' },
                                            paddingBottom: { xs: '60px', sm: '60px', md: '60px', lg: '60px', xl: '60px' },
                                        }}>

                                        <Box>
                                            <Box sx={{ mb: 0, background: "#707070", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center" }}>
                                                <Box mt={3} mb={2}><img src={Doc1}></img></Box>
                                                <Typography variant="h3" component="div" color="white" mb={1} sx={{ fontSize: { xs: '30px', sm: '30px', md: '40px', lg: '40px', xl: '40px' } }}>
                                                    Insert Contents
                                                </Typography>
                                                <Typography variant="body" color="white" mb={3}>
                                                    {/* HTML, JS, */}
                                                    Insert CSS Chunks
                                                </Typography>
                                            </Box>
                                            <Card sx={{ minWidth: 275, }}>
                                                <CardContent>
                                                    <Box>
                                                        <Typography variant="h5" component="div" mt={2} sx={{ fontSize: { xs: '18px', sm: '18px', md: '20px', lg: '24px', xl: '24px' }, textAlign: "center" }}>
                                                            {/*  HTML, JS, */}
                                                            Insert the CSS Chunks available to connect them for any template
                                                        </Typography>
                                                        <Box sx={{
                                                            marginTop: "40px",
                                                            paddingLeft: { xs: '10px', sm: '50px', md: '100px', lg: '150px', xl: '150px' },
                                                            paddingRight: { xs: '10px', sm: '50px', md: '100px', lg: '150px', xl: '150px' },
                                                        }}>
                                                            {/* <Box sx={{ display: "flex", justifyContent: "space-between" }}>
                                                                <Typography variant="body" component="div" sx={{ marginBottom: "20px", textDecoration: "underline", fontSize: "18px", fontWeight: "500" }}>
                                                                    HTML Chunks
                                                                </Typography>
                                                                <Box>
                                                                    <AddIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#3278ff", color: 'white' }} onClick={() => {
                                                                        let temp = { ...template };
                                                                        temp.html.push({ name: '', content: '' });
                                                                        setTemplate(temp);
                                                                    }} />
                                                                    <SortIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#8f32ff", color: 'white' }} onClick={() => {
                                                                        setRowData(template.html);
                                                                        handleOpen();
                                                                        setCurrentThing("html");
                                                                    }} />
                                                                </Box>
                                                            </Box>

                                                            <Box sx={{ border: "3px dashed #D4D4D4", background: "#FCFCFC", minHeight: "70px" }} p={3} pb={0}>
                                                                {template.html.map((value, index) => {
                                                                    return (
                                                                        <Box key={index}>
                                                                            <Box sx={{ display: "flex", width: "100%" }}>
                                                                                <TextField
                                                                                    sx={{
                                                                                        width: "100%",
                                                                                        marginBottom: 3,
                                                                                        "& .MuiInputBase-input:focus": {
                                                                                            outline: "none", // Remove input focus outline
                                                                                            boxShadow: "none", // Remove any remaining shadow
                                                                                        },

                                                                                    }}
                                                                                    fullWidth
                                                                                    placeholder="Enter <body> Name..."
                                                                                    size="small"
                                                                                    value={value.name}
                                                                                    onChange={(e) => {
                                                                                        let temp = { ...template };
                                                                                        temp.html[index].name = e.target.value;
                                                                                        setTemplate(temp);
                                                                                    }}
                                                                                />
                                                                                <ClearIcon sx={{ width: "38px", height: "38px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "dimgray", color: 'white' }} onClick={() => {
                                                                                    // if (template.html.length > 1) {
                                                                                    let temp = { ...template };
                                                                                    temp.html.splice(index, 1);
                                                                                    setTemplate(temp);
                                                                                    // }
                                                                                }} />
                                                                            </Box>
                                                                            <TextField
                                                                                fullWidth
                                                                                sx={{
                                                                                    width: "100%",
                                                                                    marginBottom: 3,
                                                                                    "& .MuiInputBase-input:focus": {
                                                                                        outline: "none", // Remove input focus outline
                                                                                        boxShadow: "none", // Remove any remaining shadow
                                                                                    },

                                                                                }}
                                                                                multiline
                                                                                rows={6}
                                                                                placeholder="Enter <body> chunk..."
                                                                                value={value.content}
                                                                                onChange={(e) => {
                                                                                    let temp = { ...template };
                                                                                    temp.html[index].content = e.target.value;
                                                                                    setTemplate(temp);
                                                                                }}
                                                                            />
                                                                        </Box>
                                                                    );
                                                                })}
                                                            </Box> */}
                                                            <Box sx={{ display: "flex", justifyContent: "space-between", marginTop: "20px", }}>
                                                                <Typography variant="body" component="div" sx={{ marginBottom: "20px", textDecoration: "underline", fontSize: "18px", fontWeight: "500" }}>
                                                                    CSS Chunks
                                                                </Typography>
                                                                <Box>
                                                                    <AddIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#3278ff", color: 'white' }} onClick={() => {
                                                                        let temp = { ...template };
                                                                        temp.css.push({ name: '', content: '' });
                                                                        setTemplate(temp);
                                                                    }} />
                                                                    <SortIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#8f32ff", color: 'white' }} onClick={() => {
                                                                        setRowData(template.css);
                                                                        handleOpen();
                                                                        setCurrentThing("css");
                                                                    }} />
                                                                </Box>
                                                            </Box>
                                                            <Box sx={{ border: "3px dashed #D4D4D4", background: "#FCFCFC", minHeight: "70px" }} p={3} pb={0}>
                                                                {template.css.map((value, index) => {
                                                                    return (
                                                                        <Box key={index}>
                                                                            <Box sx={{ display: "flex", width: "100%" }}>
                                                                                <TextField
                                                                                    sx={{
                                                                                        width: "100%",
                                                                                        marginBottom: 3,
                                                                                        "& .MuiInputBase-input:focus": {
                                                                                            outline: "none", // Remove input focus outline
                                                                                            boxShadow: "none", // Remove any remaining shadow
                                                                                        },

                                                                                    }}
                                                                                    fullWidth
                                                                                    placeholder="Enter <style> Name..."
                                                                                    size="small"
                                                                                    value={value.name}
                                                                                    onChange={(e) => {
                                                                                        let temp = { ...template };
                                                                                        temp.css[index].name = e.target.value;
                                                                                        setTemplate(temp);
                                                                                    }}
                                                                                />
                                                                                <ClearIcon sx={{ width: "38px", height: "38px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "dimgray", color: 'white' }} onClick={() => {
                                                                                    let temp = { ...template };
                                                                                    temp.css.splice(index, 1);
                                                                                    setTemplate(temp);
                                                                                }} />
                                                                            </Box>
                                                                            <TextField
                                                                                fullWidth
                                                                                sx={{
                                                                                    width: "100%",
                                                                                    marginBottom: 3,
                                                                                    "& .MuiInputBase-input:focus": {
                                                                                        outline: "none", // Remove input focus outline
                                                                                        boxShadow: "none", // Remove any remaining shadow
                                                                                    },

                                                                                }}
                                                                                multiline
                                                                                rows={6}
                                                                                placeholder="Enter <style> chunk..."
                                                                                value={value.content}
                                                                                onChange={(e) => {
                                                                                    let temp = { ...template };
                                                                                    temp.css[index].content = e.target.value;
                                                                                    setTemplate(temp);
                                                                                }}
                                                                            />
                                                                        </Box>
                                                                    );
                                                                })}
                                                            </Box>

                                                            <Box sx={{ display: "flex", justifyContent: "space-between", marginTop: "20px", }}>
                                                                <Typography variant="body" component="div" sx={{ marginBottom: "20px", textDecoration: "underline", fontSize: "18px", fontWeight: "500" }}>
                                                                    JS Chunks
                                                                </Typography>
                                                                <Box>
                                                                    <AddIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#3278ff", color: 'white' }} onClick={() => {
                                                                        let temp = { ...template };
                                                                        temp.js.push({ name: '', content: '' });
                                                                        setTemplate(temp);
                                                                    }} />
                                                                    <SortIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#8f32ff", color: 'white' }} onClick={() => {
                                                                        setRowData(template.js);
                                                                        handleOpen();
                                                                        setCurrentThing("js");
                                                                    }} />
                                                                </Box>
                                                            </Box>
                                                            <Box sx={{ border: "3px dashed #D4D4D4", background: "#FCFCFC", minHeight: "70px" }} p={3} pb={0}>
                                                                {template.js.map((value, index) => {
                                                                    return (
                                                                        <Box key={index}>
                                                                            <Box sx={{ display: "flex", width: "100%" }}>
                                                                                <TextField
                                                                                    sx={{
                                                                                        width: "100%",
                                                                                        marginBottom: 3,
                                                                                        "& .MuiInputBase-input:focus": {
                                                                                            outline: "none", // Remove input focus outline
                                                                                            boxShadow: "none", // Remove any remaining shadow
                                                                                        },

                                                                                    }}
                                                                                    fullWidth
                                                                                    placeholder="Enter <script> Name..."
                                                                                    size="small"
                                                                                    value={value.name}
                                                                                    onChange={(e) => {
                                                                                        let temp = { ...template };
                                                                                        temp.js[index].name = e.target.value;
                                                                                        setTemplate(temp);
                                                                                    }}
                                                                                />
                                                                                <ClearIcon sx={{ width: "38px", height: "38px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "dimgray", color: 'white' }} onClick={() => {
                                                                                    let temp = { ...template };
                                                                                    temp.js.splice(index, 1);
                                                                                    setTemplate(temp);
                                                                                }} />
                                                                            </Box>
                                                                            <TextField
                                                                                fullWidth
                                                                                sx={{
                                                                                    width: "100%",
                                                                                    marginBottom: 3,
                                                                                    "& .MuiInputBase-input:focus": {
                                                                                        outline: "none", // Remove input focus outline
                                                                                        boxShadow: "none", // Remove any remaining shadow
                                                                                    },

                                                                                }}
                                                                                multiline
                                                                                rows={6}
                                                                                placeholder="Enter <script> chunk..."
                                                                                value={value.content}
                                                                                onChange={(e) => {
                                                                                    let temp = { ...template };
                                                                                    temp.js[index].content = e.target.value;
                                                                                    setTemplate(temp);
                                                                                }}
                                                                            />
                                                                        </Box>
                                                                    );
                                                                })}
                                                            </Box>
                                                        </Box>
                                                    </Box>
                                                </CardContent>
                                            </Card>
                                        </Box>
                                    </Box> : null}
                                {activeStep == 2 ?
                                    <Box
                                        sx={{
                                            padding: { xs: '20px', sm: '20px', md: '80px', lg: '80px', xl: '80px' },
                                            paddingTop: { xs: '20px', sm: '20px', md: '20px', lg: '20px', xl: '20px' },
                                            paddingBottom: { xs: '60px', sm: '60px', md: '60px', lg: '60px', xl: '60px' },
                                        }}>

                                        <Box>
                                            <Box sx={{ mb: 0, background: "#707070", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center" }}>
                                                <Box mt={3} mb={2}><img src={Doc1}></img></Box>
                                                <Typography variant="h3" component="div" color="white" mb={1} sx={{ fontSize: { xs: '30px', sm: '30px', md: '40px', lg: '40px', xl: '40px' } }}>
                                                    Upload Files
                                                </Typography>
                                                <Typography variant="body" color="white" mb={3}>
                                                    Upload Fonts, Images
                                                </Typography>
                                            </Box>
                                            <Card sx={{ minWidth: 275 }}>
                                                <CardContent>
                                                    <Box>
                                                        <Typography variant="h5" component="div" mt={2} sx={{ fontSize: { xs: '18px', sm: '18px', md: '20px', lg: '24px', xl: '24px' }, textAlign: "center" }}>
                                                            Upload the Fonts, Images available to connect them for this template
                                                        </Typography>
                                                        <Box sx={{
                                                            marginTop: "40px",
                                                            paddingLeft: { xs: '10px', sm: '50px', md: '100px', lg: '150px', xl: '150px' },
                                                            paddingRight: { xs: '10px', sm: '50px', md: '100px', lg: '150px', xl: '150px' },
                                                        }}>
                                                            <Box sx={{ display: "flex", justifyContent: "space-between" }}>
                                                                <Typography variant="body" component="div" sx={{ marginBottom: "20px", textDecoration: "underline", fontSize: "18px", fontWeight: "500" }}>
                                                                    Fonts
                                                                </Typography>
                                                                <Box>
                                                                    <AddIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#3278ff", color: 'white' }} onClick={() => {
                                                                        let temp = { ...template };
                                                                        temp.fonts.push({ alreadyUploaded: "", name: "", size: "", file: "" });
                                                                        setTemplate(temp);
                                                                    }} />
                                                                    <SortIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#8f32ff", color: 'white' }} onClick={() => {
                                                                        setRowData(template.fonts);
                                                                        handleOpen();
                                                                        setCurrentThing("fonts");
                                                                    }} />
                                                                </Box>
                                                            </Box>
                                                            {template.fonts.map((value, index) => {

                                                                return (
                                                                    <Box key={index} sx={{ border: "3px dashed #D4D4D4", background: "#FCFCFC", minHeight: "70px" }} p={2} pl={2} mb={2}>
                                                                        <Box sx={{ display: "flex", justifyContent: "space-between" }}>
                                                                            <Box sx={{ display: "flex" }}>
                                                                                <Box> <img src={Doc2} width="25"></img> </Box>
                                                                                <Box sx={{ marginLeft: "10px" }}>
                                                                                    {value.file ?
                                                                                        <Box sx={{ marginBottom: "-8px" }}>
                                                                                            <Typography variant="body" component="div" sx={{ fontWeight: "500", marginTop: "-5px" }}>
                                                                                                File {index + 1}: {value.name}
                                                                                            </Typography>
                                                                                            <Typography variant="body" color="#8B8B8B">
                                                                                                {value.size.toFixed(2)} MB
                                                                                            </Typography>
                                                                                        </Box> : value.alreadyUploaded ? <Box>
                                                                                            <Typography variant="body" component="div" sx={{ color: "#8B8B8B", fontWeight: "500", marginTop: "5px" }}>
                                                                                                <Typography variant="body" >
                                                                                                    Already Uploaded:
                                                                                                </Typography>
                                                                                                &nbsp;{value.alreadyUploaded}
                                                                                            </Typography>
                                                                                        </Box> : <Box>
                                                                                            <Typography variant="body" component="div" sx={{ color: "#8B8B8B", fontWeight: "500", textAlign: "center", marginTop: "5px" }}>
                                                                                                <Typography variant="body" onClick={() => document.getElementById(`hiddenfont${index}`).click()} component="span" sx={{ textDecoration: "underline", color: "#323232", fontWeight: "500", marginTop: "30px", textAlign: "center", cursor: "pointer" }}>
                                                                                                    Click here
                                                                                                </Typography>
                                                                                                &nbsp; to upload your file.
                                                                                            </Typography>
                                                                                            <input type="file" multiple style={{ display: "none" }} id={`hiddenfont${index}`} onChange={(e) => {
                                                                                                let temp = { ...template };
                                                                                                Array.from(e.target.files).forEach((file, indexInside) => {
                                                                                                    const insideFile = e.target.files[indexInside];
                                                                                                    if (indexInside == 0) {
                                                                                                        temp.fonts[index].alreadyUploaded = "";
                                                                                                        temp.fonts[index].file = insideFile;
                                                                                                        temp.fonts[index].name = insideFile.name;
                                                                                                        temp.fonts[index].size = insideFile.size / 1000000;
                                                                                                    } else if (indexInside > 0) {
                                                                                                        temp.fonts.push({
                                                                                                            alreadyUploaded: "",
                                                                                                            file: insideFile,
                                                                                                            name: insideFile.name,
                                                                                                            size: insideFile.size / 1000000
                                                                                                        });
                                                                                                    }
                                                                                                });
                                                                                                setTemplate(temp);
                                                                                            }} />
                                                                                        </Box>
                                                                                    }
                                                                                </Box>
                                                                            </Box>
                                                                            <Box sx={{ marginTop: "-5px", cursor: "pointer" }}>
                                                                                <ClearIcon sx={{ color: '#8B8B8B' }} onClick={() => {
                                                                                    let temp = { ...template };
                                                                                    temp.fonts.splice(index, 1);
                                                                                    setTemplate(temp);
                                                                                }} />
                                                                            </Box>
                                                                        </Box>
                                                                    </Box>
                                                                );
                                                            })}

                                                            <Box sx={{ display: "flex", justifyContent: "space-between" }}>
                                                                <Typography variant="body" component="div" sx={{ marginBottom: "20px", textDecoration: "underline", fontSize: "18px", fontWeight: "500" }}>
                                                                    Images
                                                                </Typography>
                                                                <Box>
                                                                    <AddIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#3278ff", color: 'white' }} onClick={() => {
                                                                        let temp = { ...template };
                                                                        temp.images.push({ alreadyUploaded: "", name: "", size: "", file: "" });
                                                                        setTemplate(temp);
                                                                    }} />
                                                                    <SortIcon sx={{ width: "30px", height: "30px", borderRadius: "3px", ml: 3, cursor: "pointer", background: "#8f32ff", color: 'white' }} onClick={() => {
                                                                        setRowData(template.images);
                                                                        handleOpen();
                                                                        setCurrentThing("images");
                                                                    }} />
                                                                </Box>
                                                            </Box>

                                                            {template.images.map((value, index) => {

                                                                return (
                                                                    <Box key={index} sx={{ border: "3px dashed #D4D4D4", background: "#FCFCFC", minHeight: "70px" }} p={2} pl={2} mb={2}>
                                                                        <Box sx={{ display: "flex", justifyContent: "space-between" }}>
                                                                            <Box sx={{ display: "flex" }}>
                                                                                <Box> <img src={Doc2} width="25"></img> </Box>
                                                                                <Box sx={{ marginLeft: "10px" }}>
                                                                                    {value.file ?
                                                                                        <Box sx={{ marginBottom: "-8px" }}>
                                                                                            <Typography variant="body" component="div" sx={{ fontWeight: "500", marginTop: "-5px" }}>
                                                                                                File {index + 1}: {value.name}
                                                                                            </Typography>
                                                                                            <Typography variant="body" color="#8B8B8B">
                                                                                                {value.size.toFixed(2)} MB
                                                                                            </Typography>
                                                                                        </Box> : value.alreadyUploaded ? <Box>
                                                                                            <Typography variant="body" component="div" sx={{ color: "#8B8B8B", fontWeight: "500", marginTop: "5px" }}>
                                                                                                <Typography variant="body" >
                                                                                                    Already Uploaded:
                                                                                                </Typography>
                                                                                                &nbsp;{value.alreadyUploaded}
                                                                                            </Typography>
                                                                                        </Box> : <Box>
                                                                                            <Typography variant="body" component="div" sx={{ color: "#8B8B8B", fontWeight: "500", textAlign: "center", marginTop: "5px" }}>
                                                                                                <Typography variant="body" onClick={() => document.getElementById(`hiddenimage${index}`).click()} component="span" sx={{ textDecoration: "underline", color: "#323232", fontWeight: "500", marginTop: "30px", textAlign: "center", cursor: "pointer" }}>
                                                                                                    Click here
                                                                                                </Typography>
                                                                                                &nbsp; to upload your file.
                                                                                            </Typography>
                                                                                            <input type="file" multiple style={{ display: "none" }} id={`hiddenimage${index}`} onChange={(e) => {
                                                                                                let temp = { ...template };
                                                                                                Array.from(e.target.files).forEach((file, indexInside) => {
                                                                                                    const insideFile = e.target.files[indexInside];
                                                                                                    if (indexInside == 0) {
                                                                                                        temp.images[index].alreadyUploaded = "";
                                                                                                        temp.images[index].file = insideFile;
                                                                                                        temp.images[index].name = insideFile.name;
                                                                                                        temp.images[index].size = insideFile.size / 1000000;
                                                                                                    } else if (indexInside > 0) {
                                                                                                        temp.images.push({
                                                                                                            alreadyUploaded: "",
                                                                                                            file: insideFile,
                                                                                                            name: insideFile.name,
                                                                                                            size: insideFile.size / 1000000
                                                                                                        });
                                                                                                    }
                                                                                                });
                                                                                                setTemplate(temp);
                                                                                            }} />
                                                                                        </Box>
                                                                                    }
                                                                                </Box>
                                                                            </Box>
                                                                            <Box sx={{ marginTop: "-5px", cursor: "pointer" }}>
                                                                                <ClearIcon sx={{ color: '#8B8B8B' }} onClick={() => {
                                                                                    let temp = { ...template };
                                                                                    temp.images.splice(index, 1);
                                                                                    setTemplate(temp);
                                                                                }} />
                                                                            </Box>
                                                                        </Box>
                                                                    </Box>
                                                                );
                                                            })}

                                                        </Box>
                                                    </Box>
                                                </CardContent>
                                            </Card>
                                        </Box>
                                    </Box> : null}
                                <Box sx={{ display: "flex", justifyContent: "center" }}>
                                    <>
                                        <Box sx={{ display: 'flex', flexDirection: 'row' }}>
                                            <Button variant="contained"
                                                color="neutral"
                                                disabled={activeStep === 0}
                                                onClick={handleBack}
                                                sx={{ mr: 3 }}
                                            >
                                                Back
                                            </Button>
                                            <Button variant="contained" color="lastButton" onClick={handleNext}>{activeStep === steps.length - 1 ? 'Submit' : 'Next'}</Button>
                                        </Box>
                                    </>
                                </Box>
                            </Box>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout >
    );
}
