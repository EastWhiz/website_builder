# Flex Box Landing Page Development Plan

## Goal

Improve the landing page Add/Edit element flow and introduce a safer Flex Box layout builder for left/right or multi-column layouts.

The main purpose is to stop using unreliable Left/Right element insertion for layout and replace it with a structured Flex Box container that can preserve layout, styling, responsiveness, BD content behavior, and theme switching more reliably.

## Core Rules

- Existing landing page behavior must remain stable.
- Do not run destructive database commands.
- Keep changes backward compatible with existing legacy and structured BD landing pages.
- Implement in small phases so each phase can be tested separately.
- Normal Add Element flow should stay simple.
- Complex side-by-side layout should be handled through Flex Box, not Left/Right insertion.

## Design References

- Flex Box UI and behavior should follow the provided design screenshots.
- Current reference PDF: `D:\wamp64-3-3-5\www\website_builder\docs\Add-Flex-Box.pdf`.
- Any new design images shared for Phases 5-10 should be treated as UI/UX references for layout, labels, and flow.
- The screenshots guide the modal structure, but implementation should still preserve existing editor behavior, BD placement, save flow, and theme switching safety.

---

## Phase 0: Current Flow Audit

### Tasks

- Review current Add/Edit element modal code.
- Identify where Top, Bottom, Left, and Right options are rendered.
- Identify where new elements are inserted into the DOM.
- Review current Button add/edit flow.
- Review current Image add/edit flow.
- Review current Text add/edit flow.
- Review structured BD add/edit behavior so Flex Box changes do not break BD placement.
- Review save flow to confirm custom Flex Box HTML/CSS will be preserved.

### Output

- Clear list of files/functions to update.
- Confirm whether Flex Box can be saved as normal HTML inside landing page/BD content.
- Confirm any risk areas before implementation.

### Validation

- No code behavior changed in this phase.
- No database changes required.

### Phase 0 Audit Notes

Status: completed.

