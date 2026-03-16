<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Thank You — Preview</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet" />

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
      padding: clamp(48px, 4vw, 96px) clamp(16px, 5vw, 40px);
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
      font-weight: 800;
      letter-spacing: 2px;
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
  {{-- Preview: no redirect or pixel scripts --}}
</head>
<body>

  <header class="topbar">
    @if(!empty($logoUrl))
      <a href="#" class="logo">
        <img src="{{ $logoUrl }}" alt="Logo" />
      </a>
    @endif
  </header>

  <section class="hero" style="background: {{ $heroBackgroundColor }};">
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

      @if(!empty($profileImageUrl))
        <img src="{{ $profileImageUrl }}" alt="Profile" class="hero-image" />
      @endif

      <h1>{{ $titleText }}</h1>
      <p>{{ $description }}</p>
    </div>
  </section>

</body>
</html>
