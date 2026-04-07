# Landing UX/Performance Improvement Plan

## Context

This document records the redesign and performance pass applied to `apps/landing`, plus the remaining visual issues identified after implementation, tests, and screenshot validation on April 5, 2026.

The goal of this pass was to move the landing away from "dark SaaS cards with good copy" and closer to "premium product narrative with real interaction and visible proof".

## Product Problems Addressed

- Too much explanation by text and not enough demonstration by interaction.
- Games section looked like a placeholder instead of a product differentiator.
- AI moderation looked like a generic scanner without real media flow.
- Face search did not show a convincing facial lock / scan / match process.
- Comparison, testimonials, and pricing still felt like generic SaaS sections.
- Heavy demos were loading too early and producing avoidable runtime cost.
- Missing `.riv` assets still triggered network fetches and wasted work.

## Official References Used

These references were used to keep the implementation aligned with recent official guidance:

- GSAP React and `useGSAP`: https://gsap.com/resources/React/
- Motion `MotionConfig`: https://motion.dev/docs/react-motion-config
- Motion `useReducedMotion`: https://motion.dev/docs/react-use-reduced-motion
- Motion `LazyMotion`: https://motion.dev/docs/react-lazy-motion
- Rive React runtime: https://rive.app/docs/runtimes/react/react
- Phaser project templates: https://docs.phaser.io/phaser/getting-started/project-templates
- Phaser game objects and input hit areas: https://docs.phaser.io/phaser/concepts/gameobjects

## Delivery Scope

### 1. Games

#### Implemented

- Rebuilt the games showcase so the user selects a real event photo before launching the demo.
- Replaced the old abstract board preview with real-image previews.
- Changed the Phaser demo from eager-on-viewport to on-demand-on-click.
- Added two usable mini games:
  - puzzle with real photo tiles and tap-to-swap interaction
  - memory game with real event images and real matching logic
- Disabled Phaser audio and console banner noise.

#### Task Breakdown

- `InteractiveGamesSection`
  - create explicit photo picker
  - create mode tabs for puzzle and memory
  - create lightweight pre-launch preview
  - gate Phaser load behind a CTA click
- `PhaserGamesShowcase`
  - preload only required image assets
  - generate puzzle tile textures from a real photo
  - generate normalized memory card faces from event photos
  - implement tap-to-swap puzzle logic
  - implement real memory flip + pair matching logic
  - disable audio and banner
  - keep mode switching lightweight
- `gameAssetPipeline`
  - load remote photo once
  - crop to square
  - cache generated data URLs

#### Remaining Follow-up

- Add a stronger success state with confetti-level feedback only after user interaction, not on page idle.
- Add a compact "play instruction" overlay for first interaction on mobile.
- Consider a tiny score persistence mock so the ranking feels even more alive.

### 2. AI Moderation

#### Implemented

- Rebuilt the moderation stage as a visual media conveyor with real thumbnails.
- Added three explicit moderation modes: no moderation, manual, AI.
- Added media-level states with visual outcomes:
  - approved
  - review
  - blocked
  - free/direct
- Added a focused scanner stage with real image, scan line, state badge, and explanation panel.
- Stopped attempting to load missing Rive files for this section.

#### Task Breakdown

- Replace abstract cards with real event thumbnails
- Add selectable incoming media rail
- Add active scan stage
- Add per-mode state mapping for each media item
- Add summary lanes:
  - approved count
  - review count
  - blocked count
- Force CSS fallback until real `.riv` asset exists

#### Remaining Follow-up

- Replace the fallback with a real `.riv` scanner once the file is authored.
- Add richer "blocked" visual logic, such as contextual blur masks and severity chips.
- Add a tighter link to the downstream modules:
  - gallery
  - games
  - wall

### 3. Face Search

#### Implemented

- Rebuilt the face search area with stronger facial lock visuals.
- Added:
  - target box
  - crosshair
  - scan line
  - face mesh points
  - confidence badge
  - process rail from selfie to results
- Changed the result area so it reads as a search process, not just a loose gallery.
- Stopped attempting to load missing Rive files for this section.

#### Task Breakdown

- redesign selfie stage around facial detection
- add explicit scan affordances
- add process rail for system reasoning
- make match selection manual instead of idle-only animation
- elevate confidence feedback in the UI

#### Remaining Follow-up

- Replace the fallback with a real `.riv` / vector animation when the asset exists.
- Add animated line linking selfie scan and returned match result.
- Add a lightweight "download memories" action stub in the result block.

### 4. Comparison

#### Implemented

- Rebuilt the comparison section with visual stages instead of text-only columns.
- Added a weak/common visual state on the left and a richer ecosystem visual on the right.
- Kept the direct checklist, but framed it inside stronger theatrical product contrast.

