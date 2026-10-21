# CRM Pixel Management & Success Conversion Tracking — Implementation Plan

Status: planning only. No CRM code has been written.
Scope: Phase 1 = Success pixels. Phase 2 (Pending pixels) is designed for but not built.

## 1. Problem

The exported thank-you page fired the Facebook pixel (`conversionpixel.com/fb.php?pid=`) and the
Voluum postback (`plz.hold1sec.com/postback?cid=&payout=0&currency=USD&txid=lead`) in the visitor's
browser for **every** lead, before the CRM decided the lead's status. Failed leads were counted as
revenue and the dashboard no longer matches reality.

Already done (builder, uncommitted): the pixel scripts are commented out of
`public/api_files/thank_you.php` and `public/thankyou_templates/geo_aware_v2/thankyou.php`, and
`ThankYouPage::injectPixelUrlsIntoThankYouContent()` is a no-op.
Consequence: until the CRM module below ships, no page-side conversion is reported.

## 2. Agreed decisions

| Topic | Decision |
|---|---|
| Fire when | Lead status becomes **Success** in the CRM (direct-to-broker, manual push, or scheduler). |
| Self-hosted leads | Enter as Pending; fire only when a later manual/scheduler submission returns Success. |
| Delay | 30 seconds after Success is confirmed (queued job). |
| Pending pixels | Phase 2. Price sent as 0. Structure supports it now. |
| Pixels in use | Voluum postback and Facebook `fb.php?pid=`. |
| Re-push of an already-successful lead | Does **not** fire again. |
| Pixels per user | No limit. |
| Parameters per pixel | Unlimited, user-defined name + mapped field. |
| `pid` source | From the builder, same path as `cid`. |
| Price order | 1) manual per-lead override, 2) cap price for lead country + API. |
| No price found | Do **not** fire. Log status `skipped_no_price`; visible to admins; fires after a price is set and the lead is retried. Never send 0 on a Success pixel. |

## 3. Findings that shape the design

- Builder JS already copies `cid`, `pid`, `so` from the URL into hidden form fields
  (`AngleTemplateController` `syncTrackingParamsFromUrl`).
- `public/api_files/save_lead_handler.php` sends `cid` and `so` to CRM `POST /api/v1/save-lead`, but
  **not `pid`**. CRM `leads` has `so`, `cid`; **no `pid`**.
- Success is set in several CRM places, not one:
  `APIController` save_lead (status from builder), `ProcessImmediateLeadSubmission`,
  `ProcessQueuedLeadSubmission`, manual submit in `LeadsController`, bulk resubmit,
  `PendingLeadsRetryService`.
- Prices live in `partner_caps.lead_price`, unique on (`country`, `api_id`). Cap country is stored as a
  **country name**; lead country is also stored as a name. Lookup depends on the lead's country being
  right (see Risks: IP vs selected country).
- Queue is the database driver; a worker must be running for delayed jobs.
- `api_categories.integration_group` already supports `network|services|pixels` and API Settings has a
  (hidden) pixels tab.

## 4. Data model (all additive migrations)

- `leads`: add nullable `pid`, `manual_pixel_price` (decimal 10,2).
- `pixels`: id, organization_id, user_id, name, scenario (`success` now; `pending` later), url,
  method (GET), is_active, timestamps, soft deletes. Optional `api_id` to scope to one API.
- `pixel_params`: id, pixel_id, name, source_type (`field` | `fixed`), source_value, is_required, sort.
- `pixel_fire_logs`: id, lead_id, pixel_id, scenario, final_url, http_status, response_body (truncated),
  status (`queued|sent|failed|skipped_no_price|skipped_missing_param`), attempts, fired_at, timestamps.
  **Unique (lead_id, pixel_id, scenario)** guarantees one fire per lead per pixel.

Mappable fields (fixed list, extensible): `cid`, `pid`, `so`, `price`, `lead_id`, `email`, `country`,
`api_type`, `offer_name`, `currency` (fixed), plus a literal "fixed value".
`txid` defaults to `lead-{lead_id}` (unique per lead; never a constant).

