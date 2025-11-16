-- ============================================================================
-- Reviewer Certificate Plugin - Manual Uninstallation SQL
-- For OJS 3.3.x, 3.4.x, 3.5.x
--
-- WARNING: This will DELETE ALL certificate data!
-- Run this SQL script to remove plugin tables:
-- mysql -u [username] -p [database_name] < uninstall.sql
-- ============================================================================

-- Backup notification
SELECT 'WARNING: About to drop all Reviewer Certificate Plugin tables!' AS warning;
SELECT 'Press Ctrl+C within 5 seconds to cancel...' AS warning;

-- Drop tables in reverse order (respecting potential foreign keys)
DROP TABLE IF EXISTS reviewer_certificate_settings;
DROP TABLE IF EXISTS reviewer_certificates;
DROP TABLE IF EXISTS reviewer_certificate_templates;

-- Verify uninstallation
SELECT 'Uninstallation complete! Tables removed.' AS status;
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'reviewer_certificate_templates',
    'reviewer_certificates',
    'reviewer_certificate_settings'
  );
