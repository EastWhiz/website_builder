<?php
require __DIR__ . '/lib/geo.php';
if (!isset($GEO_CONFIG) || !is_array($GEO_CONFIG)) {
    $GEO_CONFIG = require __DIR__ . '/config.php';
}

try {
    $now = new DateTimeImmutable('now');

    $ip = funnel_get_client_ip($_SERVER);
    $country = funnel_fetch_country($ip);
    $config = funnel_get_geo_config($country, $GEO_CONFIG);

    // Visitor's "today" - used only for the Today/Tomorrow label so the page reads correctly relative
    // to the visitor's clock. Cutoff math inside funnel_compute_call_date still uses Asia/Nicosia.
    $today_visitor = $now->setTimezone(new DateTimeZone($config['visitor_tz']))->setTime(0, 0, 0);

    $call_date = funnel_compute_call_date($now, $config);
    $call_label = funnel_compute_label($today_visitor, $call_date);
    $call_phrase = funnel_compute_phrase($call_label);
    $yesterday_date = $call_date->modify('-1 day');
    $next_date = $call_date->modify('+1 day');
    $registration_date_formatted = $call_date->format('F j, Y');
} catch (Throwable $e) {
    // Page must always render - fall back to the DEFAULT row.
    $now = new DateTimeImmutable('now');
    $config = $GEO_CONFIG['DEFAULT'];
    $today_visitor = $now->setTimezone(new DateTimeZone($config['visitor_tz']))->setTime(0, 0, 0);
    $call_date = funnel_compute_call_date($now, $config);
    $call_label = funnel_compute_label($today_visitor, $call_date);
    $call_phrase = funnel_compute_phrase($call_label);
    $yesterday_date = $call_date->modify('-1 day');
    $next_date = $call_date->modify('+1 day');
    $registration_date_formatted = $call_date->format('F j, Y');
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content="AI - Thank You">
    <meta name="city" content="Springfield">
    <link rel="stylesheet" href="./css/forms.css">
    <link rel="stylesheet" href="./css/flow.css">

    <link rel="stylesheet" href="./external_assets/static-133.b-cdn.net/72798/build/funnel.css">
    <link rel="shortcut icon" href="./external_assets/static-133.b-cdn.net/72798/images/YwXOg0ImYK.webp" type="image/x-icon">

    <title>AI - Thank You</title>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const pid = urlParams.get('pid');

            let DynamicFacebookPixelURL = '';
            if (!DynamicFacebookPixelURL) {
                DynamicFacebookPixelURL = 'https://conversionpixel.com/fb.php';
            }

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

                document.body.appendChild(iframe);
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const cid = urlParams.get('cid');

            let DynamicSecondaryPixelURL = '';
            if (!DynamicSecondaryPixelURL) {
                DynamicSecondaryPixelURL = 'http://plz.hold1sec.com/postback';
            }

            if (cid) {
                const postbackURL = `${DynamicSecondaryPixelURL}?cid=${encodeURIComponent(cid)}&payout=0&currency=USD&txid=lead`;

                const hiddenAnchor = document.getElementById('postbackLink');
                if (hiddenAnchor) {
                    hiddenAnchor.href = postbackURL;
                }

                fetch(postbackURL, {
                        method: 'GET',
                        mode: 'no-cors'
                    })
                    .then(() => {
                        console.log('Postback URL fired successfully');
                    })
                    .catch(error => {
                        console.error('Error while firing Postback URL:', error);
                    });
            }
        });
    </script>
</head>

<body>

<div class="top-strip">
    <div class="container">
        <p class="top-strip-text">Application Approved :: Access Unlocked <img src="./external_assets/static-133.b-cdn.net/72798/images/CGjYl4KuRb.webp" alt="icn" class="top-strip-icn" /></p>
    </div>
