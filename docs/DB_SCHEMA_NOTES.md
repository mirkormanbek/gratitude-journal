# Database schema notes

This is a conceptual/handoff map, not a guaranteed exact live schema. Inspect production migrations/tables before changing them.

Historical tables include:
- users
- daily_entries
- ceo_reviews
- knowledge_base
- capital_entries
- daily_content
- ai_analyses
- quran_progress
- tasks
- projects
- workout_programs
- workout_days
- workout_exercises
- workout_sessions
- workout_sets
- workout_schedule_slots
- workout_schedule_skips

Known relationships/concepts:
- daily_content stores generated/selected daily Quran/Person data and personal takeaways/completion timestamps.
- tasks may be generated from a daily entry and have source metadata.
- tasks may have `project_id`.
- projects calculate progress from linked tasks.
- workout program → workout days → exercises.
- workout session belongs to program/day/date.
- workout sets belong to session/exercise.
- v5.1 introduced recurring schedule slots and skip/move semantics; workout sessions gained `origin_date` and `is_auto` in the migration history.

Codex must query/inspect the actual schema before writing migrations.
