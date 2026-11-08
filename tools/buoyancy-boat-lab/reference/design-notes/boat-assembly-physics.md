---
name: Boat assembly physics
description: Why detachable boat parts use an approximate shared-draft buoyancy model.
---

The assembly model treats components above the waterline as added mass only, and external underwater parts or sealed side floats as added buoyant volume. All displacing volume shares a single submerged fraction rather than resolving a separate waterline for each piece.

**Why:** This keeps the classroom experiment compatible with the existing two-dimensional boat illustration and single-draft status model while letting students compare how detachable parts affect mass and carrying capacity. It is an approximation, not a precise naval-architecture calculation.

**How to apply:** When adding new parts, explicitly decide whether they displace liquid or only add mass; label assumptions in the interface. For high-fidelity waterline or stability behavior, replace the shared-draft model as a dedicated extension rather than treating approximate outputs as precise.

The simulation illustration responds to changes in boat dimensions and installed-part sizes, but visual growth is bounded to keep the boat inside the basin. This visual bound must not affect the actual buoyancy calculation.

**Why:** Classroom inputs can be arbitrarily large, while the responsive two-dimensional basin has finite space. A bounded drawing with the entered dimensions visible is more useful than a boat clipped offscreen.

**How to apply:** For future geometry changes, keep the drawing and the numeric physics separate. Let part positions follow the hull as it grows or shrinks; do not use display-size limits as physics limits.