# Exported Lead Form Validation — Implementation Plan

Status: BUILT (builder side), 2026-10-21. CRM-side enforcement is still to do. Remaining follow-ups: native-speaker review of the
non-English messages; `npm run build` to pick up the editor preview changes.

### Build notes (what changed vs the plan)
- Files: `public/export_assets/{lead-validation.js, lead-form-gate.js, lead-validation-data.json, lead-validation-messages.json,
  intl-tel-input/}`, `public/api_files/lead_validation.php` + guard in `save_lead_handler.php` and all 18 provider scripts,
  `AngleTemplateController` (export wiring, old handlers removed), both editor previews (no US fallback).
- Tests: `node tests/js/lead-validation.test.cjs` (310 checks), `tests/Unit/LeadValidationPhpTest.php` and
  `LeadValidationGuardCoverageTest.php` (shared vectors in `tests/fixtures/`). After editing the message table in
  `lead-validation.js`, run `node scripts/generate-lead-validation-messages.cjs`.
- Verified in a real browser against a real export (all scenarios in section 8) and by POSTing bad data straight to a provider script.
- Finding 1: in intl-tel-input 19.5.2 the instance method `iti.isValidNumber()` is a lenient length check (it accepted
  `+1 111 555 2671`). The validator calls `intlTelInputUtils.isValidNumber(number, iso2)` (strict libphonenumber) instead.
- Finding 2: the library's `defaultToFirstCountry` defaults to true and would silently preselect the first country in its list.
  It is set to false in the export and in the previews.
- Language: the editor does not store the form language, so the page detects it from the field placeholders (falls back to English).
- Heads-up: a visitor with no IP-detected country must pick one (or type `+code`); local digits alone are rejected on purpose.
- Finding 3 (found by testing an OTP page): after OTP verification the page calls `form.requestSubmit()`, which fires a normal
  submit event. The first version of the gate swallowed it, so verified leads were never posted, and closing the OTP modal left the
  form stuck. Fixed: the gate marks the form validated before the OTP hand-off, clears the busy state afterwards, and any edit to
  name/email/phone after validation or OTP forces validation (and OTP) again. Regression test in `LeadValidationGuardCoverageTest`.
- Tested in a real browser on real exports: Trackbox (elps), GetLinked (koi, self-hosted, Italian), LeadGreed (electra, German),
  Trackbox (magicads, typo soft-block), LeadGreed + OTP (riceleads), and a UK page with OTP. Server guard checked through
  `backend.php` for the Trackbox, GetLinked and LeadGreed platform files. Editor previews (angle 144, landing page 1287) checked in the
  Builder after `npm run build`: phone box has no forced country and keeps its own label. IP lookup success/garbage/failure/slow
  paths and "visitor choice wins" checked on an export. Not tested: iRev (nauta/irev) - no page exists in the database.
- Not changed: OTP endpoints (`otp_generate.php` etc.) are still callable directly; worth rate limiting separately.
Scope: the exported landing-page form (email, phone, name), the editor previews that mirror it, and a
server-side copy in the exported PHP. CRM-side validation is a separate follow-up (section 11).

## 1. Problem

Invalid emails (spaces, `&`, `^`, typo domains like `gmal.com`, `gml.coom`) and unvalidated or missing
phone numbers reach the CRM. Some are rejected later and land in "failed leads"; the client's revenue
reporting was already skewed by the thank-you pixel, and bad input makes it worse.

## 2. Current behaviour (verified in code)

Email
- Field is `type="email"` + `required` only (`PreviewAngle.jsx` ~1677). No export JS check.
- Browser validation is intentionally loose: accepts `a@b`; allows `& ^ ! # $ ' * / = ? { | } ~`.
- Forms **without** a phone box (`AngleTemplateController.php` ~2983) get no validation beyond that.

Phone (export JS, `AngleTemplateController.php`)
- `~2159`: empty phone => `input.form.submit()` ("allow submit anyway (no phone)"). **Fallback to remove.**
- `~2257-2262`: country falls back to `"us"` when IP lookup fails/returns nothing.
- The submit handler is only attached **after** the `ipapi.co` fetch finishes (`initTelInputs` runs inside
  its `.then/.catch`). A visitor who submits before it returns posts raw `temp_phone` with **no** `phone`
  hidden field and no validation.
