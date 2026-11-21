# Development history / decisions

## v1–v3
Started as a Growth Journal with:
- Daily journal
- CEO Review
- Knowledge Base
- Capital

Moved from local/GitHub/localStorage style behavior to PHP + MariaDB on PS.KZ so records sync across desktop/iPhone.
Dark blue mobile-first redesign followed.

## v4
Added OpenAI and Quran Foundation integrations.
Introduced Quran reading, Person of the Day and AI analysis.
Learned that large detailed cards made Today too long.

Quran evolution:
- one ayah/day was too fragmented;
- changed to ~5–10 minute coherent reading;
- Al-Fatiha should be complete;
- long commentary must not be mixed into “translation”;
- tafsir source must be explicit.

Person of the Day evolution:
- originally structured as struggle/turning point/principle/error/lesson;
- user preferred an interesting readable biography about a real contributor to the world.

## v4.5–v4.8
Added tasks and projects.
Key lesson: large always-open forms and accumulated UI patches created poor mobile UX.

Task/project conceptual decision:
- multi-step work is a project;
- tasks are small checkable next actions.

## v5
Re-architected product as Growth OS with fixed five-item bottom navigation:
Today / Plan / Journal / Progress / Services.

Added workouts as a first-class service.

## v5.1
Workout model changed from a simple cycle to calendar-aware scheduling:
- Mon Day 1 / Wed Day 2 / Fri Day 3 default;
- permanent schedule editable;
- individual missed session can move without shifting recurring schedule;
- sessions deletable;
- compact 7-day Plan UI;
- bottom nav fixed to viewport with content safe-area spacing.
