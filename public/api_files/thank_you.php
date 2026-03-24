<?php
// Load config (starts session)
include_once 'config.php';

$redirectEnabled = false;
$redirectDelay = 0;
$redirectUrl = '';

if (isset($_GET['redirect_to_broker']) && strtolower(trim((string) $_GET['redirect_to_broker'])) === 'yes'
    && !empty($_GET['broker_url']) && filter_var($_GET['broker_url'], FILTER_VALIDATE_URL)) {
    $redirectEnabled = true;
    $redirectDelay = isset($_GET['broker_redirect_delay']) ? (int) $_GET['broker_redirect_delay'] : 0;
    $redirectUrl = (string) $_GET['broker_url'];
} elseif (isset($_SESSION['broker_redirect']) && is_array($_SESSION['broker_redirect'])) {
    $redirectConfig = $_SESSION['broker_redirect'];
    $redirectEnabled = !empty($redirectConfig['enabled']);
    $redirectDelay = isset($redirectConfig['delay']) ? (int) $redirectConfig['delay'] : 0;
    $redirectUrl = isset($redirectConfig['url']) ? (string) $redirectConfig['url'] : '';
    unset($_SESSION['broker_redirect']);
}

if ($redirectDelay < 0) {
    $redirectDelay = 0;
}
if (!$redirectEnabled || !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
    $redirectEnabled = false;
    $redirectUrl = '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Thank You - Schedule Your Call</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet" />

    <script>
        // Optional broker redirect after X seconds
        document.addEventListener('DOMContentLoaded', function () {
            const enabled = <?php echo $redirectEnabled ? 'true' : 'false'; ?>;
            const delaySeconds = <?php echo (int) $redirectDelay; ?>;
            const brokerUrl = <?php echo json_encode($redirectUrl, JSON_UNESCAPED_SLASHES); ?>;

            if (enabled && brokerUrl && typeof brokerUrl === 'string') {
                const ms = (delaySeconds > 0 ? delaySeconds : 0) * 1000;
                setTimeout(function () {
                    window.location.href = brokerUrl;
                }, ms);
            }
        });
    </script>

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
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --purple-dark:   #3B27A8;
            --purple-mid:    #4B35C8;
            --purple-light:  #6C55E0;
            --purple-pale:   #EEEDFE;
            --purple-muted:  #CECBF6;
            --green-pale:    #EAF3DE;
            --green-text:    #3B6D11;
            --teal-pale:     #E1F5EE;
            --teal-text:     #0F6E56;
            --text-primary:  #12112A;
            --text-secondary:#5C5B72;
            --text-muted:    #A0A0B5;
            --border:        rgba(0,0,0,0.07);
            --bg-page:       #F4F4F9;
            --bg-card:       #FFFFFF;
            --radius-lg:     16px;
            --radius-md:     10px;
            --radius-sm:     6px;
            --shadow-card:   0 1px 3px rgba(60,50,160,0.06), 0 4px 16px rgba(60,50,160,0.06);
        }

        html { font-size: 16px; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #ffffff;
            color: var(--text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .topbar {
            width: 100%;
            background: #fff;
            padding: 0 clamp(16px, 5vw, 40px);
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            margin-top: 30px;
        }

        .logo img {
            height: 36px;
            width: auto;
            max-width: 160px;
            object-fit: contain;
        }

        .hero {
            width: 90%;
            max-width: 560px;
            margin: clamp(24px, 5vw, 48px) auto;
            background: #3B27A8;
            padding: clamp(48px, 4vw, 96px) clamp(16px, 5vw, 20px);
            text-align: center;
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }

        .hero-inner {
            max-width: 600px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 100px;
            padding: 5px 14px 5px 10px;
            font-size: clamp(11px, 2vw, 13px);
            color: rgba(255,255,255,0.9);
            margin-bottom: clamp(20px, 4vw, 28px);
            animation: fadeUp 0.5s ease both;
        }

        .badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #7EFFC5;
            box-shadow: 0 0 0 3px rgba(126,255,197,0.25);
            animation: pulse 2s ease infinite;
            flex-shrink: 0;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 3px rgba(126,255,197,0.25); }
            50%       { box-shadow: 0 0 0 6px rgba(126,255,197,0.1); }
        }

        .check-ring {
            width: clamp(64px, 10vw, 80px);
            height: clamp(64px, 10vw, 80px);
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto clamp(16px, 3vw, 24px);
            animation: fadeUp 0.5s 0.1s ease both;
        }

        .check-ring svg {
            width: clamp(30px, 5vw, 40px);
            height: clamp(30px, 5vw, 40px);
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(24px, 5vw, 38px);
            font-weight: 400;
            color: #fff;
            margin-bottom: clamp(10px, 2vw, 14px);
            animation: fadeUp 0.5s 0.15s ease both;
            line-height: 1.2;
        }

        .hero p {
            font-size: clamp(14px, 2.5vw, 16px);
            color: rgba(255,255,255,0.78);
            max-width: 420px;
            margin: 0 auto;
            line-height: 1.65;
            animation: fadeUp 0.5s 0.2s ease both;
        }

        .hero-image {
            display: block;
            max-width: 100%;
            width: clamp(120px, 25vw, 300px);
            height: auto;
            margin: 0 auto clamp(20px, 4vw, 28px);
            border-radius: var(--radius-md);
            object-fit: cover;
            animation: fadeUp 0.5s 0.12s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 480px) {
            .topbar { height: 52px; }
            .logo img { height: 28px; }
            .hero { width: calc(100% - 32px); margin-left: 16px; margin-right: 16px; padding: 40px 16px; border-radius: var(--radius-md); }
            .check-ring { width: 60px; height: 60px; }
        }

        @media (min-width: 481px) and (max-width: 768px) {
            .hero { width: 85%; border-radius: var(--radius-lg); }
        }

        @media (max-height: 500px) and (orientation: landscape) {
            .hero { padding: 28px clamp(16px, 5vw, 40px); margin-top: 16px; margin-bottom: 16px; border-radius: var(--radius-md); }
            .check-ring { width: 52px; height: 52px; }
        }
    </style>
</head>

<body>

    <header class="topbar">
        <a href="#" class="logo">
            <img src="PROJECTURL/wealth.jpg" alt="Wealth Logo" onerror="this.style.display='none'" />
        </a>
    </header>

    <section class="hero">
        <div class="hero-inner">
            <div class="check-ring">
                <svg viewBox="0 0 40 40" fill="none">
                    <path d="M10 20L16 26L30 13" stroke="white" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="hero-badge">
                <span class="badge-dot"></span>
                Submission received
            </div>
            <img src="PROJECTURL/profiles.jpg" alt="Schedule with Facet" class="hero-image" onerror="this.style.display='none'" />
            <h1>Thank you for your interest!</h1>
            <p>A dedicated wealth advisor will reach out within 1 business day to discuss your financial goals.</p>
        </div>
    </section>

    <a id="postbackLink" href="#" style="display: none;"></a>

</body>

</html>
