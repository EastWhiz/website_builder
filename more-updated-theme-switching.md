# More Updated Theme Switching Development Plan

## Goal

Improve theme switching so all BD-related content is preserved reliably, including original Angle content, landing-page creation content, and content added or edited later inside BD containers.

The final behavior should be:

- Theme layout/design can change during theme switching.
- BD content remains safe and reusable.
- User-added content inside a BD container stays inside that BD.
- Content order inside each BD is preserved exactly.
- Legacy pages can gradually move into the structured BD flow when safe.

## Core Rules

- BD content is the source of truth for theme switching.
- Angle content remains the original/base source and should not be overwritten by landing page edits.
- Landing pages store their own updated BD content.
- Any content added inside a BD container becomes part of that BD.
- Any content added outside BD containers is treated as page-level custom content.
- Theme-level layout edits do not need to be preserved during theme switching.
- Legacy conversion should only happen when BD extraction is reliable.

## Phase 0: Current Flow Review

### Tasks

- Review current structured BD rendering flow.
- Confirm how `angle_template_bd_contents` records are created and updated.
- Confirm how BD wrappers are rendered in landing page preview.
- Confirm how direct page edit/add actions are currently saved.
- Identify where frontend knows the clicked element's parent BD container.
- Identify where backend receives updated page HTML.

### Output

- Short technical notes showing current save flow.
- List of files/functions that need changes.
- No functional changes in this phase.

### Phase 0 Audit Notes

Status: completed as a read-only code audit.

#### Current Structured BD Creation Flow

- Frontend creation form is handled in `resources/js/Pages/Users/UserThemes.jsx`.
- The create request posts to `landing-pages.create-from-angle-template`.
- Backend entry point is `AngleTemplateController::createFromAngleTemplate`.
- If `content_mode` is `structured_bd`, backend calls `createStructuredBdLandingPage`.
- `createStructuredBdLandingPage` maps Angle HTML content into BD identifiers and appends landing-page modal content when provided.
- Structured BD rows are saved in `angle_template_bd_contents`.
- The page is rendered by `renderAndPersistStructuredBd`.

#### Current Structured BD Storage

- `angle_templates.content_mode` stores whether the page is `legacy` or `structured_bd`.
- `angle_templates.structured_version` stores the structured format version.
- `angle_template_bd_contents` stores landing-page-level BD content.
- Each BD row stores:
  - `angle_template_id`
  - `angle_template_uuid`
  - `parent_bd`
  - `slot_key`
  - `slot_type`
  - `content`
  - `sort`
  - `metadata`
- `AngleTemplate::bdContents()` already exists.
- `AngleTemplate::isStructuredBd()` already exists.

#### Current Structured BD Rendering Flow

- Main rendering service is `AngleTemplateMergeService`.
- `renderStructuredBd` loads saved BD rows from `angle_template_bd_contents`.
- `renderStructuredBodies` injects saved BD content into matching theme placeholders.
- Each rendered BD is wrapped with:
  - `lp-structured-bd-slot`
  - `lp-structured-bd-slot-{slotKey}`
  - `data-bd-slot="{slotKey}"`
- The wrapper currently uses neutral CSS:
  - `.lp-structured-bd-slot { display: contents; }`

#### Current Structured BD Editor Flow

- Preview page is `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`.
- On preview load, if `content_mode === structured_bd`, frontend calls `loadStructuredBdContent`.
- Backend route returns saved BD rows through `AngleTemplateController::structuredBdContent`.
- The popup editor updates local `structuredBdContents`.
- Popup save calls `saveStructuredBdContent`.
- Backend updates/creates matching `AngleTemplateBdContent` rows.
- Backend re-renders the page and returns updated `main_html`, `main_css`, and `main_js`.

#### Current Direct Page Edit/Add Flow

