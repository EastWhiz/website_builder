# Cloudflare Turnstile Development Plan

## Objective

Add Cloudflare Turnstile protection to Builder-generated landing-page forms, including automatic widget/domain registration through Cloudflare API, while keeping all existing exported forms backward compatible.

The implementation must protect lead submission before any broker/API call, support exported PHP landing pages, and work with the existing honeypot, duplicate lead, OTP, AWeber, and CRM save-lead flows.

## Core Decisions

1. Turnstile is for exported landing-page forms, not only Builder login/register screens.
2. Client-side widget rendering is not enough; every protected submission must be verified server-side.
3. Turnstile must be optional per form.
4. Existing pages/forms must continue working when Turnstile is disabled.
5. Secret keys must never be exposed in exported HTML or JavaScript.
6. Failed Turnstile submissions must not be sent to broker/API.
7. Cloudflare widget/domain registration must be automated using Cloudflare API if the client provides account credentials.
8. Turnstile is registered against hostnames/domains, not physical server folders or root directories.

## Client Requirement From Screenshot

The client wants to avoid manual Cloudflare dashboard work for each exported landing page/site. Their expected flow is:

- They provide Cloudflare API access details once.
- Builder automatically creates Turnstile widgets.
- Builder automatically sends/registers the sites/domains used by exported pages.
- Builder uses the returned keys during export.
- The client does not need to manually create/update every Turnstile site in Cloudflare.

This is feasible through Cloudflare Turnstile widget management API.

Important clarification:
- If the exported files are uploaded to the root of a directory, that does not automatically matter to Turnstile.
- Turnstile validates by hostname/domain, such as `example.com` or `sub.example.com`.
- Paths like `/landing/page1/` or `/campaigns/site-a/` are not the main registration unit.
- Builder must know the final hostname/domain where the exported page will run.

## Required Client Inputs

- Cloudflare account access.
- Cloudflare Account ID.
- Cloudflare API token/access key with Turnstile widget read/write permissions.
- Domain/subdomain list used by exported landing pages.
- Target hostname/domain at export time, if Builder cannot detect it automatically.
- Confirmation of widget strategy:
  - shared widget for many domains, or
  - widget per hostname/domain.
- Confirmation of failed verification behavior:
  - show validation error, or
  - block API submission and continue normal thank-you UX.
- Staging domain for end-to-end testing.

## Recommended Architecture

Use organization-level Cloudflare settings first, with optional user-level override later if required.

Reason:
- The Builder already has organization/team ownership concepts.
- Cloudflare credentials are usually client/company-owned.
- A shared organization setting avoids duplicating API tokens per user.

Recommended default:
- Store Cloudflare API credentials at organization/admin settings level.
- Store Turnstile widget credentials per organization/domain or per landing-page form metadata.
- Store only the required secret in exported PHP config.
- Make automatic Cloudflare provisioning the default implementation path.

## Hostname And Directory Rule

Turnstile widgets are configured for hostnames/domains.

Examples:
- `example.com`
- `www.example.com`
- `lp.example.com`

These should be registered in Cloudflare.

Examples that do not need separate Cloudflare widget registration by themselves:
- `example.com/`
- `example.com/landing-page/`
- `example.com/campaigns/page-a/`

Those are paths/directories under the same hostname.

Development requirement:
- Builder export must either ask for the target hostname/domain or receive it from an existing domain/deployment setting.
- If only a server directory path is provided, Builder must still ask for the hostname.
- If the hostname is missing, Turnstile export should be blocked or exported with Turnstile disabled and a clear warning.

## Data Model Plan

### Tables/Fields

Add Turnstile settings storage.

Preferred option:
- Create `organization_turnstile_settings` table.

Fields:
- `id`
- `organization_id`
- `enabled`
- `auto_provision_enabled`
- `cloudflare_account_id`
- `cloudflare_api_token_encrypted`
- `default_widget_mode`
- `widget_scope`
- `created_at`
- `updated_at`

Suggested values:
- `default_widget_mode`: `managed`
- `widget_scope`: `shared`, `per_hostname`

Add widget/domain mapping table.

Create `turnstile_widgets` table.

Fields:
- `id`
- `organization_id`
- `hostname`
- `cloudflare_widget_id` or `cloudflare_sitekey`
- `site_key`
- `secret_key_encrypted`
- `mode`
- `domains_json`
- `last_synced_at`
- `created_at`
- `updated_at`

