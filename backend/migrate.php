<?php
require __DIR__ . '/vendor/autoload.php';

use TransMore\Backend\Database;
use TransMore\Backend\Migration;

$db = Database::connect();
$files = glob(__DIR__ . '/migrations/*.sql');
sort($files, SORT_STRING);

foreach ($files as $file) {
    $name = basename($file);
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM migrations WHERE name=:name');
        $stmt->execute([':name'=>$name]);
        if ((int)$stmt->fetchColumn() > 0) continue;
    } catch (Throwable) {
        // Migration 001 creates the tracking table.
    }
    Migration::run($file);
    $stmt = $db->prepare('INSERT IGNORE INTO migrations (name,executed_at) VALUES (:name,NOW())');
    $stmt->execute([':name'=>$name]);
    echo "Migrated: {$name}\n";
}

// Normalisasi permission JSON lama ke tabel relasional user_permissions.
$modules=['dashboard','pengiriman','kendaraan','kebun','pks','users','rls','audit-log','profile','ubah-password'];
$users=$db->query('SELECT id,role,permissions FROM users')->fetchAll(PDO::FETCH_ASSOC);
$upsert=$db->prepare('INSERT INTO user_permissions (user_id,module,can_create,can_read,can_update,can_delete,created_at,updated_at) VALUES (:user_id,:module,:can_create,:can_read,:can_update,:can_delete,NOW(),NOW()) ON DUPLICATE KEY UPDATE can_create=VALUES(can_create),can_read=VALUES(can_read),can_update=VALUES(can_update),can_delete=VALUES(can_delete),updated_at=NOW()');
foreach($users as $user){
    $permissions=json_decode($user['permissions'],true)?:[];
    foreach($modules as $module){
        $rules=$permissions[$module]??(in_array($module,['profile','ubah-password'],true)?['read'=>true,'update'=>true]:[]);
        $all=$user['role']==='superadmin';
        $upsert->execute([':user_id'=>$user['id'],':module'=>$module,':can_create'=>$all||!empty($rules['create'])?1:0,':can_read'=>$all||!empty($rules['read'])?1:0,':can_update'=>$all||!empty($rules['update'])?1:0,':can_delete'=>$all||!empty($rules['delete'])?1:0]);
    }
}
echo "Backfilled: user_permissions\n";
