# Updated Plan: Flexbox Changes and Additions

This plan is based on the latest Add/Edit Element and Flexbox testing report. We will implement the work in the safest order: first fixes, then existing behavior changes, then new feature additions.

Core rules for this work:

- Do not disturb existing landing page/theme switching behavior.
- Do not run any database refresh, wipe, or destructive database command.
- Keep editor-only UI separate from preview/export output.
- Test each phase before moving to the next one.
- Preserve existing BD-based content, page additions, and Flexbox structures during save, duplicate, export, and theme switching.

## Phase 0: Baseline Review and Safety Check

Goal: Confirm the current implementation points before changing anything.

Status: Completed. Notes are available in `docs/updated-plan-flexbox-phase-0-baseline.md`.

Tasks:

- Review Add/Edit Element modal code and identify where text, image, button, and custom HTML options are handled.
- Review Flexbox creation code and identify where desktop/mobile styles are generated.
- Review save/export/duplicate/theme-switch cleanup logic for editor-only controls.
- Review preview button behavior and route usage.
- Capture current behavior with one local test landing page before making changes.
- Note existing selectors/classes used for Flexbox wrappers, columns, delete controls, BD slots, and page additions.

Deliverable:

- Short implementation notes confirming which files/functions will be touched.

Validation:

- No code changes yet.
- No database-destructive command.

## Phase 1: Fix Add/Edit Element Style Application

Goal: Fix existing style fields that are not applying correctly.

Status: Completed. Notes are available in `docs/updated-plan-flexbox-phase-1-style-application.md`.

Tasks:

- Fix margin application for newly added elements.
- Fix padding application for newly added elements.
- Fix border application for newly added elements.
- Confirm values are applied consistently for text, heading, button, image, and custom HTML where applicable.
- Ensure empty fields do not generate broken inline CSS.
- Ensure `0` values are respected and not treated as empty.

Validation:

- Add text with margin/padding/border and verify DOM styles.
- Add heading with margin/padding/border and verify DOM styles.
- Add image/button with supported spacing/border fields and verify DOM styles.
- Save and reopen the page to confirm styles persist.

## Phase 2: Fix Image Width Persistence

Goal: Make image width stable in editor, preview, duplicate, and export.

Status: Completed. Notes are available in `docs/updated-plan-flexbox-phase-2-image-width.md`.

Tasks:

- Ensure image width defaults to `100%` when the user does not enter a width.
- Save the default `100%` width explicitly so export does not lose it.
- Confirm custom image width still works when user enters a value.
- Confirm image width does not get overwritten during theme switch or duplication.

Validation:

- Add image without width and verify `width: 100%` is saved.
- Add image with custom width and verify custom value is saved.
- Export or preview final page and confirm image width remains correct.

## Phase 3: Fix Text Styling Inside Flexbox

Goal: Ensure text added inside Flexbox respects selected style options.

Status: Completed. Notes are available in `docs/updated-plan-flexbox-phase-3-flexbox-text-styling.md`.

Tasks:

- Fix font size application for text added inside Flexbox columns.
- Fix font weight application for text added inside Flexbox columns.
- Remove unwanted automatic top/bottom margin from text inside Flexbox.
- Ensure paragraph and heading behavior remains separate.
- Ensure inherited class/style behavior only applies when source and new element types are compatible.

Validation:

- Add paragraph inside Flexbox with selected font size/weight.
- Add heading inside Flexbox with selected heading type and selected style values.
- Confirm no unexpected default margin is added.
- Confirm styles persist after save/reopen.

## Phase 4: Fix Flexbox and Column Spacing

Goal: Respect margin/padding values for Flexbox wrappers and columns.

Status: Completed. Notes are available in `docs/updated-plan-flexbox-phase-4-flexbox-spacing.md`.

Tasks:

- Fix Flexbox wrapper margin handling.
- Fix Flexbox wrapper padding handling.
- Fix column margin/padding handling where supported.
- Ensure `0px` margin and `0px` padding are actually applied when selected.
- Confirm desktop/mobile values do not conflict with each other.

Validation:

- Create Flexbox with `0` margin and padding.
- Create Flexbox with custom margin and padding.
- Add content into columns and confirm layout remains stable.
- Save/reopen and verify spacing remains correct.

## Phase 5: Fix Editor-Only Borders and Controls in Preview/Export

Goal: Keep editing helpers visible only in editor mode.

Status: Completed. Notes are available in `docs/updated-plan-flexbox-phase-5-editor-only-cleanup.md`.

Tasks:

- Ensure dashed Flexbox/column borders show in editor mode only.
- Hide/remove dashed borders in actual preview.
- Hide/remove delete/cross controls in actual preview.
- Hide/remove editor-only controls during export.
- Confirm cleanup does not remove real user content inside Flexboxes.

Validation:

- Open editor and confirm dashed borders and delete controls are visible.
- Open actual preview and confirm dashed borders/delete controls are not visible.
- Export page and confirm exported HTML is clean.
- Confirm Flexbox content still appears correctly after cleanup.

