import Doc2 from "@/Assets/document2.png";
import "@/Assets/styles.css";
import { Head, router, usePage } from '@inertiajs/react';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import ClearIcon from '@mui/icons-material/Clear';
import SwapHorizIcon from '@mui/icons-material/SwapHoriz';
import { Box, Button, FormControl, InputLabel, MenuItem, Select as MuiSelect, TextField, Typography } from '@mui/material';
import Backdrop from '@mui/material/Backdrop';
import Fade from '@mui/material/Fade';
import Modal from '@mui/material/Modal';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import convert from 'color-convert';
import { useEffect, useState } from "react";
import { HexColorPicker } from "react-colorful";
import Swal from "sweetalert2";
import Select from 'react-select';

export default function Dashboard({ id }) {

    const mainQuery = usePage().props;
    // console.log(mainQuery);

    // const backgroundSelectors = [
    //     ".main-con-box",
    //     ".main_parent_container",
    //     ".main-container",
    //     ".banner"
    // ];

    const languages = [
        { value: 'AR', label: 'Arabic' },
        { value: 'BG', label: 'Bulgarian' },
        { value: 'CS', label: 'Czech' },
        { value: 'DA', label: 'Danish' },
        { value: 'DE', label: 'German' },
        { value: 'EL', label: 'Greek' },
        { value: 'EN', label: 'English' },
        // { value: 'EN-GB', label: 'English (British)' },
        // { value: 'EN-US', label: 'English (American)' },
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
        { value: 'PT', label: 'Portuguese' },
        // { value: 'PT-BR', label: 'Portuguese (Brazilian)' },
        // { value: 'PT-PT', label: 'Portuguese (all Portuguese variants excluding Brazilian Portuguese)' },
        { value: 'RO', label: 'Romanian' },
        { value: 'RU', label: 'Russian' },
        { value: 'SK', label: 'Slovak' },
        { value: 'SL', label: 'Slovenian' },
        { value: 'SV', label: 'Swedish' },
        { value: 'TR', label: 'Turkish' },
        { value: 'UK', label: 'Ukrainian' },
        { value: 'ZH', label: 'Chinese' },
        // { value: 'ZH-HANS', label: 'Chinese (simplified)' },
        // { value: 'ZH-HANT', label: 'Chinese (traditional)' }
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

    const linkTypes = [
        'Selected Element',
        'Full Element',
    ];

    const requireds = [
        'required',
        'not-required',
    ];

    const apiTypesUrls = [
        { label: 'Elps', value: 'https://ep.elpistrack.io/api/signup/procform' },
        { label: 'Novelix', value: 'https://nexlapi.net/leads' },
        { label: 'Tigloo', value: 'https://platform.onlinepartnersed.com/api/signup/procform' },
        { label: 'Electra', value: 'https://lcaapi.net/leads' },
        { label: 'Meeseeksmedia', value: 'https://mskmd-api.com/api/v2/leads' },
        { label: 'Dark', value: 'https://tb.connnecto.com/api/signup/procform' },
    ];

    const apiTypes = [
        { label: 'Elps', value: 'elps' },
        { label: 'Novelix', value: 'novelix' },
        { label: 'Tigloo', value: 'tigloo' },
        { label: 'Electra', value: 'electra' },
        { label: 'Meeseeksmedia', value: 'meeseeksmedia' },
        { label: 'Dark', value: 'dark' },
        { label: 'Koi', value: 'koi' },
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

    const formFieldLanguageDefaults = {
        "English": { "firstname": "First Name", "lastname": "Last Name", "email": "Email", "temp_phone": "Phone" },
        "German": { "firstname": "Vorname", "lastname": "Nachname", "email": "E-Mail", "temp_phone": "Telefon" },
        "Spanish": { "firstname": "Nombre", "lastname": "Apellido", "email": "Correo electrónico", "temp_phone": "Teléfono" },
        "Japanese": { "firstname": "名", "lastname": "姓", "email": "メール", "temp_phone": "電話" },
        "French": { "firstname": "Prénom", "lastname": "Nom", "email": "E-mail", "temp_phone": "Téléphone" },
        "Italian": { "firstname": "Nome", "lastname": "Cognome", "email": "Email", "temp_phone": "Telefono" },
        "Korean": { "firstname": "이름", "lastname": "성", "email": "이메일", "temp_phone": "전화번호" },
        "Polish": { "firstname": "Imię", "lastname": "Nazwisko", "email": "E-mail", "temp_phone": "Telefon" },
        "Portuguese": { "firstname": "Nome", "lastname": "Sobrenome", "email": "E-mail", "temp_phone": "Telefone" }
    };

    const formFieldLanguageOptions = [
        { "value": "English", "label": "English" },
        { "value": "German", "label": "German" },
        { "value": "Spanish", "label": "Spanish" },
        { "value": "Japanese", "label": "Japanese" },
        { "value": "French", "label": "French" },
        { "value": "Italian", "label": "Italian" },
        { "value": "Korean", "label": "Korean" },
        { "value": "Polish", "label": "Polish" },
        { "value": "Portuguese", "label": "Portuguese" }
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
        'text',
        'rect',
        'tspan',
        'svg',
        "li",
        "ul",
        "select",
        "button",
        "option",
        "form",
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

    // function getFirstBackgroundColor(selectors) {
    //     for (let selector of selectors) {
    //         const el = document.querySelector(selector);
    //         if (el) {
    //             const bg = window.getComputedStyle(el).backgroundColor;

    //             // rgba(0,0,0,0) or transparent = no color
    //             if (bg && bg !== "rgba(0, 0, 0, 0)" && bg !== "transparent") {
    //                 return bg; // return actual background
    //             }
    //             return "rgb(255, 255, 255)"; // element found but no color
    //         }
    //     }
    //     return "rgb(255, 255, 255)"; // no element found at all
    // }

    function updateAngleImages(htmlString, currentAngle) {
        return htmlString.replace(
            /src="angle_images\//g,
            `src="../../storage/angles/${currentAngle.uuid}/images/${currentAngle.asset_unique_uuid}-`
        );
    }

    function reverseAngleImages(htmlString, currentAngle) {
        return htmlString.replace(
            new RegExp(
                `src="\\.\\.\\/\\.\\.\\/storage\\/angles\\/${currentAngle.uuid}\\/images\\/${currentAngle.asset_unique_uuid}-`,
                'g'
            ),
            'src="angle_images/'
        );
    }

    function getClickedWordFromElement() {
        const selection = window.getSelection();
        if (!selection.rangeCount) return null;

        const range = selection.getRangeAt(0);
        const node = range.startContainer;
        const offset = range.startOffset;

        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent;

            // Expand left to start of word
            let start = offset;
            while (start > 0 && /\S/.test(text[start - 1])) start--;

            // Expand right to end of word
            let end = offset;
            while (end < text.length && /\S/.test(text[end])) end++;

            const word = text.slice(start, end).trim();
            return {
                word,
                position: start // Index of the word in the text
            };
        }

        return null;
    }

    function wrapWithAnchor(str, href, styles = {}) {
        const styleString = Object.entries(styles)
            .filter(([, v]) => v !== undefined && v !== null && v !== '')
            .map(([k, v]) =>
                `${k.replace(/([A-Z])/g, '-$1').toLowerCase()}: ${v}`
            )
            .join('; ');

        // New logic using textPartsList and selectedTextPart
        if (selectedTextPart && textPartsList.length > 0) {
            // Convert the text to an array of words
            const words = str.trim().split(/\s+/);

            // Find the selected word based on selectedTextPart value (index)
            const selectedIndex = selectedTextPart.value;

            if (selectedIndex >= 0 && selectedIndex < words.length) {
                // Create anchor for the selected word
                const selectedWord = words[selectedIndex];
                const anchor = `<a class="app-anchor" href="${href}" style="${styleString}">${selectedWord}</a>`;

                // Replace the selected word with the anchor
                words[selectedIndex] = anchor;

                // Join the words back together
                return words.join(' ');
            }
        }

        // Fallback: if no specific part is selected, wrap the entire string
        return `<a class="app-anchor" href="${href}" style="${styleString}">${str}</a>`;
    }

    // Text replacement utility functions
    function createSearchRegex(searchText, matchCase, matchWholeWord) {
        let pattern = searchText;

        // Escape regex special characters
        pattern = pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

        // Add word boundaries if matchWholeWord is true
        if (matchWholeWord) {
            pattern = `\\b${pattern}\\b`;
        }

        // Create regex with appropriate flags
        const flags = matchCase ? 'g' : 'gi';
        return new RegExp(pattern, flags);
    }

    function countTextOccurrences(htmlString, searchText, matchCase, matchWholeWord) {
        if (!searchText.trim()) return 0;

        // Create temporary DOM element to parse HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlString;

        // Get all text content and count matches
        const textContent = tempDiv.textContent || tempDiv.innerText || '';
        const regex = createSearchRegex(searchText, matchCase, matchWholeWord);
        const matches = textContent.match(regex);

        return matches ? matches.length : 0;
    }

    function replaceTextInHTML(htmlString, searchText, replacementText, matchCase, matchWholeWord) {
        if (!searchText.trim()) return htmlString;

        const regex = createSearchRegex(searchText, matchCase, matchWholeWord);

        // Function to replace text in text nodes only
        function replaceInTextNodes(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                node.textContent = node.textContent.replace(regex, replacementText);
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                // Skip script and style elements
                if (node.tagName !== 'SCRIPT' && node.tagName !== 'STYLE') {
                    for (let child of [...node.childNodes]) {
                        replaceInTextNodes(child);
                    }
                }
            }
        }

        // Create temporary DOM element
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlString;

        // Replace text in all text nodes
        replaceInTextNodes(tempDiv);

        return tempDiv.innerHTML;
    }

    // URL replacement utility functions
    function countURLOccurrences(htmlString, searchURL, matchCase, matchWholeWord) {
        if (!searchURL.trim()) return 0;

        // Create temporary DOM element to parse HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlString;

        // Find all anchor tags
        const anchorTags = tempDiv.querySelectorAll('a[href]');
        let count = 0;

        const regex = createSearchRegex(searchURL, matchCase, matchWholeWord);

        anchorTags.forEach(anchor => {
            const href = anchor.getAttribute('href') || '';
            if (regex.test(href)) {
                count++;
            }
        });

        return count;
    }

    function replaceURLsInHTML(htmlString, searchURL, replacementURL, matchCase, matchWholeWord) {
        if (!searchURL.trim()) return htmlString;

        // Create temporary DOM element
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlString;

        // Find all anchor tags
        const anchorTags = tempDiv.querySelectorAll('a[href]');
        const regex = createSearchRegex(searchURL, matchCase, matchWholeWord);

        anchorTags.forEach(anchor => {
            const href = anchor.getAttribute('href') || '';
            const newHref = href.replace(regex, replacementURL);
            if (newHref !== href) {
                anchor.setAttribute('href', newHref);
            }
        });

        return tempDiv.innerHTML;
    }

    const [open, setOpen] = useState(false);
    const [data, setData] = useState(false);
    const [mainHTML, setMainHTML] = useState([{ html: '', status: true }]);
    const [mainBodies, setMainBodies] = useState([]);
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

    const [newImageUploads, setNewImageUploads] = useState([]);

    const INITIAL_IMAGE_MANAGEMENT = {
        via: "src",
        imageSrc: "",
        imageFile: { alreadyUploaded: "", name: "", size: "", file: "", blobUrl: "" },
        border: false,
        borderWidth: "",
        borderColor: "",
        imageLink: "",          // <-- Add link property
        margin: "0px 0px 0px 0px",
        padding: "0px 0px 0px 0px",
        width: "100%",
    };

    const INITIAL_TRANSLATOR = {
        fromLanguange: false,
        toLanguage: false,
        fromText: "",
        toText: "",
        currentSource: false,    // TEXT, CUSTOM_HTML
    };

    const INITIAL_CHATGPT = {
        query: "",
        response: "",
        currentSource: false,    // TEXT, CUSTOM_HTML
    };

    const INITIAL_TEXT_MANAGEMENT = {
        textInput: "",
        color: "",
        backgroundColor: "",
        fontSize: "12",
        link: "",
        linkEffect: "Selected Element", // false, true
        border: false,
        borderWidth: "",
        borderColor: "",
        margin: "0px 0px 0px 0px",
        padding: "0px 0px 0px 0px",
        textAlign: false,
    };

    const INITIAL_SPACER_MANAGEMENT = {
        height: "",
    };

    const INITIAL_CUSTOM_HTML_MANAGEMENT = {
        input: "",
    };

    const INITIAL_FORM_MANAGEMENT = {
        submitText: "",
        submitTextColor: "",
        submitBackgroundColor: "#ff7800",
        apiType: "elps",
        project_directory: "",
        margin: "0px 0px 0px 0px",
        padding: "20px 20px 20px 20px",
        border: "solid",
        borderWidth: "2",
        borderColor: "#0186ff",
        h3Text: "Form",
        h3FontSize: "24",
        h3HeadingColor: "#333333",
        h3HeadingAlignment: "center",
        inputs: [{
            name: "firstname",
            id: "firstname",
            inputName: "",
            inputType: "text",
            // required: false,
            // type: false,
        }, {
            name: "lastname",
            id: "lastname",
            inputName: "",
            inputType: "text",
            // required: false,
            // type: false,
        }, {
            name: "email",
            id: "email",
            inputName: "",
            inputType: "text",
            // required: false,
            // type: false,
        }, {
            name: "temp_phone",
            id: "phone",
            inputName: "",
            inputType: "tel",
            // required: false,
            // type: false,
        }]
    };

    const INITIAL_BUTTON_MANAGEMENT = {
        buttonText: "",
        color: "",
        backgroundColor: "",
        fontSize: "",
        margin: "0px 0px 0px 0px",
        padding: "0px 0px 0px 0px",
        border: false,
        borderWidth: "",
        borderColor: "",
    };

    const [imageManagement, setImageManagement] = useState(INITIAL_IMAGE_MANAGEMENT);
    const [translator, setTranslator] = useState(INITIAL_TRANSLATOR);
    const [chatGPT, setChatGPT] = useState(INITIAL_CHATGPT);
    const [textManagement, setTextManagement] = useState(INITIAL_TEXT_MANAGEMENT);
    const [textPartsList, setTextPartsList] = useState([]);
    const [selectedTextPart, setSelectedTextPart] = useState(false);
    const [spacerManagement, setSpacerManagement] = useState(INITIAL_SPACER_MANAGEMENT);
    const [customHTMLManagement, setCustomHTMLManagement] = useState(INITIAL_CUSTOM_HTML_MANAGEMENT);
    const [formManagement, setFormManagement] = useState(INITIAL_FORM_MANAGEMENT);
    const [selectedFormLanguage, setSelectedFormLanguage] = useState(false);
    const [buttonManagement, setButtonManagement] = useState(INITIAL_BUTTON_MANAGEMENT);

    // Replace functionality state
    const [replaceModalOpen, setReplaceModalOpen] = useState(false);
    const [replaceType, setReplaceType] = useState('text'); // 'text' or 'url'
    const [searchText, setSearchText] = useState('');
    const [replacementText, setReplacementText] = useState('');
    const [matchCase, setMatchCase] = useState(false);
    const [matchWholeWord, setMatchWholeWord] = useState(false);
    const [occurrencesCount, setOccurrencesCount] = useState(0);
    const [searchPerformed, setSearchPerformed] = useState(false);

    const [anchorHelpProperties, setAnchorHelpProperties] = useState(null);

    useEffect(() => {

        async function getData() {
            const url = route('Angle.previewContent');

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', },
                    body: JSON.stringify({ angle_id: id })
                });

                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                const json = await response.json();
                console.log(json);
                setData(json.data);
                let bodiesTemp = json.data.contents
                    .filter(value => value.type === "html")
                    .map((value, index) => ({
                        ...value,
                        selected_body: index === 0 // true if index is 0, false otherwise
                    }))
                setMainBodies(bodiesTemp);
                setMainHTML([{ html: updateAngleImages(bodiesTemp[0].content, json.data), status: true }]);

                setTimeout(() => {
                    fetch("https://ipapi.co/json/")
                        .then(res => res.json())
                        .then(data => {
                            const userCountry = data.country_code || "us"; // fallback if undefined

                            // Now loop through all telInputs and init with that country
                            document.querySelectorAll(".telInputs").forEach(input => {
                                window.intlTelInput(input, {
                                    initialCountry: userCountry,
                                });

                                // Keep full width
                                input.style.width = "100%";
                            });
                        })
                        .catch(() => {
                            // If API fails, just fallback to US
                            document.querySelectorAll(".telInputs").forEach(input => {
                                window.intlTelInput(input, {
                                    initialCountry: "us",
                                });
                                input.style.width = "100%";
                            });
                        });
                }, 200);
            } catch (error) {
                console.error(error.message);
            }
        }

        getData()

        document.addEventListener("click", function (event) {
            const isHiddenFileInput = event.target.id === "hiddenFileUpload";
            if (!isHiddenFileInput) {
                event.preventDefault();
            }
            handleClick(event);
        });

    }, []);

    useEffect(() => {
        // console.log(editing);
        if (editing && editing.actionType == "delete") {
            let elementInside = document.querySelector(`.${editing.editID}`);
            elementInside.remove();
            setMainHTML(prev => [
                ...prev.map(item => ({ ...item, status: false })), // Set previous statuses to false
                { html: document.querySelector(".mainHTML").innerHTML, status: true } // Add new entry
            ]);
            setOpen(false);
            setAnchorHelpProperties(null);
        } else if ((editing && editing.actionType == "edit" || editing && editing.actionType == "add") && ['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'i', 'p', 'span', 'text', 'rect', 'tspan', 'svg'].includes(editing.elementName)) {
            let computedStyles = window.getComputedStyle(editing.currentElement);
            if (editing && editing.actionType == "add") {
                setTextManagement(prev => ({
                    ...prev,
                    fontSize: removePxAndConvertToFloat(computedStyles.fontSize),
                    fontFamily: computedStyles.fontFamily,
                    color: `#${convert.rgb.hex(rgbToArray(computedStyles.color))}`,
                    ...(computedStyles.backgroundColor !== "rgba(0, 0, 0, 0)" && { backgroundColor: `#${convert.rgb.hex(rgbToArray(computedStyles.backgroundColor))}` }),
                }));
            } else if (editing && editing.actionType == "edit") {

                // let dynamicBackgroundColor = "";
                // if (computedStyles.backgroundColor == "rgba(0, 0, 0, 0)") {
                //     dynamicBackgroundColor = getFirstBackgroundColor(backgroundSelectors);
                //     dynamicBackgroundColor = `#${convert.rgb.hex(rgbToArray(dynamicBackgroundColor))}`;
                // } else {
                //     dynamicBackgroundColor = `#${convert.rgb.hex(rgbToArray(computedStyles.backgroundColor))}`;
                // }

                setSelectedTextPart(false);
                setTextPartsList(editing.innerHTML.trim().split(/\s+/).map((char, index) => {
                    return { value: index, label: char }
                }));

                let InsideLink = "#";
                if (editing.currentElement.tagName == 'A') {
                    InsideLink = editing.currentElement.getAttribute("href");
                }

                setTextManagement(prev => ({
                    ...prev,
                    textInput: editing.innerHTML,
                    fontSize: removePxAndConvertToFloat(computedStyles.fontSize),
                    color: `#${convert.rgb.hex(rgbToArray(computedStyles.color))}`,
                    ...(computedStyles.backgroundColor !== "rgba(0, 0, 0, 0)" && { backgroundColor: `#${convert.rgb.hex(rgbToArray(computedStyles.backgroundColor))}` }),
                    textAlign: computedStyles.textAlign,
                    border: computedStyles.borderStyle,
                    borderWidth: removePxAndConvertToFloat(computedStyles.borderWidth),
                    borderColor: `#${convert.rgb.hex(rgbToArray(computedStyles.borderColor))}`,
                    link: InsideLink,
                    fontFamily: computedStyles.fontFamily,
                    padding: `${computedStyles.paddingTop} ${computedStyles.paddingRight} ${computedStyles.paddingBottom} ${computedStyles.paddingLeft}`,
                    margin: `${computedStyles.marginTop} ${computedStyles.marginRight} ${computedStyles.marginBottom} ${computedStyles.marginLeft}`
                }));
            }
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
                imageLink: editing.currentElement.parentElement.getAttribute("href"),
                padding: `${computedStyles.paddingTop} ${computedStyles.paddingRight} ${computedStyles.paddingBottom} ${computedStyles.paddingLeft}`,
                margin: `${computedStyles.marginTop} ${computedStyles.marginRight} ${computedStyles.marginBottom} ${computedStyles.marginLeft}`,
            }));
        } else if (editing && editing.actionType == "edit" && ['button'].includes(editing.elementName)) {
            let computedStyles = window.getComputedStyle(editing.currentElement);
            setButtonManagement(prev => ({
                ...prev, // keep all previous values
                buttonText: editing.innerHTML, // only update the value you want
                color: `#${convert.rgb.hex(rgbToArray(computedStyles.color))}`,
                backgroundColor: computedStyles.backgroundColor == "rgba(0, 0, 0, 0)" ? "#ffffff" : `#${convert.rgb.hex(rgbToArray(computedStyles.backgroundColor))}`,
                fontSize: removePxAndConvertToFloat(computedStyles.fontSize),
                padding: `${computedStyles.paddingTop} ${computedStyles.paddingRight} ${computedStyles.paddingBottom} ${computedStyles.paddingLeft}`,
                margin: `${computedStyles.marginTop} ${computedStyles.marginRight} ${computedStyles.marginBottom} ${computedStyles.marginLeft}`,
                border: computedStyles.borderStyle,
                borderWidth: removePxAndConvertToFloat(computedStyles.borderWidth),
                borderColor: `#${convert.rgb.hex(rgbToArray(computedStyles.borderColor))}`,
            }));
        } else if (editing && editing.actionType == "edit" && ['form'].includes(editing.elementName)) {
            const formEl = editing.currentElement; // Assuming this is your form element
            let computedStyles = window.getComputedStyle(editing.currentElement);
            // Step 1: Get all input elements inside the form
            const inputElements = formEl.querySelectorAll("input");

            const inputs = Array.from(inputElements)
                .map(input => {
                    const name = input.getAttribute("name");
                    const id = input.getAttribute("id");

                    if (!name || name == "form_type" || name == "web_builder_user_id" || name == "project_directory" || name == "sales_page_id") return null;

                    // Find the corresponding label using the `for` attribute
                    const label = id ? formEl.querySelector(`#${id}`)?.placeholder : null;
                    const labelText = label ? label.trim() : "";

                    return {
                        name: name,
                        id: id || "",
                        inputName: labelText,
                        inputType: input.getAttribute("type") || "text",
                        // required: input.required || false,
                        // type: false
                    };
                })
                .filter(input => input !== null);

            // Step 3: Create the final payload
            const h3Element = formEl.querySelector("h3");
            setFormManagement({
                submitText: formEl.querySelector("button[type='submit']")?.textContent.trim() || "",
                submitTextColor: `#${convert.rgb.hex(rgbToArray(formEl.querySelector("button[type='submit']")?.style.color))}` || "",
                submitBackgroundColor: `#${convert.rgb.hex(rgbToArray(formEl.querySelector("button[type='submit']")?.style.backgroundColor))}` || "",
                apiType: formEl.getAttribute("data-api-type"),
                project_directory: formEl.querySelector('[name="project_directory"]')?.value || '',
                inputs: inputs,
                padding: `${computedStyles.paddingTop} ${computedStyles.paddingRight} ${computedStyles.paddingBottom} ${computedStyles.paddingLeft}`,
                margin: `${computedStyles.marginTop} ${computedStyles.marginRight} ${computedStyles.marginBottom} ${computedStyles.marginLeft}`,
                border: computedStyles.borderStyle,
                borderWidth: removePxAndConvertToFloat(computedStyles.borderWidth),
                borderColor: `#${convert.rgb.hex(rgbToArray(computedStyles.borderColor))}`,
                h3Text: h3Element?.textContent.trim() || "",
                h3FontSize: h3Element ? removePxAndConvertToFloat(window.getComputedStyle(h3Element).fontSize) : "24",
                h3HeadingColor: h3Element ? `#${convert.rgb.hex(rgbToArray(window.getComputedStyle(h3Element).color))}` : "#333333",
                h3HeadingAlignment: h3Element ? window.getComputedStyle(h3Element).textAlign : "center",
            });

            setSelectedFormLanguage(false);
        }
    }, [editing]);

    useEffect(() => {
        function handleMouseEnter(e) {
            // Only add border if element does NOT have below classes and none of its parents have it
            // This prevents the border from showing on elements that should not be acted upon
            if (!e.target.outerHTML.includes("MuiModal-backdrop") && !hasParentWithClass(e.target, 'popoverPlate') && !hasParentWithClass(e.target, 'swal2-container') && (!e.target.outerHTML.includes("doNotAct") || e.target.localName == "form")) {
                e.target.classList.add("editable-hover-border"); // Add the border class on hover
            }
        }
        function handleMouseLeave(e) {
            // Always remove the border class when mouse leaves
            e.target.classList.remove("editable-hover-border");
        }
        function addHoverListeners() {
            const editableElementsList = editableElements; // List of tags that are considered editable
            editableElementsList.forEach(tag => {
                // For each editable tag, select all elements of that tag in the DOM
                document.querySelectorAll(tag).forEach(el => {
                    // Add mouseenter event to show border on hover
                    el.addEventListener("mouseenter", handleMouseEnter);
                    // Add mouseleave event to remove border when not hovering
                    el.addEventListener("mouseleave", handleMouseLeave);
                });
            });
            // Also add listeners to all elements with the 'editableDiv' class
            document.querySelectorAll('.editableDiv').forEach(el => {
                el.addEventListener("mouseenter", handleMouseEnter);
                el.addEventListener("mouseleave", handleMouseLeave);
            });
        }
        addHoverListeners(); // Attach all hover listeners when effect runs

        return () => {
            const editableElementsList = editableElements; // List of tags that are considered editable
            editableElementsList.forEach(tag => {
                // For each editable tag, select all elements of that tag in the DOM
                document.querySelectorAll(tag).forEach(el => {
                    // Remove mouseenter event to clean up
                    el.removeEventListener("mouseenter", handleMouseEnter);
                    // Remove mouseleave event to clean up
                    el.removeEventListener("mouseleave", handleMouseLeave);
                });
            });
            // Also remove listeners from all elements with the 'editableDiv' class
            document.querySelectorAll('.editableDiv').forEach(el => {
                el.removeEventListener("mouseenter", handleMouseEnter);
                el.removeEventListener("mouseleave", handleMouseLeave);
            });
        };
        // This cleanup ensures no duplicate event listeners and prevents memory leaks
    }, [mainHTML]);

    function removePxAndConvertToFloat(value) {
        return parseFloat(value.replace('px', ''));
    }

    function rgbToArray(color) {
        // Match both rgb(...) and rgba(...)
        const result = color.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/);

        if (result) {
            // Convert r,g,b to numbers, keep alpha if present (default = 1)
            const r = Number(result[1]);
            const g = Number(result[2]);
            const b = Number(result[3]);
            const a = result[4] !== undefined ? parseFloat(result[4]) : 1;
            return [r, g, b, a];
        }

        // Default: black, fully opaque
        return [0, 0, 0, 1];
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

    // Dynamic function to handle margin/padding validation and updates
    const handleSpacingChange = (value, property, setterFunction) => {
        const input = value.trim();
        // Split the input by space
        const parts = input.split(/\s+/);
        // Regex to match valid px values (e.g., 10px, 5.5px, 0px)
        const isValid = parts.every((part) =>
            /^(\d+(\.\d+)?|\.\d+)px$/.test(part)
        );
        // Only update state if all parts are valid
        if (isValid || input === "") {
            setterFunction((prev) => ({
                ...prev,
                [property]: input,
            }));
        }
    };


    useEffect(() => {
        if (selectedFormLanguage && formFieldLanguageDefaults[selectedFormLanguage]) {
            // console.log(formFieldLanguageDefaults[selectedFormLanguage]);
            setFormManagement(prev => ({
                ...prev,
                inputs: prev.inputs.map(input => {
                    // console.log(formFieldLanguageDefaults[selectedFormLanguage][input.name]);
                    if (formFieldLanguageDefaults[selectedFormLanguage][input.name]) {
                        return {
                            ...input,
                            inputName: formFieldLanguageDefaults[selectedFormLanguage][input.name]
                        };
                    }
                    return input;
                })
            }));
        }
    }, [selectedFormLanguage]);

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
        if (!event.target.outerHTML.includes("MuiModal-backdrop") && !hasParentWithClass(event.target, 'popoverPlate') && !hasParentWithClass(event.target, 'swal2-container') && (!event.target.outerHTML.includes("doNotAct") || event.target.localName == "form")) {
            let randString = generateRandomString();
            if (editableElements.includes(event.target.localName) || event.target.classList.contains('editableDiv')) {
                setAnchorHelpProperties(getClickedWordFromElement());
                event.target.classList.add(randString);
                setOpen(true);
                resetModalHandler();
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
            // existingElement.style.marginBottom = "5px";
            existingElement.insertAdjacentElement('afterend', newElement);
        } else if (position == "top") {
            // existingElement.style.marginTop = "5px";
            existingElement.insertAdjacentElement('beforebegin', newElement);
        } else if (position == "left") {
            // existingElement.style.marginLeft = "5px";
            existingElement.parentNode.insertBefore(newElement, existingElement);
        } else if (position == "right") {
            // existingElement.style.marginRight = "5px";
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

        if (chatGPT.query) {
            if (chatGPT.currentSource == "text_management") {
                setTextManagement(prev => ({
                    ...prev,
                    textInput: chatGPT.response,
                }));
            } else if (chatGPT.currentSource == "custom_html") {
                setCustomHTMLManagement(prev => ({
                    ...prev,
                    input: chatGPT.response,
                }));
            }
            setChatGPT({
                query: "",
                response: "",
                currentSource: false, // TEXT, CUSTOM_HTML
            });
            return;
        }

        let element = document.querySelector(`.${editing.editID}`);

        //FURTHER EDITING REMAINING
        if ((editing.actionType == "edit" && ['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'i', 'p', 'span', 'text', 'rect', 'tspan', 'svg'].includes(editing.elementName)) || (editing.actionType === "add" && editing.addElementType == "p")) {
            //IF LINK IS NOT NULL THEN CONVERT ANY ELEMENT TO a

            // const styles = {
            //     color: textManagement.color,
            //     backgroundColor: textManagement.backgroundColor,
            //     fontSize: `${textManagement.fontSize}px`,
            //     border: textManagement.border,
            //     borderWidth: `${textManagement.borderWidth}px`,
            //     borderColor: textManagement.borderColor,
            //     textAlign: textManagement.textAlign,
            //     fontFamily: textManagement.fontFamily,
            //     margin: textManagement.margin,
            //     padding: textManagement.padding
            // };

            const styles = {};

            if (editing.actionType == "edit") {
                if (textManagement.link && textManagement.link != "#" && element.localName !== "a") {
                    let newElement = document.createElement('a');
                    newElement.className = element.className;
                    if (anchorHelpProperties && textManagement.linkEffect == "Selected Element") {
                        newElement.innerHTML = wrapWithAnchor(editing.innerHTML, textManagement.link, styles);
                        element.innerHTML = newElement.innerHTML;
                    } else {
                        Object.assign(newElement.style, styles);
                        newElement.innerHTML = textManagement.textInput;
                        newElement.setAttribute('href', textManagement.link);
                        newElement.classList.add('app-anchor');
                        element.parentNode.replaceChild(newElement, element);
                    }
                } else if (textManagement.link && textManagement.link != "#" && element.localName == "a") {
                    Object.assign(element.style, styles);
                    element.innerHTML = textManagement.textInput;
                    element.href = textManagement.link;
                } else {
                    Object.assign(element.style, styles);
                    element.innerHTML = textManagement.textInput;
                    element.href = "#";
                }
            } else {
                let newElement = '';
                if (textManagement.link && textManagement.link != "#") {
                    newElement = document.createElement('a');
                    Object.assign(newElement.style, styles);
                    newElement.innerHTML = textManagement.textInput;
                    newElement.className = element.className;
                    newElement.setAttribute('href', textManagement.link);
                } else {
                    newElement = document.createElement('div');
                    newElement.className = element.className;
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
                margin: imageManagement.margin,
                padding: imageManagement.padding,
                width: imageManagement.width,
            };
            if (editing.actionType == "edit") {
                Object.assign(element.style, styles);
                if (imageManagement.via == "src") {
                    element.src = imageManagement.imageSrc;
                } else {
                    element.src = imageManagement.imageFile.blobUrl;
                }

                if (imageManagement.imageLink && imageManagement.imageLink != "#") {
                    if (element.parentElement.tagName == 'A') {
                        element.parentElement.href = imageManagement.imageLink;
                    } else {
                        let anchor = document.createElement('a');
                        anchor.href = imageManagement.imageLink;
                        // anchor.target = '_blank';
                        // anchor.rel = 'noopener noreferrer';
                        let cloned = element.cloneNode(true); // clone the original element
                        anchor.appendChild(cloned); // put the clone inside the anchor
                        element.parentNode.replaceChild(anchor, element); // now replace the original
                    }
                } else {
                    if (element.parentElement.tagName == 'A') {
                        element.parentElement.href = "#";
                    }
                }
            } else {
                let newElement = document.createElement('img');
                Object.assign(newElement.style, styles);
                if (imageManagement.via == "src") {
                    newElement.src = imageManagement.imageSrc;
                } else {
                    newElement.src = imageManagement.imageFile.blobUrl;
                }

                // If a link is provided, wrap the image in an anchor
                if (imageManagement.imageLink && imageManagement.imageLink != "#") {
                    let anchor = document.createElement('a');
                    anchor.href = imageManagement.imageLink;
                    // anchor.target = '_blank'; // Optional: open in new tab
                    // anchor.rel = 'noopener noreferrer'; // Optional: security best practice
                    anchor.appendChild(newElement);
                    await addNewContentHandler(editing.addElementPosition, element, anchor);
                } else {
                    await addNewContentHandler(editing.addElementPosition, element, newElement);
                }
            }
        } else if ((editing.actionType == "edit" && ['button'].includes(editing.elementName)) || (editing.actionType === "add" && editing.addElementType == "button")) {
            const styles = {
                color: buttonManagement.color,
                backgroundColor: buttonManagement.backgroundColor,
                fontSize: `${buttonManagement.fontSize}px`,
                margin: buttonManagement.margin,
                padding: buttonManagement.padding,
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
        } else if ((editing.actionType == "edit" && ['form'].includes(editing.elementName)) || (editing.actionType === "add" && editing.addElementType == "form")) {

            // Create form HTML content
            let formHTML = '';

            // Add H3 title if provided
            if (formManagement.h3Text) {
                formHTML += `
                    <h3 className="doNotAct" style="font-weight: bold; text-align: ${formManagement.h3HeadingAlignment || 'center'}; margin-bottom: 20px; font-size: ${formManagement.h3FontSize || 24}px; color: ${formManagement.h3HeadingColor || '#333333'};">
                        ${formManagement.h3Text}
                    </h3>
                `;
            }

            // Add input fields
            formManagement.inputs.forEach(input => {
                if (input.name && input.inputType) {
                    // <label for="${input.id}" style="display: block; margin-bottom: 5px;">${input.inputName || input.name}</label>

                    let modifiedName = input.name;
                    if (input.name == "phone")
                        modifiedName = "temp_phone";

                    formHTML += `
                        <div style="margin-bottom: 15px;">
                            <input
                                type="${input.inputType}"
                                placeholder="${input.inputName || input.id}"
                                id="${input.id}"
                                name="${modifiedName}"
                                class="${input.inputType == "tel" ? 'telInputs' : ''}"
                                style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                                ${input.inputType === 'email' ? 'required' : ''}
                            />
                        </div>
                    `;
                }
            });

            formHTML += ` <input type="hidden" name="form_type" value="${formManagement.apiType}" />`;
            formHTML += ` <input type="hidden" name="web_builder_user_id" value="${mainQuery.auth.user.id}" />`;
            formHTML += ` <input type="hidden" name="project_directory" value="${formManagement.project_directory}" />`;
            formHTML += ` <input type="hidden" name="sales_page_id" value="A${id || ''}" />`;
            // Add submit button
            const submitButtonStyles = {
                backgroundColor: formManagement.submitBackgroundColor || '#007bff',
                color: formManagement.submitTextColor || '#ffffff',
                padding: '10px 20px',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer',
                fontSize: '16px',
                width: "100%",
                marginTop: "15px",
                display: "flex",
                justifyContent: "center"
            };

            const submitStyleString = Object.entries(submitButtonStyles)
                .map(([k, v]) => `${k.replace(/([A-Z])/g, '-$1').toLowerCase()}: ${v}`)
                .join('; ');

            formHTML += `
                <button type="submit" class="doNotAct" style="${submitStyleString}">
                    ${formManagement.submitText || 'Submit'}
                </button>
            `;

            if (editing.actionType == "edit") {
                // Update existing form
                element.method = 'POST';
                element.id = 'myForm'; // ← ID set here
                element.action = "api_files/backend.php";
                element.setAttribute("data-api-type", formManagement.apiType);
                element.innerHTML = formHTML;
                element.style.margin = formManagement.margin;
                element.style.padding = formManagement.padding;
                element.style.border = `${formManagement.borderWidth}px ${formManagement.border} ${formManagement.borderColor}`;
                element.style.borderRadius = '8px';
                element.style.backgroundColor = '#f9f9f9';
                element.style.width = '100%';
            } else {
                // Create new form element
                let newElement = document.createElement('form');
                newElement.style.width = '100%';
                newElement.method = 'POST';
                newElement.id = 'myForm'; // ← ID set here
                newElement.action = "api_files/backend.php";
                newElement.setAttribute("data-api-type", formManagement.apiType);
                newElement.innerHTML = formHTML;
                newElement.style.margin = formManagement.margin;
                newElement.style.padding = formManagement.padding;
                newElement.style.border = `${formManagement.borderWidth}px ${formManagement.border} ${formManagement.borderColor}`;
                newElement.style.borderRadius = '8px';
                newElement.style.backgroundColor = '#f9f9f9';
                await addNewContentHandler(editing.addElementPosition, element, newElement);
            }
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
        setAnchorHelpProperties(null);

        setTimeout(() => {
            fetch("https://ipapi.co/json/")
                .then(res => res.json())
                .then(data => {
                    const userCountry = data.country_code || "us"; // fallback if undefined

                    // Now loop through all telInputs and init with that country
                    document.querySelectorAll(".telInputs").forEach(input => {
                        window.intlTelInput(input, {
                            initialCountry: userCountry,
                        });

                        // Keep full width
                        input.style.width = "100%";
                    });
                })
                .catch(() => {
                    // If API fails, just fallback to US
                    document.querySelectorAll(".telInputs").forEach(input => {
                        window.intlTelInput(input, {
                            initialCountry: "us",
                        });
                        input.style.width = "100%";
                    });
                });
        }, 200);

        // RESET ALL
        setImageManagement(INITIAL_IMAGE_MANAGEMENT);
        setTranslator(INITIAL_TRANSLATOR);
        setChatGPT(INITIAL_CHATGPT);
        setTextManagement(INITIAL_TEXT_MANAGEMENT);
        setSpacerManagement(INITIAL_SPACER_MANAGEMENT);
        setCustomHTMLManagement(INITIAL_CUSTOM_HTML_MANAGEMENT);
        setFormManagement(INITIAL_FORM_MANAGEMENT);
        setButtonManagement(INITIAL_BUTTON_MANAGEMENT);
    }

    // Replace functionality handlers
    const openTextReplaceModal = () => {
        setReplaceType('text');
        setReplaceModalOpen(true);
        setSearchText('');
        setReplacementText('');
        setMatchCase(false);
        setMatchWholeWord(false);
        setOccurrencesCount(0);
        setSearchPerformed(false);
    };

    const openURLReplaceModal = () => {
        setReplaceType('url');
        setReplaceModalOpen(true);
        setSearchText('');
        setReplacementText('');
        setMatchCase(false);
        setMatchWholeWord(false);
        setOccurrencesCount(0);
        setSearchPerformed(false);
    };

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

    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    const chunkArray = (array, size) => {
        const result = [];
        for (let i = 0; i < array.length; i += size) {
            result.push(array.slice(i, i + size));
        }
        return result;
    };

    let abortController = null;

    const updatedThemeSaveHandler = async () => {
        try {

            const mainHTMLActiveInside = mainHTML.find(html => html.status == true);
            const finalNewImages = newImageUploads.filter(value => mainHTMLActiveInside.html.includes(value.blobUrl))

            const CHUNK_SIZE = 10; // Adjust chunk size as needed
            const imageChunks = chunkArray(finalNewImages, CHUNK_SIZE);

            let uploadedFiles = 0;
            const totalFiles = finalNewImages.length;

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

            const assetUUID = generateUUID();

            const chunks = [...imageChunks];
            if (chunks.length == 0)
                chunks.push(1);

            for (const [chunkIndex, chunk] of chunks.entries()) {

                const isLastIteration = chunkIndex === chunks.length - 1 ? true : false;

                const formData = new FormData();

                let mainHTMLDynamic = reverseAngleImages(mainHTML.find(value => value.status).html, data);
                let tempDivInside = document.createElement('div');
                tempDivInside.innerHTML = mainHTMLDynamic;

                // Replace all parents of .iti__country-container
                tempDivInside.querySelectorAll(".iti__country-container").forEach(el => {
                    el.parentElement.outerHTML = `
                    <div style="margin-bottom: 15px;">
                        <input type="tel" placeholder="Telefonnummer" id="phone" name="temp_phone" class="telInputs" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    `;
                });

                // Get the updated HTML string
                mainHTMLDynamic = tempDivInside.innerHTML;

                formData.append("last_iteration", isLastIteration);
                formData.append("asset_unique_uuid", assetUUID);
                formData.append("chunk_count", chunk == 1 ? 0 : chunk.length);
                formData.append("main_html", mainHTMLDynamic);
                formData.append("angle_content_uuid", mainBodies.find(value => value.selected_body).uuid);
                formData.append("angle_uuid", mainBodies.find(value => value.selected_body).angle_uuid);

                if (chunk != 1) {
                    chunk.forEach((item, index) => {
                        formData.append(`image${index}`, item.file);
                        formData.append(`image${index}blob_url`, item.blobUrl);
                    });
                }

                // Create a new AbortController for each chunk
                abortController = new AbortController();

                try {
                    let response = await fetch(route('editedAngle.save'), {
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

            Swal.fire({
                title: 'Success',
                text: "Angle Body Updated Successfully",
                icon: 'success',
                timer: 1000,
                showConfirmButton: false,
            });

            router.get(route('angles'));

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

    const grokAIOpenHandler = (source) => {
        if (source == "text_management") {
            setChatGPT(prev => ({
                ...prev, // keep all previous values
                query: textManagement.textInput, // only update the value you want
                currentSource: "text_management"
            }));
        } else if (source == "custom_html") {
            setChatGPT(prev => ({
                ...prev, // keep all previous values
                query: customHTMLManagement.input, // only update the value you want
                currentSource: "custom_html"
            }));
        }
    }

    const grokAIHandler = () => {
        async function getData() {
            const url = route('grok');
            try {
                const response = await fetch(url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json", // <---- MISSING BEFORE
                        "Accept": "application/json",       // (optional but good)
                    },
                    body: JSON.stringify({
                        prompt: chatGPT.query,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                const result = await response.json();
                // console.log(result);

                if (result.success) {
                    setChatGPT(prev => ({
                        ...prev,
                        response: result.data,
                    }));
                }
            } catch (error) {
                console.error(error.message);
            }
        }
        getData();
    }

    const resetModalHandler = () => {
        setTranslator({
            fromLanguange: false,
            toLanguage: false,
            fromText: "",
            toText: "",
            currentSource: false, // TEXT, CUSTOM_HTML
        });
        setChatGPT({
            query: "",
            response: "",
            currentSource: false, // TEXT, CUSTOM_HTML
        });
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
                                        if (translator.currentSource || chatGPT.currentSource) {
                                            resetModalHandler();
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
                                <Box>
                                    {editing && !editing.actionType &&
                                        <Box mt={2} sx={{ display: "flex", gap: 1 }}>
                                            <Button className="doNotAct cptlz megaButtonSquare" size='large' fullWidth color="success" variant='outlined' onClick={() => handleChange("actionType", "add")}>Add Element</Button>
                                            <Box component="div" sx={{ marginTop: "15px" }} />
                                            <Button className="doNotAct cptlz megaButtonSquare" size='large' fullWidth color="primary" variant='outlined' onClick={() => handleChange("actionType", "edit")}>Edit Element</Button>
                                            <Box component="div" sx={{ marginTop: "15px" }} />
                                            <Button className="doNotAct cptlz megaButtonSquare" size='large' fullWidth color="error" variant='outlined' onClick={() => handleChange("actionType", "delete")}>Delete Element</Button>
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
                                </Box>

                                {editing.actionType && translator.currentSource &&
                                    <Box sx={{ pt: "5px" }}>
                                        {/* DEEPL TRANSLATOR */}
                                        <Box sx={{ display: "flex", gap: "20px" }}>
                                            <FormControl fullWidth>
                                                <InputLabel id="demo-simple-select-label">Translate From</InputLabel>
                                                <MuiSelect
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
                                                </MuiSelect>
                                            </FormControl>
                                            <Box sx={{ mt: 0.5, cursor: "pointer" }}>
                                                <SwapHorizIcon onClick={() => {
                                                    setTranslator(prev => ({
                                                        ...prev,
                                                        fromLanguange: prev.toLanguage,
                                                        toLanguage: prev.fromLanguange
                                                    }))
                                                }} />
                                            </Box>
                                            <FormControl fullWidth>
                                                <InputLabel id="demo-simple-select-label">Translate To</InputLabel>
                                                <MuiSelect
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
                                                </MuiSelect>
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
                                    </Box>
                                }

                                {editing.actionType && chatGPT.currentSource &&
                                    <Box sx={{ pt: "5px" }}>
                                        <Box sx={{ display: "flex" }}>
                                            <TextField
                                                fullWidth
                                                size='small'
                                                placeholder='Enter Query'
                                                value={chatGPT.query}
                                                onChange={(e) => {
                                                    setChatGPT({ ...chatGPT, query: e.target.value })
                                                }}
                                            />
                                            <Button sx={{ ml: 2, width: "100px" }} variant="contained" className="doNotAct cptlz" onClick={grokAIHandler}>GROK</Button>
                                        </Box>
                                        <Box sx={{ mt: 2, p: 1, borderRadius: "3px", border: "1px solid black", backgroundColor: "#e5e5e5", fontSize: "12px" }}>
                                            <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{chatGPT.response == "" ? "Grok AI Response" : chatGPT.response}</pre>
                                        </Box>
                                    </Box>
                                }

                                {(!chatGPT.currentSource && !translator.currentSource) &&
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
                                                        <Box sx={{ border: "3px dashed #D4D4D4", backgroundColor: "#FCFCFC", minHeight: "10px" }} p={1}>
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

                                                                                    let tempNewImages = [...newImageUploads];
                                                                                    tempNewImages.push({ blobUrl: blobUrl, file: insideFile })
                                                                                    setNewImageUploads(tempNewImages);

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
                                                        <MuiSelect
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
                                                        </MuiSelect>
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
                                                    <TextField
                                                        sx={{ mt: 2 }}
                                                        type='text'
                                                        fullWidth
                                                        size='small'
                                                        label="Link"
                                                        slotProps={{
                                                            inputLabel: { shrink: true }
                                                        }}
                                                        placeholder='Enter Link'
                                                        value={imageManagement.imageLink}
                                                        onChange={(e) => {
                                                            setImageManagement({ ...imageManagement, imageLink: e.target.value })
                                                        }}
                                                    />
                                                    <TextField
                                                        sx={{ mt: 2.1 }}
                                                        type="text"
                                                        fullWidth
                                                        size="small"
                                                        label="Margin (top right bottom left)"
                                                        slotProps={{
                                                            inputLabel: { shrink: true },
                                                        }}
                                                        placeholder="e.g. 10px 20px 30px 40px"
                                                        value={imageManagement.margin || ""}
                                                        onChange={(e) => handleSpacingChange(e.target.value, 'margin', setImageManagement)}
                                                    />
                                                    <TextField
                                                        sx={{ mt: 2.1 }}
                                                        type="text"
                                                        fullWidth
                                                        size="small"
                                                        label="Padding (top right bottom left)"
                                                        slotProps={{
                                                            inputLabel: { shrink: true },
                                                        }}
                                                        placeholder="e.g. 10px 20px 30px 40px"
                                                        value={imageManagement.padding || ""}
                                                        onChange={(e) => handleSpacingChange(e.target.value, 'padding', setImageManagement)}
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
                                        {(editing && editing.actionType == "edit" && ['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'i', 'p', 'span', 'text', 'rect', 'tspan', 'svg'].includes(editing.elementName) ||
                                            (editing && editing.actionType === "add" && editing.addElementType == "p")
                                        ) && (
                                                <Box>
                                                    <Box mb={1.5} sx={{ display: "flex", justifyContent: "flex-end" }}>
                                                        <Button className="doNotAct" size="small" variant="contained" color="primary" onClick={() => grokAIOpenHandler("text_management")}>AI</Button>
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
                                                            <FormControl fullWidth sx={{ mt: 2.1 }}>
                                                                <InputLabel id="demo-simple-select-label">Link Effect</InputLabel>
                                                                <MuiSelect
                                                                    // displayEmpty
                                                                    renderValue={(value) => {
                                                                        if (!value) {
                                                                            return <Typography color="grey"> Select Link Effect</Typography>;
                                                                        }
                                                                        return <>{value}</>;
                                                                    }}
                                                                    value={textManagement.linkEffect}
                                                                    label="Link Effect"
                                                                    size='small'
                                                                    onChange={(e) => {
                                                                        setTextManagement({ ...textManagement, linkEffect: e.target.value })
                                                                    }}
                                                                >
                                                                    {linkTypes.map((item, index) => (
                                                                        <MenuItem className="doNotAct" value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
                                                                    ))}
                                                                </MuiSelect>
                                                            </FormControl>
                                                            {textManagement.linkEffect == "Selected Element" &&
                                                                <Box mt={1.5}>
                                                                    <Select
                                                                        menuPortalTarget={document.body}
                                                                        styles={{
                                                                            menuPortal: base => ({ ...base, zIndex: 9999 }),
                                                                        }}
                                                                        placeholder="Select Element Part"
                                                                        options={textPartsList}
                                                                        value={selectedTextPart}
                                                                        onChange={(e) => setSelectedTextPart(e)}
                                                                    />
                                                                </Box>
                                                            }
                                                            <TextField
                                                                sx={{ mt: 2.1 }}
                                                                type="text"
                                                                fullWidth
                                                                size="small"
                                                                label="Margin (top right bottom left)"
                                                                slotProps={{
                                                                    inputLabel: { shrink: true },
                                                                }}
                                                                placeholder="e.g. 10px 20px 30px 40px"
                                                                value={textManagement.margin || ""}
                                                                onChange={(e) => handleSpacingChange(e.target.value, 'margin', setTextManagement)}
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
                                                                <MuiSelect
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
                                                                </MuiSelect>
                                                            </FormControl>
                                                            <TextField
                                                                sx={{ mt: 2.1 }}
                                                                type="text"
                                                                fullWidth
                                                                size="small"
                                                                label="Padding (top right bottom left)"
                                                                slotProps={{
                                                                    inputLabel: { shrink: true },
                                                                }}
                                                                placeholder="e.g. 10px 20px 30px 40px"
                                                                value={textManagement.padding || ""}
                                                                onChange={(e) => handleSpacingChange(e.target.value, 'padding', setTextManagement)}
                                                            />
                                                            <FormControl fullWidth sx={{ mt: 2.1 }}>
                                                                <InputLabel id="demo-simple-select-label">Border</InputLabel>
                                                                <MuiSelect
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
                                                                </MuiSelect>
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
                                                            <Box sx={{ textAlign: textManagement.textAlign, minHeight: "102px", mt: 0.7, p: 1, borderRadius: "3px", border: `${textManagement.borderWidth}px ${textManagement.border} ${textManagement.borderColor}`, color: textManagement.color, backgroundColor: textManagement.backgroundColor == "" ? "#dedede" : textManagement.backgroundColor, fontSize: `${textManagement.fontSize}px` }}>
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
                                                        <Button className="doNotAct" size="small" variant="contained" color="primary" onClick={() => grokAIOpenHandler("custom_html")}>AI</Button>
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
                                                            sx={{ mt: 2.1 }}
                                                            type="text"
                                                            fullWidth
                                                            size="small"
                                                            label="Margin (top right bottom left)"
                                                            slotProps={{
                                                                inputLabel: { shrink: true },
                                                            }}
                                                            placeholder="e.g. 10px 20px 30px 40px"
                                                            value={buttonManagement.margin || ""}
                                                            onChange={(e) => handleSpacingChange(e.target.value, 'margin', setButtonManagement)}
                                                        />
                                                        <TextField
                                                            sx={{ mt: 2.1 }}
                                                            type="text"
                                                            fullWidth
                                                            size="small"
                                                            label="Padding (top right bottom left)"
                                                            slotProps={{
                                                                inputLabel: { shrink: true },
                                                            }}
                                                            placeholder="e.g. 10px 20px 30px 40px"
                                                            value={buttonManagement.padding || ""}
                                                            onChange={(e) => handleSpacingChange(e.target.value, 'padding', setButtonManagement)}
                                                        />
                                                        <FormControl fullWidth sx={{ mt: 2.1 }}>
                                                            <InputLabel id="demo-simple-select-label">Border</InputLabel>
                                                            <MuiSelect
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
                                                            </MuiSelect>
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
                                                                <Box component={"button"} sx={{ color: `${buttonManagement.color}`, backgroundColor: `${buttonManagement.backgroundColor}`, padding: `${buttonManagement.padding}px`, fontSize: `${buttonManagement.fontSize}px`, margin: `${buttonManagement.margin}px`, textAlign: "center", border: `${buttonManagement.borderWidth}px ${buttonManagement.border} ${buttonManagement.borderColor}` }}>{buttonManagement.buttonText}</Box>
                                                            </Box>
                                                        </Box>
                                                    </Box>
                                                </Box>
                                            )}

                                        {/* FORMS */}
                                        {(editing && editing.actionType == "edit" && ['form'].includes(editing.elementName) ||
                                            (editing && editing.actionType === "add" && editing.addElementType == "form")
                                        ) && (
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
                                                    <Box sx={{ display: 'flex', gap: "15px", mt: 2 }}>
                                                        <Box sx={{ width: "33%" }}>
                                                            <TextField
                                                                fullWidth
                                                                size='small'
                                                                label="Form Title"
                                                                slotProps={{
                                                                    inputLabel: { shrink: true }
                                                                }}
                                                                placeholder='Enter Form Title'
                                                                value={formManagement.h3Text}
                                                                onChange={(e) => {
                                                                    setFormManagement({ ...formManagement, h3Text: e.target.value })
                                                                }}
                                                            />
                                                        </Box>
                                                        <Box sx={{ width: "33%" }}>
                                                            <TextField
                                                                fullWidth
                                                                size='small'
                                                                type='number'
                                                                label="Title Font Size"
                                                                slotProps={{
                                                                    inputLabel: { shrink: true }
                                                                }}
                                                                placeholder='Font Size'
                                                                value={formManagement.h3FontSize}
                                                                onChange={(e) => {
                                                                    setFormManagement({ ...formManagement, h3FontSize: e.target.value })
                                                                }}
                                                            />
                                                        </Box>
                                                        <Box sx={{ width: "33%" }}>
                                                            <FormControl fullWidth>
                                                                <InputLabel id="h3-alignment-select-label">Title Alignment</InputLabel>
                                                                <MuiSelect
                                                                    // displayEmpty
                                                                    renderValue={(value) => {
                                                                        if (!value) {
                                                                            return <Typography color="grey"> Select Alignment</Typography>;
                                                                        }
                                                                        return <>{value}</>;
                                                                    }}
                                                                    value={formManagement.h3HeadingAlignment}
                                                                    label="Title Alignment"
                                                                    size='small'
                                                                    onChange={(e) => {
                                                                        setFormManagement({ ...formManagement, h3HeadingAlignment: e.target.value })
                                                                    }}
                                                                >
                                                                    {textAlignProperties.map((item, index) => (
                                                                        <MenuItem className="doNotAct" key={index} value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
                                                                    ))}
                                                                </MuiSelect>
                                                            </FormControl>
                                                        </Box>
                                                    </Box>
                                                    <Box sx={{ display: 'flex', gap: "15px", mt: 1 }}>
                                                        <Box sx={{ width: "50%", display: "flex", gap: 2 }}>
                                                            <Box sx={{ width: "50%" }} className="customPickerTwo">
                                                                <Typography variant="body" component="div" sx={{ fontSize: "14px", mb: 1 }}>
                                                                    Title Color
                                                                </Typography>
                                                                <HexColorPicker color={formManagement.h3HeadingColor} style={{ width: "100%" }} onChange={(e) => setFormManagement({ ...formManagement, h3HeadingColor: e })} />
                                                            </Box>
                                                            <Box sx={{ width: "50%" }} className="customPickerTwo">
                                                                <Typography variant="body" component="div" sx={{ fontSize: "14px" }}>
                                                                    Border Color
                                                                </Typography>
                                                                <HexColorPicker color={formManagement.borderColor} style={{ marginTop: "7px", width: "100%" }} onChange={(e) => setFormManagement({ ...formManagement, borderColor: e })} />
                                                            </Box>
                                                        </Box>
                                                        <Box sx={{ width: "50%" }}>
                                                            <FormControl fullWidth sx={{ mt: 1 }}>
                                                                <InputLabel id="demo-simple-select-label">Border</InputLabel>
                                                                <MuiSelect
                                                                    // displayEmpty
                                                                    renderValue={(value) => {
                                                                        if (!value) {
                                                                            return <Typography color="grey"> Select Border</Typography>;
                                                                        }
                                                                        return <>{value}</>;
                                                                    }}
                                                                    value={formManagement.border}
                                                                    label="Border"
                                                                    size='small'
                                                                    onChange={(e) => {
                                                                        setFormManagement({ ...formManagement, border: e.target.value })
                                                                    }}
                                                                >
                                                                    {borderStyles.map((item, index) => (
                                                                        <MenuItem className="doNotAct" key={index} value={item} sx={{ textTransform: 'capitalize' }}>{item}</MenuItem>
                                                                    ))}
                                                                </MuiSelect>
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
                                                                value={formManagement.borderWidth}
                                                                onChange={(e) => {
                                                                    setFormManagement({ ...formManagement, borderWidth: e.target.value })
                                                                }}
                                                            />
                                                        </Box>
                                                    </Box>
                                                    <Box mt={2} mb={2}>
                                                        <FormControl fullWidth>
                                                            <InputLabel id="demo-simple-select-label" shrink>
                                                                Select API
                                                            </InputLabel>
                                                            <MuiSelect
                                                                labelId="demo-simple-select-label"
                                                                value={formManagement.apiType}
                                                                label="Select API"
                                                                size="small"
                                                                onChange={(e) => {
                                                                    setFormManagement({ ...formManagement, apiType: e.target.value })
                                                                }}
                                                                displayEmpty
                                                                renderValue={(value) =>
                                                                    !value ? <Typography color="grey">Select API...</Typography> : value
                                                                }
                                                            >
                                                                {apiTypes.map((item, index) => (
                                                                    <MenuItem
                                                                        className="doNotAct"
                                                                        key={index}
                                                                        value={item.value}
                                                                        sx={{ textTransform: 'capitalize' }}
                                                                    >
                                                                        {item.label}
                                                                    </MenuItem>
                                                                ))}
                                                            </MuiSelect>
                                                        </FormControl>
                                                        <Box mt={2}>
                                                            <TextField
                                                                type="text"
                                                                fullWidth
                                                                size='small'
                                                                label="Project Directory"
                                                                slotProps={{
                                                                    inputLabel: { shrink: true }
                                                                }}
                                                                placeholder='Enter Project Directory'
                                                                value={formManagement.project_directory}
                                                                onChange={(e) => {
                                                                    setFormManagement({ ...formManagement, project_directory: e.target.value })
                                                                }}
                                                            />
                                                        </Box>
                                                        <TextField
                                                            sx={{ mt: 2.1 }}
                                                            type="text"
                                                            fullWidth
                                                            size="small"
                                                            label="Margin (top right bottom left)"
                                                            slotProps={{
                                                                inputLabel: { shrink: true },
                                                            }}
                                                            placeholder="e.g. 10px 20px 30px 40px"
                                                            value={formManagement.margin || ""}
                                                            onChange={(e) => handleSpacingChange(e.target.value, 'margin', setFormManagement)}
                                                        />
                                                        <TextField
                                                            sx={{ mt: 2.1 }}
                                                            type="text"
                                                            fullWidth
                                                            size="small"
                                                            label="Padding (top right bottom left)"
                                                            slotProps={{
                                                                inputLabel: { shrink: true },
                                                            }}
                                                            placeholder="e.g. 10px 20px 30px 40px"
                                                            value={formManagement.padding || ""}
                                                            onChange={(e) => handleSpacingChange(e.target.value, 'padding', setFormManagement)}
                                                        />
                                                    </Box>
                                                    <Box sx={{ mt: 2.1 }}>
                                                        <FormControl fullWidth>
                                                            <InputLabel id="demo-simple-select-label" shrink>
                                                                Select Defaults Language
                                                            </InputLabel>
                                                            <MuiSelect
                                                                labelId="demo-simple-select-label"
                                                                value={selectedFormLanguage}
                                                                label="Select Defaults Language"
                                                                size="small"
                                                                onChange={(e) => setSelectedFormLanguage(e.target.value)}
                                                                displayEmpty
                                                                renderValue={(value) =>
                                                                    !value ? <Typography color="grey">Select Defaults Language...</Typography> : value
                                                                }
                                                            >
                                                                {formFieldLanguageOptions.map((option) => (
                                                                    <MenuItem key={option.value} value={option.value} className="doNotAct">
                                                                        {option.label}
                                                                    </MenuItem>
                                                                ))}
                                                            </MuiSelect>
                                                        </FormControl>
                                                    </Box>
                                                    <Box mt={2} p={2} pt={0} sx={{ border: "2px dashed #a5a5a5", borderRadius: "2px" }}>
                                                        {formManagement && formManagement.inputs.map((value, index) => (
                                                            <Box key={index} sx={{ mt: 2, display: "flex" }}>
                                                                <TextField
                                                                    sx={{ width: "100%" }}
                                                                    size='small'
                                                                    label="Name"
                                                                    slotProps={{
                                                                        inputLabel: { shrink: true }
                                                                    }}
                                                                    placeholder='Enter Name'
                                                                    value={value.name}
                                                                    disabled={true}
                                                                />
                                                                <Box component="span" sx={{ marginLeft: "10px" }} />
                                                                <TextField
                                                                    sx={{ width: "100%" }}
                                                                    size='small'
                                                                    label="Visible Name"
                                                                    slotProps={{
                                                                        inputLabel: { shrink: true }
                                                                    }}
                                                                    placeholder='Enter Name'
                                                                    value={value.inputName}
                                                                    onChange={(e) => {
                                                                        let temp = { ...formManagement };
                                                                        temp.inputs[index] = { ...temp.inputs[index], inputName: e.target.value };
                                                                        setFormManagement(temp);
                                                                    }}
                                                                />
                                                                <Box component="span" sx={{ marginLeft: "10px" }} />
                                                                {/* <FormControl>
                                                                <InputLabel id="demo-simple-select-label">Required</InputLabel>
                                                                <MuiSelect
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
                                                                </MuiSelect>
                                                            </FormControl>
                                                            <Box component="span" sx={{ marginLeft: "10px" }} />
                                                            <FormControl>
                                                                <InputLabel id="demo-simple-select-label">Type</InputLabel>
                                                                <MuiSelect
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
                                                                </MuiSelect>
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
                                                            </Box> */}
                                                            </Box>
                                                        ))}
                                                    </Box>
                                                </Box>
                                            )}

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
                                    </Box>
                                }
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

            {/* REPLACE MODAL */}
            <Modal
                className="doNotAct"
                aria-labelledby="replace-modal-title"
                aria-describedby="replace-modal-description"
                open={replaceModalOpen}
                onClose={() => setReplaceModalOpen(false)}
                closeAfterTransition
                slots={{ backdrop: Backdrop }}
                slotProps={{
                    backdrop: {
                        timeout: 100,
                    },
                }}
            >
                <Fade in={replaceModalOpen}>
                    <Box className="doNotAct" sx={{
                        position: 'absolute',
                        top: '50%',
                        left: '50%',
                        transform: 'translate(-50%, -50%)',
                        width: { xs: '90%', sm: '70%', md: '60%', lg: '50%', xl: '40%' },
                        bgcolor: 'background.paper',
                        boxShadow: 10,
                        p: 4,
                        borderRadius: 2
                    }}>
                        <Box className="doNotAct">
                            <Box className="doNotAct" sx={{ display: "flex", justifyContent: "space-between", alignItems: "center", mb: 3 }}>
                                <Typography className="doNotAct" variant="h6" component="h2">
                                    {replaceType === 'text' ? 'Replace Text' : 'Replace URLs'}
                                </Typography>
                                <ClearIcon className="doNotAct" sx={{ cursor: "pointer" }} onClick={() => setReplaceModalOpen(false)} />
                            </Box>

                            <Box className="doNotAct" sx={{ mb: 2 }}>
                                <TextField
                                    className="doNotAct"
                                    fullWidth
                                    label={replaceType === 'text' ? 'Find Text' : 'Find URL'}
                                    placeholder={replaceType === 'text' ? 'Enter text to search' : 'Enter URL to search'}
                                    value={searchText}
                                    onChange={(e) => setSearchText(e.target.value)}
                                    size="small"
                                    sx={{ mb: 2 }}
                                />
                                <TextField
                                    className="doNotAct"
                                    fullWidth
                                    label={replaceType === 'text' ? 'Replace With' : 'Replace URL With'}
                                    placeholder={replaceType === 'text' ? 'Enter replacement text' : 'Enter replacement URL'}
                                    value={replacementText}
                                    onChange={(e) => setReplacementText(e.target.value)}
                                    size="small"
                                />
                            </Box>

                            <Box className="doNotAct" sx={{ mb: 2, display: 'flex', gap: 2, alignItems: 'center', flexWrap: 'wrap' }}>
                                <Box className="doNotAct" sx={{ display: 'flex', alignItems: 'center' }}>
                                    <input
                                        className="doNotAct"
                                        type="checkbox"
                                        id="matchCase"
                                        checked={matchCase}
                                        onChange={(e) => setMatchCase(e.target.checked)}
                                        onClick={(e) => e.stopPropagation()}
                                        style={{ marginRight: '8px' }}
                                    />
                                    <label className="doNotAct" htmlFor="matchCase">Match Case</label>
                                </Box>
                                <Box className="doNotAct" sx={{ display: 'flex', alignItems: 'center' }}>
                                    <input
                                        className="doNotAct"
                                        type="checkbox"
                                        id="matchWholeWord"
                                        checked={matchWholeWord}
                                        onChange={(e) => setMatchWholeWord(e.target.checked)}
                                        onClick={(e) => e.stopPropagation()}
                                        style={{ marginRight: '8px' }}
                                    />
                                    <label className="doNotAct" htmlFor="matchWholeWord">Match Whole Word</label>
                                </Box>
                                {searchPerformed && (
                                    <Typography
                                        className="doNotAct"
                                        variant="body2"
                                        sx={{
                                            color: occurrencesCount > 0 ? 'primary.main' : 'error.main',
                                            fontWeight: 'bold'
                                        }}
                                    >
                                        {occurrencesCount > 0
                                            ? `Found: ${occurrencesCount} occurrence${occurrencesCount !== 1 ? 's' : ''}`
                                            : 'No results'
                                        }
                                    </Typography>
                                )}
                            </Box>

                            <Box className="doNotAct" sx={{ display: "flex", justifyContent: "flex-end", gap: 2 }}>
                                <Button className="doNotAct" variant="outlined" onClick={() => setReplaceModalOpen(false)}>
                                    Cancel
                                </Button>
                                <Button
                                    className="doNotAct"
                                    variant="outlined"
                                    color="primary"
                                    onClick={() => {
                                        const mainHTMLActiveInside = mainHTML.find(html => html.status === true);
                                        if (mainHTMLActiveInside && searchText.trim()) {
                                            let count;
                                            if (replaceType === 'text') {
                                                count = countTextOccurrences(mainHTMLActiveInside.html, searchText, matchCase, matchWholeWord);
                                            } else {
                                                count = countURLOccurrences(mainHTMLActiveInside.html, searchText, matchCase, matchWholeWord);
                                            }
                                            setOccurrencesCount(count);
                                            setSearchPerformed(true);
                                        }
                                    }}
                                >
                                    Find
                                </Button>
                                <Button
                                    className="doNotAct"
                                    variant="contained"
                                    color="success"
                                    disabled={!searchText.trim() || !replacementText.trim() || occurrencesCount === 0}
                                    onClick={() => {
                                        const mainHTMLActiveInside = mainHTML.find(html => html.status === true);
                                        if (mainHTMLActiveInside) {
                                            let updatedHTML;
                                            if (replaceType === 'text') {
                                                updatedHTML = replaceTextInHTML(mainHTMLActiveInside.html, searchText, replacementText, matchCase, matchWholeWord);
                                            } else {
                                                updatedHTML = replaceURLsInHTML(mainHTMLActiveInside.html, searchText, replacementText, matchCase, matchWholeWord);
                                            }

                                            setMainHTML(prev => [
                                                ...prev.map(item => ({ ...item, status: false })),
                                                { html: updatedHTML, status: true }
                                            ]);

                                            setReplaceModalOpen(false);
                                            setSearchText('');
                                            setReplacementText('');
                                            setOccurrencesCount(0);
                                            setSearchPerformed(false);
                                        }
                                    }}
                                >
                                    Replace All
                                </Button>
                            </Box>
                        </Box>
                    </Box>
                </Fade>
            </Modal>
            <Head title={`Preview: ${data.name}`} />
            <div className="sticky-left-div">
                <Box sx={{ flexDirection: "column", backgroundColor: "#c0c0c0", justifyContent: "space-between", display: "flex", padding: "8px", borderRadius: "5px", borderTopLeftRadius: "0px", borderBottomLeftRadius: "0px", boxShadow: "-2px 2px 10px 5px rgba(0,0,0,0.20)" }}>
                    <Box className="doNotAct" sx={{ ml: 0.3, fontWeight: "bold" }}>
                        <svg style={{ cursor: "pointer", rotate: "180deg" }} className='doNotAct' xmlns="http://www.w3.org/2000/svg" width="25px" height="25px" viewBox="0 0 24 24" fill="none" onClick={() => router.get(route('userThemes', { id: data.user_id }))}>
                            <path className='doNotAct' id="Vector" d="M12 15L15 12M15 12L12 9M15 12H4M4 7.24802V7.2002C4 6.08009 4 5.51962 4.21799 5.0918C4.40973 4.71547 4.71547 4.40973 5.0918 4.21799C5.51962 4 6.08009 4 7.2002 4H16.8002C17.9203 4 18.4796 4 18.9074 4.21799C19.2837 4.40973 19.5905 4.71547 19.7822 5.0918C20 5.5192 20 6.07899 20 7.19691V16.8036C20 17.9215 20 18.4805 19.7822 18.9079C19.5905 19.2842 19.2837 19.5905 18.9074 19.7822C18.48 20 17.921 20 16.8031 20H7.19691C6.07899 20 5.5192 20 5.0918 19.7822C4.71547 19.5905 4.40973 19.2839 4.21799 18.9076C4 18.4798 4 17.9201 4 16.8V16.75" stroke="#000000" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    </Box>
                    <Box className="doNotAct" sx={{ mt: 5, display: "flex", flexDirection: "column", }}>
                        <svg style={{ cursor: "pointer" }} className='doNotAct' width="30px" height="30px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" onClick={undoHandler}>
                            <path className="doNotAct" fillRule="evenodd" clipRule="evenodd" d="M10.7071 4.29289C11.0976 4.68342 11.0976 5.31658 10.7071 5.70711L8.41421 8H13.5C16.5376 8 19 10.4624 19 13.5C19 16.5376 16.5376 19 13.5 19H11C10.4477 19 10 18.5523 10 18C10 17.4477 10.4477 17 11 17H13.5C15.433 17 17 15.433 17 13.5C17 11.567 15.433 10 13.5 10H8.41421L10.7071 12.2929C11.0976 12.6834 11.0976 13.3166 10.7071 13.7071C10.3166 14.0976 9.68342 14.0976 9.29289 13.7071L5.29289 9.70711C4.90237 9.31658 4.90237 8.68342 5.29289 8.29289L9.29289 4.29289C9.68342 3.90237 10.3166 3.90237 10.7071 4.29289Z" fill="#000000" />
                        </svg>
                        <svg style={{ cursor: "pointer" }} className="doNotAct" width="30px" height="30px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" onClick={redoHandler}>
                            <path className="doNotAct" fillRule="evenodd" clipRule="evenodd" d="M13.2929 4.29289C13.6834 3.90237 14.3166 3.90237 14.7071 4.29289L18.7071 8.29289C19.0976 8.68342 19.0976 9.31658 18.7071 9.70711L14.7071 13.7071C14.3166 14.0976 13.6834 14.0976 13.2929 13.7071C12.9024 13.3166 12.9024 12.6834 13.2929 12.2929L15.5858 10H10.5C8.567 10 7 11.567 7 13.5C7 15.433 8.567 17 10.5 17H13C13.5523 17 14 17.4477 14 18C14 18.5523 13.5523 19 13 19H10.5C7.46243 19 5 16.5376 5 13.5C5 10.4624 7.46243 8 10.5 8H15.5858L13.2929 5.70711C12.9024 5.31658 12.9024 4.68342 13.2929 4.29289Z" fill="#000000" />
                        </svg>
                    </Box>
                    <Box className="doNotAct" sx={{ mt: 5, display: "flex", flexDirection: "column", }}>
                        <svg style={{ cursor: "pointer" }} xmlns="http://www.w3.org/2000/svg" className='doNotAct' width="30px" height="30px" viewBox="0 0 16 16" onClick={openTextReplaceModal}><path fill="#000000" fillRule="evenodd" d="m3.221 3.739l2.261 2.269L7.7 3.784l-.7-.7l-1.012 1.007l-.008-1.6a.523.523 0 0 1 .5-.526H8V1H6.48A1.482 1.482 0 0 0 5 2.489V4.1L3.927 3.033l-.706.706zm6.67 1.794h.01c.183.311.451.467.806.467c.393 0 .706-.168.94-.503c.236-.335.353-.78.353-1.333c0-.511-.1-.913-.301-1.207c-.201-.295-.488-.442-.86-.442c-.405 0-.718.194-.938.581h-.01V1H9v4.919h.89v-.386zm-.015-1.061v-.34c0-.248.058-.448.175-.601a.54.54 0 0 1 .445-.23a.49.49 0 0 1 .436.233c.104.154.155.368.155.643c0 .33-.056.587-.169.768a.524.524 0 0 1-.47.27a.495.495 0 0 1-.411-.211a.853.853 0 0 1-.16-.532zM9 12.769c-.256.154-.625.231-1.108.231c-.563 0-1.02-.178-1.369-.533c-.349-.355-.523-.813-.523-1.374c0-.648.186-1.158.56-1.53c.374-.376.875-.563 1.5-.563c.433 0 .746.06.94.179v.998a1.26 1.26 0 0 0-.792-.276c-.325 0-.583.1-.774.298c-.19.196-.283.468-.283.816c0 .338.09.603.272.797c.182.191.431.287.749.287c.282 0 .558-.092.828-.276v.946zM4 7L3 8v6l1 1h7l1-1V8l-1-1H4zm0 1h7v6H4V8z" clipRule="evenodd" /></svg>
                        <svg style={{ cursor: "pointer" }} xmlns="http://www.w3.org/2000/svg" className='doNotAct' width="30px" height="30px" viewBox="-4 -10 33 33" fill="#000000" onClick={openURLReplaceModal}><g fill="none" stroke="#000000" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"><path d="M14 4c0-1.1.9-2 2-2m4 0c1.1 0 2 .9 2 2m0 4c0 1.1-.9 2-2 2m-4 0c-1.1 0-2-.9-2-2M3 7l3 3l3-3" /><path d="M6 10V5c0-1.7 1.3-3 3-3h1" /><rect className="doNotAct" width="8" height="8" x="2" y="14" rx="2" /><path d="M14 14c1.1 0 2 .9 2 2v4c0 1.1-.9 2-2 2m6-8c1.1 0 2 .9 2 2v4c0 1.1-.9 2-2 2" /></g></svg>
                    </Box>
                    <Box sx={{ mt: 5, ml: 0.7, mb: 0.5, fontWeight: "bold" }}>
                        <svg className="doNotAct" style={{ cursor: "pointer", marginTop: "5px", }} width="20px" height="20px" xmlns="http://www.w3.org/2000/svg" fill="#000000" version="1.1" id="Capa_1" viewBox="0 0 407.096 407.096" xmlSpace="preserve" onClick={updatedThemeSaveHandler}>
                            <path className="doNotAct" d="M402.115,84.008L323.088,4.981C319.899,1.792,315.574,0,311.063,0H17.005C7.613,0,0,7.614,0,17.005v373.086    c0,9.392,7.613,17.005,17.005,17.005h373.086c9.392,0,17.005-7.613,17.005-17.005V96.032    C407.096,91.523,405.305,87.197,402.115,84.008z M300.664,163.567H67.129V38.862h233.535V163.567z" />
                            <path className="doNotAct" d="M214.051,148.16h43.08c3.131,0,5.668-2.538,5.668-5.669V59.584c0-3.13-2.537-5.668-5.668-5.668h-43.08    c-3.131,0-5.668,2.538-5.668,5.668v82.907C208.383,145.622,210.92,148.16,214.051,148.16z" />
                        </svg>
                    </Box>
                </Box>
            </div>
            <div style={{ display: "flex" }}>
                <div style={{ width: "14%" }}></div>
                <div style={{ width: "70%" }}>
                    <Box sx={{ mt: 0.8, ml: 0.5 }}>
                        <Typography className="doNotAct" variant="body" sx={{ fontWeight: 'bold', fontSize: { xs: '16px', sm: '16px', md: '18px', lg: '18px', xl: '18px' } }}>
                            Select Body
                        </Typography>
                        <Box sx={{ mt: 0.5 }}>
                            <select value={mainBodies.length > 0 && mainBodies.find(it => it.selected_body).id} className="doNotAct" style={{ width: "100%", padding: "5px" }} onChange={(e) => {
                                const selectedId = e.target.value;
                                function proceedFurther(selectedId) {
                                    setMainBodies((prev) =>
                                        prev.map((body) => ({
                                            ...body,
                                            selected_body: body.id == selectedId
                                        }))
                                    );
                                    let selectedBody = mainBodies.find(value => value.id == selectedId);
                                    setMainHTML([{ html: updateAngleImages(selectedBody.content, data), status: true }]);
                                }
                                if (mainHTML.length == 1) {
                                    proceedFurther(selectedId);
                                } else if (mainHTML.length > 1) {
                                    Swal.fire({
                                        title: "Are you sure?",
                                        text: "Your unsaved progress will be deleted!",
                                        icon: "warning",
                                        showCancelButton: true,
                                        confirmButtonColor: "#3085d6",
                                        cancelButtonColor: "#d33",
                                        confirmButtonText: "Yes, Sure!"
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            proceedFurther(selectedId);
                                        }
                                    });
                                }

                            }}>
                                {mainBodies.map((body, index) => {
                                    return (
                                        <option className="doNotAct" key={index} value={body.id}>
                                            {body.name}
                                        </option>
                                    );
                                })}
                            </select>
                        </Box>
                    </Box>
                    <Box sx={{ border: "1px solid black", ml: 0.5, p: 1, mt: 2 }}>
                        {data && <div className='mainHTML' dangerouslySetInnerHTML={{ __html: mainHTMLActive.html }} />}
                    </Box>
                </div>
            </div>
        </div >
    );
}
