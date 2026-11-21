# Current state — handoff from ChatGPT development

## Deployment
- Host: PS.KZ / Plesk
- Production domain: `journal.luckysport.kz`
- Database: MariaDB
- Existing authenticated single-user/private journal deployment
- HTTPS configured with Let's Encrypt

## Integration status
### OpenAI
Server-side OpenAI API connection was tested successfully.
Credentials live server-side and must not be committed.
AI powers:
- Person of the Day generation
- selected journal analysis
- optional discussion/reflection

### Quran Foundation
Server-side OAuth/client-credentials connection was tested successfully in Prelive.
Prelive returned only the first two surahs in testing, so production access is required eventually for the full Quran.
Token is cached server-side/session in the implementation history.

## Existing modules
- Daily journal
- CEO Review
- Knowledge Base
- Capital
- Quran daily reading
- Person of the Day
- AI analysis
- Tasks
- Projects
- Workouts
- Export

## Current Growth OS v5 navigation
- Today
- Plan
- Journal
- Progress
- Services

## Tasks
Daily journal has a “tasks for tomorrow” field.
Each non-empty line can become one task on `journal_date + 1 day`.
Important behavior:
- editing should update pending generated tasks, not duplicate them;
- completed generated tasks remain history;
- manually rescheduled generated tasks preserve their chosen date;
- overdue tasks remain visible.

## Projects
Project represents a multi-step outcome.
Tasks can be attached to a project.
Project progress is calculated from linked tasks.
The UI should emphasize the next action, not expose every control all the time.

## Workout module v5.1 intent
Default weekly schedule:
- Monday: Day 1
- Wednesday: Day 2
- Friday: Day 3
- other days: rest

Requirements already established:
- monthly calendar;
- edit permanent weekly schedule;
- move one missed session to another date without shifting the entire schedule;
- delete planned, in-progress, completed or test workout sessions;
- delete the recorded sets belonging to a deleted session;
- record each set (weight/reps);
- show previous result for an exercise;
- rest timer after a saved set;
- resume an in-progress workout;
- preserve history and progress.

## Initial training program (from user’s notes)
Training frequency: approximately 3 times/week.
The notes contain three workout days. The exact live database/program should be treated as source of truth because one or more handwritten values were ambiguous during import.

Notably, horizontal row weight was deliberately left unguessed when it was unclear.

## Known production compatibility issue
A Quran endpoint initially used PHP `str_contains()` and production failed with:
`Call to undefined function str_contains()`.
It was replaced with `strpos()`. Therefore do not assume PHP 8+.