- Hidden fields are appended before the number is validated (duplicates on retry).
- `utils.js` is loaded from jsDelivr CDN (`~2029`, and again as `utilsScript`); if blocked, validation
  cannot work.
- Editor previews (`PreviewAngle.jsx`, `PreviewAngleTemplate.jsx`) have the same US fallback.

Server
- Every provider script in `public/api_files/*.php` calls `maybeBlockFakeLeadAndExit()` then
  `maybeBlockDuplicateLeadAndExit()` (see `save_lead_handler.php`). No format validation exists there;
  `normalizeLeadEmail()` only trims and lowercases.

## 3. Decisions (defaults accepted)

| # | Decision |
|---|---|
| 1 | Strict email local-part characters. Allowed: letters, digits, `. _ + - %`. |
| 2 | Typo domains: **soft block** — show "Did you mean …?", stop the submit once; same value on the next submit goes through. Non-existent TLD typos (`.coom .con .cmo .ocm .comm .vom`) are **hard** errors. |
| 3 | Block disposable domains and reserved/test domains (curated list, kept in a data file). |
| 4 | Phone types allowed: mobile, fixed-line, fixed-line-or-mobile, and VoIP. Block premium-rate, shared-cost, toll-free, personal, pager, UAN, voicemail. Valid-but-unknown type is allowed. |
| 5 | Fake-pattern phone rule (0000000, 1234567890): **not included** now. |
| 6 | Ship intl-tel-input assets with the export (no CDN dependency for validation). **Done:** 19.5.2 bundled in `public/export_assets/intl-tel-input/` (integrity verified against npm). |
| 7 | Server-side copy included in this phase (exported PHP). CRM-side enforcement is a **later** phase, after the builder side is finished. |
| 10 | When the exported PHP rejects input, it **records a failed lead in the CRM** (reason `invalid_input`) as well as redirecting the visitor with a message. Nothing is sent to a broker. |
| 11 | Submit before the validator is ready: **hold and queue** the submit (loader shown) until ready, with a ~5 s timeout and a retry message. The button is not disabled up front. |
| 12 | SweetAlert2 is **not** downloaded now. Validation messages are inline and never depend on `Swal`; existing `Swal.fire` calls get a guard/fallback. |
| 8 | Light name check: 2–100 chars, Unicode letters/space/hyphen/apostrophe/period, no digits, no `@`, no `<>`/URLs. |
| 9 | Messages in the 9 form languages: English, German, Spanish, Japanese, French, Italian, Korean, Polish, Portuguese. |

## 4. Validation rules

### 4.1 Normalise (before any check)
- `String.normalize('NFKC')` (turns full-width Japanese/Korean input into ASCII digits and `@`).
- Strip zero-width and non-breaking spaces and control characters.
- Trim ends. Email: lowercase. Write the cleaned value back into the field on blur.

### 4.2 Email
1. Not empty. Exactly one `@`. No whitespace anywhere (after trim).
2. Total length <= 254; local 1–64; domain <= 253.
3. Local part: `[a-z0-9._%+-]+` only; no leading/trailing dot; no `..`.
4. Domain: labels 1–63 chars of `[a-z0-9-]`, no leading/trailing hyphen, no empty labels, at least one dot.
   TLD: letters only (or `xn--` punycode), 2–24 chars. No IP literals. No non-ASCII after NFKC.
5. Blocked: reserved/test domains (`example.com/org/net`, `test.com`, `*.test`, `*.invalid`,
   `*.localhost`, `*.example`) and disposable domains from `data/blocked-email-domains.json`.
   (`dev.com` is a real domain and is **not** blocked.)
6. Hard TLD typos (see decision 2) => error.
7. Typo suggestion (soft block): compare the domain to common providers with a small edit distance
   (`gmal.com`, `gmial.com`, `gmail.co`, `yaho.com`, `hotmial.com`, `outlok.com`, `icloud.con` …) and
   offer the correction as a clickable button.

### 4.3 Phone
1. Required; empty is an error (remove the "submit anyway" branch).
2. Country required: use the IP country only if it is a valid 2-letter code; otherwise no default.
   The user picks a country or types `+<code>`. If no country resolves => "Select your country".
3. Allowed input characters: digits, `+`, space, `-`, `(`, `)`, `.`; anything else is stripped on input.
   Max 20 characters.
4. `iti.isValidNumber()` must be true for the selected country. When false, map
   `getValidationError()` to a message: invalid country code, too short, too long, invalid number.
