-- Tambahkan permission halaman akun tanpa menimpa RLS yang sudah disesuaikan.
INSERT INTO user_permissions (user_id,module,can_create,can_read,can_update,can_delete,created_at,updated_at)
SELECT id,'profile',0,1,1,0,NOW(),NOW() FROM users
ON DUPLICATE KEY UPDATE module=VALUES(module);

INSERT INTO user_permissions (user_id,module,can_create,can_read,can_update,can_delete,created_at,updated_at)
SELECT id,'ubah-password',0,1,1,0,NOW(),NOW() FROM users
ON DUPLICATE KEY UPDATE module=VALUES(module);