## Phase 6: Change Popup Actions After Save

Goal: Adjust the action popup behavior after the landing page is saved.

Tasks:

- Identify saved structured BD/editor mode state.
- After save, show only:
  - Add Element
  - Add Flex Box
- Hide Edit/Delete options where they should no longer be available after save.
- Ensure legacy pages keep their expected behavior if they are not using the structured BD flow.

Validation:

- Save a structured landing page.
- Click an element and verify only Add Element/Add Flex Box are shown.
- Test legacy page behavior to ensure it is not accidentally broken.

## Phase 7: Change Preview Landing Page Button Behavior

Goal: Make Preview Landing Page open the actual preview instead of the editor.

Tasks:

- Identify the current Preview Landing Page button route/action.
- Change it to open the real preview URL.
- Prefer opening preview in a new tab if consistent with current UX.
- Ensure editor page remains available separately.

Validation:

- Click Preview Landing Page.
- Confirm it opens actual landing page preview, not editor mode.
- Confirm no editor-only controls appear in that preview.

## Phase 8: Add Border Radius and Border Side Controls

Goal: Add requested border styling controls.

Tasks:

- Add Border Radius input/dropdown/control to Add/Edit Element styling options.
- Add Border Side dropdown with:
  - All Sides
  - Top
  - Right
  - Bottom
  - Left
- Generate correct CSS based on selected border side.
- Ensure border width/style/color work with selected border side.
- Ensure existing border behavior remains backward compatible.

Validation:

- Add element with all-side border.
- Add element with top-only border.
- Add element with left/right/bottom border.
- Add border radius and verify it persists after save/reopen.

## Phase 9: Add Font Family Support

Goal: Let user select font family instead of relying only on inherited/default font.

Tasks:

- Add Font Family dropdown in Add/Edit styling options.
- Include theme fonts already detected/used in the selected theme where feasible.
- Add predefined Google font choices:
  - Inter
  - Roboto
  - Poppins
  - Montserrat
  - DM Sans
  - Source Sans 3
  - Playfair Display
  - Lora
  - Merriweather
  - Oswald
- Ensure selected font is applied to newly added text/heading.
- Ensure selected font persists after save/reopen.
- Remove or neutralize automatic `.font-sans` override if it conflicts with selected/theme fonts.

Validation:

- Add paragraph with selected font.
- Add heading with selected font.
- Add text inside Flexbox with selected font.
- Confirm theme font is not overridden by default `Figtree`.
- Confirm no layout regression on existing pages.

## Phase 10: Add Edit Flex Box Option

Goal: Allow users to edit an existing Flexbox after creation.

Tasks:

- Add an Edit Flex Box action when user selects/clicks an existing Flexbox wrapper.
- Load existing Flexbox settings into the modal.
- Support editing:
  - Number of columns
  - Flex direction
  - Gap
  - Width
  - Desktop settings
  - Mobile settings
  - Margin
  - Padding
  - Background
  - Border
  - Border radius
  - Alignment
- Preserve existing content inside columns when possible.
- If reducing column count, decide safely how removed column content is handled before deleting it.
- Update responsive/internal CSS for the edited Flexbox.

Validation:

- Create Flexbox, save, reopen, then edit it.
- Change gap/direction/alignment and verify layout changes.
- Change columns from 2 to 3 and confirm existing content remains.
- Change columns from 3 to 2 and confirm behavior is safe and predictable.
- Save/reopen and verify edited Flexbox persists.

## Phase 11: Save, Duplicate, Export, and Theme Switch Regression

Goal: Ensure all fixes/changes/new features survive core landing page flows.

Tasks:

- Test save/reopen after Add/Edit Element changes.
- Test duplicate page after Add/Edit Element changes.
- Test export after Add/Edit Element changes.
- Test theme switch after Add/Edit Element changes.
- Test Flexbox edit persistence after save/reopen.
- Test Flexbox edit persistence after duplicate.
- Test Flexbox edit persistence after theme switch.
- Confirm page-level additions remain in correct BD/page location.
- Confirm no editor-only controls leak into preview/export.

Validation:

- Run focused unit tests related to `AngleTemplateMergeService`.
- Run frontend build.
- Execute manual browser QA on at least:
  - One structured BD page.
  - One page with Flexbox inside BD.
  - One page with page-level Flexbox.
  - One page after theme switch.

## Phase 12: Final QA and Client Testing Notes

Goal: Prepare the change for client QA.

Tasks:

- Prepare a short test checklist for the client.
- Mention which flows were changed:
  - Add/Edit Element styling.
  - Font family.
  - Border controls.
  - Preview behavior.
  - Flexbox edit.
  - Clean preview/export.
- Mention any known limitation or decision, especially around reducing Flexbox columns.
- Confirm no destructive DB command was used.

Deliverable:

- Final implementation summary.
- Test result summary.
- Client QA checklist.