- Direct visual add/edit actions are handled in `PreviewAngleTemplate.jsx`.
- The final page save button calls `updatedThemeSaveHandler`.
- `updatedThemeSaveHandler` posts rendered `main_html` and uploaded images to `editedAngleTemplate.save`.
- Backend save logic updates `angle_templates.main_html`.
- For structured BD pages, this currently saves rendered page HTML but does not automatically update `angle_template_bd_contents`.
- This is the main gap for the newly agreed client requirement.

#### Current Frontend BD Detection

- `PreviewAngleTemplate.jsx` already detects structured BD pages using `structuredModeRef`.
- Click detection already finds the nearest `.lp-structured-bd-slot`.
- The rendered wrapper already exposes `data-bd-slot`.
- Current modal behavior can identify whether the clicked area is BD-related.
- Missing piece: the save flow does not yet capture and send the full updated BD container content back to the backend.

#### Current Theme Switching Flow

- Theme switch request posts to `landing-pages.change-theme`.
- Backend entry point is `AngleTemplateController::changeTheme`.
- If page is structured BD, backend calls `changeStructuredBdTheme`.
- Structured theme switching copies the saved BD rows and renders them into the selected target theme.
- If page is legacy, backend uses `AngleTemplateMergeService::changeThemePreservingContent`.
- Legacy pages now try invisible BD boundary markers first, then fallback extraction.
- `lp-theme-safe-content` wrapper/CSS has been removed from fallback behavior.

#### Confirmed Implementation Gap

- New structured BD pages already have stable backend storage and render wrappers.
- Theme switching already uses saved BD records when available.
- The missing feature is automatic synchronization from direct visual edits/additions inside a BD wrapper back into `angle_template_bd_contents`.
- Once this is implemented, direct BD edits and popup BD edits can share the same source of truth.

#### Files To Touch In Later Phases

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`
  - detect BD slot during add/edit
  - capture updated BD wrapper inner HTML
  - keep popup state synced
  - send updated BD content during save
- `app/Http/Controllers/AngleTemplateController.php`
  - accept/sync updated BD content from direct visual save
  - optionally convert legacy pages to structured BD when safe
- `app/Services/AngleTemplateMergeService.php`
  - reuse existing render/refresh/extraction helpers
  - add helper only if direct BD extraction needs backend-side sanitation
- `tests/Unit/AngleTemplateMergeServiceTest.php`
  - add/adjust coverage for BD wrapper content extraction and order preservation
- Potential new test file
  - direct structured BD visual edit save flow

#### Phase 0 Conclusion

- No new database tables are required for the core direct-BD-sync feature.
- Existing structured BD storage is sufficient.
- The next phase should focus on frontend BD container identification and storing the active BD slot/content snapshot during direct edits.
- The overall change is medium-sized and mostly affects the save/edit flow, not the full architecture.

## Phase 1: BD Container Identification

### Tasks

- Ensure every rendered BD container has a stable identifier.
- Use attributes like `data-bd-slot="BD1"` on BD wrappers.
- Ensure sub-slots also have stable identifiers such as `BD2_HEADER`.
- Update frontend click detection to find the nearest BD parent container.
- Separate three click contexts:
  - BD content area
  - Theme/Angle/page layout area
  - Page-level custom additions

### Output

- Frontend can reliably detect whether an action happened inside `BD1`, `BD2`, etc.
- No BD save behavior changes yet.

### Phase 1 Implementation Notes

Status: completed.

#### What Changed

- Added structured edit context fields to the preview editor state:
  - `structuredEditContext`
  - `bdSlotKey`
  - `bdSlotElement`
- Added a helper to resolve BD slot keys from rendered BD containers.
- Slot detection now reads:
  - `data-bd-slot`
  - fallback class name like `lp-structured-bd-slot-BD1`
- Added a helper to classify structured click context as:
  - `bd`
  - `angle`
  - `page_addition`
  - `theme`
- When a user clicks inside a structured BD area, the editor now stores the active BD slot key.
- Newly added structured elements now receive lightweight metadata:
  - `data-lp-edit-context`
  - `data-bd-slot` when the action belongs to a BD

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`

#### Validation