#### Task Breakdown

- create common-side static product stage
- create premium-side ecosystem stage
- add module chips on premium panel
- preserve explicit yes/no comparison rows

#### Remaining Follow-up

- Add short commercial outcome metrics above each column.
- Add subtle interaction to switch between event types:
  - wedding
  - graduation
  - corporate

### 5. Testimonials

#### Implemented

- Added visual event covers to each testimonial.
- Turned testimonials into mini case-study cards instead of plain quote blocks.
- Strengthened volume / event type visibility.

#### Task Breakdown

- add event visual per testimonial
- keep quote prominent
- surface "what surprised" and "result" as separate proof blocks

#### Remaining Follow-up

- Add client logo / company mark where applicable.
- Add one short numeric outcome per case.

### 6. Pricing

#### Implemented

- Rebuilt plan cards to feel less generic and more commercial.
- Added entry signals / delivery framing / positioning panel per plan.
- Strengthened the featured middle plan visually.

#### Task Breakdown

- add plan signals
- add commercial framing block
- preserve feature list but increase hierarchy
- keep CTA structure clear

#### Remaining Follow-up

- Add plan comparison toggles for one-off event vs recurring partner.
- Add a smaller ROI framing line for each plan.

### 7. Fine Polish Round

#### Implemented

- Rebuilt the header from a two-row text-heavy bar into a more compact command bar.
- Reduced visual noise in the top area by removing redundant header copy and collapsing the product signals into a tighter brand capsule.
- Added clearer visual separation between sections with stronger container backplates and divider treatments.
- Rebuilt the footer into a higher-authority closing block with:
  - operation framing
  - CTA band
  - stronger contact card
  - more intentional navigation grouping
- Fixed the Phaser puzzle hit area bug by removing the incorrect negative-coordinate custom hit box and relying on a correctly sized interactive object.
- Improved Phaser demo sharpness and sizing by:
  - switching from `CANVAS` to `AUTO`
  - capping resolution
  - removing CSS distortion of the canvas height
  - constraining the phone frame to a real aspect ratio
- Made the preview state itself clickable so the games stage behaves more like an interactive product demo.
- Tightened the puzzle footer metrics after screenshot validation exposed label overlap.
- Rebalanced the hero headline width so the fold reads more cleanly in desktop capture.
- Simplified the header one more time by removing the extra desktop capability capsule and the extra active-section pill, leaving a clearer single hierarchy:
  - brand
  - nav
  - CTA cluster
- Rebuilt the comparison area into a true line-by-line matrix instead of two separated cards, so the visitor can compare common solutions versus Evento Vivo in the same visual row.
- Added a comparison spotlight panel driven by the active row to make the explanation more interactive and less static.
- Reduced the excessive section-box feeling by removing the heavy generic section backplates and keeping lighter separators.
- Compressed the middle of the mobile experience by converting some long vertical selectors into horizontal snap rails:
  - ecosystem cards
  - gallery flow steps
  - technical trust selectors
- Removed the hero eyebrow on mobile to reduce top clutter and improve first-screen clarity.
- Fixed the top-menu interaction lag by introducing an immediate active-state override on click while smooth scrolling catches up, so the clicked section is highlighted right away.
- Reduced hero CTA competition by keeping the primary button and downgrading the ecosystem action into a lighter text action, removing the third competing CTA from the hero cluster.
- Rebuilt the footer again into a much lighter commercial close:
  - one clear callout
  - one primary CTA
  - one inline WhatsApp action
  - one light metadata row with brand, quick links, and contact
- Replaced the remaining abstract game preview blocks with real event photos in:
  - the hero ecosystem stage
  - the ecosystem cards section
- Re-centered the face scanner lock so the target box and crosshair align better with the model's face instead of framing too much empty torso space.

#### Task Breakdown

- Header
  - remove the old desktop topline
  - keep active section state visible without another full row of copy
  - shorten the brand descriptor
  - preserve desktop and mobile navigation consistency
- Section separation
  - add subtle backplate shells to anchored sections
  - add stronger divider glow lines between blocks
  - keep the hero exempt from the new generic backplate treatment
- Footer
  - add a commercial closeout callout above the actual footer grid
  - turn the footer into a premium information architecture instead of a bare link list
  - keep contact, navigation, and commercial actions visibly distinct
- Puzzle and memory
  - fix hit area setup
  - preserve lazy loading
  - validate interaction with click automation
  - tune canvas sizing to remove blur / misalignment

#### Remaining Follow-up