#### Files Reviewed

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`
- `app/Http/Controllers/AngleTemplateController.php`
- `app/Services/AngleTemplateMergeService.php`
- `app/Services/AngleTemplateCloneService.php`
- `app/Services/LandingPageAssetService.php`
- `app/Http/Controllers/Admin/OrganizationContentCloneController.php`
- `docs/Add-Flex-Box.pdf`

#### Current Add/Edit Modal Findings

- The main Action Center modal is implemented in `PreviewAngleTemplate.jsx`.
- The first Action Center currently shows:
  - `Add Element`
  - `Edit Element`
  - `Delete Element`
- `Edit Element` and `Delete Element` are hidden in structured BD add-only context.
- Normal Add Element position selection currently shows:
  - `Top Side`
  - `Left Side`
  - `Right Side`
  - `Bottom Side`
- This confirms Phase 1 can safely start by removing only the visible `Left Side` and `Right Side` buttons while keeping existing Top/Bottom behavior.

#### Current Element Insertion Findings

- New elements are inserted through `addNewContentHandler(position, existingElement, newElement)`.
- Current supported positions are `top`, `bottom`, `left`, and `right`.
- In structured BD mode, insertions inside a BD go through `insertStructuredBdAddition(...)`.
- `insertStructuredBdAddition(...)` already keeps added content inside the correct `.lp-structured-bd-slot`.
- For Phase 1, the UI can remove Left/Right first while leaving the helper fallback code untouched to reduce risk.

#### Current Element Type Findings

- Existing add types are handled in `updateHTMLHandler`.
- Current add element types include:
  - Image
  - Text
  - Spacer
  - Custom HTML
  - Form
  - Button
- Button currently creates a plain `button` element and does not have a dedicated URL field.
- Image state already has `width`, but the UI/behavior needs to be checked and standardized during Phase 3.
- Text currently creates a `p` element for new text. Heading selection does not exist yet.
- Custom HTML is already supported and can preserve HTML-based structures.

#### Current Save And BD Compatibility Findings

- The editor saves rendered content through `updatedThemeSaveHandler`.
- For structured BD pages, the frontend extracts BD content from `.lp-structured-bd-slot` using `extractStructuredBdContentsFromHtml(...)`.
- Extracted BD content is sent to the backend as `structured_bd_contents`.
- Backend save logic in `AngleTemplateController.php` can sync structured BD content records.
- `AngleTemplateMergeService.php` already renders structured BD content back into matching theme placeholders.
- This means Flex Box HTML can be preserved inside BD content if inserted inside the correct BD wrapper.

#### Current Duplicate And Export Findings

- Normal landing page duplicate through `AngleTemplateController::duplicateAngleTemplate(...)` copies:
  - `main_html`
  - `main_css`
  - `main_js`
  - `content_mode`
  - `structured_version`
  - structured BD content rows from `bdContents()`
  - referenced local assets through `LandingPageAssetService`
- This means Flex Box structure should duplicate correctly through the normal landing page duplicate flow if Flex Box HTML/CSS is saved in `main_html`, `main_css`, or structured BD rows.
- Export/download uses the landing page `main_html`, `main_css`, and `main_js` when building the exported HTML package.
- Export asset preparation also scans page HTML/CSS/JS for storage references, so Flex Box images/assets should export if they use supported storage paths.
- Organization member clone through `AngleTemplateCloneService::cloneIntoOrgForUser(...)` currently copies `main_html`, `main_css`, and `main_js`, but does not currently copy `content_mode`, `structured_version`, or structured BD rows.
- Super Admin cross-organization clone currently copies the main row and extra contents, but its child clone path does not currently copy structured BD rows.
- For Flex Box specifically, HTML/CSS stored directly in `main_html/main_css` should still travel through these clone paths.
- For structured BD editing after organization clone/cross-org clone, we should add/verify BD row cloning in a later phase if the source page is structured.

#### Flex Box Feasibility Finding

- Basic Flex Box can be implemented as normal HTML inside the editor first.
- Each Flex Box should use stable identifiers/classes, for example:
  - `lp-flex-box`
  - `data-lp-flex-id`
  - `lp-flex-column`
  - `data-lp-flex-column`
- Internal CSS must be scoped to the specific Flex Box instance to avoid affecting theme CSS globally.
- Editor-only controls should use `doNotAct` and/or dedicated editor-only classes so they do not interfere with save, public preview, or exported HTML.
- To make duplication/export reliable, Flex Box runtime structure should be stored in page HTML and Flex Box runtime CSS should be stored in `main_css` or as scoped CSS included with the saved page.
- Flex Box editor-only controls should not be required for public rendering. Only stable `data-*` metadata needed for future editing should remain.

#### Phase 0 Result

- No application behavior was changed.
- No database command was run.
- Flex Box implementation is feasible without a database structural change at the first stage.
- Main implementation work will be in `PreviewAngleTemplate.jsx`.
- Backend changes may be needed later for organization clone/cross-org clone if structured BD rows must remain editable after clone.
- Backend changes may also be needed later if we decide to store Flex Box CSS separately instead of embedding/scoping it inside page HTML or `main_css`.

---

## Phase 1: Simplify Add Element Position Options

### Tasks

- Remove `Left` option from the Add/Edit modal placement UI.
- Remove `Right` option from the Add/Edit modal placement UI.
- Keep `Top` and `Bottom` options unchanged.
- Ensure existing code paths for Top and Bottom still work.
- Keep old Left/Right insertion helper code untouched initially if removing it has risk.

### Output

- Normal element insertion only shows Top and Bottom.
- Users no longer create side-by-side layouts through direct Left/Right insertion.

### Validation

- Add paragraph on Top.
- Add paragraph on Bottom.
- Add image on Top/Bottom.
- Add button on Top/Bottom.
- Test inside a structured BD container.
- Test outside a BD container.

### Phase 1 Implementation Notes

Status: completed.

#### What Changed

- Removed the visible `Left Side` button from the Add Element position step.
- Removed the visible `Right Side` button from the Add Element position step.
- Kept `Top Side` and `Bottom Side` unchanged.
- Kept the lower-level `left` and `right` insertion branches inside `addNewContentHandler(...)` untouched for backward compatibility and lower-risk rollout.

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`

#### Expected Behavior

- When user clicks `Add Element`, the position step now only shows:
  - `Top Side`
  - `Bottom Side`
- Existing Top/Bottom insertion behavior remains the same.
- Side-by-side layout will be handled later through the new `Add Flex Box` flow instead of direct Left/Right insertion.