- `npm run build` passed.
- Existing Vite warnings remained:
  - old Browserslist data
  - existing `eval` warning in preview editor
  - large chunk warning

#### Phase 1 Result

- The frontend can now reliably know which BD container a structured edit/add action belongs to.
- This prepares Phase 2, where the updated BD container HTML will be saved back into `angle_template_bd_contents`.
- No backend save behavior was changed in Phase 1.

## Phase 2: Save Edited BD Container Content

### Tasks

- When user edits content inside a BD container, capture the full updated inner HTML of that BD container.
- Send the BD slot key and updated BD HTML to backend.
- Backend updates the matching `angle_template_bd_contents` row.
- If the BD row does not exist, create it for that landing page.
- Re-render the landing page from current theme + updated BD records.

### Output

- Direct BD edits are saved into structured BD storage.
- BD popup/editor and rendered page use the same latest BD content.

### Phase 2 Implementation Notes

Status: completed.

#### What Changed

- Direct visual save now extracts all rendered `.lp-structured-bd-slot` containers before posting the page save request.
- The extracted payload is sent with the existing `editedAngleTemplate.save` request as `structured_bd_contents`.
- The backend reads `structured_bd_contents` only when the saved page is `structured_bd`.
- The backend updates or creates matching `angle_template_bd_contents` rows by `slot_key`.
- If newly uploaded images are saved during the same request, blob URLs are also replaced inside the BD content before syncing the BD rows.
- Added logic to keep newly added elements inside the active BD wrapper when the action belongs to a BD.
- This helps preserve the exact order inside the BD container.

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`
- `app/Http/Controllers/AngleTemplateController.php`

#### Current Save Behavior After Phase 2

- User edits/adds content inside `BD1`.
- Frontend keeps the new content inside the `BD1` wrapper.
- On save, frontend extracts `BD1` wrapper inner HTML.
- Backend updates the `BD1` row in `angle_template_bd_contents`.
- Future structured theme switching uses the updated `BD1` content.

#### Validation

- `php -l app/Http/Controllers/AngleTemplateController.php` passed.
- `npm run build` passed.
- Existing Vite warnings remained:
  - old Browserslist data
  - existing `eval` warning in preview editor
  - large chunk warning

#### Phase 2 Result

- Direct visual BD edits/additions are now connected to structured BD storage.
- BD content edited directly from the page can be preserved during future structured theme switching.
- No database refresh or destructive command was used.

## Phase 3: Preserve Content Order Inside BD

### Tasks

- Ensure added content inside a BD container is saved exactly where the user placed it.
- Save the complete updated BD container content as one ordered block.
- Avoid saving original Angle content and added content as separate forced sections.
- Test cases:
  - Original: `A -> B -> C`
  - User adds: `A -> New Content -> B -> C`
  - Saved result must remain: `A -> New Content -> B -> C`

### Output

- User-added/edited BD content keeps exact order after save and after theme switching.

### Phase 3 Implementation Notes

Status: completed.

#### What Was Confirmed

- Phase 2 already saves the full BD container `innerHTML`.
- Because the whole BD wrapper content is saved as one block, original content and added content are not split into separate forced sections.
- If the user changes a BD from `A -> B -> C` to `A -> New Content -> B -> C`, the saved BD row keeps that exact order.
- Rendering the saved BD content into another theme also keeps that exact order.

#### Tests Added

- Added coverage for refreshing a structured BD slot while preserving content order.
- Added coverage for rendering saved ordered BD content into another theme.

#### Files Updated

- `tests/Unit/AngleTemplateMergeServiceTest.php`

#### Validation

- `php -l app/Services/AngleTemplateMergeService.php` passed.
- `php artisan test tests/Unit/AngleTemplateMergeServiceTest.php` passed.
- Result: `36 passed`.

#### Phase 3 Result

- Content order inside each BD is now covered by tests.
- Directly edited/added BD content should remain in the same order after save and future theme switching.
- No database refresh or destructive command was used.

## Phase 4: Page-Level Custom Content Handling

### Tasks

- Detect content added outside BD containers.
- Do not force outside content into any BD.
- Preserve outside content as page-level custom content.
- Decide controlled placement during re-render:
  - before BD layout
  - after BD layout
  - or inside a dedicated custom content area
- Avoid mixing theme layout HTML into BD records.

### Output

- BD records remain clean.
- Extra user content is not lost.
- Theme switching remains predictable.

### Phase 4 Implementation Notes

Status: completed.

#### What Changed

- Additions made outside BD containers are now marked as page-level custom content.
- Additions made inside BD containers still remain part of that BD and are not treated as separate page-level content.
- Added backend helpers to extract page-level additions from rendered structured pages.
- The extractor ignores additions that are inside `.lp-structured-bd-slot`.
- During structured theme switching, outside-BD additions are preserved separately and appended after the new theme BD layout.
- This avoids forcing non-BD content into `BD1`, `BD2`, etc.

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`
- `app/Services/AngleTemplateMergeService.php`
- `app/Http/Controllers/AngleTemplateController.php`
- `tests/Unit/AngleTemplateMergeServiceTest.php`

