// import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
// import { Head } from '@inertiajs/react';

import { useEffect, useState } from "react";

// export default function Dashboard() {
//     return (
//         <AuthenticatedLayout
//             header={
//                 <h2 className="text-xl font-semibold leading-tight text-gray-800">
//                     Dashboard
//                 </h2>
//             }
//         >
//             <Head title="Dashboard" />

//             <div className="py-16">
//                 {/* sm:px-6 lg:px-8 */}
//                 <div className="mx-auto max-w-7xl">
//                     <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
//                         {/* APPLICATION STARTED HERE */}
//                         <div className="p-6 text-gray-900">
//                             Page Builder
//                         </div>
//                     </div>
//                 </div>
//             </div>
//         </AuthenticatedLayout>
//     );
// }

const PageBuilder = () => {

    const [data, setData] = useState(false);
    const [mainHTML, setMainHTML] = useState('');
    const [mainCSS, setMainCSS] = useState('');

    useEffect(() => {

        async function getData() {
            const url = route('pageBuilderContent');
            try {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                const json = await response.json();
                console.log(json);
                setData(json.data);
                // console.log(json.data.template.index);
                let updated = json.data.template.index.replace('<!--INTERNAL--BD1--EXTERNAL-->', json.data.body.content);
                updated = updated.replace('<!--INTERNAL--BD2--EXTERNAL-->', json.data.body2.content);
                updated = updated.replace('<!--INTERNAL--BD3--EXTERNAL-->', json.data.body3.content);
                updated = updated.replace(/src="/g, `src="storage/templates/${json.data.template.uuid}/`);
                setMainHTML(updated);
                let css = json.data.css.content.replace(/fonts\//g, `storage/templates/${json.data.template.uuid}/fonts/`);
                setMainCSS(css);

            } catch (error) {
                console.error(error.message);
            }
        }

        getData()

        // const button = document.createElement("button");
        // button.textContent = "Click Me";
        // button.style.display = "block";
        // button.style.marginTop = "10px"; // Optional: Adds spacing between paragraph and button

        // document.addEventListener("click", function (event) {
        //     console.log("You clicked somewhere on the page!", event.target);
        //     // event.target.insertAdjacentElement("afterend", button);
        //     // event.target.parentNode.insertBefore(button, event.target);
        //     // event.target.insertAdjacentElement("afterbegin", button);

        //     // Create a container div with flex styling
        //     const container = document.createElement("div");
        //     container.style.display = "flex";
        //     container.style.alignItems = "center";

        //     // Move the paragraph into the container

        //     container.appendChild(event.target.cloneNode(true));
        //     container.appendChild(button);

        //     // Replace the original paragraph with the container
        //     event.target.replaceWith(container);

        // });
    }, [])

    return (
        <div>
            {data &&
                <div>
                    <div dangerouslySetInnerHTML={{ __html: data.template.head }} />
                    <style>
                        {mainCSS}
                    </style>
                    {/* <pre>{mainHTML}</pre> */}
                    <div dangerouslySetInnerHTML={{ __html: mainHTML }} />
                </div>
            }
        </div>
    );
}

export default PageBuilder;
