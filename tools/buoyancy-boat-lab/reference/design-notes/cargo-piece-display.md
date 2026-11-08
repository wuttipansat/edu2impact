---
name: Cargo piece display
description: Why the simulation treats cargo piece count as a visualization derived from mass.
---

The boat's physics uses total cargo mass, not an independently stored inventory of objects. A piece in the visualization represents one selected mass increment. Counts are exact only when total mass divides evenly by the selected increment; otherwise label the rounded-up visible count as approximate.

**Why:** Students can enter cargo mass directly and can change the increment afterward. Claiming an exact number of objects from those inputs would be misleading. Deriving the visualization avoids changing saved experiment data or the physics model for a display-only improvement.

**How to apply:** Keep cargo mass authoritative for buoyancy. If a later feature needs a truly exact, individually tracked inventory, model piece additions explicitly rather than reusing the inferred display count as recorded data.