# Phase 3: Optional Sub-Slot Foundation

Date: 2026-06-15

## Objective

Phase 3 adds a backward-compatible foundation for themes that need separate parts of one BD rendered in different locations. It does not automatically split existing HTML and does not change any existing theme.

## Naming Contract

Plain body placeholders remain unchanged:

```html
<!--INTERNAL--BD1--EXTERNAL-->
<!--INTERNAL--BD2--EXTERNAL-->
```

Optional sub-slot placeholders use an uppercase suffix:

```html
<!--INTERNAL--BD2_HEADER--EXTERNAL-->
<!--INTERNAL--BD2_BANNER--EXTERNAL-->
<!--INTERNAL--BD3_PUBLISHER--EXTERNAL-->
```

The matching angle HTML content must use the exact same `name`, such as `BD2_HEADER`.

## Safety Rules

- Existing `BD1` to `BD5` themes remain backward-compatible.
- Existing legacy angle bodies without BD names retain positional mapping.
- Sub-slot content is used only when an explicitly named angle HTML body exists.
- The backend never guesses which HTML elements represent a header, banner, or publisher.
- Theme creation does not require any fixed set of BDs or sub-slots.
- New landing-page creation treats every missing BD or sub-slot as optional and removes its empty placeholder without warning.
- If a target theme requests a sub-slot that is unavailable, theme switching uses safe content preservation mode.
- Theme switching never fills missing slots from the original angle because that could restore stale content over landing-page edits.
- Switching from split sub-slots back to a required full BD uses safe preservation unless explicit full-BD content is available.
- Sub-slot content is rendered without the full-body layout wrapper.

## Mapping Status

Theme switching now reports:

```text
bd_mapped
slot_mapped
safe_fallback
```

The response also includes:

```text
target_sub_slots
unresolved_sub_slots
unresolved_body_ids
```

## Frontend Rollout Steps

1. Select one split-layout theme as a Phase 3 pilot.
2. Replace repeated partial BD placeholders with explicit sub-slot placeholders.
3. Add matching named HTML bodies for the sections that should display; missing sections will be omitted.
4. Create the pilot landing page directly from that angle and theme.
5. Duplicate the pilot page before switching between compatible sub-slot themes.
6. Confirm the response reports `slot_mapped`.
7. Confirm switching from a plain-BD page into the pilot theme uses safe fallback rather than stale angle content.
8. Test preview, editor save, translation, export, desktop, and mobile.
9. Roll out to additional themes only after the pilot passes.

## Current Audit Result

At implementation time:

```text
Themes using sub-slots:      0
Angle bodies using sub-slots: 0
```

Therefore, the Phase 3 foundation does not alter the behavior of existing themes until frontend deliberately adopts the new contract.

## Validation

The Phase 3 foundation passed:

```text
Isolated Phase 3 unit tests: 20 passed
Syntax checks:               passed
Database-modifying commands: none run
```

The existing landing-page compatibility audit was not rerun during the final safety-rule update.
