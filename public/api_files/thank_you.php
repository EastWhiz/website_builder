<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Thank You - Schedule Your Call</title>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const pid = urlParams.get('pid'); // Extract 'pid'

            let DynamicFacebookPixelURL = '';

            if (pid) {
                const iframe = document.createElement('iframe');
                iframe.src = `${DynamicFacebookPixelURL}?pid=${encodeURIComponent(pid)}`;
                iframe.rel = "noreferrer";
                iframe.crossOrigin = "anonymous";
                iframe.scrolling = "no";
                iframe.frameBorder = "0";
                iframe.width = "1";
                iframe.height = "1";
                iframe.style.display = "none";

                document.body.appendChild(iframe); // Inject iframe into the DOM
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const cid = urlParams.get('cid'); // Extract 'cid'

            let DynamicSecondaryPixelURL = '';

            if (cid) {
                // Generate the postback URL
                const postbackURL = `${DynamicSecondaryPixelURL}?cid=${encodeURIComponent(cid)}&payout=0&currency=USD&txid=lead`;

                // Set the hidden anchor tag href
                const hiddenAnchor = document.getElementById('postbackLink');
                hiddenAnchor.href = postbackURL; // Set the link

                fetch(postbackURL, {
                        method: 'GET',
                        mode: 'no-cors'
                    })
                    .then(() => {
                        console.log('Postback URL fired successfully');
                    })
                    .catch(error => {
                        // Log error details
                        console.error('Error while firing Postback URL:', error);
                    });
            }
        });
    </script>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #f3f4f6 0%, #ffffff 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            text-align: center;
        }

        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo img {
            max-height: 40px;
            width: auto;
        }

        .main-content {
            text-align: center;
            margin-bottom: 3rem;
        }

        .schedule-image {
            max-width: 100%;
            height: auto;
            margin: 0 auto 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            display: block;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        p {
            font-size: 1.25rem;
            color: #4b5563;
            margin-bottom: 2rem;
        }

        .image-container {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header with Logo -->
        <div class="header">
            <div class="logo">
                <img src="PROJECTURL/wealth.jpg" alt="NWealth Logo">
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="image-container">
                <img src="PROJECTURL/profiles.jpg" alt="Schedule with Facet"
                    class="schedule-image">
            </div>
            <h1>Thank You for Your Interest!</h1>
            <p>We will be in touch shortly to discuss your financial goals.</p>
        </div>

        <!-- Hidden anchor tag for postback URL -->
        <a id="postbackLink" href="#" style="display: none;"></a>
    </div>
</body>

</html>
