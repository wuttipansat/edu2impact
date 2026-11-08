---
name: Fair boat competitions
description: Shared race conditions and how to decide winners in a classroom boat challenge.
---

Compare every boat in the same freshwater with zero starting cargo, adding an equal amount of mass at each shared time step. Do not import the owner's current liquid or test cargo into the race. The race finishes only when all participating boats have sunk. When multiple boats sink on the final step, treat them as joint winners rather than arbitrarily picking one.

**Why:** Separate liquids or starting cargo would make the classroom comparison misleading. A shared clock allows the result to stay consistent for viewers on different devices or returning after a reload; equal final sinking steps cannot establish one last survivor.

**How to apply:** Keep the test conditions uniform when adding race controls, charts, rematches, or result exports. If conditions become configurable, set them once for the whole room before anyone competes and disclose them to every participant.