#### Validation

- Frontend build should pass after this change.
- Manual UI check should confirm `Left Side` and `Right Side` no longer appear.

---

## Phase 2: Button URL Support

### Tasks

- Add a URL input field in the Button add/edit modal.
- Store button URL in button state.
- When adding a button with URL, wrap it in an anchor tag or apply a click-safe link approach.
- When editing an existing button, detect existing URL if the button is already inside an anchor.
- Preserve existing button styling behavior.
- Validate empty URL behavior.

### Output

- User can create a linked button.
- User can edit button URL later.
- Existing buttons without links continue working normally.

### Validation

- Add button without URL.
- Add button with internal URL.
- Add button with external URL.
- Edit existing linked button URL.
- Remove URL from existing linked button.
- Confirm save and preview preserve the link.

### Phase 2 Implementation Notes

Status: completed.

#### What Changed

- Added `buttonLink` to button editor state.
- Added `Button URL` input to the Button add/edit modal.
- When adding a button with a URL, the generated button is wrapped in an anchor tag.
- When editing a button, the editor detects an existing parent anchor URL.
- Editing can update the button URL.
- Clearing the URL removes the parent anchor and keeps the button element.

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`

#### Expected Behavior

- Button without URL remains a normal button.
- Button with URL becomes clickable through an anchor wrapper.
- Existing linked buttons can be edited without losing the link.

---

## Phase 3: Image Width Option

### Tasks

- Add image width input to Image add/edit modal.
- Support width values such as `%` and `px`.
- Apply width only when user provides a value.
- Detect current image width while editing existing images.
- Avoid forcing default width if user does not set it.
- Ensure uploaded images and URL images both support width.

### Output

- User can control image width from the editor.
- Existing images keep their current behavior if width is not changed.

### Validation

- Add image with no width.
- Add image with `100%` width.
- Add image with fixed `300px` width.
- Edit existing image width.
- Test image inside BD content.
- Test image after theme switching.

### Phase 3 Implementation Notes

Status: completed.

#### What Changed

- Added visible `Width` input to the Image add/edit modal.
- Image width supports free-form CSS values such as `100%`, `300px`, `50vw`, etc.
- New images apply width only when the user provides a value.
- Editing an existing image detects current inline/computed width.
- Clearing width removes the inline width from the edited image.
- Image preview uses the configured width when available.

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`

#### Expected Behavior

- Image width can be controlled directly from the modal.
- Images without a width value are not forced to a default inline width.
- Width is preserved when saved as part of page/BD HTML.

---

## Phase 4: Text Type Support

### Tasks

- Add Text Type selector in the text add modal.
- Options: `Paragraph` and `Heading`.
- If Paragraph is selected, create a `p` tag.
- If Heading is selected, show Heading Level dropdown.
- Heading level options: `H1`, `H2`, `H3`, `H4`, `H5`, `H6`.
- Create the selected heading tag when adding text.
- Preserve edit behavior for existing paragraph/heading elements.
- Avoid forcing old computed inline styles onto new text elements unless user explicitly styles them.
- For Paragraph type, inherit safe/reusable classes from the clicked text element.
- For Heading type, do not blindly inherit paragraph classes from the clicked element.
- If the clicked/source element is also a heading, allow safe heading class inheritance.
- If the clicked/source element is a paragraph or normal text, create the heading tag without paragraph classes so the active theme can style `h1`-`h6` naturally.
- Keep internal/editor-only classes excluded from inheritance.

### Output

- User can add semantic paragraph or heading content.
- Headings render as real heading tags, not plain text.
- Newly added paragraphs can match nearby paragraph styling through class inheritance.
- Newly added headings should not accidentally look like paragraphs because of copied paragraph classes.

### Validation

- Add paragraph.
- Add H1.
- Add H2-H6.
- Click a paragraph, then add a paragraph and confirm paragraph classes are inherited.
- Click a paragraph, then add H1 and confirm paragraph styling is not blindly copied.
- Click an existing heading, then add another heading and confirm safe heading class inheritance works.
- Save and reopen preview.
- Test inside structured BD content.
- Test after theme switching.

### Phase 4 Implementation Notes

Status: completed.

#### What Changed

