# Updated Theme Switching Development Plan

## Structured BD Editing and Safer Theme Switching Roadmap

### Purpose

This plan defines a safer implementation path for the new theme switching architecture accepted by the client. The goal is to stop relying only on final rendered HTML and gradually move toward structured BD content while keeping the existing live system stable.

## Guiding Principles

- Do not modify old landing pages, old angles, or old themes during the first implementation.
- Create new records for the structured BD flow instead of converting existing records immediately.
- Keep the current rendered HTML-based flow working for existing pages.
- Build and test the new flow separately before replacing or promoting it.
- Use duplicate-first and safe fallback behavior only for legacy pages where structured BD data is not available.
- Never run database-destructive commands during development or testing.

## Phase 0: Code Branch and Baseline Safety

Objective: prepare a safe workspace for the structured BD implementation without disturbing the current working code.

Tasks:

- Create a new Git branch for this work.
- Record current behavior of theme switching, page creation, preview, editor save, export, and translation.
- Identify the exact Blade views, JS files, controllers, services, and routes involved in landing page creation and editing.
- Do not duplicate every code file blindly; only create new files where a separate structured BD flow is needed.
- Confirm that existing tests still pass before starting implementation.

Deliverable: baseline notes and a list of files/modules that will be touched.

## Phase 1: Structured BD Data Model Design

Objective: define how BD content and sub-slots will be stored for new structured landing pages.

Tasks:

- Define parent BD sections: `BD1`, `BD2`, `BD3`, `BD4`, `BD5`.
- Define optional sub-slots inside BDs, for example `BD2_HEADER`, `BD2_SUBHEADER`, `BD2_BANNER`, `BD3_PUBLISHER`.
- Decide whether structured data will be stored in a new table or a JSON column associated with the landing page.
- Design a version field so the system can identify structured-BD pages versus legacy pages.
- Define default content for full BD placeholders when sub-slots exist.

Deliverable: approved structured BD schema and placeholder contract.

## Phase 2: New Structured Records Only

Objective: create new records for structured BD testing without changing existing pages, angles, or themes.

Tasks:

- Create new test angles/content records for structured BD data.
- Create new structured-compatible theme records or new theme versions.
- Create new landing page records that reference structured BD content.
- Keep old records unchanged and continue serving them through the current legacy flow.
- Add clear labels/names for structured test records so they are not confused with production legacy pages.

Deliverable: isolated structured BD test dataset.

## Phase 3: BD-Based Editor UI

Objective: create a controlled editor where users update content by BD section instead of freely editing the entire rendered page.

Tasks:

- Create a new BD editor view or editor mode.
- Show editable sections for `BD1` to `BD5`.
- Show sub-slot fields inside each BD where needed, such as Header, Subheader, Banner, Publisher, Body, Image, CTA.
- Allow image/content upload inside the correct BD or sub-slot.
- Save structured BD data separately from rendered HTML.
- Keep the current full-page editor available for legacy pages.

Deliverable: structured BD editor working for new structured pages.

## Phase 4: Renderer for Structured BD Pages

Objective: rebuild landing page HTML from structured BD data plus selected theme.

Tasks:

- Build a renderer that injects `BD1` to `BD5` content into matching placeholders.
- Support sub-slot placeholders such as `BD2_HEADER` and `BD2_BANNER`.
- If a theme requests a full BD, render the full/default BD content.
- If a theme requests a sub-slot, render only that sub-slot content.
- Preserve target theme layout, CSS, JS, image paths, and fonts.
- Do not carry old theme-specific wrappers or styling into the new theme.

Deliverable: structured renderer that produces final `main_html`, `main_css`, and `main_js`.

## Phase 5: Structured Theme Switching Flow

Objective: make theme switching reliable for structured pages.

Tasks:

- When structured BD data exists, rebuild using structured BD content plus the selected theme.
- Create a new landing page record for the switched theme result.
- Keep the original structured page unchanged.
- Return the new landing page ID to the frontend.
- Frontend must open the returned new ID, not the original ID.
- For legacy pages without structured data, keep the current safe fallback behavior.

Deliverable: reliable theme switching for structured pages without reverse-extracting from final HTML.

## Phase 6: Preview, Save, Export, and Translation Compatibility

Objective: make sure structured pages still support all existing landing page functionality.

Tasks:

- Preview structured pages after create, edit, and theme switch.
- Save BD editor changes and verify both structured data and rendered output update correctly.
- Export/download structured pages and verify assets, CSS, JS, and integrations.
- Test translation and confirm translated content updates structured BD fields.
- Test image uploads and asset cloning for structured pages.

Deliverable: QA checklist passed for core landing page flows.

## Phase 7: Legacy Page Strategy

Objective: decide how old pages should behave without forcing risky migration.

Tasks:

- Keep old pages on the current legacy flow by default.
- If a legacy page cannot be safely mapped, continue using Safe Content Preservation Mode.
- Add a clear frontend message explaining that the old page was preserved because it could not be safely mapped.
- Optionally provide a manual "Recreate as structured page" action later.
- Do not automatically overwrite old records in the first release.

Deliverable: clear policy for legacy pages and client-approved migration options.

## Phase 8: Pilot Rollout

Objective: test the structured flow with a small controlled set before broad rollout.

Tasks:

- Select two or three new structured themes.
- Create structured test pages with obvious BD labels.
- Test switching between compatible structured themes.
- Test sub-slot themes such as `BD2_HEADER`, `BD2_BANNER`, and `BD3_PUBLISHER`.
- Ask frontend/client to validate expected behavior and user experience.

Deliverable: pilot approval before migration or wider release.

## Phase 9: Controlled Production Rollout

Objective: release structured theme switching safely.

Tasks:

- Enable structured BD flow for new pages only.
- Keep legacy pages on old flow unless manually recreated.
- Monitor safe fallback frequency and user feedback.
- Document how frontend developers should create structured-compatible themes.
- Plan old page migration only after structured flow is stable.

Deliverable: production-ready structured BD theme switching for new pages.

## Acceptance Criteria

- Existing pages, angles, and themes remain unchanged during development.
- New structured pages can switch themes without relying on final HTML extraction.
- User content edits are preserved through structured BD fields.
- Old theme layout and styling are not carried into the new theme.
- Sub-slots render correctly where target themes request them.
- Preview, editor save, export, translation, and images continue working.

## Recommended Client Message

We will implement the new structured BD theme-switching flow safely by creating new records and a parallel structured flow first. Existing landing pages, angles, and themes will remain untouched. Once the new flow is tested and approved, we can gradually use it for new pages and later decide how to handle legacy pages.