#### Current Behavior After Phase 4

- User adds content inside `BD1`: it is saved as part of `BD1`.
- User adds content outside any BD: it is marked as page-level custom content.
- On structured theme switch, BD content is rendered into the new theme.
- Outside-BD custom content is preserved separately after the new theme layout.

#### Validation

- `php -l app/Services/AngleTemplateMergeService.php` passed.
- `php -l app/Http/Controllers/AngleTemplateController.php` passed.
- `php artisan test tests/Unit/AngleTemplateMergeServiceTest.php` passed.
- Result: `38 passed`.
- `npm run build` passed.

#### Phase 4 Result

- BD records remain clean.
- Extra outside-BD content is not lost during structured theme switching.
- Non-BD content is preserved separately instead of being forced into a BD.
- No database refresh or destructive command was used.

## Phase 5: Structured Theme Switching Update

### Tasks

- Confirm structured pages always switch themes using saved BD records.
- Confirm theme switching does not depend on rendered HTML extraction when BD records exist.
- Ensure updated BD content is injected into matching new theme placeholders.
- Ensure missing target theme slots render empty instead of failing.
- Ensure repeated BD placeholders reuse the same saved BD content.
- Ensure sub-slots are handled as their own BD records.

### Output

- Structured pages switch themes using latest saved BD content.
- Manual BD edits are preserved during theme switching.

### Phase 5 Implementation Notes

Status: completed.

#### What Was Confirmed

- Structured pages switch themes using saved `angle_template_bd_contents` rows.
- Structured theme switching does not depend on rendered HTML extraction when BD rows exist.
- Updated BD content is injected into matching placeholders in the target theme.
- Missing target placeholders are removed/left empty instead of failing.
- Repeated target placeholders reuse the same saved BD content.
- Sub-slots such as `BD2_HEADER` are treated as independent saved BD rows.
- Outside-BD page additions are preserved separately after the target theme layout.

#### Tests Added

- Added coverage for structured theme switch output using:
  - latest edited BD rows
  - repeated BD placeholders
  - independent sub-slots
  - missing target slots
  - separate page-level additions

#### Files Updated

- `tests/Unit/AngleTemplateMergeServiceTest.php`

#### Validation

- `php -l app/Services/AngleTemplateMergeService.php` passed.
- `php -l app/Http/Controllers/AngleTemplateController.php` passed.
- `php artisan test tests/Unit/AngleTemplateMergeServiceTest.php` passed.
- Result: `39 passed`.

#### Phase 5 Result

- Structured theme switching is now covered by targeted tests.
- Latest saved BD content is the source for theme switching.
- Manual BD edits preserved in BD rows are rendered into the selected target theme.
- No database refresh or destructive command was used.

## Phase 6: Legacy Page Conversion On Save

### Tasks

- When a legacy page is saved, attempt to extract BD content from:
  - invisible BD markers
  - existing BD wrappers
  - current theme placeholders
