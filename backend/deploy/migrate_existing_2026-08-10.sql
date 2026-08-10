-- TransMore release migration 2026-08-10
-- Aman dijalankan ulang pada database yang sudah memiliki tabel transactions.

SET @doc_link_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transactions'
    AND COLUMN_NAME = 'doc_link'
);

SET @migration_sql = IF(
  @doc_link_exists = 0,
  'ALTER TABLE transactions ADD COLUMN doc_link TEXT NULL AFTER notes',
  'SELECT 1'
);

PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

INSERT IGNORE INTO migrations (name, executed_at)
VALUES ('009_transaction_doc_link.sql', NOW());