- The desktop hero is much cleaner now, but the left copy block can still be compressed one more pass if the goal is an even more dominant right-side stage.
- The page is visually more segmented, but the middle of the landing still has some long-scroll fatigue on mobile.
- The footer is finally in the right category, but it can gain one more pass once real client logos / trust marks exist.

### 8. Hero Conversion Reset

#### Implemented

- Recreated the missing `HeroExperience.tsx` component and replaced the old hero structure with a simpler conversion-first layout.
- Reduced the top fold to a clearer hierarchy:
  - brand/navigation
  - direct headline
  - one primary CTA
  - one light internal action
  - one connected product stage
- Changed the hero narrative from a loose "ecosystem of mini cards" to a connected flow:
  - guest sends
  - AI decides
  - output becomes gallery, game, wall, or facial match
- Reframed the right-side visual stage so it explains the product as one flow instead of five competing modules.
- Replaced the overloaded left explainer area with:
  - concise value proposition
  - three-step summary
  - compact proof row
- Fixed the previous headline break problem by replacing the overly poetic long line with a shorter, more commercial headline that fits the fold better.
- Tightened the desktop header shell so the top area carries less stacked information.

#### Task Breakdown

- Hero component
  - recreate missing TSX file
  - rebuild layout into left copy + right connected stage
  - keep GSAP only for entrance/scan enrichment
  - keep Motion only for output switching
- Hero copy
  - shorten headline
  - keep subheadline direct and operational
  - reduce CTA competition
- Hero visual logic
  - move AI to the center of the story
  - show input and outputs as connected cards
  - make the output area interactive through tabs
- Header support
  - reduce shell padding
  - reduce brand noise
  - keep subtitle hidden on common desktop widths

#### Remaining Follow-up

- The hero is now clearer and more commercial, but the left-side proof row could still be collapsed further if the goal is an even more editorial fold.
- The right stage already explains the flow better, but it can gain a second pass of micro-polish:
  - stronger connectors between columns
  - slightly richer output preview states
  - more dominant contrast on the active output
- Mobile first fold is cleaner, but the flow summary still occupies notable vertical space before the interactive stage appears.

## Performance Work

### Implemented

- Phaser no longer loads on viewport intersection. It loads only after explicit user intent.
- Phaser audio is disabled.
- Phaser console banner is disabled.
- Missing Rive assets no longer need to be fetched in these two key sections because the wrappers are forced into fallback mode.
- Removed idle auto-rotation from AI moderation and face search so those sections are interaction-led instead of timer-led.

### Performance Tasks and Subtasks

- Games
  - move lazy load trigger from `IntersectionObserver` to CTA click
  - avoid canvas boot before user intent
  - remove audio startup warnings
- Rive
  - stop fetch attempts when there is no asset in repo
  - keep premium fallback presentational layer active
- Animation
  - keep GSAP in hero / gallery storytelling
  - avoid adding new JS loops for AI and face search
  - respect reduced motion

### Still Open

- `phaser` chunk remains large, but it is now isolated and truly deferred.
- Sass still prints the `legacy-js-api` deprecation warning from the current toolchain.
- A future pass can move more `motion` usage behind `LazyMotion` if bundle pressure becomes relevant.

## Files Changed In This Pass

- `apps/landing/src/components/InteractiveGamesSection.tsx`
- `apps/landing/src/components/InteractiveGamesSection.module.scss`
- `apps/landing/src/components/games/PhaserGamesShowcase.tsx`
- `apps/landing/src/components/games/gameAssetPipeline.ts`
- `apps/landing/src/components/Navbar.tsx`
- `apps/landing/src/components/Navbar.module.scss`
- `apps/landing/src/components/ModerationAISection.tsx`
- `apps/landing/src/components/ModerationAISection.module.scss`
- `apps/landing/src/components/FaceSearchSection.tsx`
- `apps/landing/src/components/FaceSearchSection.module.scss`
- `apps/landing/src/components/ComparisonSection.tsx`
- `apps/landing/src/components/ComparisonSection.module.scss`
- `apps/landing/src/components/TestimonialsSection.tsx`
- `apps/landing/src/components/TestimonialsSection.module.scss`
- `apps/landing/src/components/PricingSection.tsx`
- `apps/landing/src/components/PricingSection.module.scss`
- `apps/landing/src/components/FooterSection.tsx`
- `apps/landing/src/components/FooterSection.module.scss`
- `apps/landing/src/components/HeroExperience.tsx`
- `apps/landing/src/components/HeroExperience.module.scss`
- `apps/landing/src/styles/globals.scss`

## Validation

### Tests Run

- `npm run type-check`
  - result: passed
- `npm run test`
  - result: passed with `No test files found`
- `npm run build`
  - result: passed

### Screenshot Capture

- Mobile/full-page validation screenshot:
  - `docs/architecture/landing-validation-mobile-2026-04-05-round3.png`