Add form-level flag.

For generated landing page form metadata, persist:
- `use_turnstile`
- `turnstile_widget_scope`
- `turnstile_hostname`
- `turnstile_site_key`

Implementation location depends on current form storage. If forms are stored inside `full_html` only, inject hidden metadata during HTML generation and parse it when editing.

Hidden input examples:

```html
<input type="hidden" name="use_turnstile" value="yes">
<input type="hidden" name="turnstile_site_key" value="...">
```

Do not add `turnstile_secret_key` to HTML.

## Builder UI Plan

### Admin/Organization Settings

Add a Turnstile settings panel.

Controls:
- Enable Turnstile globally.
- Enable automatic Cloudflare widget provisioning.
- Cloudflare Account ID.
- Cloudflare API Token.
- Default widget mode.
- Widget scope:
  - shared widget
  - per hostname
- Test Cloudflare connection button.

Validation:
- Account ID is required if auto-provision is enabled.
- API token is required if auto-provision is enabled.
- API token must be encrypted at rest.
- Do not echo token value back to frontend after save.

### Form Builder Controls

Add form-level option:
- Protect with Turnstile.

Optional controls:
- Hostname/domain for exported page.
- Widget scope override.

Default:
- OFF for legacy forms.
- Product decision needed for new forms: recommended ON only after settings are configured.

## Cloudflare API Automation Plan

### Service Class

Create a service:

```text
app/Services/CloudflareTurnstileService.php
```

Responsibilities:
- Create Turnstile widget.
- List/get existing widgets.
- Update widget domains.
- Store returned site key and secret key.
- Handle API failures with clear messages.
- Enforce hostname-based registration instead of directory/path-based registration.
- Reuse existing widgets when possible to avoid unnecessary Cloudflare objects.

Cloudflare endpoints:
- `POST /accounts/{account_id}/challenges/widgets`
- `GET /accounts/{account_id}/challenges/widgets`
- `PUT /accounts/{account_id}/challenges/widgets/{sitekey}`
- `DELETE /accounts/{account_id}/challenges/widgets/{sitekey}`

### Provisioning Trigger

Recommended trigger:
1. On export, resolve target hostname/domain.
2. If form uses Turnstile:
   - find existing widget for hostname/scope,
   - create widget if missing,
   - update widget if hostname is not registered,
   - store latest site key/secret.

Alternative later:
- Provision at form save or domain assignment time.

Export-time provisioning is recommended first because the final hostname matters most at export.

### Hostname Resolution Rules

Priority order:
1. Use a saved landing-page/custom-domain setting if the Builder already has one.
2. Use a domain entered in the export modal.
3. Use an organization-level default domain if the exported pages always deploy under the same hostname.
4. If none exists, require the user to enter the hostname before enabling Turnstile export.

Normalize the hostname before sending it to Cloudflare:
- remove `http://` or `https://`
- remove path/query/fragment
- lowercase hostname
- trim trailing slash

Example:

```text
https://example.com/campaign/page-1?cid=abc
```

should register:

```text
example.com
```

### Widget Strategy

Recommended first version:
- Use a shared organization widget with multiple hostnames where possible.

Why:
- Easier for client management.
- Fewer Cloudflare widgets.
- Matches the client goal of reducing manual work.

Fallback:
- If hostname limits are reached or the client wants stronger separation, create one widget per hostname.

Store both strategies cleanly through `widget_scope`.

## Export HTML Plan

When `use_turnstile=true`:

1. Inject Cloudflare script once per page:

```html
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
```

2. Inject widget inside each protected form:

```html
<div class="cf-turnstile" data-sitekey="<SITE_KEY>"></div>
```

3. Ensure normal form submit includes Cloudflare-generated:

```text
cf-turnstile-response
```

4. Preserve existing form hidden inputs:
- `form_type`
- `api_platform_file`
- `api_category_id`
- `user_api_instance_id`
- `stop_spamming`
- AWeber fields
- tracking params

5. Add non-secret metadata:

```html
<input type="hidden" name="use_turnstile" value="yes">
```

## Export Backend/PHP Plan

### Config Changes

Update exported `public/api_files/config.php` generation or replacement flow to include:

```php
define('TURNSTILE_ENABLED', true);
define('TURNSTILE_SECRET_KEY', '...');
```

If multiple forms/widgets exist in one page, use a map:

```php
$TURNSTILE_SECRETS = [
    'default' => '...',
    'form_identifier' => '...',
];
```

