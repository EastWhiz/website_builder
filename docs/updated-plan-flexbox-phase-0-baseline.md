# Updated Flexbox Plan: Phase 0 Baseline

Date: 2026-08-27

Scope: Baseline review only. No implementation changes were made in this phase.

## Files Reviewed

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`
- `resources/js/Pages/Users/UserThemes.jsx`
- `app/Services/AngleTemplateMergeService.php`
- `app/Http/Controllers/AngleTemplateController.php`
- `routes/web.php`
- `updated-plan-flexbox-changes-additions.md`

## Current Frontend Editing Structure

Main editor file:

- `resources/js/Pages/AngleTemplates/PreviewAngleTemplate.jsx`

Important state blocks:

- `INITIAL_IMAGE_MANAGEMENT`
- `INITIAL_TEXT_MANAGEMENT`
- `INITIAL_BUTTON_MANAGEMENT`
- `INITIAL_FORM_MANAGEMENT`
- `INITIAL_FLEX_BOX_MANAGEMENT`
- `INITIAL_FLEX_COLUMN_MANAGEMENT`

Current Add/Edit element support:

- Text supports paragraph/heading selection.
- Text supports heading level selection from `h1` to `h6`.
- Text currently has color, background, font size, font weight, link, border, margin, padding, and text alignment fields.
- Image supports source/upload, link, border, margin, padding, and width.
- Button supports button text, URL, colors, font size, margin, padding, and border.
- Custom HTML supports raw HTML input.
- Add Element currently supports only top and bottom placement.

Important Add/Edit handler area:

- Add/Edit logic is handled around the main submit/action block in `PreviewAngleTemplate.jsx`.
- Save history is updated through `serializablePreviewHtml`.
- Structured BD extraction is handled by `extractStructuredBdContentsFromHtml`.

## Current Flexbox Structure

Flexbox creation already exists.

Current Flexbox classes/data attributes:

- `.lp-flex-box-wrapper`
- `.lp-flex-box`
- `.lp-flex-column`
- `.lp-flex-column-add-button`
- `.lp-flex-delete-control`
- `data-lp-flex-id`
- `data-lp-flex-columns`
- `data-lp-flex-column`
- `data-lp-flex-responsive-style`

Current Flexbox options:

- Position: top/bottom.
- Columns: 1-6.
- Width and width unit.
- Max width.
- Min height and unit.
- Flex direction.
- Flex wrap.
- Justify content.
- Align items.
- Gap and gap unit.
- Margin.
- Padding.
- Background color.
- Border style.
- Border width.
- Border color.
- Border radius.
- Box shadow.
- Mobile breakpoint.
- Mobile columns.
- Mobile flex direction.
- Mobile wrap.
- Mobile justify content.
- Mobile align items.
- Mobile gap and unit.

Current column options:

- Selected column.
- Width and unit.
- Margin.
- Padding.
- Background color.
- Border style.
- Border width.
- Border color.
- Border radius.
- Box shadow.
- Text alignment.
- Vertical alignment.

Known current gap:

- There is an `Edit Columns` option, but there is no full `Edit Flex Box` option yet for editing wrapper/box settings after creation.

## Current Editor-Only Cleanup

Frontend cleanup:

- `sanitizeEditorOnlyPreviewMarkup` removes `.lp-flex-delete-control`.
- It also removes `[data-lp-editor-only="true"]`.
- `serializablePreviewHtml` uses this cleanup before saving editor HTML into history.

Backend cleanup:

- `AngleTemplateMergeService::removeEditorOnlyMarkup` removes:
  - `[data-lp-editor-only="true"]`
  - `.lp-flex-delete-control`

Backend paths already using cleanup:

- Rendered landing page save.
- Structured BD content extraction from rendered save.
- Duplicate landing page.
- Structured/theme-switch processing where relevant.

Important note:

- Cleanup currently removes controls, but dashed borders are normal inline styles on wrappers/columns/placeholders. They are not fully editor-only yet, so Phase 5 must handle final preview/export border cleanup without removing user-selected real borders.

## Current Preview and Landing Page List Behavior

Main list file:

- `resources/js/Pages/Users/UserThemes.jsx`

Routes:

- Preview route renders `AngleTemplates/PreviewAngleTemplate`.
- `previewAngleTemplate` route is `/angle-templates/preview/{id}`.

Current behavior:

- `openLandingPreview` opens `/angle-templates/preview/{id}/` in a new tab.
- In the non-organization list, both the edit icon and preview icon currently call `openLandingPreview`.
- This likely explains the report comment that Preview opens the editor, because the preview route currently loads the same editor-capable preview page.

Phase 7 decision needed:

- Either add a true read-only/final preview mode to the existing preview route.
- Or add a separate final-preview route/action that renders without editor toolbar, action modal, BD editor button, and editor-only helpers.

## Current Save, Duplicate, Export, and Theme Switch Touchpoints

Save:

- Frontend save uses `updatedThemeSaveHandler`.
- It sends `main_html`.
- In structured BD mode, it also sends `structured_bd_contents`.
- Backend save is in `AngleTemplateController`.

Duplicate:

- `AngleTemplateController::duplicateAngleTemplate` clones the landing page.
- It already calls `removeEditorOnlyMarkup` for copied `main_html`.

Export:

- `AngleTemplateController::downloadTemplate` handles export.
- Structured BD pages are rendered/persisted before export through `renderAndPersistStructuredBd`.
- Export asset preparation happens through `LandingPageAssetService`.

Theme switch:

- Theme switch is handled through `AngleTemplateController::changeTheme`.
- Structured rendering and content preservation are handled through `AngleTemplateMergeService`.

## Risk Areas for Later Phases

- Do not treat `0`, `0px`, or empty string the same. Some reported spacing issues may come from falsy checks.
- Do not remove user-selected dashed borders from actual content when hiding editor-only dashed borders.
- Flexbox wrapper/column default dashed borders are currently written inline, so separating editor border from user border needs care.
- Adding Font Family support must not globally override theme fonts.
- Removing or changing `.font-sans` must be checked carefully because it may affect layout/UI outside the landing page preview.
- Edit Flex Box must preserve existing column content when increasing columns.
- Reducing column count needs a safe UX decision before content is removed.
- Theme switch, duplicate, export, and save must continue preserving:
  - BD-owned Flexboxes.
  - Page-level Flexboxes.
  - Responsive Flexbox style tags.
  - Structured BD content order.

## Phase 0 Result

Phase 0 is complete.

No code behavior was changed.
No database command was run.
No destructive command was run.

