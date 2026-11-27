-- Simple key/value app settings (module switches etc.).
CREATE TABLE IF NOT EXISTS app_settings (
  name VARCHAR(64) NOT NULL PRIMARY KEY,
  value VARCHAR(1000) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- 2026-11-27: the user paused «Человек дня» and «Коран» (can be re-enabled in Сервисы → Модули).
INSERT IGNORE INTO app_settings (name, value) VALUES ('module.quran', '0'), ('module.person', '0');