- Added `textType` to text editor state.
- Added `headingLevel` to text editor state.
- Added `Text Type` dropdown in Add Text mode.
- Text Type options:
  - `Paragraph`
  - `Heading`
- Added `Heading Level` dropdown when `Heading` is selected.
- Heading Level options:
  - `H1`
  - `H2`
  - `H3`
  - `H4`
  - `H5`
  - `H6`
- Paragraph creates a real `p` tag.
- Heading creates the selected real heading tag, such as `h1`, `h2`, etc.
- Paragraphs can inherit safe classes from the clicked element.
- Headings do not blindly inherit paragraph classes.
- Headings only inherit source classes when the clicked/source element is also a heading.
- If a link is provided, the paragraph/heading element is wrapped in an anchor instead of replacing the semantic tag with only an `a` tag.

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`

#### Expected Behavior

- Users can add semantic paragraphs and headings.
- Headings should rely on active theme heading styles unless added after another heading.
- Newly added heading content should not accidentally look like paragraph text because of copied paragraph classes.
- Linked headings/paragraphs should preserve their semantic tag inside the anchor wrapper.

#### Phase 4 Styling Refinement

- Added an intentional `Style Source` choice to the Add Element flow.
- Default option remains `Use Theme/Class Style` when the clicked element does not have inline style.
- If the clicked element has inline style, the Add Element flow defaults to `Convert Selected Inline Style To Class`.
- Added optional `Convert Selected Inline Style To Class`.
- If selected, the system reads the clicked element's inline style, generates a reusable internal CSS class, and applies that class to the new element.
- Generated inline-style CSS is scoped and stored with the page/BD content so save, duplicate, export, and theme switching can preserve it.
- This avoids copying raw inline styles into each new element, while still preserving the visual style by using a reusable internal class when inline-only styling is the selected source.
- Style/class reuse is type-aware: text styles only apply to text, heading styles only apply to headings, image styles only apply to images, and button styles only apply to buttons.
- This prevents cases like adding an image from a clicked paragraph and accidentally applying paragraph color/font styles to the image.

---

## Phase 5: Basic Add Flex Box Option In Action Center

### Tasks

- Add `Add Flex Box` button to the first Action Center modal where `Edit Element`, `Delete Element`, and `Add Element` are shown.
- Match the Action Center layout with the shared design image: `Edit Element`, `Delete Element`, `Add Element`, and `Add Flex Box` shown as separate action buttons.
- Keep `Add Element` for normal content such as text, image, button, custom HTML, form, and spacer.
- Keep `Add Flex Box` separate because it is a layout/container builder, not a simple element.
- Create a new Flex Box configuration panel/modal based on the provided Flex Box design screens.
- Allow selecting position: Top or Bottom only.
- Allow setting number of columns from 1 to 6.
- Generate Flex Box HTML structure.
- Add stable editor classes/data attributes to identify Flex Box containers and columns.
- Add editor-only empty column add buttons/placeholders.
- Ensure generated Flex Box can be inserted inside the selected BD container when user is editing inside BD content.

### Output

- Action Center shows a separate `Add Flex Box` action beside the normal `Add Element` action.
- User can add a basic Flex Box with columns.
- Flex Box is saved as part of the page/BD HTML.

### Validation

- Click an editable element and confirm the Action Center shows `Edit Element`, `Delete Element`, `Add Element`, and `Add Flex Box`.
- Click `Add Element` and confirm the existing normal element flow opens.
- Click `Add Flex Box` and confirm the Flex Box configuration flow opens.
- Add 1-column Flex Box.
- Add 2-column Flex Box.
- Add 3-column Flex Box.
- Add 6-column Flex Box.
- Add Flex Box inside BD1.
- Confirm Flex Box stays inside BD1 after save.

### Phase 5 Implementation Notes

Status: completed.

#### What Changed

- Added a separate `Add Flex Box` action to the first Action Center screen.
- Kept the existing `Add Element` flow unchanged for text, image, button, custom HTML, form, and spacer.
- Added a basic Flex Box configuration panel with:
  - Position: `Top` or `Bottom`
  - Columns: `1` through `6`
- Generated stable Flex Box markup using:
  - `lp-flex-box-wrapper`
  - `lp-flex-box`
  - `data-lp-flex-id`
  - `data-lp-flex-columns`
  - `lp-flex-column`
  - `data-lp-flex-column`
- Added basic empty-column editor placeholders using:
  - `lp-flex-column-add-button`
  - `data-lp-editor-only="true"`
- Inserted Flex Box through the existing `addNewContentHandler(...)` path so structured BD placement remains safe.
- When added inside a structured BD slot, the Flex Box is saved inside that BD content instead of being placed outside the slot.
- Normalized Flex Box position handling so changing the column count cannot reset a selected `Bottom` position back to `Top`.
- For structured BD content, Flex Box insertion uses the clicked visible element as the placement anchor, while the existing BD-aware save path keeps the new Flex Box attached to the correct BD slot.
- BD slots that contain Flex Box content receive `lp-structured-bd-slot-has-flexbox` so the slot becomes a real visual container only when needed; normal BD slots remain unaffected.

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`

