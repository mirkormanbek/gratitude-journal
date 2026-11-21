-- Workout program mode:
--  'rotation' — next workout = the day after the last completed one (День 1 → 2 → 3 → 1), any weekday;
--  'schedule' — fixed weekdays with a calendar (previous behaviour).
ALTER TABLE workout_programs ADD COLUMN IF NOT EXISTS mode VARCHAR(20) NOT NULL DEFAULT 'rotation';
