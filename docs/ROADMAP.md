# Roadmap

## Priority 0 — stabilize v5 architecture
Before adding many new services:
- remove leftover patch-era UI/code where safe;
- standardize reusable components;
- ensure bottom nav/safe-area behavior is solid;
- ensure Plan, Today and Services stay compact on mobile;
- verify production PHP version and document it;
- add a repeatable migration strategy.

## Priority 1 — workout module maturity
- calendar is source of truth;
- recurring weekly schedule editor;
- per-session move/reschedule;
- delete test/completed session safely;
- program editor;
- exercise library;
- previous performance shown clearly;
- set entry optimized for gym use;
- rest timer;
- workout history;
- exercise progression charts.

## Priority 2 — unified calendar/event layer
Create a common event projection model so services can surface items into Plan without duplicating calendar code.
Potential event producers:
- tasks;
- workouts;
- recurring habits;
- meetings/events;
- Quran/journal reminders if desired.

## Priority 3 — Goals layer (only after Projects proves useful)
Potential hierarchy:
Goal → Projects → Tasks.
Do not add it prematurely if Projects already provides sufficient context.

## Priority 4 — Progress and review
- monthly dashboard;
- task completion trends;
- project progress;
- workout adherence/performance;
- Quran progress;
- journal consistency;
- optional user-triggered AI weekly/monthly synthesis.

## Future service ideas
- sleep;
- nutrition;
- weight/body metrics;
- reading/books;
- networking/contacts;
- habits.

All future services must respect the five-item primary navigation architecture.
