# UX / UI principles and lessons learned

## What went wrong in v4.x
Features were added sequentially to the same interface:
- Quran cards became too long;
- Person of the Day became a large questionnaire-like block;
- task/project controls accumulated visibly;
- project forms broke hierarchy on mobile;
- navigation gained too many destinations;
- patch JS/CSS layers made the UI increasingly fragile.

The user explicitly called out that the product needed UX that anticipates growth rather than adapting after every feature.

## Rules for future UI work

### Mobile first
Primary device is an iPhone-sized screen. Design at ~390px width first.

### Fixed bottom nav
Bottom nav:
- stays visually attached to bottom of viewport;
- uses iPhone safe-area padding;
- never overlays the last content;
- content container must have bottom padding >= nav height + safe area + margin.

### Progressive disclosure
Summary first, details on demand.
Good:
- “Al-Fatiha · full · 7 ayat · ~7 min → Start reading”
- “Workout Day 1 · 7 exercises → Start”
- “Project 4/11 → Next action”

Bad:
- full tafsir/biography/form/workout plan rendered inline on Today.

### Forms
Use modal/sheet forms for occasional creation/edit actions.
Do not keep large creation forms permanently open above lists.

### Actions
The primary action should be obvious.
Secondary actions (move/delete/change priority/source etc.) can be contextual.

### Compact Plan
“Next 7 days” should be horizontally compact on mobile rather than seven tall cards.

### Consistent component system
Prefer reusable patterns:
- summary card
- service tile
- progress bar
- compact metric
- modal/sheet
- calendar day
- action row

Avoid one-off CSS/interaction patterns for each new service.