## 5. Flow

1. A Lead model hook detects `api_response_status` changing to `Success` (covers every path above),
   for leads created/succeeded **after the go-live timestamp** only.
2. Dispatch `FirePixelsJob` with a 30-second delay.
3. Job: re-load lead; stop if no longer Success. Resolve price (manual, then cap by country+API).
   If none: write `skipped_no_price` for each pixel and stop.
4. For each active `success` pixel of the lead's owner (and API scope): build URL from params,
   URL-encode values, skip with `skipped_missing_param` if a required param is empty.
5. Insert fire log first (unique key), then send the request; store response; retry failures
   (e.g. 3 attempts, backoff). A duplicate insert means already fired: do nothing.
6. Safety sweep (scheduler, every few minutes): Success leads after go-live with no log row, or with
   `queued` rows older than a threshold, get re-dispatched. Covers worker outages and failed save-lead
   handoffs.

## 6. Security

- Calls user-entered URLs from the server: allow only http/https, block private/loopback/link-local
  addresses (SSRF), short timeout, no redirects, response size cap.
- Scope pixels by organization and owner; a lead only triggers its owner's pixels.
- Explicit mappable-field list (no arbitrary column access). Mask PII (email) in fire logs.

## 7. Builder changes

1. `save_lead_handler.php`: send `pid` as a top-level field and inside `api_payload`.
2. Real-time form validation: strict email (trim, lowercase, reject spaces/`&`/`^` etc., same rules as
   CRM `email` validation), inline errors, block submit until valid. Phone validation already exists.
3. Thank-you pixel removal: done (see section 1).
4. Existing exports in the wild keep the old behaviour (page pixel + no `pid`) until re-exported.

## 8. CRM UI

- New "Pixel Management" under the user area next to API Management: list, add/edit pixel, repeatable
  parameter rows with a field dropdown, active toggle, "test fire" with a sample lead, fire-log view.
- Lead screen: show price used, source (manual/cap), pixel status; manual price input + "retry pixels".

## 9. Phases

Phase 1: migrations, model hook + job, URL builder, price resolver, Pixel Management UI, fire log,
sweep, builder `pid` + validation. Seed two example pixels (Voluum, Facebook) disabled by default.
Phase 2: `pending` scenario (reuses tables, price forced to 0, its own trigger on Pending).

## 10. Testing

Unit: URL building/encoding, price resolution order, missing price/param skips, unique-fire guard,
SSRF blocklist. Feature: each Success path (save-lead, immediate, queued, manual, bulk resubmit,
pending retry) dispatches exactly once; re-push does not re-fire; self-hosted Pending then Success fires;
go-live cutoff excludes old leads. Manual: one real Voluum test click end to end.

## 11. Risks / items to verify before build

1. **Facebook pixel server-side.** `fb.php?pid=` was loaded in a browser iframe. A server-side GET may
   not register (cookies/JS). Test it, or ask the client whether it can be fired server-to-server.
2. **Voluum offer payout mode** must be **Auto**, or Voluum ignores the price we send.
3. **Voluum duplicate rule:** same `cid` is discarded unless `txid` differs; a first fire is final.
   This is why we never fire Success without a real price.
4. **Country accuracy for cap lookup.** Lead country comes from IP geolocation; a visitor's selected
   country is better. The reverted `resolveLeadCountry()` change addressed this and should be
   re-applied as part of this work, or prices may use the wrong country.
5. **Queue worker** must be running in production; the sweep is the backstop.
6. **No retroactive fire:** go-live cutoff is mandatory. Past mistracked revenue is a separate
   correction (Voluum Conversion Upload), not part of this module.
7. Voluum negative payout via postback is **not documented**; reversals are out of scope here.

## 12. Still to confirm with the client

- Test click/offer in Voluum for an end-to-end check.
- Can the Facebook pixel fire server-side? (risk 1)
- Is the lead's selected country acceptable as the price-lookup country?