5. Number type check per decision 4 via `getNumberType()`.
6. Submit value: E.164 (`iti.getNumber()`), plus `country` (ISO2) and `area_code` (`+<dial>`).
7. Wait for the utils script (`iti.promise` / `utils` loaded) before validating; fail closed with a
   retry message if utils cannot load.

### 4.4 Name
First and last name per decision 8, after normalisation.

## 5. Behaviour / UX
- Validate on blur; after the first error, re-validate on every input event.
- Inline message under the field, `aria-invalid="true"`, `aria-describedby` to the message, clear style.
- Email field attributes: `autocomplete="email"`, `inputmode="email"`, `autocapitalize="off"`,
  `spellcheck="false"`, `maxlength="254"`. Phone: `autocomplete="tel"`, `inputmode="tel"`.
- On submit: run all checks **first**; if any fail, stop, focus the first invalid field, keep the button
  enabled, show **no** loader and add **no** hidden fields. Only after success: loader, hidden fields
  (via `upsertHiddenInput`), then OTP or `form.submit()`.
- Attach validation immediately on DOM ready (not after the IP fetch). Apply the IP country later with
  `setCountry` only if the visitor has not chosen one.
- Same validation for forms with and without a phone box, and before the OTP step.
- Language: write `data-form-language` on the `<form>` at export time; messages come from a table.
  Default English.

## 6. Where the code goes

One shared validator, so the three places cannot drift:
- New file `public/export_assets/lead-validation.js` (single source: rules, messages for 9 languages,
  email typo list, `blockedDomains` loaded from the data file). Embedded inline into the exported page
  by `AngleTemplateController` (replaces the ad-hoc checks at ~2122-2250 and ~2983-3015) and loaded in
  the editor previews (`PreviewAngle.jsx`, `PreviewAngleTemplate.jsx`) so preview behaves the same.
  (Verify how the preview injects scripts before wiring it.)
- New file `public/api_files/lead_validation.php` (PHP port of the same rules). Add it to
  `$exportFixedFiles` in `AngleTemplateController.php`. Add `maybeBlockInvalidLeadAndExit()` and call it
  in each provider script next to the existing two guards (mechanical edit across ~19 files).
  On failure: redirect back with `api_error=<message>` (existing helper), log it, and do **not** forward
  to a broker or the CRM.
- Bundle intl-tel-input 19.5.2 (JS, utils.js, CSS, flag images) under `public/export_assets/` and add to
  the export zip; update the `<script>`/`<link>` tags and `utilsScript`. Keep the CDN as a fallback only
  if the local file is missing.
- Shared data: `data/blocked-email-domains.json`, one list read by both JS and PHP.
- Keep untouched: honeypot, tracking-param sync, OTP flow, Turnstile injection, duplicate check.

## 7. Phases

0. DONE: intl-tel-input 19.5.2 downloaded, integrity-verified and stored in `public/export_assets/`.
1. Shared rule definitions + test vectors (JSON): valid/invalid emails, phones per country, names.
2. JS validator + single submit gate + embed into export + remove the phone fallbacks + timeouts.
3. Export packaging: add local intl-tel-input to the zip, switch tags to local paths, fail loudly if missing.
4. Editor preview alignment (remove US fallback, use shared validator).
5. Server-side PHP validator + provider script guards + failed-lead recording + export file list.
6. Language messages, QA in the browser, docs.
(CRM-side enforcement: later, with the other CRM work.)

## 8. Testing
- **Parity vectors:** `tests/fixtures/lead-validation-vectors.json` used by (a) a Pest test for the PHP
  validator and (b) a small Node script for the JS validator, so both give identical results.
- Cases: spaces (leading/trailing/inside), `& ^` and other banned characters, double dots, missing TLD,
  `a@b`, 255-char email, full-width `ｕｓｅｒ＠ｇｍａｉｌ．ｃｏｍ`, zero-width space, typo domains, hard TLD
  typos, disposable and reserved domains, uppercase input.
- Phones: valid and invalid numbers for US, GB, DE, FR, JP, KR, PL, PT, ES, IT, PK, AE, AU; too short/long;
  letters; empty; no country selected; IP lookup failing; utils blocked; premium/toll-free numbers.
- Browser pass in the built-in browser against a real export: submit before IP returns, retry after an
  error (no duplicate hidden fields), OTP path, form without phone box, each language.
