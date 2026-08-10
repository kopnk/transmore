UPDATE users
SET iduser = UUID()
WHERE iduser = '' OR iduser IS NULL;

INSERT INTO users (iduser,email,password_hash,name,handphone,alamat,role,status,permissions,must_change_password,created_by,created_at)
VALUES (
  UUID(),
  'superadmin@transmore.id',
  '$2y$10$wNa32bONK2nlbi0tKu6sQOuGRrxSxwWbt3gX2XWrm9f3pm4n/ifMy',
  'Super Administrator','','','superadmin','Aktif',
  JSON_OBJECT(
    'dashboard',JSON_OBJECT('create',true,'read',true,'update',true,'delete',true),
    'users',JSON_OBJECT('create',true,'read',true,'update',true,'delete',true),
    'kendaraan',JSON_OBJECT('create',true,'read',true,'update',true,'delete',true),
    'pks',JSON_OBJECT('create',true,'read',true,'update',true,'delete',true),
    'kebun',JSON_OBJECT('create',true,'read',true,'update',true,'delete',true),
    'rls',JSON_OBJECT('create',true,'read',true,'update',true,'delete',true),
    'audit-log',JSON_OBJECT('create',true,'read',true,'update',true,'delete',true),
    'pengiriman',JSON_OBJECT('create',true,'read',true,'update',true,'delete',true)
  ),0,'database-migration',NOW()
)
ON DUPLICATE KEY UPDATE role='superadmin',status='Aktif',permissions=VALUES(permissions),updated_at=NOW();

CREATE INDEX idx_sessions_expires_at ON sessions (expires_at);
CREATE INDEX idx_transactions_created_at ON transactions (created_at);
