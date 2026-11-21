-- Notes service (merged with Knowledge Base).
-- kind: 'note' (free text) | 'idea' (knowledge flow: idea → why → apply → status → result).
CREATE TABLE IF NOT EXISTS notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(20) NOT NULL DEFAULT 'note',
  title VARCHAR(200) NULL,
  body TEXT NULL,
  category VARCHAR(100) NULL,
  source VARCHAR(255) NULL,
  why TEXT NULL,
  apply TEXT NULL,
  result TEXT NULL,
  status VARCHAR(40) NULL,
  tags VARCHAR(500) NULL,
  project_id BIGINT UNSIGNED NULL,
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  note_date DATE NOT NULL,
  legacy_kb_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notes_legacy (legacy_kb_id),
  KEY idx_notes_project (project_id),
  KEY idx_notes_list (archived, pinned, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copy Knowledge Base entries once (knowledge_base itself is kept untouched as a backup).
INSERT IGNORE INTO notes (kind, body, category, source, why, apply, result, status, tags, note_date, legacy_kb_id, created_at)
SELECT 'idea', insight, NULLIF(category,''), NULLIF(source,''), NULLIF(why_important,''), NULLIF(how_to_apply,''), NULLIF(result,''), NULLIF(status,''), NULLIF(tags,''), entry_date, id, created_at
FROM knowledge_base;
