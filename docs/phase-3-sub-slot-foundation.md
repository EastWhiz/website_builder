# Phase 3: Optional Sub-Slot Foundation

Date: 2026-06-11

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
- If a target theme requests a sub-slot that is unavailable, theme switching uses safe content preservation mode.
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
```

## Frontend Rollout Steps

1. Select one split-layout theme as a Phase 3 pilot.
2. Replace repeated partial BD placeholders with explicit sub-slot placeholders.
3. Ensure the source angle provides matching named HTML bodies.
4. Duplicate a landing page before changing its theme.
5. Confirm the response reports `slot_mapped`.
6. Test preview, editor save, translation, export, desktop, and mobile.
7. Roll out to additional themes only after the pilot passes.

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
Unit tests:             14 passed
Compatibility audit:   724 landing pages
Audit errors:           0
```

