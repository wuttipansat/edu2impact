---
name: Liquid illustration
description: Rationale for visually distinct liquid treatments in the boat simulation.
---

Represent naturally colorless alcohol and glycerin with subtle cool and warm neutral tints rather than another blue basin. Water remains blue, edible oils and sugars use warm tones, and mercury reads as opaque metal.

**Why:** A fully transparent fill would look like the same water illustration, which made it hard for users to tell when they had changed the liquid. The tints are visual cues, not claims that the real clear liquids have those colors.

**How to apply:** Keep the selector swatch, basin surface, and submerged-boat treatment coordinated for any new liquid. Visual palette changes must not alter density calculations or remove the mercury safety warning.

Liquid motion is an illustration of loading and the existing flotation states, not a separate fluid simulation. Use the model's remaining interior capacity to signal that water may soon enter the boat; reserve stronger splashes and side spill for the model's overflow and sinking states. Respect reduced-motion preferences.

**Why:** A dramatic wave should help students notice a threshold without implying that animation determines whether the boat floats. Different liquids can share motion choreography while retaining distinct visual treatments and density calculations.

**How to apply:** When changing animations or warnings, derive their urgency from the same model states the numeric results use. Do not introduce a second, contradictory "will sink" calculation just to drive effects.

At the sinking state, the drawn liquid surface may rise and the boat may move farther down to make complete submersion unmistakable. Treat that depth as schematic, not as a newly computed quantity of liquid in the basin.

**Why:** A hull shaded as fully submerged can still look like it is at the surface, especially when cargo protrudes above it. Showing the whole cargo stack below a visible surface communicates the model's sinking result without changing buoyancy or claiming to simulate the basin filling.

**How to apply:** Keep physical displacement and status calculations authoritative; adjust only drawing geometry. Make sure the entire boat stays inside the basin on narrow screens and that above-water labels disappear when no part of the boat is above water.