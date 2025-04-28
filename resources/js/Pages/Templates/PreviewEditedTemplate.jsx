import Doc2 from "@/Assets/document2.png";
import "@/Assets/styles.css";
import { Head, router } from '@inertiajs/react';
import ClearIcon from '@mui/icons-material/Clear';
import RemoveCircleOutlineIcon from '@mui/icons-material/RemoveCircleOutline';
import SwapHorizIcon from '@mui/icons-material/SwapHoriz';
import { Box, Button, FormControl, InputLabel, MenuItem, Select, TextField, Typography } from '@mui/material';
import Backdrop from '@mui/material/Backdrop';
import Fade from '@mui/material/Fade';
import Modal from '@mui/material/Modal';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import { useEffect, useState } from "react";
import { HexColorPicker } from "react-colorful";
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import Swal from "sweetalert2";
import convert from 'color-convert';

export default function Dashboard({ id }) {

    const languages = [
        { value: 'AR', label: 'Arabic' },
        { value: 'BG', label: 'Bulgarian' },
        { value: 'CS', label: 'Czech' },
        { value: 'DA', label: 'Danish' },
        { value: 'DE', label: 'German' },
        { value: 'EL', label: 'Greek' },
        { value: 'EN', label: 'English (all English variants)' },
        { value: 'EN-GB', label: 'English (British)' },
        { value: 'EN-US', label: 'English (American)' },
        { value: 'ES', label: 'Spanish' },
        { value: 'ET', label: 'Estonian' },
        { value: 'FI', label: 'Finnish' },
        { value: 'FR', label: 'French' },
        { value: 'HU', label: 'Hungarian' },
        { value: 'ID', label: 'Indonesian' },
        { value: 'IT', label: 'Italian' },
        { value: 'JA', label: 'Japanese' },
        { value: 'KO', label: 'Korean' },
        { value: 'LT', label: 'Lithuanian' },
        { value: 'LV', label: 'Latvian' },
        { value: 'NB', label: 'Norwegian Bokmål' },
        { value: 'NL', label: 'Dutch' },
        { value: 'PL', label: 'Polish' },
        { value: 'PT', label: 'Portuguese (all Portuguese variants)' },
        { value: 'PT-BR', label: 'Portuguese (Brazilian)' },
        { value: 'PT-PT', label: 'Portuguese (all Portuguese variants excluding Brazilian Portuguese)' },
        { value: 'RO', label: 'Romanian' },
        { value: 'RU', label: 'Russian' },
        { value: 'SK', label: 'Slovak' },
        { value: 'SL', label: 'Slovenian' },
        { value: 'SV', label: 'Swedish' },
        { value: 'TR', label: 'Turkish' },
        { value: 'UK', label: 'Ukrainian' },
        { value: 'ZH', label: 'Chinese (all Chinese variants)' },
        { value: 'ZH-HANS', label: 'Chinese (simplified)' },
        { value: 'ZH-HANT', label: 'Chinese (traditional)' }
    ];

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

    const editableElements = [
        "h1",
        "h2",
        "h3",
        "h4",
        "h5",
        "h6",
        "img",
        "a",
        "i",
        "p",
        "span",
        "li",
        "ul",
        "select",
        "button",
        "option",
        // "section",
        // "div"
    ];

    const style = {
        position: 'absolute',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
        width: { xs: '90%', sm: '70%', md: '60%', lg: '50%', xl: '50%' },
        bgcolor: 'background.paper',
        // border: '2px solid #000',
        boxShadow: 10,
        p: 3,
        pt: 3,
        height: "400px",
        overflow: "hidden"
    };

    const [open, setOpen] = useState(false);
    const [data, setData] = useState(false);
    const [mainHTML, setMainHTML] = useState([{ html: '', status: true }]);
    const [mainCSS, setMainCSS] = useState('');
    const [editing, setEditing] = useState({
        editID: false,
        currentElement: false,
        elementName: "",
        innerHTML: "",
        imageSrc: "",
        actionType: false,
        addElementPosition: false,
        addElementType: false,
    });
    const [imageManagement, setImageManagement] = useState({
        via: "src",
        imageSrc: "",
        imageFile: { alreadyUploaded: "", name: "", size: "", file: "", blobUrl: "" },
        border: false,
        borderWidth: "",
        borderColor: "",
    });
    const [translator, setTranslator] = useState({
        fromLanguange: false,
        toLanguage: false,
        fromText: "",
        toText: "",
        currentSource: false, // TEXT, CUSTOM_HTML
    });
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
    const [spacerManagement, setSpacerManagement] = useState({
        height: ""
    });
    const [customHTMLManagement, setCustomHTMLManagement] = useState({
        input: "",
    });
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
    const [buttonManagement, setButtonManagement] = useState({
        buttonText: "",
        color: "",
        backgroundColor: "",
        fontSize: "",
        margin: "",
        padding: "",
        border: false,
        borderWidth: "",
        borderColor: "",
    });

    useEffect(() => {

        async function getData() {
            const url = route('editedTemplates.previewContent');

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', },
                    body: JSON.stringify({ edited_template_id: id })
                });

                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                const json = await response.json();
                // console.log(json);
                setData(json.data);

                let updated = json.data.editedTemplate.main_html;
                updated = updated.replace(/src="images\//g, `src="../../storage/templates/${json.data.template.uuid}/images/${json.data.template.asset_unique_uuid}-`);
                setMainHTML([{ html: updated, status: true }]);

                let css = json.data.css.content.replace(/fonts\//g, `../../storage/templates/${json.data.template.uuid}/fonts/${json.data.template.asset_unique_uuid}-`);
                setMainCSS(css);

            } catch (error) {
                console.error(error.message);
            }
        }

        getData()

        document.addEventListener("click", function (event) {
            event.preventDefault();
            handleClick(event);
        });

    }, []);

    useEffect(() => {
        // console.log(editing);
        if (editing && editing.actionType == "edit" && ['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'i', 'p', 'span'].includes(editing.elementName)) {
            let computedStyles = window.getComputedStyle(editing.currentElement);
            setTextManagement(prev => ({
                ...prev,
                textInput: editing.innerHTML,
                fontSize: removePxAndConvertToFloat(computedStyles.fontSize),
                color: `#${convert.rgb.hex(rgbToArray(computedStyles.color))}`,
                backgroundColor: `#${convert.rgb.hex(rgbToArray(computedStyles.background))}`,
                textAlign: computedStyles.textAlign,
                border: computedStyles.borderStyle,
                borderWidth: removePxAndConvertToFloat(computedStyles.borderWidth),
                borderColor: `#${convert.rgb.hex(rgbToArray(computedStyles.borderColor))}`,
            }));
        } else if (editing && editing.actionType == "edit" && ['li', 'ul', 'select', 'option'].includes(editing.elementName)) {
            setCustomHTMLManagement(prev => ({
                ...prev, // keep all previous values
                input: editing.innerHTML, // only update the value you want
            }));
        } else if (editing && editing.actionType == "edit" && ['img'].includes(editing.elementName)) {
            let computedStyles = window.getComputedStyle(editing.currentElement);
            setImageManagement(prev => ({
                ...prev, // keep all previous values
                imageSrc: editing.imageSrc, // only update the value you want
                border: computedStyles.borderStyle,
                borderWidth: removePxAndConvertToFloat(computedStyles.borderWidth),
                borderColor: `#${convert.rgb.hex(rgbToArray(computedStyles.borderColor))}`,
            }));
        } else if (editing && editing.actionType == "edit" && ['button'].includes(editing.elementName)) {
            let computedStyles = window.getComputedStyle(editing.currentElement);
            setButtonManagement(prev => ({
                ...prev, // keep all previous values
                buttonText: editing.innerHTML, // only update the value you want\
                color: `#${convert.rgb.hex(rgbToArray(computedStyles.color))}`,
                backgroundColor: `#${convert.rgb.hex(rgbToArray(computedStyles.background))}`,
                fontSize: removePxAndConvertToFloat(computedStyles.fontSize),
                margin: removePxAndConvertToFloat(computedStyles.margin),
                padding: removePxAndConvertToFloat(computedStyles.padding),
                border: computedStyles.borderStyle,
                borderWidth: removePxAndConvertToFloat(computedStyles.borderWidth),
                borderColor: `#${convert.rgb.hex(rgbToArray(computedStyles.borderColor))}`,
            }));
        }
    }, [editing]);

    function removePxAndConvertToFloat(value) {
        return parseFloat(value.replace('px', ''));
    }

    function rgbToArray(rgb) {
        const result = rgb.match(/^rgb\((\d+), (\d+), (\d+)\)$/);
        return result ? result.slice(1).map(Number) : [0, 0, 0]; // Return black if not valid
    }

    const generateRandomString = () => {
        const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        // First character is always a letter
        let result = letters.charAt(Math.floor(Math.random() * letters.length));

        // Generate the remaining 9 characters (can be letters or numbers)
        for (let i = 1; i < 10; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        return result;
    }

    const hasParentWithClass = (element, className) => {
        while (element && element !== document) {
            if (element.classList.contains(className)) {
                return true;
            }
            element = element.parentElement;
        }
        return false;
    }

    const handleClick = (event) => {
        // console.log(event.target.outerHTML);
        if (!event.target.outerHTML.includes("MuiModal-backdrop") && !hasParentWithClass(event.target, 'popoverPlate') && !event.target.outerHTML.includes("doNotAct")) {
            let randString = generateRandomString();
            if (editableElements.includes(event.target.localName) || event.target.classList.contains('editableDiv')) {
                event.target.classList.add(randString);
                setOpen(true);
                setEditing({
                    editID: randString,
                    currentElement: event.target,
                    elementName: event.target.localName,
                    innerHTML: event.target.innerHTML,
                    imageSrc: event.target.src,
                    actionType: false,
                    addElementPosition: false,
                })
            }
        }
    };

    const addNewContentHandler = async (position, existingElement, newElement) => {

        if (position == "bottom") {
            existingElement.style.marginBottom = "5px";
            existingElement.insertAdjacentElement('afterend', newElement);
        } else if (position == "top") {
            existingElement.style.marginTop = "5px";
            existingElement.insertAdjacentElement('beforebegin', newElement);
        } else if (position == "left") {
            existingElement.style.marginLeft = "5px";
            existingElement.parentNode.insertBefore(newElement, existingElement);
        } else if (position == "right") {
            existingElement.style.marginRight = "5px";
            existingElement.parentNode.insertBefore(newElement, existingElement.nextSibling);
        }
    }

    const updateHTMLHandler = async () => {

        if (translator.currentSource) {
            if (translator.currentSource == "text_management") {
                setTextManagement(prev => ({
                    ...prev,
                    textInput: translator.toText,
                }));
            } else if (translator.currentSource == "custom_html") {
                setCustomHTMLManagement(prev => ({
                    ...prev,
                    input: translator.toText,
                }));
            }
            setTranslator({
                fromLanguange: false,
                toLanguage: false,
                fromText: "",
                toText: "",
                currentSource: false, // TEXT, CUSTOM_HTML
            });
            return;
        }

        let element = document.querySelector(`.${editing.editID}`);

        //FURTHER EDITING REMAINING
        if ((editing.actionType == "edit" && ['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'i', 'p', 'span'].includes(editing.elementName)) || (editing.actionType === "add" && editing.addElementType == "p")) {
            //IF LINK IS NOT NULL THEN CONVERT ANY ELEMENT TO a
            const styles = {
                color: textManagement.color,
                background: textManagement.backgroundColor,
                fontSize: `${textManagement.fontSize}`,
                border: textManagement.border,
                borderWidth: `${textManagement.borderWidth}px`,
                borderColor: textManagement.borderColor,
                textAlign: textManagement.textAlign
            };

            if (editing.actionType == "edit") {
                if (textManagement.link && element.localName !== "a") {
                    let newElement = document.createElement('a');
                    Object.assign(newElement.style, styles);
                    newElement.innerHTML = textManagement.textInput;
                    newElement.className = element.className;
                    newElement.setAttribute('href', textManagement.link);
                    element.parentNode.replaceChild(newElement, element);
                } else {
                    Object.assign(element.style, styles);
                    element.innerHTML = textManagement.textInput;
                }
            } else {
                let newElement = '';
                if (textManagement.link) {
                    newElement = document.createElement('a');
                    Object.assign(newElement.style, styles);
                    newElement.innerHTML = textManagement.textInput;
                    newElement.setAttribute('href', textManagement.link);
                } else {
                    newElement = document.createElement('div');
                    newElement.classList.add('editableDiv');
                    Object.assign(newElement.style, styles);
                    newElement.innerHTML = textManagement.textInput;
                }
                await addNewContentHandler(editing.addElementPosition, element, newElement);
            }
        } else if ((editing.actionType == "edit" && ['li', 'ul', 'select', 'option'].includes(editing.elementName)) || (editing.actionType === "add" && editing.addElementType == "html")) {
            if (editing.actionType == "edit") {
                document.querySelector(`.${editing.editID}`).innerHTML = customHTMLManagement.input;
            } else {
                let newElement = document.createElement('div');
                newElement.classList.add('editableDiv');
                newElement.innerHTML = customHTMLManagement.input;
                await addNewContentHandler(editing.addElementPosition, element, newElement);
            }
        } else if ((editing.actionType == "edit" && ['img'].includes(editing.elementName)) || (editing.actionType === "add" && editing.addElementType == "img")) {
            const styles = {
                border: imageManagement.border,
                borderWidth: `${imageManagement.borderWidth}px`,
                borderColor: imageManagement.borderColor,
            };
            if (editing.actionType == "edit") {
                Object.assign(element.style, styles);
                element.src = imageManagement.imageSrc;
            } else {
                let newElement = document.createElement('img');
                Object.assign(newElement.style, styles);
                newElement.src = imageManagement.imageSrc;
                await addNewContentHandler(editing.addElementPosition, element, newElement);
            }
        } else if ((editing.actionType == "edit" && ['button'].includes(editing.elementName)) || (editing.actionType === "add" && editing.addElementType == "button")) {
            const styles = {
                color: buttonManagement.color,
                background: buttonManagement.backgroundColor,
                fontSize: `${buttonManagement.fontSize}px`,
                margin: `${buttonManagement.margin}px`,
                padding: `${buttonManagement.padding}px`,
                border: buttonManagement.border,
                borderWidth: `${buttonManagement.borderWidth}px`,
                borderColor: buttonManagement.borderColor,
            };

            if (editing.actionType == "edit") {
                Object.assign(element.style, styles);
                element.innerHTML = buttonManagement.buttonText;
            } else {
                let newElement = document.createElement('button');
                Object.assign(newElement.style, styles);
                newElement.innerHTML = buttonManagement.buttonText;
                await addNewContentHandler(editing.addElementPosition, element, newElement);
            }
        } else if (editing.actionType == "add" && editing.addElementType == "form") {

        } else if (editing.actionType == "add" && editing.addElementType == "br") {
            let newElement = document.createElement('div');
            newElement.classList.add('editableDiv');
            newElement.style.height = `${spacerManagement.height}px`;
            await addNewContentHandler(editing.addElementPosition, element, newElement);
        }

        let elementInside = document.querySelector(`.${editing.editID}`)
        elementInside.classList.remove(editing.editID);
        setMainHTML(prev => [
            ...prev.map(item => ({ ...item, status: false })), // Set previous statuses to false
            { html: document.querySelector(".mainHTML").innerHTML, status: true } // Add new entry
        ]);
        setOpen(false);
    }

    const undoHandler = () => {
        let temp = [...mainHTML];
        let currentIndex = temp.findIndex(html => html.status == true);
        if (currentIndex > 0 && (currentIndex + 1) <= mainHTML.length) {
            temp.forEach((html, index) => {
                if ((currentIndex - 1) == index)
                    temp[index].status = true;
                else
                    temp[index].status = false;
            });
            setMainHTML(temp);
        }
    }

    const redoHandler = () => {
        let temp = [...mainHTML];
        let currentIndex = temp.findIndex(html => html.status == true);
        if (currentIndex >= 0 && (currentIndex + 1) < mainHTML.length) {
            temp.forEach((html, index) => {
                if ((currentIndex + 1) == index)
                    temp[index].status = true;
                else
                    temp[index].status = false;
            });
            setMainHTML(temp);
        }
    }

    const handleChange = (property, value) => {
        let temp = { ...editing };
        temp[property] = value;
        setEditing(temp);
    }

    const updatedThemeSaveHandler = () => {
        async function getData() {
            const url = route('editedTemplates.save');

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', },
                    body: JSON.stringify({
                        edit_id: data.editedTemplate.id,
                        main_html: mainHTML.find(value => value.status).html,
                    })
                });

                const result = await response.json();
                if (result.success) {
                    Swal.fire({
                        title: 'Success',
                        text: result.message,
                        icon: 'success',
                        timer: 1000,
                        showConfirmButton: false,
                    });
                    router.get(route('userThemes', { id: data.editedTemplate.user_id }))
                }
            } catch (error) {
                Swal.fire({
                    title: 'Error!',
                    text: error.toString(),
                    icon: 'error',
                    timer: 1000,
                    showConfirmButton: false,
                });
            }
        }
        getData();
    }

    const translateOpenHandler = (source) => {
        if (source == "text_management") {
            setTranslator(prev => ({
                ...prev, // keep all previous values
                fromText: textManagement.textInput, // only update the value you want
                currentSource: "text_management"
            }));
        } else if (source == "custom_html") {
            setTranslator(prev => ({
                ...prev, // keep all previous values
                fromText: customHTMLManagement.input, // only update the value you want
                currentSource: "custom_html"
            }));
        }
    }

    const translateHandler = () => {
        async function getData() {
            const url = route('deepL');
            try {
                const response = await fetch(url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json", // <---- MISSING BEFORE
                        "Accept": "application/json",       // (optional but good)
                    },
                    body: JSON.stringify({
                        text: translator.fromText,
                        language: translator.toLanguage,
                        source_language: translator.fromLanguange,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                const result = await response.json();
                // console.log(result);

                if (result.success) {
                    setTranslator(prev => ({
                        ...prev,
                        toText: result.data,
                    }));
                }
            } catch (error) {
                console.error(error.message);
            }
        }
        getData();
    }

    const mainHTMLActive = mainHTML.find(html => html.status == true)

    return (
        <div>
            <Modal
                aria-labelledby="transition-modal-title"
                aria-describedby="transition-modal-description"
                open={open}
                closeAfterTransition
                slots={{ backdrop: Backdrop }}
                slotProps={{
                    backdrop: {
                        timeout: 100,
                        sx: {
                            backgroundColor: 'rgba(255, 255, 255, 0.8)', // <-- darker
                            opacity: "0.5 !important"
                        },
                    },
                }}
                className="popoverPlate"
            >
                <Fade in={open}>
                    <Box sx={style}>
                        <Box>
                            <Box sx={{ display: "flex", justifyContent: "space-between" }}>
                                <Box>
                                    <ArrowBackIcon sx={{ marginTop: '-4px', marginRight: "10px", cursor: "pointer" }} onClick={() => {
                                        if (translator.currentSource) {
                                            setTranslator({
                                                fromLanguange: false,
                                                toLanguage: false,
                                                fromText: "",
                                                toText: "",
                                                currentSource: false, // TEXT, CUSTOM_HTML
                                            });
                                        } else if (editing.addElementType) {
                                            setEditing(prev => ({
                                                ...prev, // keep all previous values
                                                addElementType: false, // only update the value you want
                                            }));
                                        } else if (editing.addElementPosition) {
                                            setEditing(prev => ({
                                                ...prev, // keep all previous values
                                                addElementPosition: false, // only update the value you want
                                            }));
                                        } else if (editing.actionType) {
                                            setEditing(prev => ({
                                                ...prev, // keep all previous values
                                                actionType: false, // only update the value you want
                                            }));
                                        }
                                    }} />
                                    <Typography variant="body" component="span" sx={{ fontWeight: 'bold', pt: 0.5, fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                                        Action Center
                                    </Typography>
                                </Box>
                                <div style={{ marginTop: "3px", cursor: "pointer", width: "18px", height: "18px", }} className='doNotAct' onClick={() => {
                                    let elementInside = document.querySelector(`.${editing.editID}`);
                                    elementInside.classList.remove(editing.editID);
                                    setOpen(false);
                                }}>
                                    <svg className='doNotAct' xmlns="http://www.w3.org/2000/svg" viewBox="50 50 412 412">
                                        <polygon fill="var(--ci-primary-color, currentColor)" points="427.314 107.313 404.686 84.687 256 233.373 107.314 84.687 84.686 107.313 233.373 256 84.686 404.687 107.314 427.313 256 278.627 404.686 427.313 427.314 404.687 278.627 256 427.314 107.313" className="doNotAct" />
                                    </svg>
                                </div>
                            </Box>
                            <Box mt={1.5} sx={{ height: "265px", overflow: "auto" }}>
                                {!translator.currentSource &&
                                    <>
                                        {editing && !editing.actionType &&
                                            <Box mt={2} sx={{ display: "flex", gap: 1 }}>
                                                <Button className="doNotAct cptlz megaButtonSquare" size='large' fullWidth color="primary" variant='outlined' onClick={() => handleChange("actionType", "add")}>Add Element</Button>
                                                <Box component="div" sx={{ marginTop: "15px" }} />
                                                <Button className="doNotAct cptlz megaButtonSquare" size='large' fullWidth color="warning" variant='outlined' onClick={() => handleChange("actionType", "edit")}>Edit Element</Button>
                                            </Box>
                                        }
                                        {editing && editing.actionType == "add" && !editing.addElementPosition &&
                                            <Box mt={2} sx={{ display: "flex", gap: 2, height: "100px" }}>
                                                <Box sx={{ width: "50%", height: "100px" }}>
                                                    <Button className="doNotAct cptlz megaButton" size='large' fullWidth color="primary" variant='outlined' onClick={() => handleChange("addElementPosition", "top")}>Top Side</Button>
                                                    <Box component="div" sx={{ marginTop: "15px" }} />
                                                    <Button className="doNotAct cptlz megaButton" size='large' fullWidth color="warning" variant='outlined' onClick={() => handleChange("addElementPosition", "left")}>Left Side</Button>
                                                </Box>
                                                <Box sx={{ width: "50%" }}>
                                                    <Button className="doNotAct cptlz megaButton" size='large' fullWidth color="secondary" variant='outlined' onClick={() => handleChange("addElementPosition", "right")}>Right Side</Button>
                                                    <Box component="div" sx={{ marginTop: "15px" }} />
                                                    <Button className="doNotAct cptlz megaButton" size='large' fullWidth color="success" variant='outlined' onClick={() => handleChange("addElementPosition", "bottom")}>Bottom Side</Button>
                                                </Box>
                                            </Box>
                                        }
                                        {((editing?.actionType === "add" && editing?.addElementPosition)) && !editing.addElementType && (
                                            <Box mt={2} sx={{ display: "flex", flexWrap: "wrap", gap: 2 }}>
                                                <Button className="doNotAct cptlz megaButton" variant='outlined' color="warning" sx={{ textTransform: "capitalize" }} onClick={() => handleChange("addElementType", "img")}>Image</Button>
                                                <Box component="span" sx={{ marginTop: "10px" }} />
                                                {/* <Button className="doNotAct cptlz megaButton" variant='outlined' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => handleChange("addElementType", "image")}>DeepL</Button> */}
                                                {/* <Box component="span" sx={{ marginTop: "10px" }} /> */}
                                                {/* <Button className="doNotAct cptlz megaButton" variant='outlined' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => handleChange("addElementType", "image")}>Chat GPT</Button> */}
                                                {/* <Box component="span" sx={{ marginTop: "10px" }} /> */}
                                                <Button className="doNotAct cptlz megaButton" variant='outlined' color="success" sx={{ textTransform: "capitalize" }} onClick={() => handleChange("addElementType", "p")}>Text</Button>
                                                <Box component="span" sx={{ marginTop: "10px" }} />
                                                <Button className="doNotAct cptlz megaButton" variant='outlined' color="secondary" sx={{ textTransform: "capitalize" }} onClick={() => handleChange("addElementType", "br")}>Spacer</Button>
                                                <Box component="span" sx={{ marginTop: "10px" }} />
                                                <Button className="doNotAct cptlz megaButton" variant='outlined' color="primary" sx={{ textTransform: "capitalize" }} onClick={() => handleChange("addElementType", "html")}>Custom HTML</Button>
                                                <Box component="span" sx={{ marginTop: "10px" }} />
                                                <Button className="doNotAct cptlz megaButton" variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => handleChange("addElementType", "form")}>Form</Button>
                                                <Box component="span" sx={{ marginTop: "10px" }} />
                                                <Button className="doNotAct cptlz megaButton" variant='outlined' color="error" sx={{ textTransform: "capitalize" }} onClick={() => handleChange("addElementType", "button")}>Button</Button>
                                            </Box>
                                        )}
                                    </>
                                }

                                {translator.currentSource ?
                                    <Box sx={{ pt: "5px" }}>
                                        {/* DEEPL TRANSLATOR */}
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
                                                    {languages.map((item, index) => (
                                                        <MenuItem className="doNotAct" value={item.value} sx={{ textTransform: 'capitalize' }}>{item.label}</MenuItem>
                                                    ))}
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
                                                    {languages.map((item, index) => (
                                                        <MenuItem className="doNotAct" value={item.value} sx={{ textTransform: 'capitalize' }}>{item.label}</MenuItem>
                                                    ))}
                                                </Select>
                                            </FormControl>
                                            <Button variant="contained" className="doNotAct cptlz" sx={{ width: "200px" }} onClick={translateHandler}>Translate</Button>
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
                                    </Box> : (
                                        (editing?.actionType === "add" && editing?.addElementPosition && editing.addElementType) ||
                                        (editing?.actionType === "edit")
                                    ) ? (
                                        <Box>
                                            {/* IMAGE MANAGEMENT MODAL */}
                                            {(editing && editing.actionType == "edit" && ['img'].includes(editing.elementName) ||
                                                (editing && editing.actionType === "add" && editing.addElementType == "img")
                                            ) && (
                                                    <Box>
                                                        <Box mb={1.5} sx={{ display: "flex", justifyContent: "flex-end" }}>
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
                                                                    <MenuItem className="doNotAct" value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
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
                                                                inputLabel: { shrink: true }
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
                                                )}

                                            {/* TEXT MANAGEMENT MODAL */}
                                            {(editing && editing.actionType == "edit" && ['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'i', 'p', 'span'].includes(editing.elementName) ||
                                                (editing && editing.actionType === "add" && editing.addElementType == "p")
                                            ) && (
                                                    <Box>
                                                        <Box mb={1.5} sx={{ display: "flex", justifyContent: "flex-end" }}>
                                                            <Button className="doNotAct" size="small" variant="contained" color="primary" >AI</Button>
                                                            <Box component="span" sx={{ marginLeft: "10px" }} />
                                                            <Button className="doNotAct" size="small" variant="contained" color="primary" sx={{ textTransform: "capitalize" }} onClick={() => translateOpenHandler("text_management")}>Translate</Button>
                                                        </Box>
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
                                                                            <MenuItem className="doNotAct" value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
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
                                                                            <MenuItem className="doNotAct" value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
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
                                                                        inputLabel: { shrink: true }
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
                                                )}

                                            {/* CUSTOM HTML */}
                                            {(editing && editing.actionType == "edit" && ['li', 'ul', 'select', 'option'].includes(editing.elementName) ||
                                                (editing && editing.actionType === "add" && editing.addElementType == "html")
                                            ) && (
                                                    <Box>
                                                        <Box mb={1.5} sx={{ display: "flex", justifyContent: "flex-end" }}>
                                                            <Button className="doNotAct" size="small" variant="contained" color="primary" >AI</Button>
                                                            <Box component="span" sx={{ marginLeft: "10px" }} />
                                                            <Button className="doNotAct" size="small" variant="contained" color="primary" sx={{ textTransform: "capitalize" }} onClick={() => translateOpenHandler("custom_html")}>Translate</Button>
                                                        </Box>
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
                                                )}

                                            {/* BUTTOM MANAGEMENT */}
                                            {(editing && editing.actionType == "edit" && ['button'].includes(editing.elementName) ||
                                                (editing && editing.actionType === "add" && editing.addElementType == "button")
                                            ) && (
                                                    <Box sx={{ padding: "10px 0px" }}>
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
                                                                        <HexColorPicker class color={buttonManagement.color} style={{ marginTop: "7px", width: "100%" }} onChange={(e) => setButtonManagement({ ...buttonManagement, color: e })} />
                                                                    </Box>
                                                                    <Box sx={{ width: "50%" }}>
                                                                        <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                                            Background
                                                                        </Typography>
                                                                        <HexColorPicker color={buttonManagement.backgroundColor} style={{ marginTop: "7px", width: "100%" }} onChange={(e) => setButtonManagement({ ...buttonManagement, backgroundColor: e })} />
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
                                                                }}
                                                                placeholder='Enter Font Size'
                                                                value={buttonManagement.fontSize}
                                                                onChange={(e) => {
                                                                    setButtonManagement({ ...buttonManagement, fontSize: e.target.value })
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
                                                                }}
                                                                placeholder='Enter Margin'
                                                                value={buttonManagement.margin}
                                                                onChange={(e) => {
                                                                    setButtonManagement({ ...buttonManagement, margin: e.target.value })
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
                                                                }}
                                                                placeholder='Enter Padding'
                                                                value={buttonManagement.padding}
                                                                onChange={(e) => {
                                                                    setButtonManagement({ ...buttonManagement, padding: e.target.value })
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
                                                                    value={buttonManagement.border}
                                                                    label="Border"
                                                                    size='small'
                                                                    onChange={(e) => {
                                                                        setButtonManagement({ ...buttonManagement, border: e.target.value })
                                                                    }}
                                                                >
                                                                    {borderStyles.map((item, index) => (
                                                                        <MenuItem className="doNotAct" value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
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
                                                                    inputLabel: { shrink: true }
                                                                }}
                                                                placeholder='Enter Border Width'
                                                                value={buttonManagement.borderWidth}
                                                                onChange={(e) => {
                                                                    setButtonManagement({ ...buttonManagement, borderWidth: e.target.value })
                                                                }}
                                                            />
                                                            <Box mt={1} sx={{ display: "flex" }} className="customPicker">
                                                                <Box sx={{ width: "50%" }}>
                                                                    <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                                        Border Color
                                                                    </Typography>
                                                                    <HexColorPicker color={buttonManagement.borderColor} style={{ marginTop: "7px", width: "100%", paddingRight: "20px" }} onChange={(e) => setButtonManagement({ ...buttonManagement, borderColor: e })} />
                                                                </Box>
                                                                <Box sx={{ width: "50%" }}>
                                                                    <Typography variant="body" component="div" sx={{ mb: 1, fontSize: "14px" }}>
                                                                        View
                                                                    </Typography>
                                                                    <Box component={"button"} sx={{ color: `${buttonManagement.color}`, background: `${buttonManagement.backgroundColor}`, padding: `${buttonManagement.padding}px`, fontSize: `${buttonManagement.fontSize}px`, margin: `${buttonManagement.margin}px`, textAlign: "center", border: `${buttonManagement.borderWidth}px ${buttonManagement.border} ${buttonManagement.borderColor}` }}>{buttonManagement.buttonText}</Box>
                                                                </Box>
                                                            </Box>
                                                        </Box>
                                                    </Box>
                                                )}

                                            {/* FORMS */}
                                            {editing && editing.actionType == "add" && editing.addElementType == "form" &&
                                                <Box sx={{ padding: "10px 0px" }}>
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
                                                                            <MenuItem className="doNotAct" value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
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
                                                                            <MenuItem className="doNotAct" value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
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
                                            }

                                            {/* SPACER */}
                                            {editing && editing.actionType == "add" && editing.addElementType == "br" &&
                                                <Box sx={{ pt: "5px" }}>
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
                                            }

                                            {/* CHATGPT */}
                                            {/* <Box sx={{ pt: "5px" }}>
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
                                            </Box> */}
                                        </Box>
                                    ) : null}
                            </Box>
                            <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
                                <Button variant='outlined' color="info" sx={{ textTransform: "capitalize" }} onClick={() => {
                                    let elementInside = document.querySelector(`.${editing.editID}`)
                                    elementInside.classList.remove(editing.editID);
                                    setOpen(false);
                                }}>Cancel</Button>
                                <Box component="span" sx={{ marginLeft: "20px" }} />
                                <Button variant='contained' color="success" sx={{ textTransform: "capitalize" }} onClick={updateHTMLHandler}>Add</Button>
                            </Box>
                        </Box>
                    </Box>
                </Fade>
            </Modal>
            <Head title={`Preview (${data && data.template.name})`} />
            <div>
                <Box sx={{ background: "#c0c0c0", justifyContent: "space-between", display: "flex" }}>
                    <Box className="doNotAct" sx={{ mt: 0.3, ml: 0.5, fontWeight: "bold" }}>
                        <svg style={{ cursor: "pointer", rotate: "180deg" }} className='doNotAct' xmlns="http://www.w3.org/2000/svg" width="25px" height="25px" viewBox="0 0 24 24" fill="none" onClick={() => router.get(route('userThemes', { id: data.editedTemplate.user_id }))}>
                            <path className='doNotAct' id="Vector" d="M12 15L15 12M15 12L12 9M15 12H4M4 7.24802V7.2002C4 6.08009 4 5.51962 4.21799 5.0918C4.40973 4.71547 4.71547 4.40973 5.0918 4.21799C5.51962 4 6.08009 4 7.2002 4H16.8002C17.9203 4 18.4796 4 18.9074 4.21799C19.2837 4.40973 19.5905 4.71547 19.7822 5.0918C20 5.5192 20 6.07899 20 7.19691V16.8036C20 17.9215 20 18.4805 19.7822 18.9079C19.5905 19.2842 19.2837 19.5905 18.9074 19.7822C18.48 20 17.921 20 16.8031 20H7.19691C6.07899 20 5.5192 20 5.0918 19.7822C4.71547 19.5905 4.40973 19.2839 4.21799 18.9076C4 18.4798 4 17.9201 4 16.8V16.75" stroke="#000000" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    </Box>
                    <Box className="doNotAct" sx={{ display: "flex" }}>
                        <svg style={{ cursor: "pointer" }} className='doNotAct' width="30px" height="30px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" onClick={undoHandler}>
                            <path className="doNotAct" fillRule="evenodd" clipRule="evenodd" d="M10.7071 4.29289C11.0976 4.68342 11.0976 5.31658 10.7071 5.70711L8.41421 8H13.5C16.5376 8 19 10.4624 19 13.5C19 16.5376 16.5376 19 13.5 19H11C10.4477 19 10 18.5523 10 18C10 17.4477 10.4477 17 11 17H13.5C15.433 17 17 15.433 17 13.5C17 11.567 15.433 10 13.5 10H8.41421L10.7071 12.2929C11.0976 12.6834 11.0976 13.3166 10.7071 13.7071C10.3166 14.0976 9.68342 14.0976 9.29289 13.7071L5.29289 9.70711C4.90237 9.31658 4.90237 8.68342 5.29289 8.29289L9.29289 4.29289C9.68342 3.90237 10.3166 3.90237 10.7071 4.29289Z" fill="#000000" />
                        </svg>
                        <svg style={{ cursor: "pointer" }} className="doNotAct" width="30px" height="30px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" onClick={redoHandler}>
                            <path className="doNotAct" fillRule="evenodd" clipRule="evenodd" d="M13.2929 4.29289C13.6834 3.90237 14.3166 3.90237 14.7071 4.29289L18.7071 8.29289C19.0976 8.68342 19.0976 9.31658 18.7071 9.70711L14.7071 13.7071C14.3166 14.0976 13.6834 14.0976 13.2929 13.7071C12.9024 13.3166 12.9024 12.6834 13.2929 12.2929L15.5858 10H10.5C8.567 10 7 11.567 7 13.5C7 15.433 8.567 17 10.5 17H13C13.5523 17 14 17.4477 14 18C14 18.5523 13.5523 19 13 19H10.5C7.46243 19 5 16.5376 5 13.5C5 10.4624 7.46243 8 10.5 8H15.5858L13.2929 5.70711C12.9024 5.31658 12.9024 4.68342 13.2929 4.29289Z" fill="#000000" />
                        </svg>
                    </Box>
                    <Box>
                        <svg className="doNotAct" style={{ cursor: "pointer", marginTop: "5px", marginRight: "5px" }} width="20px" height="20px" xmlns="http://www.w3.org/2000/svg" fill="#000000" version="1.1" id="Capa_1" viewBox="0 0 407.096 407.096" xmlSpace="preserve" onClick={updatedThemeSaveHandler}>
                            <path className="doNotAct" d="M402.115,84.008L323.088,4.981C319.899,1.792,315.574,0,311.063,0H17.005C7.613,0,0,7.614,0,17.005v373.086    c0,9.392,7.613,17.005,17.005,17.005h373.086c9.392,0,17.005-7.613,17.005-17.005V96.032    C407.096,91.523,405.305,87.197,402.115,84.008z M300.664,163.567H67.129V38.862h233.535V163.567z" />
                            <path className="doNotAct" d="M214.051,148.16h43.08c3.131,0,5.668-2.538,5.668-5.669V59.584c0-3.13-2.537-5.668-5.668-5.668h-43.08    c-3.131,0-5.668,2.538-5.668,5.668v82.907C208.383,145.622,210.92,148.16,214.051,148.16z" />
                        </svg>
                    </Box>
                </Box>
                {data &&
                    <div>
                        <div dangerouslySetInnerHTML={{ __html: data.template.head }} />
                        <style>
                            {mainCSS}
                        </style>
                        {/* <pre>{mainHTML}</pre> */}
                        <div className='mainHTML' dangerouslySetInnerHTML={{ __html: mainHTMLActive.html }} />
                    </div>
                }
            </div>
        </div>
    );
}