- Run `tests/Unit` and confirm only the 7 known failures remain (Turnstile/CRM export tests; they fail
  on the unmodified code too).

## 9. Acceptance criteria
- No form can post with an empty/invalid phone or an email that breaks the rules above.
- A bypass POST straight to a provider script is rejected by the PHP validator.
- Failed submit shows a clear, language-correct message, focuses the field, adds no hidden fields.
- Exported pages work with the CDN blocked.
- Valid, normal leads (including `name+tag@gmail.com`, valid international numbers) are never blocked.

## 10. Risks
- Strict email rules will reject a few real addresses (e.g. `o'brien@x.com`). Accepted by decision 1.
- intl-tel-input 19.5.2 validity data differs by country and version; test with real numbers per market.
- Blocked/disposable lists need occasional upkeep.
- Pages exported before this change keep the old behaviour; clients must re-export.
- ~19 provider scripts change in one mechanical edit; verify each with `php -l`.

## 11. Solutions for the three gaps

### 11.1 CDN dependency
- Bundled locally (done): `public/export_assets/intl-tel-input/{js,css,img}`, license and `VERSION.txt`.
- Export: add the folder to the zip with the existing `addDirectoryToZip()` helper as
  `assets/intl-tel-input/`; `index.php` references it with relative paths. Load `utils.js` once
  (script tag, global `intlTelInputUtils`); drop the duplicate `utilsScript` CDN option.
- If the local source folder is missing at export time, the export **fails with a clear error**; it never
  silently falls back to a CDN or ships a page without validation.
- External calls that remain (`ipapi.co`, `ipinfo.io`): wrap in `AbortController` with ~3 s timeout;
  failure never blocks submit or validation. SweetAlert2 stays on the CDN for now; add a guard so a
  missing `Swal` cannot throw inside the submit path (fall back to the inline message).
- Existing exports are unchanged until re-exported.

### 11.2 Forms must never skip validation
Causes today: handler attached only after the IP lookup; second handler for forms without a phone box
has no validation; several independent submit handlers; "no phone, submit anyway" branch.
Design:
1. One capture-phase `submit` listener on `document`, registered as soon as the script runs, covering
   every lead form (with or without a phone box). The second handler is removed.
2. Phone field initialises immediately; the IP country is applied later (`setCountry`) only if the visitor
   has not chosen one.
3. If the validator is not ready (library still loading), the gate holds the submit, shows the loader,
   and continues when ready; after ~5 s it shows a retry message. It never lets an unchecked form through.
4. A single `submitValidated(form)` is the only caller of `form.submit()`. OTP and Turnstile steps run
   after validation passes.
5. Listen to the `submit` event (Enter, mobile "Go", autofill), not button clicks.
6. Add `novalidate` from JS only once initialised, so native browser checks still work if our script
   fails to load; the server is the safety net.
7. Double-submit guard: button disabled after a pass until the response/redirect; re-enabled on failure.

### 11.3 Server-side checks (exported PHP, this phase)
- New `public/api_files/lead_validation.php` (added to `$exportFixedFiles`), included by
  `save_lead_handler.php`. New `maybeBlockInvalidLeadAndExit()` is called in every provider script in the
  order **fake lead -> invalid input -> duplicate**. A Pest test fails if any provider script that calls
  the fake-lead guard lacks the invalid-input guard.
- Rules: email and name in full (same as the JS); phone must be present, E.164 (`+` and 7-15 digits) and
  its dial code must agree with the submitted `country`/`area_code`. Full per-country validity needs
  libphonenumber (CRM phase).
- Must run without PHP's `intl` extension (use `Normalizer` only if available).
- On failure: record a failed lead in the CRM via the existing `saveLead()` path with
  `failure_reason = invalid_input` (same pattern as the duplicate check), do **not** call the broker,
  redirect back with `api_error` in the form language (via a posted `form_language` field), preserving
  `cid`, `pid`, `so`. Log without PII.
- CRM `save-lead` enforcement (libphonenumber, strict email) comes after the builder side, with the other
  CRM work. Until then `save-lead` remains open to direct posts.

## 12. Follow-ups (not in this plan)
- CRM `save-lead`: replace Laravel's loose `email` rule with the same strict rules; tighten `contact`.
- Optional: IANA TLD list check, server-side MX lookup, fake-phone pattern rule.