Start with single/default secret if current pages normally contain one lead form.

### Helper File

Add:

```text
public/api_files/turnstile_verify.php
```

Responsibilities:
- Read `cf-turnstile-response`.
- Call Cloudflare Siteverify:
  - `https://challenges.cloudflare.com/turnstile/v0/siteverify`
- Send:
  - `secret`
  - `response`
  - optional `remoteip`
- Timeout quickly.
- Return normalized result:
  - success true/false
  - error codes
  - raw response for debug-safe logging if needed

Use cURL if available because exported hosting is plain PHP.

### Backend Flow Order

Recommended order in exported submission flow:

1. Receive POST.
2. Resolve `use_turnstile`.
3. If enabled:
   - verify token server-side.
   - if failed, stop broker/API submission.
   - optionally save failed lead to CRM with reason `Turnstile Failed`.
4. Run existing honeypot/fast-submit checks.
5. Run duplicate lead checks.
6. Run OTP/caps/provider-specific validations as currently designed.
7. Submit to broker/API only if all checks pass.
8. Continue existing thank-you/redirect behavior.

## Failed Verification Handling

Recommended product behavior:
- Do not submit to broker/API.
- Save failed attempt to CRM/failed submissions when possible.
- Use failure reason:

```text
Turnstile Failed
```

Payload metadata:

```json
{
  "turnstile": {
    "success": false,
    "error_codes": [],
    "blocked_ip": "..."
  }
}
```

Do not expose secret key or full sensitive verification payload.

UX options:
- Option A: show visible validation error.
- Option B: redirect to thank-you while silently blocking API submission.

Recommended for anti-abuse:
- Option B, if current fake/duplicate handling already uses normal thank-you UX.

## Security Requirements

- Encrypt Cloudflare API token in Builder database.
- Encrypt Turnstile secret keys in Builder database.
- Never include secret key in rendered HTML, React props, JavaScript, or exported source visible to users.
- Only put secret key in exported server-side PHP files.
- Do not log API token or secret key.
- Use request timeout for Cloudflare calls.
- Treat missing token as failed verification.
- Treat Cloudflare timeout as failed verification unless product decides fail-open.

Recommended:
- Fail closed for protected forms.

## Files Likely To Change

Builder backend:
- `config/services.php`
- `app/Services/CloudflareTurnstileService.php`
- `app/Http/Controllers/...` settings controller
- `app/Http/Controllers/AngleTemplateController.php`
- migrations under `database/migrations`
- models for settings/widgets

Builder frontend:
- settings page/component for Turnstile configuration
- form builder/component where form metadata is managed
- export/domain UI if hostname input is needed

Exported PHP package:
- `public/api_files/config.php`
- `public/api_files/backend.php`
- `public/api_files/save_lead_handler.php`
- new `public/api_files/turnstile_verify.php`
- export file list logic in `AngleTemplateController.php`

Tests:
- service tests for Cloudflare payload building.
- export tests for script/widget injection.
- backend helper tests where practical.
- regression tests for Turnstile OFF forms.

## Development Phases

### Phase 0 - Confirm Build Rules

Goal:
- Lock the few product decisions that affect implementation.

Steps:
1. Confirm Turnstile settings will be organization-level first.
2. Confirm default widget scope will be shared organization widget.
3. Confirm per-hostname widget will be fallback only.
4. Confirm Builder must ask for target hostname/domain during export if no domain is already saved.
5. Confirm failed verification behavior:
   - recommended: block broker/API submission and continue normal thank-you UX.
6. Confirm whether failed Turnstile attempts should be saved to CRM failed submissions.
7. Confirm first rollout target:
   - one staging landing page first.

Deliverable:
- Approved rules before coding starts.

Verification:
- No code change required.
- Document decision answers in the task notes or this file.

### Phase 1 - Database Foundation

Status:
- Completed.
- Migration files and models were added.
- Migrations were not run, so existing database data was not touched.

Goal:
- Add storage for Cloudflare credentials and provisioned Turnstile widgets.

Steps:
1. Create migration for `organization_turnstile_settings`.
2. Add columns:
   - `organization_id`
   - `enabled`
   - `auto_provision_enabled`
   - `cloudflare_account_id`
   - `cloudflare_api_token_encrypted`
   - `default_widget_mode`
   - `widget_scope`