#### Expected Behavior

- The Action Center now shows `Add Flex Box` separately from `Add Element`.
- Users can add a basic 1-6 column Flex Box above or below the clicked target.
- In structured BD pages, Flex Box content remains attached to the correct BD slot.
- Advanced desktop/mobile style controls are intentionally left for Phase 6 and Phase 7.

---

## Phase 5.1: Delete Flex Box And Delete Column

### Tasks

- Add an outer wrapper around every Flex Box.
- Add a visible editor-only cross icon on the outer Flex Box wrapper.
- Add a visible editor-only cross icon on each column's `Add content` placeholder box.
- Confirm before deleting the full Flex Box.
- Confirm before deleting a single Flex Box column.
- Delete the full `.lp-flex-box-wrapper` from the main Flex Box cross icon.
- Delete only the selected `.lp-flex-column` from the column cross icon.
- Prevent deleting the last remaining column from a Flex Box.
- After deleting a column, update `data-lp-flex-columns` and renumber `data-lp-flex-column` values.
- Keep all delete operations inside the existing HTML history/save flow.
- Ensure structured BD content remains attached to the correct BD after deletion.
- Keep delete icons editor-only and do not store them as permanent landing page content.

### Output

- Users can remove a full Flex Box.
- Users can remove a single Flex Box column.
- Deleted Flex Box/column changes are preserved after save, duplicate, export, and theme switching through the existing HTML/BD content flow.

### Phase 5.1 Implementation Notes

Status: completed.

#### What Changed

- Added an outer `.lp-flex-box-wrapper` around each Flex Box.
- Added a visible wrapper boundary and editor-only cross icon on each `.lp-flex-box-wrapper` to delete the complete Flex Box.
- The full Flex Box delete icon is visually different from column delete icons so users can distinguish wrapper-level delete from column-level delete.
- The full Flex Box delete icon is placed on the outer wrapper boundary.
- Column delete icons are anchored to each `Add content` placeholder box when available, so they appear on the top-right corner of the actual Add Content box.
- If a column does not have an Add Content placeholder, the column delete icon falls back to the column container.
- Added click handling for `data-lp-flex-delete="box"` and `data-lp-flex-delete="column"`.
- Added confirmation before deleting a full Flex Box or a column.
- Column deletion keeps at least one column in the Flex Box.
- Remaining columns are renumbered after a column is deleted.
- Existing page history is updated after deletion so undo/save behavior remains consistent.
- Flex delete icons are stripped from stored page HTML so they do not become permanent landing page content.
- Existing Flex Boxes are wrapped and receive delete controls dynamically in the editor, so older test Flex Boxes do not need to be recreated.

#### Final Expected Behavior