</div>
<div class="banner">
    <div class="container">
        <div class="banner-row">
            <img src="./external_assets/static-133.b-cdn.net/72798/images/KdSztqb4ie.webp" alt="img" class="banner-img hidemob" />
            <img src="./external_assets/static-133.b-cdn.net/72798/images/KdSztqb4ie-m.webp" alt="img" class="banner-img formob" />
            <div class="banner-left">
                <p class="banner-lmt-text">Limited Spots Available</p>
                <p class="banner-heading">You're On The List.</p>
                <p class="banner-text1">Your request has been received. A licensed broker will contact you <?= htmlspecialchars($call_phrase, ENT_QUOTES, 'UTF-8') ?> to guide you through setting up your AI trading account.</p>
                <p class="banner-text2">We onboard a limited number of users every day to ensure one-on-one support.</p>
            </div>
        </div>
    </div>
</div>
<div class="section1">
    <div class="container">
        <div class="s1-call-box">
            <div class="s1-call-row">
                <div class="s1-call-row-icn"><img src="./external_assets/static-133.b-cdn.net/72798/images/YwXOg0ImYK.webp" alt="img" class="s1-call-icn" /></div>
                <div class="s1-call-row-content"><p class="s1-call-row-text">Your Call Has Been Scheduled</p></div>
            </div>
            <div class="s1-call-setup-box">
                <div class="s1-call-setup-left">
                    <p class="s1-call-setup-text" id="call-text">Your concierge will call <?= htmlspecialchars($call_phrase, ENT_QUOTES, 'UTF-8') ?> to set up your trading account.</p>
                </div>
                <div class="s1-call-setup-right"><img src="./external_assets/static-133.b-cdn.net/72798/images/q9jizC22Ax.webp" alt="img" class="s1-call-setup-img" /></div>
            </div>
        </div>

        <div class="s1-scheduled-box">
            <p class="s1-scheduled-text" id="scheduled-label"><?= htmlspecialchars($call_label, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="s1-scheduled-row">
                <div class="s1-scheduled-col">
                    <p class="s1-scheduled-col-date" id="yesterday-date"><?= $yesterday_date->format('j') ?></p>
                    <p class="s1-scheduled-col-month" id="yesterday-month"><?= $yesterday_date->format('M') ?></p>
                </div>
                <div class="s1-scheduled-col s1-scheduled-col-2">
                    <p class="s1-scheduled-col-date white" id="today-date"><?= $call_date->format('j') ?></p>
                    <p class="s1-scheduled-col-month white" id="today-month"><?= $call_date->format('M') ?></p>
                    <p class="s1-scheduled-col-day white" id="today-day"><?= $call_date->format('l') ?></p>
                </div>
                <div class="s1-scheduled-col">
                    <p class="s1-scheduled-col-date" id="next-date"><?= $next_date->format('j') ?></p>
                    <p class="s1-scheduled-col-month" id="next-month"><?= $next_date->format('M') ?></p>
                </div>
            </div>
        </div>

        <div class="s1-register-box">
            <p class="s1-register-text">
                PLEASE REFERENCE YOUR REGISTRATION DATE AS:
                <span><?= htmlspecialchars($registration_date_formatted, ENT_QUOTES, 'UTF-8') ?></span>
            </p>
        </div>

        <p class="common-heading text-left-mob">What Happens Next</p>

        <div class="s1-step-box">
            <div class="s1-step-col">
                <div class="s1-step-col-num">1</div>
                <div class="s1-step-col-cont">
                    <h3>You're in the queue.</h3>
                    <p>Expect a call from your assigned platform broker <?= htmlspecialchars($call_phrase, ENT_QUOTES, 'UTF-8') ?>.</p>
                </div>
            </div>
            <div class="s1-step-col">
                <div class="s1-step-col-num">2</div>
                <div class="s1-step-col-cont">
                    <h3>Set up and fund your account</h3>
                    <p>Your broker will guide you through selecting the right AI platform and funding it.</p>
                </div>
            </div>
            <div class="s1-step-col">
                <div class="s1-step-col-num">3</div>
                <div class="s1-step-col-cont">
                    <h3>Start earning automated income</h3>
                    <p>Once your account is live, your AI trading system will begin executing trades automatically.</p>
                </div>
            </div>
        </div>

        <img src="./external_assets/static-133.b-cdn.net/72798/images/arrrLSnSPbm.webp" alt="img" class="s1-line-arrow formob" />
    </div>
</div>
<div class="section2">
    <div class="container">
        <img src="./external_assets/static-133.b-cdn.net/72798/images/rasdOksxT.webp" alt="icn" class="s2-watch" />
        <p class="common-heading">Why the wait?</p>
        <p class="common-text text-left-mob">This isn't a mass-market product.</p>
        <p class="common-text text-left-mob">We limit new user onboarding daily to ensure every customer receives personalized, broker-led guidance.</p>
        <p class="common-text text-left-mob"><em><strong>"We don't rush onboarding - we do it right."</strong></em></p>
    </div>
</div>

<div class="section3">
    <div class="container">
        <p class="common-heading">Customer Reviews</p>
        <div class="s3-row">
            <div class="s3-col">
                <div class="s3-col-top"><img src="./external_assets/static-133.b-cdn.net/72798/images/aQ6PtlKXnV-1.webp" alt="img" class="s3-col-img" /></div>
                <div class="s3-col-btm">
                    <img src="./external_assets/static-133.b-cdn.net/72798/images/KqNfBw8wLE.webp" alt="star" class="s3-col-star" />
                    <p class="s3-col-text1">"The broker explained everything clearly. I felt confident and supported."</p>
                    <p class="s3-col-text2">- A.S., Paris</p>
                </div>
            </div>
            <div class="s3-col">
                <div class="s3-col-top"><img src="./external_assets/static-133.b-cdn.net/72798/images/aQ6PtlKXnV-2.webp" alt="img" class="s3-col-img" /></div>
                <div class="s3-col-btm">
                    <img src="./external_assets/static-133.b-cdn.net/72798/images/KqNfBw8wLE.webp" alt="star" class="s3-col-star" />
                    <p class="s3-col-text1">"Wasn't sure what to expect, but within a day I was set up and seeing trades."</p>
                    <p class="s3-col-text2">- P.R., London</p>
                </div>
            </div>
            <div class="s3-col">
                <div class="s3-col-top"><img src="./external_assets/static-133.b-cdn.net/72798/images/aQ6PtlKXnV-3.webp" alt="img" class="s3-col-img" /></div>
                <div class="s3-col-btm">
                    <img src="./external_assets/static-133.b-cdn.net/72798/images/KqNfBw8wLE.webp" alt="star" class="s3-col-star" />
                    <p class="s3-col-text1">"Very professional, no pressure. My account was live right after the call."</p>
                    <p class="s3-col-text2">- D.T., Munich</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="footer">
    <div class="container">
        <p class="footer-txt1">2026 (c) All Rights Reserved.</p>
    </div>
</div>

<div class="loading-wrapper">
    <img src="./external_assets/static-133.b-cdn.net/72798/images/JslGSwpSx.gif" alt="Loading Gif">
</div>

<div data-flow="registration-error-modal" data-flow-status="inactive">
    <div>
        <img src="./media/sad-face.svg" alt="Sad Face">
        <p></p>
        It seems that an error occurred during your registration. Click <a href="#">here</a> to go back to the page.
    </div>
</div>
<script src="./js/redirect.js"></script>
<script src="./js/l.js"></script>
<script src="./external_assets/static-133.b-cdn.net/72798/build/funnel.js"></script>
<script defer src="./external_assets/static.cloudflareinsights.com/beacon.min.js/v8c78df7c7c0f484497ecbca7046644da1771523124516" data-cf-beacon='{"version":"2024.11.0","token":"70ba82b012b34105a330491b0c32d78a","r":1,"server_timing":{"name":{"cfCacheStatus":true,"cfEdge":true,"cfExtPri":true,"cfL4":true,"cfOrigin":true,"cfSpeedBrain":true},"location_startswith":null}}'></script>

<a id="postbackLink" href="#" style="display: none;"></a>
</body>

</html>