3. Create migration for `turnstile_widgets`.
4. Add columns:
   - `organization_id`
   - `hostname`
   - `cloudflare_widget_id` or `cloudflare_sitekey`
   - `site_key`
   - `secret_key_encrypted`
   - `mode`
   - `domains_json`
   - `last_synced_at`
5. Add indexes:
   - `organization_turnstile_settings.organization_id`
   - `turnstile_widgets.organization_id`
   - `turnstile_widgets.hostname`
6. Add models for both tables.
7. Add casts for booleans, arrays, and encrypted values where appropriate.

Deliverable:
- Settings and widget records can be stored safely.

Verification:
- Run migration locally only when ready.
- Confirm model create/read works through a tinker/manual test.

### Phase 2 - Settings Backend

Status:
- Completed.
- Routes, request validation, and controller endpoints were added.
- Functional DB testing was not run because migrations have not been run.

Goal:
- Allow admins/org admins to save Cloudflare Turnstile settings.

Steps:
1. Add routes for Turnstile settings:
   - view/load settings
   - save settings
   - test Cloudflare connection
2. Add controller or extend existing organization settings controller.
3. Add request validation:
   - account ID required when auto-provision is enabled.
   - API token required on first save when auto-provision is enabled.
   - widget scope must be `shared` or `per_hostname`.
   - widget mode defaults to `managed`.
4. Save API token encrypted.
5. Never return the raw API token to React/Inertia.
6. Return only safe flags:
   - token exists true/false
   - account ID
   - enabled state
   - widget scope
7. Add permission checks so only allowed admins can manage settings.

Deliverable:
- Backend can save and load Turnstile configuration securely.

Verification:
- Save settings.
- Reload page/API and confirm token is not exposed.
- Confirm unauthorized users cannot update settings.

### Phase 3 - Settings UI

Status:
- Completed.
- Turnstile settings panel was added to the Profile page for organization managers.
- Frontend build passed.

Goal:
- Add a simple Turnstile configuration screen in Builder.

Steps:
1. Add Turnstile settings panel in the existing settings/profile/admin area.
2. Add fields:
   - Enable Turnstile
   - Enable auto-provisioning
   - Cloudflare Account ID
   - Cloudflare API token/access key
   - Widget scope
   - Default widget mode
3. Add "Test connection" action.
4. Show token as write-only:
   - blank input on load.
   - "token saved" status if one exists.
5. Show clear validation errors.
6. Keep UI hidden or disabled for roles that cannot manage organization settings.

Deliverable:
- Admin can configure Turnstile from Builder UI.

Verification:
- Save settings from UI.
- Refresh page and confirm token value is not shown.
- Test connection returns success/failure message.

### Phase 4 - Cloudflare API Service

Status:
- Completed.
- `CloudflareTurnstileService` was added and the settings connection test now uses it.
- Focused unit tests passed with faked Cloudflare HTTP responses.

Goal:
- Build the internal service that talks to Cloudflare.

Steps:
1. Create `app/Services/CloudflareTurnstileService.php`.
2. Add method to build authenticated Cloudflare HTTP requests.
3. Add method to test credentials/account access.
4. Add method to create widget.
5. Add method to update widget domains.
6. Add method to list/get widgets if needed.
7. Add method to normalize hostnames.
8. Add error handling:
   - invalid account ID
   - invalid token
   - permission denied
   - hostname rejected
   - Cloudflare timeout
9. Return normalized service responses instead of raw Cloudflare payloads.

Deliverable:
- Builder can communicate with Cloudflare Turnstile API.

Verification:
- Unit test hostname normalization.
- Unit test request payload generation.
- Manual test with Cloudflare test account/token if available.

### Phase 5 - Widget Provisioning Logic

Goal:
- Automatically create/reuse/update widgets for export hostnames.

Steps:
1. Add `resolveTurnstileWidget(organization, hostname, options)` method.
2. Normalize hostname before lookup.
3. Check local `turnstile_widgets` for existing widget.
4. For shared scope:
   - find organization shared widget.
   - append hostname to `domains_json` if missing.
   - call Cloudflare update endpoint.
5. For per-hostname scope:
   - find widget by hostname.
   - create widget if missing.
6. Store returned `site_key`.
7. Store returned `secret_key` encrypted.
8. Update `last_synced_at`.
9. Handle Cloudflare limits gracefully:
   - if shared widget hostname limit is reached, create per-hostname fallback if allowed.
10. Add clear error if provisioning fails.