- The outer wrapper cross deletes the complete Flex Box after confirmation.
- The column-level cross deletes only that specific column after confirmation.
- When a column is deleted, the remaining columns automatically realign through the existing Flex layout.
- Users cannot delete the last remaining column; they must delete the full Flex Box instead.
- Delete controls are visible only in the editor and are removed from saved/exported HTML.

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`
- `flexbox-landing-page.md`

---

## Phase 6: Desktop Flex Box Properties

### Breakdown

- `Phase 6.1`: Desktop size and spacing controls.
- `Phase 6.2`: Desktop flex layout controls.
- `Phase 6.3`: Desktop visual style controls.

### Tasks

- Match the desktop configuration UI with the provided design reference.
- Add desktop width field.
- Add desktop max-width field.
- Add desktop min-height field.
- Add flex direction selector.
- Add justify-content selector.
- Add align-items selector.
- Add wrap selector.
- Add gap value and unit fields.
- Add margin field.
- Add padding field.
- Add background field.
- Add border field.
- Add border-radius field.
- Add box-shadow field.
- Apply these settings to the Flex Box container.

### Output

- Flex Box supports desktop layout styling.
- Users can create basic responsive-ready layouts from editor UI.

### Validation

- Test row layout.
- Test column layout.
- Test gap.
- Test margin/padding.
- Test background/border/radius/shadow.
- Save and refresh preview.

### Phase 6.1 Implementation Notes

Status: completed.

#### What Changed

- Added Desktop Size fields to the Add Flex Box configuration panel:
  - Width
  - Width unit: `%` or `px`
  - Max Width in `px`
  - Min Height
  - Min Height unit: `px` or `vh`
- Added Desktop Spacing fields:
  - Margin
  - Padding
- Applied these values to newly created `.lp-flex-box` containers.

### Phase 6.2 Implementation Notes

Status: completed.

#### What Changed

- Added Desktop Layout controls:
  - Flex Direction: Row, Row Reverse, Column, Column Reverse
  - Justify Content: Start, Center, End, Space Between, Space Around, Space Evenly
  - Align Items: Start, Center, End, Stretch
  - Wrap: No Wrap, Wrap, Wrap Reverse
  - Gap
  - Gap unit: `px` or `%`
- Applied these controls to newly created `.lp-flex-box` containers.

### Phase 6.3 Implementation Notes

Status: completed.

#### What Changed

- Added Desktop Style controls:
  - Background
  - Border style
  - Border width
  - Border color
  - Border radius
  - Box shadow
- Background and Border Color use the same color palette picker style used elsewhere in the editor.
- Background and Border Color are placed together at the end of the Desktop Style section so normal input/select fields remain aligned.
- Added helper logic to safely convert form values and units into CSS values.
- Applied these style controls to newly created `.lp-flex-box` containers.

#### Current Scope

- Phase 6 applies desktop properties when creating a new Flex Box.
- Editing an existing Flex Box's desktop properties will be handled in a later editing phase.
- Mobile/responsive properties remain planned for Phase 7.

#### Files Updated

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`
- `flexbox-landing-page.md`

---

## Phase 7: Mobile Responsive Settings

### Tasks

- Match the mobile/responsive configuration UI with the provided design reference.
- Add Mobile settings section.
- Add mobile breakpoint setting, default `750px`.
- Support mobile number of columns/layout behavior.
- Support mobile flex direction.
- Support mobile justify-content.
- Support mobile align-items.
- Support mobile wrap.
- Support mobile gap.
- Generate scoped internal CSS for mobile behavior.
- Ensure CSS is scoped to the specific Flex Box instance.

### Output

- Flex Box supports separate desktop and mobile behavior.
- Mobile CSS does not affect other page sections or themes.

### Validation

- Desktop 3 columns, mobile 1 column.
- Desktop row, mobile column.
- Confirm breakpoint at 750px.
- Confirm multiple Flex Boxes on same page do not conflict.

---

## Phase 8: Edit Columns

### Tasks

- Add `Edit Columns` option for Flex Box containers.
- Match the Edit Columns UI with the provided design reference.
- Show list of generated columns.
- Allow editing each column width.
- Allow editing column margin.
- Allow editing column padding.
- Allow editing column background.
- Allow editing column border.
- Allow editing column border-radius.
- Allow editing column box-shadow.
- Apply column settings safely.
- Preserve column settings after save/reload.

### Output

- User can style each Flex Box column independently.
- Custom layouts like 50/50 or 40/30/30 become possible.

### Validation

- Edit column 1 width.
- Edit column 2 width.
- Apply different backgrounds per column.
- Apply column padding/border.
- Save and refresh preview.

---

## Phase 9: Add Element Inside Flex Columns

### Tasks

