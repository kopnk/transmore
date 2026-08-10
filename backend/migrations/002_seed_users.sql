INSERT INTO users (email, password_hash, name, role, status, permissions, must_change_password, created_at)
VALUES
('admin@transmore.local', '$2y$10$hh1rjif34A6AXW3Tku/0Je6JSG.lEy6GMZbX4tFXGJHbL6L6whGJS', 'Administrator', 'superadmin', 'Aktif', JSON_ARRAY('manage_users', 'view_dashboard', 'manage_pengiriman'), 0, NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    role = VALUES(role),
    status = VALUES(status),
    permissions = VALUES(permissions),
    updated_at = NOW();