Deliverable:
- Given an organization and hostname, Builder returns a usable Turnstile site key/secret pair.

Verification:
- Same hostname reuses existing widget.
- Same hostname with different path does not create a new widget.
- New hostname updates shared widget or creates fallback widget.

### Phase 6 - Form Metadata Toggle

Goal:
- Allow each landing-page form to opt into Turnstile.

Steps:
1. Find the form management state in the landing page builder/customizer.
2. Add `use_turnstile` boolean to form metadata.
3. Add UI checkbox: `Protect with Turnstile`.
4. Persist the flag into generated form HTML/metadata.
5. On edit, parse existing `use_turnstile` value back into form state.
6. Default legacy forms to OFF.
7. If global Turnstile settings are disabled, show the checkbox disabled or show a configuration warning.

Deliverable:
- Builder can mark a form as Turnstile-protected.

Verification:
- Create a new form with Turnstile ON.
- Edit page and confirm checkbox remains ON.
- Legacy forms remain OFF.

### Phase 7 - Export Hostname Input

Goal:
- Ensure export knows the final hostname/domain before provisioning.

Steps:
1. Inspect the current export modal/flow.
2. Add target hostname/domain input if no existing domain source exists.
3. Normalize the entered value:
   - remove protocol
   - remove path
   - remove query string
   - lowercase hostname
4. Validate hostname is required when exporting a Turnstile-enabled form.
5. Show message that directory/root path is not enough; hostname is required.
6. Pass hostname to backend export request.
7. Store last-used hostname if useful for repeat exports.

Deliverable:
- Export flow can provide a valid hostname to Cloudflare provisioning.

Verification:
- `https://example.com/folder/page` becomes `example.com`.
- Missing hostname blocks Turnstile export with clear message.
- Turnstile OFF export does not require hostname.

### Phase 8 - Export HTML Injection

Goal:
- Add Turnstile script/widget to exported pages only when enabled.

Steps:
1. During export, detect protected form(s).
2. Resolve/provision Turnstile widget using Phase 5 logic.
3. Inject Cloudflare script once into page head/body.
4. Inject widget markup inside protected form:
   - `div.cf-turnstile`
   - `data-sitekey`
5. Add hidden non-secret marker:
   - `use_turnstile=yes`
6. Do not inject `secret_key` into HTML.
7. Preserve all existing hidden inputs and tracking fields.
8. Ensure multiple form export does not duplicate script unnecessarily.

Deliverable:
- Exported HTML renders Turnstile widget for protected forms.

Verification:
- Page source contains site key.
- Page source does not contain secret key.
- Turnstile OFF export has no Turnstile script/widget.

### Phase 9 - Export Package Files

Goal:
- Include the PHP verification helper and config values in exported zip.

Steps:
1. Add `public/api_files/turnstile_verify.php`.
2. Add it to export file list.
3. Add Turnstile config replacement/injection in exported PHP:
   - `TURNSTILE_ENABLED`
   - `TURNSTILE_SECRET_KEY`
4. If multiple widgets/forms are supported, add a secret map.
5. Ensure exported package includes helper only when needed, or include always if simpler and harmless.
6. Verify no placeholder secret remains in exported output.

Deliverable:
- Export zip contains everything needed for server-side Turnstile verification.

Verification:
- Export zip contains `api_files/turnstile_verify.php`.
- Exported config contains secret only in PHP server file.
- Exported HTML does not contain secret.

### Phase 10 - Server-Side Verification

Goal:
- Block invalid Turnstile submissions before broker/API submission.

Steps:
1. Implement `turnstile_verify.php`.
2. Read `cf-turnstile-response` from POST.
3. Read Turnstile secret from config.
4. Send server-side request to Cloudflare Siteverify.
5. Include remote IP when available.
6. Use short timeout.
7. Return normalized result:
   - success
   - error codes
   - message
8. Call verification early in exported backend flow.
9. If verification fails:
   - do not send to broker/API.
   - optionally save failed lead to CRM.
   - follow selected thank-you/error UX.
10. Ensure Turnstile OFF forms skip verification.

Deliverable:
- Missing/invalid Turnstile token prevents broker/API submission.

Verification:
- Missing token fails.
- Invalid token fails.
- Turnstile OFF form still submits normally.

### Phase 11 - CRM Failed Submission Tracking

Goal:
- Track blocked Turnstile attempts if product decision requires it.

