import Doc2 from "@/Assets/document2.png";
import "@/Assets/styles.css";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ClearIcon from '@mui/icons-material/Clear';
import RemoveCircleOutlineIcon from '@mui/icons-material/RemoveCircleOutline';
import SwapHorizIcon from '@mui/icons-material/SwapHoriz';
import { Box, Button, FormControl, InputLabel, MenuItem, Select, TextField, Typography } from "@mui/material";
import Backdrop from '@mui/material/Backdrop';
import Fade from '@mui/material/Fade';
import Modal from '@mui/material/Modal';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import { useState } from 'react';
import { HexColorPicker } from "react-colorful";

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

    const textAlignProperties = [
        'left',       // Aligns text to the left
        'right',      // Aligns text to the right
        'center',     // Centers the text
        'justify',    // Stretches the lines so that each line has equal width
        'start',      // Aligns text to the start of the writing mode (LTR or RTL)
        'end',        // Aligns text to the end of the writing mode (LTR or RTL)
        'match-parent' // Inherits the alignment from the parent, but adjusts for direction
    ];

    const requireds = [
        'required',
        'not-required',
    ];

    const commonInputTypes = [
        "text",
        "email",
        "password",
        "number",
        "checkbox",
        "radio",
        "file",
        "submit"
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

    const [openThree, setOpenThree] = useState(false);
    const [chatGPT, setChatGPT] = useState({
        query: "",
        response: `<div class="comment">
        <strong>Ali Raza</strong>: This product looks amazing! 🔥
        </div>

        <div class="comment">
        <strong>Sara Khan</strong>: I’ve been using this for a month, worth it!
        </div>

        <div class="comment">
        <strong>Hamza Sheikh</strong>: Where can I buy this?
        </div>

        <div class="comment">
        <strong>Mehwish Aslam</strong>: Just ordered mine! Can’t wait 😍
        </div>

        <div class="comment">
        <strong>Bilal Ahmed</strong>: Does it come in different sizes?
        </div>`,
    });

    const [openFour, setOpenFour] = useState(false);
    const [textManagement, setTextManagement] = useState({
        textInput: "",
        color: "",
        backgroundColor: "",
        fontSize: "12",
        link: "",
        border: false,
        borderWidth: "",
        borderColor: "",
        textAlign: false,
    });

    const [openFive, setOpenFive] = useState(false);
    const [spacerManagement, setSpacerManagement] = useState({
        height: ""
    });

    const [openSix, setOpenSix] = useState(false);
    const [customHTMLManagement, setCustomHTMLManagement] = useState({
        input: "",
    });

    const [openSeven, setOpenSeven] = useState(false);
    const [formManagement, setFormManagement] = useState({
        submitText: "",
        submitTextColor: "",
        submitBackgroundColor: "",
        inputs: [{
            name: "",
            required: false,
            type: false,
            sort: ""
        }]
    });

    const [openEight, setOpenEight] = useState(false);
    const [buttonManagement, setButtonManagement] = useState({
        buttonText: "",
        buttonTextColor: "",
        buttonBackgroundColor: "",
        buttonFontSize: "",
        buttonMargin: "",
        buttonPadding: "",
        buttonBorder: false,
        buttonBorderWidth: "",
        buttonBorderColor: "",
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
                                                return <Typography color="grey"> Select Border</Typography>;
                                            }
                                            return <>{value}</>;
                                        }}
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
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => setOpen(false)}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={() => setOpen(false)}>Add</Button>
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
                                <Typography variant="body" component="div" sx={{ fontWeight: 'bold', fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                    DeepL Translator
                                </Typography>
                            </Box>
                            <Box sx={{ pt: "5px", height: "260px", overflow: "auto" }}>
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

            {/* CHATGPT */}
            <Modal
                aria-labelledby="transition-modal-title"
                aria-describedby="transition-modal-description"
                open={openThree}
                onClose={() => setOpenThree(false)}
                closeAfterTransition
                slots={{ backdrop: Backdrop }}
                slotProps={{
                    backdrop: {
                        timeout: 100,
                    },
                }}
            >
                <Fade in={openThree}>
                    <Box sx={style}>
                        <Box>
                            <Box sx={{ mb: 1.5 }}>
                                <Typography variant="body" component="div" sx={{ fontWeight: 'bold', fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                    AI (Chat GPT)
                                </Typography>
                            </Box>
                            <Box sx={{ pt: "5px", height: "260px", overflow: "auto" }}>
                                <TextField
                                    fullWidth
                                    size='small'
                                    placeholder='Enter Query'
                                    value={chatGPT.query}
                                    onChange={(e) => {
                                        setChatGPT({ ...chatGPT, query: e.target.value })
                                    }}
                                />
                                <Box sx={{ mt: 2, p: 1, borderRadius: "3px", border: "1px solid black", background: "#e5e5e5", fontSize: "12px" }}>
                                    <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{chatGPT.response}</pre>
                                </Box>
                            </Box>
                            <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => setOpenThree(false)}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={() => setOpenThree(false)}>Add</Button>
                            </Box>
                        </Box>
                    </Box>
                </Fade>
            </Modal>

            {/* TEXT MANAGEMENT MODAL */}
            <Modal
                aria-labelledby="transition-modal-title"
                aria-describedby="transition-modal-description"
                open={openFour}
                onClose={() => setOpenFour(false)}
                closeAfterTransition
                slots={{ backdrop: Backdrop }}
                slotProps={{
                    backdrop: {
                        timeout: 100,
                    },
                }}
            >
                <Fade in={openFour}>
                    <Box sx={style}>
                        <Box>
                            <Box sx={{ display: "flex", justifyContent: "space-between", mb: 1 }}>
                                <Typography variant="body" component="div" sx={{ fontWeight: 'bold', fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                    Text Management
                                </Typography>
                                <Box>
                                    <Button size="small" variant="contained" color="primary" >AI</Button>
                                    <Box component="span" sx={{ marginLeft: "10px" }} />
                                    <Button size="small" variant="contained" color="primary" sx={{ textTransform: "capitalize" }}>Translate</Button>
                                </Box>
                            </Box>
                            <Box sx={{ padding: "5px 0px", height: "260px", overflow: "auto" }}>
                                <Box sx={{ display: 'flex', gap: "15px" }}>
                                    <Box sx={{ width: "50%" }}>
                                        <TextField
                                            className="multilineCss"
                                            fullWidth
                                            size='small'
                                            placeholder='Enter Text'
                                            value={textManagement.textInput}
                                            multiline
                                            rows={5}
                                            onChange={(e) => {
                                                setTextManagement({ ...textManagement, textInput: e.target.value })
                                            }}
                                        />
                                        <TextField
                                            sx={{ mt: 2 }}
                                            className="multilineCss"
                                            fullWidth
                                            size='small'
                                            placeholder='Enter Http:// Link'
                                            value={textManagement.link}
                                            multiline
                                            rows={3.5}
                                            label="Link"
                                            slotProps={{
                                                inputLabel: { shrink: true },
                                                htmlInput: {
                                                    min: 0,    // Minimum value allowed
                                                    max: 72    // Maximum value allowed
                                                }
                                            }}
                                            onChange={(e) => {
                                                setTextManagement({ ...textManagement, link: e.target.value })
                                            }}
                                        />
                                        <TextField
                                            sx={{ mt: 2 }}
                                            type='number'
                                            fullWidth
                                            size='small'
                                            label="Font Size"
                                            slotProps={{
                                                inputLabel: { shrink: true },
                                                htmlInput: {
                                                    min: 0,    // Minimum value allowed
                                                    max: 72    // Maximum value allowed
                                                }
                                            }}
                                            placeholder='Enter Font Size'
                                            value={textManagement.fontSize}
                                            onChange={(e) => {
                                                setTextManagement({ ...textManagement, fontSize: e.target.value })
                                            }}
                                        />
                                    </Box>
                                    <Box sx={{ width: "50%" }}>
                                        <Box sx={{ display: "flex", gap: "15px" }} className="customPicker">
                                            <Box sx={{ width: "50%" }}>
                                                <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                    Color
                                                </Typography>
                                                <HexColorPicker class color={textManagement.color} style={{ marginTop: "7px", width: "100%" }} onChange={(e) => setTextManagement({ ...textManagement, color: e })} />
                                            </Box>
                                            <Box sx={{ width: "50%" }}>
                                                <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                    Background
                                                </Typography>
                                                <HexColorPicker color={textManagement.backgroundColor} style={{ marginTop: "7px", width: "100%" }} onChange={(e) => setTextManagement({ ...textManagement, backgroundColor: e })} />
                                            </Box>
                                        </Box>
                                        <FormControl fullWidth sx={{ mt: 2.1 }}>
                                            <InputLabel id="demo-simple-select-label">Text Align</InputLabel>
                                            <Select
                                                // displayEmpty
                                                renderValue={(value) => {
                                                    if (!value) {
                                                        return <Typography color="grey"> Select Text Alignment</Typography>;
                                                    }
                                                    return <>{value}</>;
                                                }}
                                                value={textManagement.textAlign}
                                                label="Text Align"
                                                size='small'
                                                onChange={(e) => {
                                                    setTextManagement({ ...textManagement, textAlign: e.target.value })
                                                }}
                                            >
                                                {textAlignProperties.map((item, index) => (
                                                    <MenuItem value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
                                                ))}
                                            </Select>
                                        </FormControl>
                                        <FormControl fullWidth sx={{ mt: 2.1 }}>
                                            <InputLabel id="demo-simple-select-label">Border</InputLabel>
                                            <Select
                                                // displayEmpty
                                                renderValue={(value) => {
                                                    if (!value) {
                                                        return <Typography color="grey"> Select Border</Typography>;
                                                    }
                                                    return <>{value}</>;
                                                }}
                                                value={textManagement.border}
                                                label="Border"
                                                size='small'
                                                onChange={(e) => {
                                                    setTextManagement({ ...textManagement, border: e.target.value })
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
                                            value={textManagement.borderWidth}
                                            onChange={(e) => {
                                                setTextManagement({ ...textManagement, borderWidth: e.target.value })
                                            }}
                                        />
                                    </Box>
                                </Box>
                                <Box mt={1} sx={{ display: "flex" }}>
                                    <Box sx={{ width: "28%" }} className="customPicker">
                                        <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                            Border Color
                                        </Typography>
                                        <HexColorPicker color={textManagement.borderColor} style={{ marginTop: "7px", width: "100%", paddingRight: "20px" }} onChange={(e) => setTextManagement({ ...textManagement, borderColor: e })} />
                                    </Box>
                                    <Box sx={{ width: "72%" }}>
                                        <Typography variant="body" component="div" sx={{ mb: 1, fontSize: "14px" }}>
                                            View
                                        </Typography>
                                        <Box sx={{ textAlign: textManagement.textAlign, minHeight: "102px", mt: 0.7, p: 1, borderRadius: "3px", border: `${textManagement.borderWidth}px ${textManagement.border} ${textManagement.borderColor}`, color: textManagement.color, background: textManagement.backgroundColor == "" ? "#dedede" : textManagement.backgroundColor, fontSize: `${textManagement.fontSize}px` }}>
                                            <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{textManagement.textInput == "" ? "No Text" : textManagement.textInput}</pre>
                                        </Box>
                                    </Box>
                                </Box>
                            </Box>
                            <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => setOpenFour()}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={() => setOpenFour()}>Add</Button>
                            </Box>
                        </Box>
                    </Box>
                </Fade>
            </Modal>

            {/* SPACER */}
            <Modal
                aria-labelledby="transition-modal-title"
                aria-describedby="transition-modal-description"
                open={openFive}
                onClose={() => setOpenFive(false)}
                closeAfterTransition
                slots={{ backdrop: Backdrop }}
                slotProps={{
                    backdrop: {
                        timeout: 100,
                    },
                }}
            >
                <Fade in={openFive}>
                    <Box sx={style}>
                        <Box>
                            <Box sx={{ mb: 1.5 }}>
                                <Typography variant="body" component="div" sx={{ fontWeight: 'bold', fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                    Spacer
                                </Typography>
                            </Box>
                            <Box sx={{ pt: "5px", height: "260px", overflow: "auto" }}>
                                <TextField
                                    fullWidth
                                    type="number"
                                    size='small'
                                    label="Spacer Height"
                                    slotProps={{
                                        inputLabel: { shrink: true }
                                    }}
                                    placeholder='Enter Spacer Height'
                                    value={spacerManagement.height}
                                    onChange={(e) => {
                                        setSpacerManagement({ ...spacerManagement, height: e.target.value })
                                    }}
                                />
                            </Box>
                            <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => setOpenFive(false)}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={() => setOpenFive(false)}>Add</Button>
                            </Box>
                        </Box>
                    </Box>
                </Fade>
            </Modal>

            {/* CUSTOM HTML */}
            <Modal
                aria-labelledby="transition-modal-title"
                aria-describedby="transition-modal-description"
                open={openSix}
                onClose={() => setOpenSix(false)}
                closeAfterTransition
                slots={{ backdrop: Backdrop }}
                slotProps={{
                    backdrop: {
                        timeout: 100,
                    },
                }}
            >
                <Fade in={openSix}>
                    <Box sx={style}>
                        <Box>
                            <Box sx={{ display: "flex", justifyContent: "space-between", mb: 1 }}>
                                <Typography variant="body" component="div" sx={{ fontWeight: 'bold', fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                    Custom HMTL
                                </Typography>
                                <Box>
                                    <Button size="small" variant="contained" color="primary" >AI</Button>
                                    <Box component="span" sx={{ marginLeft: "10px" }} />
                                    <Button size="small" variant="contained" color="primary" sx={{ textTransform: "capitalize" }}>Translate</Button>
                                </Box>
                            </Box>
                            <Box sx={{ pt: "10px", height: "260px", overflow: "auto" }}>
                                <TextField
                                    className="multilineCss"
                                    fullWidth
                                    size='small'
                                    placeholder='Enter Custom HTML'
                                    value={customHTMLManagement.input}
                                    multiline
                                    rows={10} // You can adjust the number of rows as needed
                                    onChange={(e) => {
                                        setCustomHTMLManagement({ ...customHTMLManagement, input: e.target.value })
                                    }}
                                />
                            </Box>
                            <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => setOpenSix(false)}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={() => setOpenSix(false)}>Add</Button>
                            </Box>
                        </Box>
                    </Box>
                </Fade>
            </Modal>

            {/* FORMS */}
            <Modal
                aria-labelledby="transition-modal-title"
                aria-describedby="transition-modal-description"
                open={openSeven}
                onClose={() => setOpenSeven(false)}
                closeAfterTransition
                slots={{ backdrop: Backdrop }}
                slotProps={{
                    backdrop: {
                        timeout: 100,
                    },
                }}
            >
                <Fade in={openSeven}>
                    <Box sx={style}>
                        <Box>
                            <Box sx={{ display: "flex", justifyContent: "space-between", mb: 1 }}>
                                <Typography variant="body" component="div" sx={{ fontWeight: 'bold', fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                    Form Management
                                </Typography>
                            </Box>
                            <Box sx={{ padding: "10px 0px", height: "270px", overflow: "auto" }}>
                                <Box sx={{ display: 'flex', gap: "15px" }}>
                                    <Box sx={{ width: "50%" }}>
                                        <TextField
                                            fullWidth
                                            size='small'
                                            label="Submit Button Text"
                                            multiline
                                            rows={3}
                                            slotProps={{
                                                inputLabel: { shrink: true }
                                            }}
                                            placeholder='Enter Button Text'
                                            value={formManagement.submitText}
                                            onChange={(e) => {
                                                setFormManagement({ ...formManagement, submitText: e.target.value })
                                            }}
                                        />
                                    </Box>
                                    <Box mt={-1.6} sx={{ width: "50%" }}>
                                        <Box sx={{ display: "flex", gap: "15px" }} className="customPickerTwo" >
                                            <Box sx={{ width: "50%" }}>
                                                <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                    Color
                                                </Typography>
                                                <HexColorPicker class color={formManagement.submitTextColor} style={{ marginTop: "7px", width: "100%" }} onChange={(e) => setFormManagement({ ...formManagement, submitTextColor: e })} />
                                            </Box>
                                            <Box sx={{ width: "50%" }}>
                                                <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                    Background
                                                </Typography>
                                                <HexColorPicker color={formManagement.submitBackgroundColor} style={{ marginTop: "7px", width: "100%" }} onChange={(e) => setFormManagement({ ...formManagement, submitBackgroundColor: e })} />
                                            </Box>
                                        </Box>
                                    </Box>
                                </Box>
                                <Box sx={{ display: "flex", justifyContent: "flex-end" }} mt={2}>
                                    <Button size="small" variant="contained" color="primary" sx={{ textTransform: "capitalize" }} onClick={() => {
                                        let temp = { ...formManagement };
                                        temp.inputs.push({
                                            name: "",
                                            required: false,
                                            type: false,
                                            sort: ""
                                        });
                                        console.log(temp);
                                        setFormManagement(temp);
                                    }}>Add Input</Button>
                                </Box>
                                <Box mt={2} p={2} pt={0} sx={{ border: "2px dashed #a5a5a5", borderRadius: "2px" }}>
                                    {formManagement && formManagement.inputs.map((value, index) => (
                                        <Box key={index} sx={{ mt: 2, display: "flex" }}>
                                            <TextField
                                                sx={{ width: "150px" }}
                                                size='small'
                                                label="Input Name"
                                                slotProps={{
                                                    inputLabel: { shrink: true }
                                                }}
                                                placeholder='Enter Name'
                                                value={value.name}
                                                onChange={(e) => {
                                                    let temp = { ...formManagement };
                                                    temp.inputs[index] = { ...temp.inputs[index], name: e.target.value };
                                                    setFormManagement(temp);
                                                }}
                                            />
                                            <Box component="span" sx={{ marginLeft: "10px" }} />
                                            <FormControl>
                                                <InputLabel id="demo-simple-select-label">Required</InputLabel>
                                                <Select
                                                    sx={{ width: "150px" }}
                                                    // displayEmpty
                                                    renderValue={(value) => {
                                                        if (!value) {
                                                            return <Typography color="grey"> Select...</Typography>;
                                                        }
                                                        return <>{value}</>;
                                                    }}
                                                    value={value.required}
                                                    label="Required"
                                                    size='small'
                                                    onChange={(e) => {
                                                        let temp = { ...formManagement };
                                                        temp.inputs[index] = { ...temp.inputs[index], required: e.target.value };
                                                        setFormManagement(temp);
                                                    }}
                                                >
                                                    {requireds.map((item, index) => (
                                                        <MenuItem value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
                                                    ))}
                                                </Select>
                                            </FormControl>
                                            <Box component="span" sx={{ marginLeft: "10px" }} />
                                            <FormControl>
                                                <InputLabel id="demo-simple-select-label">Type</InputLabel>
                                                <Select
                                                    sx={{ width: "140px" }}
                                                    // displayEmpty
                                                    renderValue={(value) => {
                                                        if (!value) {
                                                            return <Typography color="grey"> Select...</Typography>;
                                                        }
                                                        return <>{value}</>;
                                                    }}
                                                    value={value.type}
                                                    label="Type"
                                                    size='small'
                                                    onChange={(e) => {
                                                        let temp = { ...formManagement };
                                                        temp.inputs[index] = { ...temp.inputs[index], type: e.target.value };
                                                        setFormManagement(temp);
                                                    }}
                                                >
                                                    {commonInputTypes.map((item, index) => (
                                                        <MenuItem value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
                                                    ))}
                                                </Select>
                                            </FormControl>
                                            <Box component="span" sx={{ marginLeft: "10px" }} />
                                            <TextField
                                                sx={{ width: "60px" }}
                                                size='small'
                                                placeholder='Sort'
                                                value={value.sort}
                                                onChange={(e) => {
                                                    let temp = { ...formManagement };
                                                    temp.inputs[index] = { ...temp.inputs[index], sort: e.target.value };
                                                    setFormManagement(temp);
                                                }}
                                            />
                                            <Box component="span" sx={{ marginLeft: "11px" }} />
                                            <Box mt={0.5}>
                                                <RemoveCircleOutlineIcon sx={{ cursor: "pointer" }} onClick={() => {
                                                    let temp = { ...formManagement };
                                                    temp.inputs.splice(index, 1);
                                                    setFormManagement(temp);
                                                }} />
                                            </Box>
                                        </Box>
                                    ))}
                                </Box>
                            </Box>
                            <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => setOpenSeven(false)}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={() => setOpenSeven(false)}>Add</Button>
                            </Box>
                        </Box>
                    </Box>
                </Fade>
            </Modal>

            {/* BUTTON MANAGEMENT */}
            <Modal
                aria-labelledby="transition-modal-title"
                aria-describedby="transition-modal-description"
                open={openEight}
                onClose={() => setOpenEight(false)}
                closeAfterTransition
                slots={{ backdrop: Backdrop }}
                slotProps={{
                    backdrop: {
                        timeout: 100,
                    },
                }}
            >
                <Fade in={openEight}>
                    <Box sx={style}>
                        <Box>
                            <Box sx={{ display: "flex", justifyContent: "space-between", mb: 1 }}>
                                <Typography variant="body" component="div" sx={{ fontWeight: 'bold', fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                    Button Management
                                </Typography>
                            </Box>
                            <Box sx={{ padding: "10px 0px", height: "270px", overflow: "auto" }}>
                                <Box sx={{ display: 'flex', gap: "15px" }}>
                                    <Box sx={{ width: "50%" }}>
                                        <TextField
                                            fullWidth
                                            size='small'
                                            label="Button Text"
                                            multiline
                                            rows={3}
                                            slotProps={{
                                                inputLabel: { shrink: true }
                                            }}
                                            placeholder='Enter Button Text'
                                            value={buttonManagement.buttonText}
                                            onChange={(e) => {
                                                setButtonManagement({ ...buttonManagement, buttonText: e.target.value })
                                            }}
                                        />
                                    </Box>
                                    <Box mt={-1.6} sx={{ width: "50%" }}>
                                        <Box sx={{ display: "flex", gap: "15px" }} className="customPickerTwo" >
                                            <Box sx={{ width: "50%" }}>
                                                <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                    Color
                                                </Typography>
                                                <HexColorPicker class color={buttonManagement.buttonTextColor} style={{ marginTop: "7px", width: "100%" }} onChange={(e) => setButtonManagement({ ...buttonManagement, buttonTextColor: e })} />
                                            </Box>
                                            <Box sx={{ width: "50%" }}>
                                                <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                    Background
                                                </Typography>
                                                <HexColorPicker color={buttonManagement.buttonBackgroundColor} style={{ marginTop: "7px", width: "100%" }} onChange={(e) => setButtonManagement({ ...buttonManagement, buttonBackgroundColor: e })} />
                                            </Box>
                                        </Box>
                                    </Box>
                                </Box>
                                <Box>
                                    <TextField
                                        sx={{ mt: 2 }}
                                        type='number'
                                        fullWidth
                                        size='small'
                                        label="Font Size"
                                        slotProps={{
                                            inputLabel: { shrink: true },
                                            htmlInput: {
                                                min: 0,    // Minimum value allowed
                                                max: 72    // Maximum value allowed
                                            }
                                        }}
                                        placeholder='Enter Font Size'
                                        value={buttonManagement.buttonFontSize}
                                        onChange={(e) => {
                                            setButtonManagement({ ...buttonManagement, buttonFontSize: e.target.value })
                                        }}
                                    />
                                    <TextField
                                        sx={{ mt: 2 }}
                                        type='number'
                                        fullWidth
                                        size='small'
                                        label="Margin"
                                        slotProps={{
                                            inputLabel: { shrink: true },
                                            htmlInput: {
                                                min: 0,    // Minimum value allowed
                                                max: 72    // Maximum value allowed
                                            }
                                        }}
                                        placeholder='Enter Margin'
                                        value={buttonManagement.buttonMargin}
                                        onChange={(e) => {
                                            setButtonManagement({ ...buttonManagement, buttonMargin: e.target.value })
                                        }}
                                    />
                                    <TextField
                                        sx={{ mt: 2 }}
                                        type='number'
                                        fullWidth
                                        size='small'
                                        label="Padding"
                                        slotProps={{
                                            inputLabel: { shrink: true },
                                            htmlInput: {
                                                min: 0,    // Minimum value allowed
                                                max: 72    // Maximum value allowed
                                            }
                                        }}
                                        placeholder='Enter Padding'
                                        value={buttonManagement.buttonPadding}
                                        onChange={(e) => {
                                            setButtonManagement({ ...buttonManagement, buttonPadding: e.target.value })
                                        }}
                                    />
                                    <FormControl fullWidth sx={{ mt: 2.1 }}>
                                        <InputLabel id="demo-simple-select-label">Border</InputLabel>
                                        <Select
                                            // displayEmpty
                                            renderValue={(value) => {
                                                if (!value) {
                                                    return <Typography color="grey"> Select Border</Typography>;
                                                }
                                                return <>{value}</>;
                                            }}
                                            value={buttonManagement.buttonBorder}
                                            label="Border"
                                            size='small'
                                            onChange={(e) => {
                                                setButtonManagement({ ...buttonManagement, buttonBorder: e.target.value })
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
                                        value={buttonManagement.buttonBorderWidth}
                                        onChange={(e) => {
                                            setButtonManagement({ ...buttonManagement, buttonBorderWidth: e.target.value })
                                        }}
                                    />
                                    <Box mt={1} sx={{ display: "flex" }}>
                                        <Box sx={{ width: "50%" }}>
                                            <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                Border Color
                                            </Typography>
                                            <HexColorPicker color={buttonManagement.buttonBorderColor} style={{ marginTop: "7px", width: "100%", paddingRight: "20px" }} onChange={(e) => setButtonManagement({ ...buttonManagement, buttonBorderColor: e })} />
                                        </Box>
                                        <Box sx={{ width: "50%" }}>
                                            <Typography variant="body" component="div" sx={{ mb: 1, fontSize: "14px" }}>
                                                View
                                            </Typography>
                                            <Box component={"button"} sx={{ color: `${buttonManagement.buttonTextColor}`, background: `${buttonManagement.buttonBackgroundColor}`, padding: `${buttonManagement.buttonPadding}px`, fontSize: `${buttonManagement.buttonFontSize}px`, margin: `${buttonManagement.buttonMargin}px`, textAlign: "center", border: `${buttonManagement.buttonBorderWidth}px ${buttonManagement.buttonBorder} ${buttonManagement.buttonBorderColor}` }}>{buttonManagement.buttonText}</Box>
                                        </Box>
                                    </Box>
                                </Box>
                            </Box>

                            <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => setOpenEight(false)}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={() => setOpenEight(false)}>Add</Button>
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
                            <Box component="span" sx={{ marginLeft: "10px" }} />
                            <Button variant='contained' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => setOpenThree(true)}>Chat GPT</Button>
                            <Box component="span" sx={{ marginLeft: "10px" }} />
                            <Button variant='contained' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => setOpenFour(true)}>Text</Button>
                            <Box component="span" sx={{ marginLeft: "10px" }} />
                            <Button variant='contained' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => setOpenFive(true)}>Spacer</Button>
                            <Box component="span" sx={{ marginLeft: "10px" }} />
                            <Button variant='contained' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => setOpenSix(true)}>Custom HTML</Button>
                            <Box component="span" sx={{ marginLeft: "10px" }} />
                            <Button variant='contained' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => setOpenSeven(true)}>Form</Button>
                            <Box component="span" sx={{ marginLeft: "10px" }} />
                            <Button variant='contained' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => setOpenEight(true)}>Button</Button>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout >
    );
}
