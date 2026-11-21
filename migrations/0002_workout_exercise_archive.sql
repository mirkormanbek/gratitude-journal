-- Program editor: exercises are archived (not deleted) so past sets keep their history.
ALTER TABLE workout_exercises ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0;
-- Per-exercise rest timer (seconds); NULL = default 90 s.
ALTER TABLE workout_exercises ADD COLUMN IF NOT EXISTS rest_seconds SMALLINT UNSIGNED NULL;
-- Faster progress queries per exercise.
CREATE INDEX IF NOT EXISTS idx_sets_exercise_completed ON workout_sets (exercise_id, completed_at);
