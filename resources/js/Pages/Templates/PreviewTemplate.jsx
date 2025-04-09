import { Head } from '@inertiajs/react';
import { Box, Button, TextField, Typography } from '@mui/material';
import Popover from '@mui/material/Popover';
import * as React from 'react';
import { useEffect, useState } from "react";
import UndoIcon from '@mui/icons-material/Undo';
import RedoIcon from '@mui/icons-material/Redo';

export default function Dashboard({ id }) {

    function generateRandomString() {
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

    function hasParentWithClass(element, className) {
        while (element && element !== document) {
            if (element.classList.contains(className)) {
                return true;
            }
            element = element.parentElement;
        }
        return false;
    }

    const [data, setData] = useState(false);
    const [mainHTML, setMainHTML] = useState([{ html: '', status: true }]);
    const [mainCSS, setMainCSS] = useState('');

    const [anchorEl, setAnchorEl] = React.useState(null);
    const [editing, setEditing] = useState({
        editID: false,
        currentElement: false,
        currentElementName: "",
        innerHTML: "",
        insideText: "",
        imageSrc: "",
    });

    const handleClick = (event) => {
        // console.log(event.target.outerHTML);
        if (!event.target.outerHTML.includes("MuiModal-backdrop") && !hasParentWithClass(event.target, 'popoverPlate')) {
            if (!event.target.outerHTML.includes("closeCheck")) {
                if (!hasParentWithClass(event.target, 'popoverPlate')) {
                    let randString = generateRandomString();
                    setAnchorEl(event.target);
                    event.target.classList.add(randString);
                    setEditing({
                        editID: randString,
                        currentElement: event.target,
                        currentElementName: event.target.localName,
                        innerHTML: event.target.innerHTML,
                        insideText: event.target.innerHTML,
                        imageSrc: event.target.src,
                    })
                }

            }
        }
    };

    useEffect(() => {

        async function getData() {
            const url = route('templates.previewContent');

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', },
                    body: JSON.stringify({ template_id: id })
                });

                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                const json = await response.json();
                console.log(json);
                setData(json.data);

                let updated = json.data.template.index.replace('<!--INTERNAL--BD1--EXTERNAL-->', json.data.body.content);
                updated = updated.replace('<!--INTERNAL--BD2--EXTERNAL-->', json.data.body2.content);
                updated = updated.replace('<!--INTERNAL--BD3--EXTERNAL-->', json.data.body3.content);
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

    const updateHTMLHandler = () => {
        console.log(editing);
        console.log(mainHTML);
        if (editing.currentElementName == "img") {
            document.querySelector(`.${editing.editID}`).src = editing.imageSrc;

        } else {
            document.querySelector(`.${editing.editID}`).innerHTML = editing.insideText;
        }

        setMainHTML(prev => [
            ...prev.map(item => ({ ...item, status: false })), // Set previous statuses to false
            { html: document.querySelector(".mainHTML").innerHTML, status: true } // Add new entry
        ]);
        setAnchorEl(false);
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

    const open = Boolean(anchorEl);
    const popoverId = open ? 'simple-popover' : undefined;

    const mainHTMLActive = mainHTML.find(html => html.status == true)

    return (

        <div>
            <Popover
                id={popoverId}
                open={open}
                anchorEl={anchorEl}
                onClose={() => { }}
                anchorOrigin={{
                    vertical: 'bottom',
                    horizontal: 'left',
                }}
            >
                {/* POPOVER H's  */}
                <Box p={2} sx={{ width: "300px" }} className="popoverPlate">
                    <Box pb={2} sx={{ display: "flex", justifyContent: "space-between" }}>
                        <Typography variant="body1" component="div" sx={{}}>
                            Editor
                        </Typography>
                        <div style={{ marginTop: "3px", cursor: "pointer", width: "18px", height: "18px", }} className='closeCheck' onClick={() => setAnchorEl(false)}>
                            <svg className='closeCheck' xmlns="http://www.w3.org/2000/svg" viewBox="50 50 412 412">
                                <polygon fill="var(--ci-primary-color, currentColor)" points="427.314 107.313 404.686 84.687 256 233.373 107.314 84.687 84.686 107.313 233.373 256 84.686 404.687 107.314 427.313 256 278.627 404.686 427.313 427.314 404.687 278.627 256 427.314 107.313" className="closeCheck" />
                            </svg>
                        </div>
                    </Box>
                    {/* POPOVER BODY */}
                    <Box>
                        {editing.currentElementName == "img" ?
                            <TextField
                                label="Image Src" // Add label here
                                sx={{
                                    width: "100%",
                                    marginBottom: 2,
                                    "& .MuiInputBase-input:focus": {
                                        outline: "none", // Remove input focus outline
                                        boxShadow: "none", // Remove any remaining shadow
                                    },
                                    fontSize: "12px"
                                }}
                                fullWidth
                                placeholder="Image Src"
                                size="small"
                                value={editing.imageSrc}
                                onChange={(e) => {
                                    let temp = { ...editing };
                                    temp.imageSrc = e.target.value;
                                    setEditing(temp);
                                }}
                                multiline
                                rows={4}
                            /> :
                            <TextField
                                label="HTML Content" // Add label here
                                sx={{
                                    width: "100%",
                                    marginBottom: 2,
                                    "& .MuiInputBase-input:focus": {
                                        outline: "none", // Remove input focus outline
                                        boxShadow: "none", // Remove any remaining shadow
                                    },
                                    fontSize: "12px"
                                }}
                                fullWidth
                                placeholder="HTML Content"
                                size="small"
                                value={editing.insideText}
                                onChange={(e) => {
                                    let temp = { ...editing };
                                    temp.insideText = e.target.value;
                                    setEditing(temp);
                                }}
                                multiline
                                rows={4}
                            />
                        }
                        <Box sx={{ display: "flex", justifyContent: "flex-end" }}>
                            <Button variant='contained' color='primary' onClick={updateHTMLHandler}>Add</Button>
                        </Box>
                    </Box>
                </Box>
            </Popover>
            <Head title={`Preview (${data && data.template.name})`} />
            <div>
                <Box className="closeCheck" sx={{ background: "grey", justifyContent: "center", display: "flex" }}>
                    <svg style={{ cursor: "pointer" }} className='closeCheck' width="30px" height="30px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" onClick={undoHandler}>
                        <path className="closeCheck" fillRule="evenodd" clipRule="evenodd" d="M10.7071 4.29289C11.0976 4.68342 11.0976 5.31658 10.7071 5.70711L8.41421 8H13.5C16.5376 8 19 10.4624 19 13.5C19 16.5376 16.5376 19 13.5 19H11C10.4477 19 10 18.5523 10 18C10 17.4477 10.4477 17 11 17H13.5C15.433 17 17 15.433 17 13.5C17 11.567 15.433 10 13.5 10H8.41421L10.7071 12.2929C11.0976 12.6834 11.0976 13.3166 10.7071 13.7071C10.3166 14.0976 9.68342 14.0976 9.29289 13.7071L5.29289 9.70711C4.90237 9.31658 4.90237 8.68342 5.29289 8.29289L9.29289 4.29289C9.68342 3.90237 10.3166 3.90237 10.7071 4.29289Z" fill="#000000" />
                    </svg>
                    <svg style={{ cursor: "pointer" }} className="closeCheck" width="30px" height="30px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" onClick={redoHandler}>
                        <path className="closeCheck" fillRule="evenodd" clipRule="evenodd" d="M13.2929 4.29289C13.6834 3.90237 14.3166 3.90237 14.7071 4.29289L18.7071 8.29289C19.0976 8.68342 19.0976 9.31658 18.7071 9.70711L14.7071 13.7071C14.3166 14.0976 13.6834 14.0976 13.2929 13.7071C12.9024 13.3166 12.9024 12.6834 13.2929 12.2929L15.5858 10H10.5C8.567 10 7 11.567 7 13.5C7 15.433 8.567 17 10.5 17H13C13.5523 17 14 17.4477 14 18C14 18.5523 13.5523 19 13 19H10.5C7.46243 19 5 16.5376 5 13.5C5 10.4624 7.46243 8 10.5 8H15.5858L13.2929 5.70711C12.9024 5.31658 12.9024 4.68342 13.2929 4.29289Z" fill="#000000" />
                    </svg>
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
        </div >
    );
}