Steps:
1. Decide whether Builder should save failed Turnstile attempts to CRM.
2. Add failure reason:
   - `Turnstile Failed`
3. Add payload metadata:
   - `turnstile.success`
   - `turnstile.error_codes`
   - `turnstile.blocked_ip`
4. Reuse existing failed/fake lead save flow where possible.
5. Ensure failed Turnstile attempts are not shown as successful leads.

Deliverable:
- CRM can show/debug Turnstile-blocked attempts.

Verification:
- Failed Turnstile attempt appears in failed submissions if enabled.
- No successful lead row/provider submission is created for failed verification.

### Phase 12 - Regression Testing

Goal:
- Confirm existing lead flow is not broken.

Steps:
1. Test Turnstile OFF form.
2. Test Turnstile ON valid token.
3. Test Turnstile ON missing token.
4. Test Turnstile ON invalid token.
5. Test Cloudflare timeout behavior.
6. Test honeypot still blocks fake leads.
7. Test duplicate check still works after Turnstile success.
8. Test AWeber fields are preserved.
9. Test OTP fields are preserved where used.
10. Test CRM save-lead payload still includes expected fields.
11. Test export zip file list.
12. Test no secret appears in frontend source.

Deliverable:
- Feature is safe for staging.

Verification:
- Focused manual QA plus automated tests where practical.

### Phase 13 - Staging Rollout

Goal:
- Validate with a real staging domain and Cloudflare widget.

Steps:
1. Configure Cloudflare Account ID and API token in Builder.
2. Export one protected landing page.
3. Register staging hostname automatically.
4. Upload/export page to staging domain.
5. Submit valid human test.
6. Submit missing-token/invalid-token test.
7. Verify Cloudflare dashboard activity.
8. Verify CRM failed submission behavior.
9. Verify broker/API does not receive failed Turnstile submissions.

Deliverable:
- Staging sign-off.

Verification:
- Client or internal QA confirms successful protected form behavior.

### Phase 14 - Production Rollout

Goal:
- Enable Turnstile carefully for live forms.

Steps:
1. Enable on one low-risk live form first.
2. Monitor submissions.
3. Monitor failed Turnstile attempts.
4. Confirm no valid lead loss.
5. Confirm no broker/API failures.
6. Enable for more forms/domains gradually.
7. Keep Turnstile OFF fallback available per form.

Deliverable:
- Controlled production deployment.

Verification:
- Live submissions continue normally.
- Bot/invalid submissions are blocked before broker/API.

## Open Questions

1. Should Turnstile settings be organization-level only, or should each user/media buyer have separate credentials?
2. Should exported pages use one shared widget by default, with per-hostname widgets only as fallback?
3. Where will the export hostname/domain come from in the current Builder flow?
4. Should failed Turnstile attempts be saved in CRM `failed_lead_submissions`?
5. Should users see a visible validation error, or should failed bot submissions receive normal thank-you UX?
6. Do we need Turnstile on Builder auth pages too, or only exported landing pages?
7. Should Turnstile default ON for new forms once settings exist?
8. What should happen if Cloudflare auto-provisioning fails during export: block export or allow export with Turnstile disabled?

## Developer Notes

- Current exported forms already have anti-spam/honeypot support through generated hidden fields and `public/api_files/save_lead_handler.php`.
- Turnstile should run before honeypot and duplicate checks so obviously invalid traffic stops early.
- Existing `stop_spamming` behavior should remain separate from `use_turnstile`.
- Cloudflare Siteverify tokens are short-lived and single-use, so retries must require a fresh token.
- Local `file://` testing will not work for Turnstile; use localhost/staging over HTTP/HTTPS with Cloudflare test keys.
- Turnstile hostname registration should use only the domain/hostname, not the upload directory or URL path.

## Acceptance Criteria

- Admin can configure Cloudflare Turnstile settings securely.
- Builder can programmatically create/update Turnstile widgets through Cloudflare API.
- Builder can programmatically register/update allowed hostnames/domains through Cloudflare API.
- Builder normalizes export URLs to hostnames before Cloudflare registration.
- Form builder can enable/disable Turnstile per form.
- Exported HTML renders the Turnstile widget for protected forms.
- Exported PHP verifies Turnstile token server-side.
- Failed verification prevents broker/API submission.
- Existing non-protected forms continue working without code or configuration changes.
- Secret keys are not visible in page source or JavaScript.
- QA confirms valid, missing, invalid, and expired-token scenarios.