- Compare extracted BDs against current theme-required BDs.
- If required BD is missing, create it as empty.
- If extraction is reliable, create structured BD records.
- Mark landing page as `structured_bd`.
- Re-render from current theme + extracted BD records.
- If extraction fails, keep page as legacy and show a warning.

### Output

- Legacy pages gradually move into structured BD mode when safe.
- No risky automatic migration when extraction is unreliable.

### Phase 6 Implementation Notes

Status: completed.

#### What Changed

- Legacy landing pages now attempt safe structured BD conversion during the existing visual save flow.
- Conversion runs only after uploaded image blob URLs are replaced with final storage URLs.
- Conversion is attempted only when:
  - the page is still `legacy`
  - the selected theme contains BD placeholders
  - BD extraction returns reliable content
- Extraction tries the safest sources in this order:
  - invisible BD boundary markers
  - rendered structured BD wrappers
  - current theme shell matching
  - decoded theme shell matching
  - tolerant regex theme matching
- If extraction succeeds, backend creates/updates `angle_template_bd_contents` rows for the current theme slots.
- If a theme-required slot is missing from extracted content, it is created as an empty BD row.
- The landing page is marked as `structured_bd`.
- The landing page is re-rendered from the current theme and extracted BD rows.
- If extraction fails, the page remains legacy and a log entry is written.

#### Files Updated

- `app/Services/AngleTemplateMergeService.php`
- `app/Http/Controllers/AngleTemplateController.php`
- `tests/Unit/AngleTemplateMergeServiceTest.php`

#### Safety Notes

- This is not a bulk migration.
- Existing pages are converted only when they are saved and extraction is reliable.
- Pages without reliable BD extraction remain in legacy mode.
- No old landing pages are modified automatically in the background.

#### Validation

- `php -l app/Services/AngleTemplateMergeService.php` passed.
- `php -l app/Http/Controllers/AngleTemplateController.php` passed.
- `php artisan test tests/Unit/AngleTemplateMergeServiceTest.php` passed.
- Result: `41 passed`.

#### Phase 6 Result

- Legacy pages can now gradually move into structured BD mode on save when safe.
- Missing theme-required BDs are created as empty rows.
- Unsafe legacy pages remain legacy instead of risking incorrect mapping.
- No database refresh or destructive command was used.

## Phase 7: BD Editor Sync

### Tasks

- Ensure BD popup/editor loads latest saved BD records.
- After direct BD edit/save, popup should show the updated BD content.
- After popup save, rendered page should show updated BD content.
- Keep both editing surfaces connected through structured BD records.

### Output

- BD popup and direct BD editing stay in sync.
- `angle_template_bd_contents` remains the shared source of truth.

### Phase 7 Implementation Notes

Status: completed.

#### What Changed

- The visual save endpoint now returns structured BD state when the saved page is `structured_bd`.
- Response data includes:
  - `angle_template_id`
  - `content_mode`
  - `structured_version`
  - `bd_contents`
- After the final visual save chunk succeeds, the frontend reads this response and updates:
  - `structuredBdContents`
  - `structuredModeRef`
  - local `data.content_mode`
  - local `data.structured_version`
- If a legacy page is safely converted during save, the frontend is also informed that it is now `structured_bd`.
- The structured BD popup/editor will use the latest BD rows returned by the backend.

#### Files Updated