- Desktop/top-page validation screenshot:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round3.png`
- Desktop top validation screenshot after fine polish:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round4-top-final.png`
- Desktop footer validation screenshot:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round4-footer.png`
- Mobile footer validation screenshot:
  - `docs/architecture/landing-validation-mobile-2026-04-05-round4-footer.png`
- Puzzle interaction validation screenshot:
  - `docs/architecture/landing-validation-puzzle-2026-04-05-round4-final.png`
- Desktop top validation after header simplification:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round5-top.png`
- Desktop comparison validation:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round5-comparison.png`
- Mobile top validation after hero cleanup:
  - `docs/architecture/landing-validation-mobile-2026-04-05-round5-top-final-3.png`
- Mobile comparison validation:
  - `docs/architecture/landing-validation-mobile-2026-04-05-round5-comparison.png`
- Desktop hero validation after CTA / preview cleanup:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round6-hero.png`
- Desktop ecosystem validation with real game preview imagery:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round6-ecosystem.png`
- Desktop face-search validation after scanner alignment:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round6-face.png`
- Desktop footer validation after simplification:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round6-footer.png`
- Desktop hero validation after conversion reset:
  - `docs/architecture/landing-validation-desktop-2026-04-05-round7-hero.png`
- Mobile hero validation after conversion reset:
  - `docs/architecture/landing-validation-mobile-2026-04-05-round7-hero.png`
- Scrollspy click-state DOM validation:
  - clicking `Jogos` now marks the `Jogos` link active immediately instead of leaving the previous section highlighted

### Visual Review Notes

#### Improved

- Games now read as real product interaction instead of symbolic placeholders.
- AI moderation is substantially more understandable because real media enters and receives explicit outcomes.
- Face search now communicates detection, scanning, and result confidence more clearly.
- Lower commercial sections no longer feel like plain dark cards with copy only.
- The hero now loads with essential content already visible, which fixed the previous screenshot validation issue where the fold looked empty in desktop capture.
- The hero no longer feels like a pile of disconnected product cards above the fold; it now reads as a single operational flow.
- The top area now contains less competing information and the CTA cluster is easier to understand.
- The new hero headline is more direct commercially and no longer suffers from the previous cropped / awkward break.
- Mobile spacing is tighter and the first sections now feel less like a long corridor of oversized blocks.
- The header now feels like a compact product control bar instead of a stacked strip of unrelated text chips.
- The header is now substantially cleaner than the previous round and no longer suffers from the "everything on top of everything" feeling captured in the earlier desktop screenshot.
- The footer finally closes the page with a commercial structure that looks deliberate and premium.
- The games demo now responds to real click interaction in validation, and the puzzle board no longer behaves like the hit area is offset.
- The comparison section now reads as an actual comparison surface, not as two adjacent marketing cards.
- The mobile top is cleaner because the eyebrow no longer competes with the fixed header.
- The hero is less noisy because the visitor no longer sees three competing CTAs in the same cluster.
- The footer is significantly less box-heavy and now behaves more like a clean commercial outro than a grid of administrative cards.
- The face scanner is visually closer to the actual face center and reads more like recognition instead of a generic overlay.
- The games previews now look connected to real event imagery rather than abstract placeholder tiles.

#### Still Not Good Enough

- The hero desktop is now in a much healthier state, but can still gain one more pass of polish:
  - slightly stronger line connectors inside the right-side stage
  - one more reduction in supporting proof density on the left if the fold needs to feel even cleaner
- The page still feels long on mobile. The quality of sections improved, but vertical density is still high.
- The ecosystem / product narrative after the hero is better, but there is still room to compress copy and increase diagrammatic explanation.
- Real `.riv` assets are still absent, so these sections are premium fallbacks, not final motion assets.
- Some mid-page sections still rely more on premium card treatment than on truly interactive explanation, especially outside games / AI / face search.
- The comparison matrix works much better now, but it can still gain one stronger "commercial win" layer such as ROI or perceived-value framing above the rows.
- There is still room to reduce explanatory copy in a few left columns where benefits are repeated once in the headline and again in the supporting copy.

## Next Pass Recommendation

### Priority 1

- Rebalance the desktop hero so the right visual stage wins more attention above the fold.
- Tighten mobile spacing and reduce section fatigue.

### Priority 2

- Add a real Rive asset pipeline for:
  - moderation scanner
  - face search
- Add one compact interactive proof to the comparison or pricing section.

### Priority 3

- Add actual Vitest coverage for the landing helpers and key sections:
  - `gameAssetPipeline`
  - game CTA loading behavior
  - anchor navigation behavior
  - reduced motion fallback behavior
