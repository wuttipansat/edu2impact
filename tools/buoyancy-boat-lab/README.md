# ห้องทดลองเรือรับน้ำหนัก: source

Source of the web tool published at `apps/buoyancy-boat-lab/` (build output, uploaded to the host).
The KU host has no Node, so build here and upload the generated folder.

```
cd tools/buoyancy-boat-lab
pnpm install
pnpm run typecheck
pnpm run build        # writes ../../apps/buoyancy-boat-lab/ (the folder is emptied first)
```

- Base path defaults to `/apps/buoyancy-boat-lab/` (override with `BASE_PATH`). The site must be served from the domain root.
- The tab icons are the main site's `assets/images/favicon.svg` and `apple-touch-icon.png` (linked, not copied).
- The app is one page with no server calls. Trials are kept in the browser (`localStorage`: `buoyancy-lab-current-boat`, `buoyancy-lab-experiments`); do not rename those keys or students lose their saved trials.
- `packages/boat-physics` holds the physics (a classroom approximation: all displacing parts share one draft).
- Race mode (rooms, shared race clock, post-race review) was removed. `reference/design-notes/` still holds the original design notes; race-fairness and race-review-privacy are history only.
- `screenshots/` are usable as post illustrations.