- `app/Http/Controllers/AngleTemplateController.php`
- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`

#### Current Behavior After Phase 7

- Popup save updates BD rows and refreshes rendered page.
- Direct visual save updates BD rows and returns the latest BD rows.
- Frontend syncs its BD editor state from the backend response.
- Both editing surfaces now share `angle_template_bd_contents` as the source of truth.

#### Validation

- `php -l app/Http/Controllers/AngleTemplateController.php` passed.
- `php artisan test tests/Unit/AngleTemplateMergeServiceTest.php` passed.
- Result: `41 passed`.
- `npm run build` passed.

#### Phase 7 Result

- BD popup and direct visual BD editing are now synced through backend BD records.
- Structured BD rows remain the shared source of truth for theme switching.
- No database refresh or destructive command was used.

## Phase 8: Validation And Edge Cases

### Tasks

- Test BD order preservation.
- Test repeated BD placeholders.
- Test sub-slots.
- Test empty/missing BD slots.
- Test user-added images inside BD containers.
- Test user-added content outside BD containers.
- Test structured page theme switching.
- Test legacy page save and conversion.
- Test failed legacy conversion warning.
- Test organization/user permissions remain unchanged.

### Output

- Confirm no existing functionality is broken.
- Confirm no destructive database commands are required.

### Phase 8 Validation Notes

Status: completed.

#### Automated Checks Completed

- PHP syntax check passed for `app/Services/AngleTemplateMergeService.php`.
- PHP syntax check passed for `app/Http/Controllers/AngleTemplateController.php`.
- Focused backend unit tests passed for `tests/Unit/AngleTemplateMergeServiceTest.php`.
- Result: `41 passed`, `106 assertions`.
- Frontend production build passed with `npm run build`.

#### Edge Cases Covered By Existing Focused Tests

- BD content is extracted by stable BD identifier instead of section order.
- Reordered target themes receive the correct BD content.
- Repeated BD placeholders reuse the same saved BD content.
- Sub-slots render independently from plain BD slots.
- Optional/missing sub-slots do not block page creation.
- Encoded BD HTML renders as HTML, not plain escaped text.
- Saved BD row content is preferred over original angle slot content.
- Direct BD edits preserve exact DOM/content order.
- User-added content outside BD wrappers is extracted separately.
- Page-level custom additions are appended separately after theme switching.
- Legacy pages can convert to structured BD only when reliable extraction is possible.
- Safe fallback does not add `lp-theme-safe-content` layout wrapper CSS.
- Theme media cleanup does not remove preserved article assets.

#### Manual QA Still Recommended

- Create a new structured landing page from a BD-compatible theme and verify Angle content, Theme content, and BD modal content all render.
- Add text/image inside a BD slot, save, reopen the BD editor, and confirm the content remains inside the correct BD in the same order.
- Add content outside a BD slot, save, then switch theme and confirm it remains separate from BD content.
- Switch a structured page to a theme with reordered/repeated BD placeholders.
- Switch a structured page to a theme with sub-slots such as `BD2_HEADER` and `BD2_BANNER`.
- Save an old legacy page and confirm it converts only when the BD content can be extracted safely.
- Test Organization Manager and Media Buyer flows to confirm permissions and duplicate/theme-switch behavior remain correct.

#### Known Non-Blocking Build Warnings

- Vite reports old Browserslist data.
- Vite warns about existing `eval` usage in `PreviewAngleTemplate.jsx`.
- Vite reports large chunks after minification.
- These warnings did not fail the build and are not newly introduced by Phase 8.

#### Phase 8 Result

- Automated validation passed.
- No database refresh or destructive command was used.
- Remaining validation is manual UI QA on local/dev using copied/new pages only.

## Phase 9: Rollout Strategy

### Tasks

- Enable changes first on local/dev.
- Test with new BD-compatible themes.
- Test with copied legacy pages.
- Avoid changing old live pages in bulk.
- Convert legacy pages only when user edits/saves and extraction is reliable.
- Keep logs for conversion success/failure.

### Output

- Safe rollout with minimal risk.
- Existing landing pages remain usable.
- New and converted pages become more reliable for future theme switching.

## Acceptance Criteria

- BD content is saved separately by BD identifier.
- Direct edits inside BD containers update the correct BD record.
- User-added content inside BD preserves exact order.
- Theme switching uses latest saved BD records.
- Extra content outside BD is preserved separately.
- Legacy pages are converted only when safe.
- No `lp-theme-safe-content` wrapper/CSS is introduced.
- Existing legacy pages continue working if they cannot be converted.
- No database refresh or destructive commands are required.
