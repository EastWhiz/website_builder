import "@/Assets/styles.css";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Button, Box, FormControl, InputLabel, MenuItem, Select, TextField, Typography } from "@mui/material";
import Backdrop from '@mui/material/Backdrop';
import Fade from '@mui/material/Fade';
import Modal from '@mui/material/Modal';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import { useState } from 'react';
import Doc2 from "@/Assets/document2.png";
import ClearIcon from '@mui/icons-material/Clear';
import { HexColorPicker } from "react-colorful";
import SwapHorizIcon from '@mui/icons-material/SwapHoriz';

export default function Dashboard() {

    const borderStyles = [
        'solid',
        'dashed',
        'dotted',
        'double',
        'groove',
        'ridge',
        'inset',
        'outset',
    ];

    const style = {
        position: 'absolute',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
        width: { xs: '90%', sm: '70%', md: '60%', lg: '50%', xl: '50%' },
        bgcolor: 'background.paper',
        // border: '2px solid #000',
        boxShadow: 24,
        p: 3,
        pt: 3,
        height: "400px",
        overflow: "hidden"
    };

    const [open, setOpen] = useState(false);
    const [imageManagement, setImageManagement] = useState({
        via: "src",
        imageSrc: "",
        imageFile: { alreadyUploaded: "", name: "", size: "", file: "", blobUrl: "" },
        border: false,
        borderWidth: "",
        borderColor: "",
    });


    const [openTwo, setOpenTwo] = useState(false);
    const [translator, setTranslator] = useState({
        fromLanguange: false,
        toLanguage: false,
        fromText: "",
        toText: "",
        currentSource: false, // TEXT, CUSTOM_HTML
    });

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Frontend
                </h2>
            }
        >
            <Head title="Dashboard" />

            {/* IMAGE MANAGEMENT MODAL */}
            <Modal
                aria-labelledby="transition-modal-title"
                aria-describedby="transition-modal-description"
                open={open}
                onClose={() => setOpen(false)}
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
                            <Box sx={{ display: "flex", justifyContent: "space-between", mb: 3 }}>
                                <Typography variant="body" component="div" sx={{ fontWeight: 'bold', pt: 0.5, fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                    Image Management
                                </Typography>
                                <Box>
                                    <ToggleButtonGroup
                                        color="primary"
                                        value={imageManagement.via}
                                        exclusive
                                        onChange={(event, newAlignment) => {
                                            setImageManagement({ ...imageManagement, via: newAlignment })
                                        }}
                                        aria-label="Platform"
                                    >
                                        <ToggleButton className='toggle_button' value="src">Src</ToggleButton>
                                        <ToggleButton className='toggle_button' value="upload">Upload</ToggleButton>
                                    </ToggleButtonGroup>
                                </Box>
                            </Box>
                            <Box sx={{ padding: "5px 0px", height: "240px", overflow: "auto" }}>
                                {imageManagement.via == "src" ?
                                    <TextField
                                        fullWidth
                                        size='small'
                                        label="Image Src"
                                        slotProps={{
                                            inputLabel: { shrink: true }
                                        }}
                                        placeholder='Enter Image URL'
                                        value={imageManagement.imageSrc}
                                        onChange={(e) => {
                                            setImageManagement({ ...imageManagement, imageSrc: e.target.value })
                                        }}
                                    /> :
                                    <Box sx={{ border: "3px dashed #D4D4D4", background: "#FCFCFC", minHeight: "10px" }} p={1}>
                                        <Box sx={{ display: "flex", justifyContent: "space-between" }}>
                                            <Box sx={{ display: "flex" }}>
                                                <Box> <img src={Doc2} width="15"></img> </Box>
                                                <Box sx={{ marginLeft: "10px" }}>
                                                    {imageManagement.imageFile.file ?
                                                        <Box sx={{ marginBottom: "-8px" }}>
                                                            <Typography variant="body" component="div" sx={{ fontWeight: "500", marginTop: "-5px" }}>
                                                                File: {imageManagement.imageFile.name}
                                                            </Typography>
                                                            <Typography variant="body" color="#8B8B8B">
                                                                {imageManagement.imageFile.size.toFixed(2)} MB
                                                            </Typography>
                                                        </Box> : imageManagement.imageFile.alreadyUploaded ? <Box>
                                                            <Typography variant="body" component="div" sx={{ color: "#8B8B8B", fontWeight: "500", marginTop: "5px" }}>
                                                                <Typography variant="body" >
                                                                    Already Uploaded:
                                                                </Typography>
                                                                &nbsp;{imageManagement.imageFile.alreadyUploaded}
                                                            </Typography>
                                                        </Box> : <Box>
                                                            <Typography variant="body" component="div" sx={{ color: "#8B8B8B", fontWeight: "500", textAlign: "center", marginTop: "0px" }}>
                                                                <Typography variant="body" onClick={() => document.getElementById(`hiddenFileUpload`).click()} component="span" sx={{ textDecoration: "underline", color: "#323232", fontWeight: "500", marginTop: "30px", textAlign: "center", cursor: "pointer" }}>
                                                                    Click here
                                                                </Typography>
                                                                &nbsp; to upload your file.
                                                            </Typography>
                                                            <input type="file" multiple style={{ display: "none" }} id={`hiddenFileUpload`} onChange={(e) => {
                                                                const insideFile = e.target.files[0];
                                                                console.log(insideFile);
                                                                let temp = { ...imageManagement };
                                                                temp.imageFile.alreadyUploaded = "";
                                                                temp.imageFile.file = insideFile;
                                                                temp.imageFile.name = insideFile.name;
                                                                temp.imageFile.size = insideFile.size / 1000000;
                                                                const blob = new Blob([insideFile], { type: 'image/png' });
                                                                const blobUrl = URL.createObjectURL(blob);
                                                                temp.imageFile.blobUrl = blobUrl;
                                                                setImageManagement(temp);
                                                            }} />
                                                        </Box>
                                                    }
                                                </Box>
                                            </Box>
                                            <Box sx={{ marginTop: "", cursor: "pointer" }}>
                                                <ClearIcon sx={{ color: '#8B8B8B' }} onClick={() => {
                                                    let temp = { ...imageManagement };
                                                    temp.imageFile = { alreadyUploaded: "", name: "", size: "", file: "", blobUrl: "" };
                                                    setImageManagement(temp);
                                                }} />
                                            </Box>
                                        </Box>
                                    </Box>
                                }
                                <FormControl fullWidth sx={{ mt: 2.1 }}>
                                    <InputLabel id="demo-simple-select-label">Border</InputLabel>
                                    <Select
                                        // displayEmpty
                                        renderValue={(value) => {
                                            if (!value) {
                                                return <Typography color="grey"> Select Border Color</Typography>;
                                            }
                                            return <>{value}</>;
                                        }}
                                        placeholder='Select Border Style'
                                        value={imageManagement.border}
                                        label="Border"
                                        size='small'
                                        onChange={(e) => {
                                            setImageManagement({ ...imageManagement, border: e.target.value })
                                        }}
                                    >
                                        {borderStyles.map((item, index) => (
                                            <MenuItem value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
                                        ))}
                                    </Select>
                                </FormControl>
                                <TextField
                                    sx={{ mt: 2 }}
                                    type='number'
                                    fullWidth
                                    size='small'
                                    label="Border Width"
                                    slotProps={{
                                        inputLabel: { shrink: true },
                                        htmlInput: {
                                            min: 0,    // Minimum value allowed
                                            max: 10    // Maximum value allowed
                                        }
                                    }}
                                    placeholder='Enter Border Width'
                                    value={imageManagement.borderWidth}
                                    onChange={(e) => {
                                        setImageManagement({ ...imageManagement, borderWidth: e.target.value })
                                    }}
                                />
                                <Box mt={1} sx={{ display: "flex" }}>
                                    <Box sx={{ width: "50%" }}>
                                        <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                            Border Color
                                        </Typography>
                                        <HexColorPicker color={imageManagement.borderColor} style={{ marginTop: "7px", width: "100%", paddingRight: "20px" }} onChange={(e) => setImageManagement({ ...imageManagement, borderColor: e })} />
                                    </Box>
                                    <Box sx={{ width: "50%" }}>
                                        <Typography variant="body" component="div" sx={{ mb: 1, fontSize: "14px" }}>
                                            View
                                        </Typography>
                                        <Box component="img" src={imageManagement.via == 'src' ? (imageManagement.imageSrc != '' ? imageManagement.imageSrc : 'https://placehold.co/600x390/dedede/000000/png') : (imageManagement.imageFile.blobUrl != '' ? imageManagement.imageFile.blobUrl : 'https://placehold.co/600x390/dedede/000000/png')} sx={{ objectFit: "cover", border: `${imageManagement.borderWidth}px ${imageManagement.border} ${imageManagement.borderColor}` }} />
                                    </Box>
                                </Box>
                            </Box>
                            <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => handleClose()}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={() => handleClose()}>Add</Button>
                            </Box>
                        </Box>
                    </Box>
                </Fade>
            </Modal>

            {/* DEEPL TRANSLATOR */}
            <Modal
                aria-labelledby="transition-modal-title"
                aria-describedby="transition-modal-description"
                open={openTwo}
                onClose={() => setOpenTwo(false)}
                closeAfterTransition
                slots={{ backdrop: Backdrop }}
                slotProps={{
                    backdrop: {
                        timeout: 100,
                    },
                }}
            >
                <Fade in={openTwo}>
                    <Box sx={style}>
                        <Box>
                            <Box sx={{ mb: 1.5 }}>
                                <Typography variant="body" component="div" sx={{ fontWeight: 'bold', pt: 0.5, fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                    DeepL Translator
                                </Typography>
                            </Box>
                            <Box sx={{ pt: "5px", height: "255px", overflow: "auto" }}>
                                <Box sx={{ display: "flex", gap: "20px" }}>
                                    <FormControl fullWidth>
                                        <InputLabel id="demo-simple-select-label">Translate From</InputLabel>
                                        <Select
                                            // displayEmpty
                                            renderValue={(value) => {
                                                if (!value) {
                                                    return <Typography color="grey">From Langugage</Typography>;
                                                }
                                                return <>{value}</>;
                                            }}
                                            placeholder='Select Border Style'
                                            value={translator.fromLanguange}
                                            label="Translate From"
                                            size='small'
                                            onChange={(e) => {
                                                setTranslator({ ...translator, fromLanguange: e.target.value })
                                            }}
                                        >
                                            <MenuItem value={"English"} sx={{ textTransform: 'capitalize' }}>English</MenuItem>
                                            <MenuItem value={"German"} sx={{ textTransform: 'capitalize' }}>German</MenuItem>
                                        </Select>
                                    </FormControl>
                                    <Box sx={{ mt: 0.5, cursor: "pointer" }}>
                                        <SwapHorizIcon />
                                    </Box>
                                    <FormControl fullWidth>
                                        <InputLabel id="demo-simple-select-label">Translate To</InputLabel>
                                        <Select
                                            // displayEmpty
                                            renderValue={(value) => {
                                                if (!value) {
                                                    return <Typography color="grey">To Langugage</Typography>;
                                                }
                                                return <>{value}</>;
                                            }}
                                            placeholder='Select Border Style'
                                            value={translator.toLanguage}
                                            label="Translate To"
                                            size='small'
                                            onChange={(e) => {
                                                setTranslator({ ...translator, toLanguage: e.target.value })
                                            }}
                                        >
                                            <MenuItem value={"English"} sx={{ textTransform: 'capitalize' }}>English</MenuItem>
                                            <MenuItem value={"German"} sx={{ textTransform: 'capitalize' }}>German</MenuItem>
                                        </Select>
                                    </FormControl>
                                </Box>
                                <Box sx={{ mt: 2, display: "flex", gap: "20px" }}>
                                    <TextField
                                        className="multilineCss"
                                        fullWidth
                                        size='small'
                                        placeholder='Enter Text'
                                        value={translator.fromText}
                                        multiline
                                        rows={7.5} // You can adjust the number of rows as needed
                                        onChange={(e) => {
                                            setTranslator({ ...translator, fromText: e.target.value })
                                        }}
                                    />
                                    <TextField
                                        className="multilineCss"
                                        fullWidth
                                        size='small'
                                        placeholder='Translation'
                                        value={translator.toText}
                                        multiline
                                        rows={7.5} // You can adjust the number of rows as needed
                                        onChange={(e) => {
                                            setTranslator({ ...translator, toText: e.target.value })
                                        }}
                                    />
                                </Box>
                            </Box>
                            <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => setOpenTwo(false)}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={() => setOpenTwo(false)}>Translate</Button>
                            </Box>
                        </Box>
                    </Box>
                </Fade>
            </Modal>

            <div className="py-16">
                {/* sm:px-6 lg:px-8 */}
                <div className="mx-auto max-w-7xl">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <Button variant='contained' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => setOpen(true)}>Image</Button>
                            <Box component="span" sx={{ marginLeft: "10px" }} />
                            <Button variant='contained' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => setOpenTwo(true)}>DeepL</Button>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout >
    );
}