- Match the empty-column Add Element/Add Flex Box behavior with the provided design reference.
- Show editor-only Add option inside empty columns.
- Add Element inside selected column.
- Add Flex Box inside selected column if nested layout is required.
- Hide empty-column Add icon once content exists.
- Ensure added content is inserted into the correct column.
- Ensure added content inside a BD Flex Box still belongs to the parent BD.
- Ensure click/edit/delete behavior works for content inside columns.

### Output

- Flex Box columns become editable content areas.
- User can build multi-column layouts without using Left/Right insertion.

### Validation

- Add paragraph inside column.
- Add image inside column.
- Add button inside column.
- Add nested Flex Box inside column.
- Save and refresh preview.
- Switch theme and confirm BD-owned Flex Box content remains in correct BD.

---

## Phase 10: Save, Theme Switching, And BD Compatibility

### Tasks

- Confirm generated Flex Box markup follows the approved design and data-attribute structure.
- Confirm Flex Box HTML is saved in `main_html`.
- Confirm Flex Box inside structured BD is saved into the correct BD record.
- Confirm scoped internal CSS is preserved.
- Confirm theme switching moves Flex Box content with the correct BD.
- Confirm page-level Flex Box outside BD is preserved separately when applicable.
- Confirm duplicated landing pages carry the full Flex Box structure.
- Confirm exported landing pages carry the full Flex Box structure.
- Confirm duplicated/exported pages include required scoped Flex Box CSS and responsive CSS.
- Confirm Flex Box editor metadata needed for future edits is preserved through duplication.
- Confirm editor-only controls/icons are hidden or removed from duplicated/exported public output as required.
- Ensure editor-only controls are not visible in final/exported landing page if required.

### Output

- Flex Box works safely with structured BD content and theme switching.

### Validation

- Create landing page with Flex Box inside BD1.
- Add elements inside Flex Box columns.
- Save page.
- Switch theme.
- Confirm Flex Box remains inside BD1 content.
- Confirm mobile CSS still works after theme switch.
- Duplicate a landing page that contains Flex Box and confirm the duplicated page keeps:
  - Flex Box container structure
  - Flex Box columns
  - Column content
  - Scoped desktop CSS
  - Scoped mobile CSS
- Export a landing page that contains Flex Box and confirm the exported page keeps:
  - Flex Box container structure
  - Flex Box columns
  - Column content
  - Scoped desktop CSS
  - Scoped mobile CSS
- Confirm editor-only Add icons/buttons do not appear in the public/exported landing page.

---

## Phase 11: QA And Regression Testing

### Tasks

- Test legacy landing page editing.
- Test structured BD landing page editing.
- Test Add/Edit/Delete old element types.
- Test Button URL.
- Test Image width.
- Test Text paragraph/heading.
- Test Flex Box creation.
- Test Flex Box column editing.
- Test nested Flex Box.
- Test theme switching after Flex Box edits.
- Test mobile responsiveness.
- Test save/reload behavior.

### Output

- QA checklist completed.
- Any issues documented and fixed before client testing.

### Validation

- Frontend build passes.
- Focused backend tests pass if backend code is touched.
- Manual UI testing completed on local/dev.
- No destructive database commands used.

---

## Suggested Delivery Order

1. Complete Phases 0-4 first because they are smaller and safer.
2. Release/test modal improvements.
3. Start Flex Box with basic structure in Phase 5.
4. Add desktop settings.
5. Add mobile settings.
6. Add column editor.
7. Add nested Add Element support.
8. Perform full BD/theme-switching QA.

## Key Risks

- Internal CSS must be scoped per Flex Box instance to avoid affecting the full theme.
- Nested Flex Boxes can become complex, so initial nesting should be simple and controlled.
- Editor-only icons/buttons must not appear in public/exported pages.
- Flex Box content inside BD containers must remain attached to the correct BD during theme switching.
- Existing legacy pages should not be forced into the new Flex Box behavior.

## Final Acceptance Criteria

- Normal Add Element only supports Top and Bottom placement.
- Button elements can be linked to URLs.
- Image elements support width control.
- Text elements can be added as Paragraph or H1-H6 headings.
- Flex Box can be added as a structured multi-column layout.
- Flex Box supports desktop and mobile settings.
- Flex Box columns can be edited individually.
- Elements can be added inside Flex Box columns.
- Flex Box content is preserved during save, refresh, and theme switching.
- No existing landing page functionality is broken.
