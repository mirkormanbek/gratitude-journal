-- Structure of the production tables as of 2026-11-21 (no data).
-- CI loads this into an empty MariaDB before running migrations/tests,
-- so migrations that ALTER existing tables can be tested.
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE knowledge_base (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  category VARCHAR(100) NULL,
  source VARCHAR(255) NULL,
  insight TEXT NOT NULL,
  why_important TEXT NULL,
  how_to_apply TEXT NULL,
  status VARCHAR(50) NULL DEFAULT 'Идея',
  result TEXT NULL,
  tags VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (entry_date), KEY (category), KEY (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  outcome TEXT NULL,
  deadline DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (deadline), KEY (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  due_date DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
  project_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(32) NOT NULL DEFAULT 'manual',
  source_date DATE NULL,
  source_line_no INT NULL,
  is_rescheduled TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (due_date), KEY (project_id), KEY (source_type), KEY (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_programs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  target_per_week TINYINT UNSIGNED NOT NULL DEFAULT 3,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_days (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  program_id BIGINT UNSIGNED NOT NULL,
  day_order TINYINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  KEY (program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_exercises (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  day_id BIGINT UNSIGNED NOT NULL,
  exercise_order SMALLINT UNSIGNED NOT NULL,
  name VARCHAR(220) NOT NULL,
  target_sets TINYINT UNSIGNED NOT NULL DEFAULT 3,
  target_reps VARCHAR(60) NULL,
  default_weight DECIMAL(8,2) NULL,
  notes VARCHAR(500) NULL,
  KEY (day_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_schedule_skips (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  program_id BIGINT UNSIGNED NOT NULL,
  original_date DATE NOT NULL,
  reason VARCHAR(40) NOT NULL DEFAULT 'skip',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (program_id, original_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_schedule_slots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  program_id BIGINT UNSIGNED NOT NULL,
  day_id BIGINT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  effective_from DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  program_id BIGINT UNSIGNED NOT NULL,
  day_id BIGINT UNSIGNED NOT NULL,
  scheduled_date DATE NOT NULL,
  origin_date DATE NULL,
  is_auto TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'planned',
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (program_id), KEY (day_id), KEY (scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_sets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  exercise_id BIGINT UNSIGNED NOT NULL,
  set_no TINYINT UNSIGNED NOT NULL,
  weight_kg DECIMAL(8,2) NULL,
  reps SMALLINT UNSIGNED NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (session_id, exercise_id, set_no),
  KEY (exercise_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixture: one user, the program skeleton (3 days, a few exercises) like production
INSERT INTO users (id, username) VALUES (1, 'ci');
INSERT INTO workout_programs (id, name, target_per_week) VALUES (1, 'Программа 3×', 3);
INSERT INTO workout_days (id, program_id, day_order, name) VALUES (1,1,1,'День 1'),(2,1,2,'День 2'),(3,1,3,'День 3');
INSERT INTO workout_exercises (id, day_id, exercise_order, name, target_sets, target_reps, default_weight) VALUES
 (1,1,1,'Жим лёжа',4,'6–8',60),(2,1,2,'Тяга верхняя',4,'8',55),
 (3,2,1,'Брусья',4,'12',80),(4,2,2,'Жим лёжа',3,'10',50),
 (5,3,1,'Жим ногами',4,'10',80);
INSERT INTO workout_schedule_slots (program_id, day_id, weekday, effective_from) VALUES (1,1,1,'2026-01-01'),(1,2,3,'2026-01-01'),(1,3,5,'2026-01-01');
INSERT INTO projects (id, name, outcome) VALUES (1, 'Оптовый канал', '5 активных клиентов');
INSERT INTO tasks (title, due_date, project_id) VALUES ('Собрать каталог', '2026-11-20', 1);
INSERT INTO knowledge_base (entry_date, category, insight, why_important, how_to_apply, tags) VALUES ('2026-10-01','Продажи','Оптовикам важна стабильность наличия','Повторные заказы','Держать резерв топ-SKU','опт, продажи');
