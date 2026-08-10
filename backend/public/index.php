<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TransMore\Backend\Auth;
use TransMore\Backend\Config;
use TransMore\Backend\Database;
use TransMore\Backend\EnvLoader;
use TransMore\Backend\Migration;
use TransMore\Backend\OfflineGrant;
use TransMore\Backend\Request;
use TransMore\Backend\Response;
use TransMore\Backend\Validator;

ini_set('display_errors', Config::get('APP_DEBUG', '0') === '1' ? '1' : '0');
error_reporting(E_ALL);

EnvLoader::load(__DIR__ . '/../.env');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$request = new Request();
enforceRequestOrigin($method);

$apiPrefix = '/api';

if (strpos($uri, $apiPrefix) !== 0) {
    Response::error('API route not found', 404);
}

$path = substr($uri, strlen($apiPrefix));

$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$resource = $segments[0] ?? null;
$resourceId = $segments[1] ?? null;

try {
    switch (true) {
        case $path === '/health' && $method === 'GET':
            healthCheck();
            break;
        case $path === '/migrate' && $method === 'POST':
            migrate();
            break;
        case $path === '/login' && $method === 'POST':
            login($request);
            break;
        case $path === '/logout' && $method === 'POST':
            logout();
            break;
        case $path === '/me' && $method === 'GET':
            me();
            break;
        case $path === '/me' && $method === 'PUT':
            updateMe($request);
            break;
        case $path === '/me/password' && $method === 'PUT':
            updateMyPassword($request);
            break;
        case $path === '/bootstrap/import' && $method === 'POST':
            importLegacyMasterData($request);
            break;
        case $path === '/rls/users' && $method === 'GET':
            listRlsUsers();
            break;
        case $resource === 'users' && $method === 'GET' && $resourceId === null:
            listUsers();
            break;
        case $resource === 'users' && $method === 'GET' && $resourceId !== null:
            getUser((int) $resourceId);
            break;
        case count($segments) === 3 && $segments[0] === 'users' && $segments[2] === 'reset-password' && $method === 'POST':
            resetUserPassword((int) $segments[1]);
            break;
        case count($segments) === 3 && $segments[0] === 'users' && $segments[2] === 'permissions' && $method === 'PUT':
            updateUserPermissions((int) $segments[1], $request);
            break;
        case $resource === 'users' && $method === 'POST' && $resourceId === null:
            createUser($request);
            break;
        case $resource === 'users' && $method === 'PUT' && $resourceId !== null:
            updateUser((int) $resourceId, $request);
            break;
        case $resource === 'users' && $method === 'DELETE' && $resourceId !== null:
            deleteUser((int) $resourceId);
            break;
        case $resource === 'kendaraan' && $method === 'GET' && $resourceId === null:
            listKendaraan();
            break;
        case $resource === 'kendaraan' && $method === 'POST':
            createKendaraan($request);
            break;
        case $resource === 'kendaraan' && $method === 'PUT' && $resourceId !== null:
            updateKendaraan((int) $resourceId, $request);
            break;
        case $resource === 'kendaraan' && $method === 'DELETE' && $resourceId !== null:
            deleteKendaraan((int) $resourceId);
            break;
        case $resource === 'pks' && $method === 'GET' && $resourceId === null:
            listPks();
            break;
        case $resource === 'pks' && $method === 'POST':
            createPks($request);
            break;
        case $resource === 'pks' && $method === 'PUT' && $resourceId !== null:
            updatePks((int) $resourceId, $request);
            break;
        case $resource === 'pks' && $method === 'DELETE' && $resourceId !== null:
            deletePks((int) $resourceId);
            break;
        case $resource === 'kebun' && $method === 'GET' && $resourceId === null:
            listKebun();
            break;
        case $resource === 'kebun' && $method === 'POST':
            createKebun($request);
            break;
        case $resource === 'kebun' && $method === 'PUT' && $resourceId !== null:
            updateKebun((int) $resourceId, $request);
            break;
        case $resource === 'kebun' && $method === 'DELETE' && $resourceId !== null:
            deleteKebun((int) $resourceId);
            break;
        case $resource === 'transactions' && $method === 'GET' && $resourceId === null:
            listTransactions();
            break;
        case $path === '/transaction-options' && $method === 'GET':
            listTransactionOptions();
            break;
        case $path === '/transactions/sync' && $method === 'POST':
            syncTransaction($request);
            break;
        case count($segments) === 3 && $segments[0] === 'transactions' && $segments[1] === 'by-uuid' && $method === 'DELETE':
            deleteTransactionByUuid((string) $segments[2]);
            break;
        case $resource === 'transactions' && $method === 'POST':
            createTransaction($request);
            break;
        case $resource === 'transactions' && $method === 'PUT' && $resourceId !== null:
            updateTransaction((int) $resourceId, $request);
            break;
        case $resource === 'transactions' && $method === 'DELETE' && $resourceId !== null:
            deleteTransaction((int) $resourceId);
            break;
        case $resource === 'audit-logs' && $method === 'GET':
            listAuditLogs();
            break;
        case $resource === 'audit-logs' && $method === 'POST':
            createAuditLog($request);
            break;
        case $resource === 'audit-logs' && $method === 'DELETE' && $resourceId !== null:
            deleteAuditLog((int) $resourceId);
            break;
        default:
            Response::error('Endpoint not found', 404);
    }
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 422);
} catch (\PDOException $e) {
    if ((string)$e->getCode() === '23000') Response::error('Data yang sama sudah digunakan', 409);
    error_log(sprintf('[TransMore] Database error in %s:%d', $e->getFile(), $e->getLine()));
    Response::error('Internal server error', 500);
} catch (\Throwable $e) {
    error_log(sprintf('[TransMore] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
    Response::error(Config::get('APP_DEBUG', '0') === '1' ? $e->getMessage() : 'Internal server error', 500);
}

function enforceRequestOrigin(string $method): void
{
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;
    $origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    if ($origin === '') return;
    $allowedOrigins = array_filter(array_map(fn($value) => rtrim(trim($value), '/'), explode(',', (string)Config::get('FRONTEND_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'))));
    foreach ($allowedOrigins as $allowed) if (hash_equals($allowed, $origin)) return;
    Response::error('Origin not allowed', 403);
}

function migrate(): void
{
    $user = authenticate();
    if (($user['role'] ?? '') !== 'superadmin') {
        Response::error('Forbidden', 403);
    }
    $migrations = glob(__DIR__ . '/../migrations/*.sql');
    sort($migrations, SORT_STRING);
    $db = Database::connect();
    $db->beginTransaction();

    $hasMigrationsTable = true;
    try {
        $db->query('SELECT 1 FROM migrations LIMIT 1');
    } catch (\PDOException $e) {
        $hasMigrationsTable = false;
    }

    foreach ($migrations as $file) {
        $name = basename($file);

        if ($hasMigrationsTable) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM migrations WHERE name = :name');
            $stmt->execute([':name' => $name]);

            if ($stmt->fetchColumn() > 0) {
                continue;
            }
        }

        Migration::run($file);

        if (!$hasMigrationsTable) {
            $hasMigrationsTable = true;
        }

        $stmt = $db->prepare('INSERT INTO migrations (name, executed_at) VALUES (:name, :executed_at)');
        $stmt->execute([
            ':name' => $name,
            ':executed_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    $db->commit();
    Response::json(['success' => true]);
}

function authenticate(bool $authorizeRequest=true): array
{
    $sessionId = $_COOKIE['transmore_session'] ?? '';
    if (!$sessionId) {
        Response::error('Not authenticated', 401);
    }

    $session = Auth::getSession($sessionId);
    if (!$session) {
        Response::error('Session expired or invalid', 401);
    }

    $user = Auth::getUserById((int) $session['user_id']);
    if (!$user || ($user['status'] ?? '') !== 'Aktif') {
        Response::error('User not found', 401);
    }

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $parts = array_values(array_filter(explode('/', trim(substr($uri, strlen('/api')), '/'))));
    $resource = $parts[0] ?? '';
    $pageMap = ['users'=>'users','kendaraan'=>'kendaraan','pks'=>'pks','kebun'=>'kebun','transactions'=>'pengiriman','audit-logs'=>'audit-log','me'=>(($parts[1]??'')==='password'?'ubah-password':'profile')];
    $action = $method === 'GET' ? 'read' : ($method === 'POST' ? 'create' : ($method === 'PUT' ? 'update' : 'delete'));
    if ($authorizeRequest && ($user['role'] ?? '') !== 'superadmin' && isset($pageMap[$resource])) {
        $permissions = permissionsForUser((int)$user['id'], $user['permissions'] ?? '{}');
        if (empty($permissions[$pageMap[$resource]][$action])) {
            Response::error('Forbidden', 403);
        }
    }

    return $user;
}

function healthCheck(): void
{
    Database::connect()->query('SELECT 1')->fetchColumn();
    Response::json([
        'success' => true,
        'data' => [
            'backend' => 'connected',
            'database' => 'connected',
            'checkedAt' => (new DateTime())->format(DateTime::ATOM),
        ],
    ]);
}

function permissionsForUser(int $userId, string $fallbackJson='{}'): array
{
    $pages=['dashboard','pengiriman','kendaraan','kebun','pks','users','rls','audit-log','profile','ubah-password'];
    $permissions=[];foreach($pages as $page)$permissions[$page]=['create'=>false,'read'=>false,'update'=>false,'delete'=>false];
    try{
        $stmt=Database::connect()->prepare('SELECT module,can_create,can_read,can_update,can_delete FROM user_permissions WHERE user_id=:user_id');
        $stmt->execute([':user_id'=>$userId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rows){foreach($rows as $row)$permissions[$row['module']]=['create'=>(bool)$row['can_create'],'read'=>(bool)$row['can_read'],'update'=>(bool)$row['can_update'],'delete'=>(bool)$row['can_delete']];return $permissions;}
    }catch(Throwable){}
    $fallback=json_decode($fallbackJson,true);return is_array($fallback)?array_replace_recursive($permissions,$fallback):$permissions;
}

function savePermissions(int $userId, array $permissions): void
{
    $stmt=Database::connect()->prepare('INSERT INTO user_permissions (user_id,module,can_create,can_read,can_update,can_delete,created_at,updated_at) VALUES (:user_id,:module,:can_create,:can_read,:can_update,:can_delete,NOW(),NOW()) ON DUPLICATE KEY UPDATE can_create=VALUES(can_create),can_read=VALUES(can_read),can_update=VALUES(can_update),can_delete=VALUES(can_delete),updated_at=NOW()');
    foreach($permissions as $module=>$rules){if(!is_array($rules))continue;$stmt->execute([':user_id'=>$userId,':module'=>$module,':can_create'=>!empty($rules['create'])?1:0,':can_read'=>!empty($rules['read'])?1:0,':can_update'=>!empty($rules['update'])?1:0,':can_delete'=>!empty($rules['delete'])?1:0]);}
}

function listRlsUsers(): void
{
    $actor=authenticate(false);
    $actorPermissions=permissionsForUser((int)$actor['id'],$actor['permissions']??'{}');
    if(($actor['role']??'')!=='superadmin'&&empty($actorPermissions['rls']['read']))Response::error('Forbidden',403);
    $stmt=Database::connect()->query('SELECT id,iduser,email,name,handphone,alamat,role,status,permissions,must_change_password,created_by,created_at,updated_by,updated_at FROM users ORDER BY created_at DESC');
    $users=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($users as &$user)$user['permissions']=permissionsForUser((int)$user['id'],$user['permissions']);
    Response::json(['success'=>true,'data'=>$users]);
}

function importLegacyMasterData(Request $request): void
{
    $user = authenticate();
    if (!in_array($user['role'] ?? '', ['superadmin', 'admin'], true)) {
        Response::error('Forbidden', 403);
    }
    $body = $request->body();
    foreach (['kendaraan','pks','kebun'] as $key) {
        if (!isset($body[$key]) || !is_array($body[$key]) || count($body[$key]) > 5000) {
            Response::json(['success'=>false,'errors'=>[$key=>'Payload import tidak valid.']], 422);
        }
    }
    $db = Database::connect();
    $db->beginTransaction();
    try {
        $kendaraan = $db->prepare('INSERT INTO kendaraan (idkendaraan,namaPemilik,tnkb,tahun,handphone,bank,rekening,alamat,status,created_by,created_at,updated_by,updated_at) VALUES (:idkendaraan,:namaPemilik,:tnkb,:tahun,:handphone,:bank,:rekening,:alamat,:status,:created_by,:created_at,:updated_by,:updated_at) ON DUPLICATE KEY UPDATE namaPemilik=VALUES(namaPemilik),tnkb=VALUES(tnkb),tahun=VALUES(tahun),handphone=VALUES(handphone),bank=VALUES(bank),rekening=VALUES(rekening),alamat=VALUES(alamat),status=VALUES(status),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)');
        foreach ($body['kendaraan'] as $row) {
            $validator=(new Validator())->required('idkendaraan',$row['idkendaraan']??null)->uuid('idkendaraan',$row['idkendaraan']??null)->required('tnkb',$row['tnkb']??null)->required('namaPemilik',$row['namaPemilik']??null);
            if($validator->fails())throw new InvalidArgumentException('Data kendaraan legacy tidak valid.');
            $kendaraan->execute([':idkendaraan'=>$row['idkendaraan'],':namaPemilik'=>$row['namaPemilik'],':tnkb'=>$row['tnkb'],':tahun'=>(int)($row['tahun']??date('Y')),':handphone'=>$row['handphone']??'',':bank'=>$row['bank']??'',':rekening'=>$row['rekening']??'',':alamat'=>$row['alamat']??'',':status'=>$row['status']??'Aktif',':created_by'=>$row['createdBy']??$user['email'],':created_at'=>$row['createdAt']??date('Y-m-d H:i:s'),':updated_by'=>$user['email'],':updated_at'=>$row['updatedAt']??date('Y-m-d H:i:s')]);
        }
        $pks = $db->prepare('INSERT INTO pks (idpks,nama,pic,handphone,alamat,status,created_by,created_at,updated_by,updated_at) VALUES (:uuid,:nama,:pic,:handphone,:alamat,:status,:created_by,:created_at,:updated_by,:updated_at) ON DUPLICATE KEY UPDATE nama=VALUES(nama),pic=VALUES(pic),handphone=VALUES(handphone),alamat=VALUES(alamat),status=VALUES(status),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)');
        foreach ($body['pks'] as $row) {
            $validator=(new Validator())->required('idpks',$row['idpks']??null)->uuid('idpks',$row['idpks']??null)->required('nama',$row['nama']??null);
            if($validator->fails())throw new InvalidArgumentException('Data PKS legacy tidak valid.');
            $pks->execute([':uuid'=>$row['idpks'],':nama'=>$row['nama'],':pic'=>$row['pic']??'',':handphone'=>$row['handphone']??'',':alamat'=>$row['alamat']??'',':status'=>$row['status']??'Aktif',':created_by'=>$row['createdBy']??$user['email'],':created_at'=>$row['createdAt']??date('Y-m-d H:i:s'),':updated_by'=>$user['email'],':updated_at'=>$row['updatedAt']??date('Y-m-d H:i:s')]);
        }
        $kebun = $db->prepare('INSERT INTO kebun (idkebun,nama,pic,handphone,alamat,status,created_by,created_at,updated_by,updated_at) VALUES (:uuid,:nama,:pic,:handphone,:alamat,:status,:created_by,:created_at,:updated_by,:updated_at) ON DUPLICATE KEY UPDATE nama=VALUES(nama),pic=VALUES(pic),handphone=VALUES(handphone),alamat=VALUES(alamat),status=VALUES(status),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)');
        foreach ($body['kebun'] as $row) {
            $validator=(new Validator())->required('idkebun',$row['idkebun']??null)->uuid('idkebun',$row['idkebun']??null)->required('nama',$row['nama']??null);
            if($validator->fails())throw new InvalidArgumentException('Data kebun legacy tidak valid.');
            $kebun->execute([':uuid'=>$row['idkebun'],':nama'=>$row['nama'],':pic'=>$row['pic']??'',':handphone'=>$row['handphone']??'',':alamat'=>$row['alamat']??'',':status'=>$row['status']??'Aktif',':created_by'=>$row['createdBy']??$user['email'],':created_at'=>$row['createdAt']??date('Y-m-d H:i:s'),':updated_by'=>$user['email'],':updated_at'=>$row['updatedAt']??date('Y-m-d H:i:s')]);
        }
        $audit=$db->prepare('INSERT INTO audit_logs (module,action,detail,actor,via,created_at) VALUES ("Migration","IMPORT",:detail,:actor,"browser-indexeddb",NOW())');
        $audit->execute([':detail'=>sprintf('Imported %d kendaraan, %d PKS, %d kebun',count($body['kendaraan']),count($body['pks']),count($body['kebun'])),':actor'=>$user['email']]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
    Response::json(['success'=>true,'data'=>['kendaraan'=>count($body['kendaraan']),'pks'=>count($body['pks']),'kebun'=>count($body['kebun'])]]);
}

function listUsers(): void
{
    authenticate();
    $db = Database::connect();
    $stmt = $db->query('SELECT id, iduser, email, name, handphone, alamat, role, status, permissions, must_change_password, created_by, created_at, updated_by, updated_at FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $user['permissions'] = permissionsForUser((int)$user['id'], $user['permissions']);
    }

    Response::json(['success' => true, 'data' => $users]);
}

function getUser(int $id): void
{
    authenticate();
    $user = Auth::getUserById($id);
    if (!$user) {
        Response::error('User not found', 404);
    }

    $user['permissions'] = permissionsForUser((int)$user['id'], $user['permissions']);
    Response::json(['success' => true, 'data' => $user]);
}

function createUser(Request $request): void
{
    authenticate();
    $body = $request->body();
    $validator = new Validator();
    $validator->required('email', $body['email'] ?? null)
              ->email('email', $body['email'] ?? null)
              ->required('name', $body['name'] ?? null)
              ->required('role', $body['role'] ?? null)
              ->in('role', $body['role'] ?? null, ['superadmin', 'admin', 'driver'])
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Aktif', 'Nonaktif'])
              ->required('iduser', $body['iduser'] ?? null)->uuid('iduser', $body['iduser'] ?? null)
              ->required('handphone', $body['handphone'] ?? null)->phone('handphone', $body['handphone'] ?? null)
              ->required('alamat', $body['alamat'] ?? null);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $permissions = is_array($body['permissions'] ?? null) ? $body['permissions'] : [];
    $password = generateTemporaryPassword();
    $passwordHash = Auth::hashPassword($password);
    $now = (new DateTime())->format('Y-m-d H:i:s');

    $db = Database::connect();
    $stmt = $db->prepare('INSERT INTO users (iduser, email, password_hash, name, handphone, alamat, role, status, permissions, must_change_password, created_by, created_at) VALUES (:iduser, :email, :password_hash, :name, :handphone, :alamat, :role, :status, :permissions, :must_change_password, :created_by, :created_at)');
    $stmt->execute([
        ':iduser' => $body['iduser'],
        ':email' => strtolower(trim((string)$body['email'])),
        ':password_hash' => $passwordHash,
        ':name' => $body['name'],
        ':handphone' => Auth::normalizePhone((string)$body['handphone']),
        ':alamat' => $body['alamat'],
        ':role' => $body['role'],
        ':status' => $body['status'],
        ':permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
        ':must_change_password' => 1,
        ':created_by' => authenticate()['email'],
        ':created_at' => $now,
    ]);

    $newId=(int)$db->lastInsertId();savePermissions($newId,$permissions);
    Response::json(['success' => true, 'message' => 'User created', 'data'=>['id'=>$newId,'password'=>$password]]);
}

function updateUser(int $id, Request $request): void
{
    authenticate();
    $body = $request->body();
    $validator = new Validator();
    $validator->required('email', $body['email'] ?? null)
              ->email('email', $body['email'] ?? null)
              ->required('handphone', $body['handphone'] ?? null)
              ->phone('handphone', $body['handphone'] ?? null)
              ->required('name', $body['name'] ?? null)
              ->required('role', $body['role'] ?? null)
              ->in('role', $body['role'] ?? null, ['superadmin', 'admin', 'driver'])
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Aktif', 'Nonaktif']);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $permissions = is_array($body['permissions'] ?? null) ? $body['permissions'] : [];
    $db = Database::connect();
    $stmt = $db->prepare('UPDATE users SET email = :email, name = :name, handphone=:handphone, alamat=:alamat, role = :role, status = :status, permissions = :permissions, updated_by = :updated_by, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':email' => strtolower(trim((string)$body['email'])),
        ':name' => $body['name'],
        ':handphone' => Auth::normalizePhone((string)($body['handphone'] ?? '')),
        ':alamat' => $body['alamat'] ?? '',
        ':role' => $body['role'],
        ':status' => $body['status'],
        ':permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
        ':updated_by' => 'api',
        ':updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ':id' => $id,
    ]);

    savePermissions($id,$permissions);
    Response::json(['success' => true, 'message' => 'User updated']);
}

function deleteUser(int $id): void
{
    authenticate();
    $db = Database::connect();
    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);

    Response::json(['success' => true, 'message' => 'User deleted']);
}

function listKendaraan(): void
{
    authenticate();
    $db = Database::connect();
    $stmt = $db->query('SELECT * FROM kendaraan ORDER BY created_at DESC');
    Response::json(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createKendaraan(Request $request): void
{
    authenticate();
    $body = $request->body();
    $validator = new Validator();
    $validator->required('idkendaraan', $body['idkendaraan'] ?? null)
              ->required('namaPemilik', $body['namaPemilik'] ?? null)
              ->required('tnkb', $body['tnkb'] ?? null)
              ->required('tahun', $body['tahun'] ?? null)
              ->integer('tahun', $body['tahun'] ?? null)
              ->required('handphone', $body['handphone'] ?? null)
              ->required('bank', $body['bank'] ?? null)
              ->required('rekening', $body['rekening'] ?? null)
              ->required('alamat', $body['alamat'] ?? null)
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Aktif', 'Nonaktif']);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $db = Database::connect();
    $stmt = $db->prepare('INSERT INTO kendaraan (idkendaraan, namaPemilik, tnkb, tahun, handphone, bank, rekening, alamat, status, created_by, created_at) VALUES (:idkendaraan, :namaPemilik, :tnkb, :tahun, :handphone, :bank, :rekening, :alamat, :status, :created_by, :created_at)');
    $stmt->execute([
        ':idkendaraan' => $body['idkendaraan'],
        ':namaPemilik' => $body['namaPemilik'],
        ':tnkb' => $body['tnkb'],
        ':tahun' => $body['tahun'],
        ':handphone' => $body['handphone'],
        ':bank' => $body['bank'],
        ':rekening' => $body['rekening'],
        ':alamat' => $body['alamat'],
        ':status' => $body['status'],
        ':created_by' => 'api',
        ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);

    Response::json(['success' => true, 'message' => 'Kendaraan created']);
}

function updateKendaraan(int $id, Request $request): void
{
    authenticate();
    $body = $request->body();
    $validator = new Validator();
    $validator->required('idkendaraan', $body['idkendaraan'] ?? null)
              ->required('namaPemilik', $body['namaPemilik'] ?? null)
              ->required('tnkb', $body['tnkb'] ?? null)
              ->required('tahun', $body['tahun'] ?? null)
              ->integer('tahun', $body['tahun'] ?? null)
              ->required('handphone', $body['handphone'] ?? null)
              ->required('bank', $body['bank'] ?? null)
              ->required('rekening', $body['rekening'] ?? null)
              ->required('alamat', $body['alamat'] ?? null)
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Aktif', 'Nonaktif']);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $stmt = Database::connect()->prepare('UPDATE kendaraan SET idkendaraan = :idkendaraan, namaPemilik = :namaPemilik, tnkb = :tnkb, tahun = :tahun, handphone = :handphone, bank = :bank, rekening = :rekening, alamat = :alamat, status = :status, updated_by = :updated_by, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':idkendaraan' => $body['idkendaraan'],
        ':namaPemilik' => $body['namaPemilik'],
        ':tnkb' => $body['tnkb'],
        ':tahun' => $body['tahun'],
        ':handphone' => $body['handphone'],
        ':bank' => $body['bank'],
        ':rekening' => $body['rekening'],
        ':alamat' => $body['alamat'],
        ':status' => $body['status'],
        ':updated_by' => 'api',
        ':updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ':id' => $id,
    ]);

    Response::json(['success' => true, 'message' => 'Kendaraan updated']);
}

function deleteKendaraan(int $id): void
{
    authenticate();
    $stmt = Database::connect()->prepare('DELETE FROM kendaraan WHERE id = :id');
    $stmt->execute([':id' => $id]);
    Response::json(['success' => true, 'message' => 'Kendaraan deleted']);
}

function listPks(): void
{
    authenticate();
    $stmt = Database::connect()->query('SELECT * FROM pks ORDER BY created_at DESC');
    Response::json(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createPks(Request $request): void
{
    authenticate();
    $body = $request->body();
    $validator = new Validator();
    $validator->required('idpks', $body['idpks'] ?? null)
              ->required('nama', $body['nama'] ?? null)
              ->required('pic', $body['pic'] ?? null)
              ->required('handphone', $body['handphone'] ?? null)
              ->required('alamat', $body['alamat'] ?? null)
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Aktif', 'Nonaktif']);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $stmt = Database::connect()->prepare('INSERT INTO pks (idpks, nama, pic, handphone, alamat, status, created_by, created_at) VALUES (:idpks, :nama, :pic, :handphone, :alamat, :status, :created_by, :created_at)');
    $stmt->execute([
        ':idpks' => $body['idpks'],
        ':nama' => $body['nama'],
        ':pic' => $body['pic'],
        ':handphone' => $body['handphone'],
        ':alamat' => $body['alamat'],
        ':status' => $body['status'],
        ':created_by' => 'api',
        ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);

    Response::json(['success' => true, 'message' => 'PKS created']);
}

function updatePks(int $id, Request $request): void
{
    authenticate();
    $body = $request->body();
    $validator = new Validator();
    $validator->required('idpks', $body['idpks'] ?? null)
              ->required('nama', $body['nama'] ?? null)
              ->required('pic', $body['pic'] ?? null)
              ->required('handphone', $body['handphone'] ?? null)
              ->required('alamat', $body['alamat'] ?? null)
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Aktif', 'Nonaktif']);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $stmt = Database::connect()->prepare('UPDATE pks SET idpks = :idpks, nama = :nama, pic = :pic, handphone = :handphone, alamat = :alamat, status = :status, updated_by = :updated_by, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':idpks' => $body['idpks'],
        ':nama' => $body['nama'],
        ':pic' => $body['pic'],
        ':handphone' => $body['handphone'],
        ':alamat' => $body['alamat'],
        ':status' => $body['status'],
        ':updated_by' => 'api',
        ':updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ':id' => $id,
    ]);

    Response::json(['success' => true, 'message' => 'PKS updated']);
}

function deletePks(int $id): void
{
    authenticate();
    $stmt = Database::connect()->prepare('DELETE FROM pks WHERE id = :id');
    $stmt->execute([':id' => $id]);
    Response::json(['success' => true, 'message' => 'PKS deleted']);
}

function listKebun(): void
{
    authenticate();
    $stmt = Database::connect()->query('SELECT * FROM kebun ORDER BY created_at DESC');
    Response::json(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createKebun(Request $request): void
{
    authenticate();
    $body = $request->body();
    $validator = new Validator();
    $validator->required('idkebun', $body['idkebun'] ?? null)
              ->required('nama', $body['nama'] ?? null)
              ->required('pic', $body['pic'] ?? null)
              ->required('handphone', $body['handphone'] ?? null)
              ->required('alamat', $body['alamat'] ?? null)
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Aktif', 'Nonaktif']);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $stmt = Database::connect()->prepare('INSERT INTO kebun (idkebun, nama, pic, handphone, alamat, status, created_by, created_at) VALUES (:idkebun, :nama, :pic, :handphone, :alamat, :status, :created_by, :created_at)');
    $stmt->execute([
        ':idkebun' => $body['idkebun'],
        ':nama' => $body['nama'],
        ':pic' => $body['pic'],
        ':handphone' => $body['handphone'],
        ':alamat' => $body['alamat'],
        ':status' => $body['status'],
        ':created_by' => 'api',
        ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);

    Response::json(['success' => true, 'message' => 'Kebun created']);
}

function updateKebun(int $id, Request $request): void
{
    authenticate();
    $body = $request->body();
    $validator = new Validator();
    $validator->required('idkebun', $body['idkebun'] ?? null)
              ->required('nama', $body['nama'] ?? null)
              ->required('pic', $body['pic'] ?? null)
              ->required('handphone', $body['handphone'] ?? null)
              ->required('alamat', $body['alamat'] ?? null)
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Aktif', 'Nonaktif']);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $stmt = Database::connect()->prepare('UPDATE kebun SET idkebun = :idkebun, nama = :nama, pic = :pic, handphone = :handphone, alamat = :alamat, status = :status, updated_by = :updated_by, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':idkebun' => $body['idkebun'],
        ':nama' => $body['nama'],
        ':pic' => $body['pic'],
        ':handphone' => $body['handphone'],
        ':alamat' => $body['alamat'],
        ':status' => $body['status'],
        ':updated_by' => 'api',
        ':updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ':id' => $id,
    ]);

    Response::json(['success' => true, 'message' => 'Kebun updated']);
}

function deleteKebun(int $id): void
{
    authenticate();
    $stmt = Database::connect()->prepare('DELETE FROM kebun WHERE id = :id');
    $stmt->execute([':id' => $id]);
    Response::json(['success' => true, 'message' => 'Kebun deleted']);
}

function listTransactions(): void
{
    $user=authenticate();
    $stmt = Database::connect()->query('SELECT * FROM transactions ORDER BY created_at DESC');
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if (($user['role'] ?? '') === 'driver') {
        foreach ($rows as &$row) {
            $row['price']=0;
            $row['fee']=0;
        }
        unset($row);
    }
    Response::json(['success' => true, 'data' => $rows]);
}

function listTransactionOptions(): void
{
    $user=authenticate(false);
    $permissions=permissionsForUser((int)$user['id'],$user['permissions']??'{}');
    if (($user['role']??'') !== 'superadmin' && empty($permissions['pengiriman']['read'])) {
        Response::error('Forbidden',403);
    }

    $db=Database::connect();
    Response::json(['success'=>true,'data'=>[
        'kebun'=>$db->query("SELECT nama FROM kebun WHERE status='Aktif' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC),
        'kendaraan'=>$db->query("SELECT tnkb,namaPemilik FROM kendaraan WHERE status='Aktif' ORDER BY tnkb")->fetchAll(PDO::FETCH_ASSOC),
        'pks'=>$db->query("SELECT nama FROM pks WHERE status='Aktif' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC),
    ]]);
}

function resetUserPassword(int $id): void
{
    $actor=authenticate(false);
    $actorPermissions=permissionsForUser((int)$actor['id'],$actor['permissions']??'{}');
    if(($actor['role']??'')!=='superadmin'&&(($actor['role']??'')!=='admin'||empty($actorPermissions['users']['update'])))Response::error('Forbidden',403);
    $target=Auth::getUserById($id);if(!$target)Response::error('User not found',404);
    if(($target['role']??'')==='superadmin')Response::error('Superadmin cannot be reset here',403);
    $password=generateTemporaryPassword();
    $stmt=Database::connect()->prepare('UPDATE users SET password_hash=:hash,must_change_password=1,updated_by=:actor,updated_at=NOW() WHERE id=:id');
    $stmt->execute([':hash'=>Auth::hashPassword($password),':actor'=>$actor['email'],':id'=>$id]);
    Response::json(['success'=>true,'data'=>['password'=>$password]]);
}

function generateTemporaryPassword(int $length=14): string
{
    $groups=['ABCDEFGHJKLMNPQRSTUVWXYZ','abcdefghijkmnopqrstuvwxyz','23456789','!@#$%'];
    $pool=implode('',$groups);$characters=[];
    foreach($groups as $group)$characters[]=$group[random_int(0,strlen($group)-1)];
    while(count($characters)<$length)$characters[]=$pool[random_int(0,strlen($pool)-1)];
    for($index=count($characters)-1;$index>0;$index--){$target=random_int(0,$index);[$characters[$index],$characters[$target]]=[$characters[$target],$characters[$index]];}
    return implode('',$characters);
}

function updateUserPermissions(int $id, Request $request): void
{
    $actor=authenticate(false);
    $actorPermissions=permissionsForUser((int)$actor['id'],$actor['permissions']??'{}');
    if(($actor['role']??'')!=='superadmin'&&empty($actorPermissions['rls']['update']))Response::error('Tidak memiliki izin update RLS',403);
    $target=Auth::getUserById($id);
    if(!$target)Response::error('User not found',404);
    if(($target['role']??'')==='superadmin')Response::error('Permission superadmin dilindungi sistem',403);
    $permissions=$request->body()['permissions']??null;
    $pages=['dashboard','users','kendaraan','pks','kebun','rls','audit-log','pengiriman','profile','ubah-password'];
    $actions=['create','read','update','delete'];
    if(!is_array($permissions))Response::json(['success'=>false,'errors'=>['permissions'=>'Format permission tidak valid.']],422);
    foreach($pages as $page){
        if(!isset($permissions[$page])||!is_array($permissions[$page]))Response::json(['success'=>false,'errors'=>['permissions'=>"Permission {$page} tidak lengkap."]],422);
        foreach($actions as $action)if(!array_key_exists($action,$permissions[$page])||!is_bool($permissions[$page][$action]))Response::json(['success'=>false,'errors'=>['permissions'=>"Permission {$page}.{$action} harus boolean."]],422);
    }
    $db=Database::connect();$db->beginTransaction();
    try{
        $stmt=$db->prepare('UPDATE users SET permissions=:permissions,updated_by=:actor,updated_at=NOW() WHERE id=:id');
        $stmt->execute([':permissions'=>json_encode($permissions,JSON_UNESCAPED_UNICODE),':actor'=>$actor['email'],':id'=>$id]);
        savePermissions($id,$permissions);
        $audit=$db->prepare('INSERT INTO audit_logs (module,action,detail,actor,via,created_at) VALUES ("RLS","UPDATE",:detail,:actor,"api",NOW())');
        $audit->execute([':detail'=>'Permission updated for '.$target['email'],':actor'=>$actor['email']]);
        $db->commit();
    }catch(Throwable $exception){if($db->inTransaction())$db->rollBack();throw $exception;}
    Response::json(['success'=>true]);
}

function validateTransaction(array $body): Validator
{
    $validator = new Validator();
    $validator->required('idpengiriman', $body['idpengiriman'] ?? null)
        ->uuid('idpengiriman', $body['idpengiriman'] ?? null)
        ->required('number', $body['number'] ?? null)->minLength('number', $body['number'] ?? null, 3)
        ->required('date', $body['date'] ?? null)->date('date', $body['date'] ?? null)
        ->required('kebun', $body['kebun'] ?? null)->required('divisi', $body['divisi'] ?? null)
        ->required('vehicle', $body['vehicle'] ?? null)
        ->required('odoStart', $body['odoStart'] ?? null)->integer('odoStart', $body['odoStart'] ?? null)
        ->required('pks', $body['pks'] ?? null)
        ->required('driver', $body['driver'] ?? null)
        ->url('docLink', $body['docLink'] ?? null)
        ->required('status', $body['status'] ?? null)->in('status', $body['status'] ?? null, ['Draft','Proses','Selesai']);
    if (is_numeric($body['odoStart'] ?? null) && is_numeric($body['odoEnd'] ?? null) && $body['odoEnd'] < $body['odoStart']) {
        $validator->add('odoEnd', 'Odo akhir tidak boleh lebih kecil dari odo awal.');
    }
    return $validator;
}

function applyTransactionPolicy(array $body, array $user, ?int $transactionId=null): array
{
    if (($user['role'] ?? '') !== 'driver') return $body;

    $body['driver'] = $user['name'];
    $stmt = $transactionId === null
        ? Database::connect()->prepare('SELECT price,fee FROM transactions WHERE idpengiriman=:key LIMIT 1')
        : Database::connect()->prepare('SELECT price,fee FROM transactions WHERE id=:key LIMIT 1');
    $stmt->execute([':key'=>$transactionId??($body['idpengiriman']??'')]);
    $financial = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $body['price'] = $financial['price'] ?? 0;
    $body['fee'] = $financial['fee'] ?? 0;
    return $body;
}

function syncTransaction(Request $request): void
{
    $user = authenticate();
    $body = applyTransactionPolicy($request->body(), $user);
    $validator = validateTransaction($body);
    if ($validator->fails()) Response::json(['success'=>false,'errors'=>$validator->getErrors()], 422);
    $sql = 'INSERT INTO transactions (idpengiriman,number,date,kebun,divisi,vehicle,odo_start,odo_end,driver,load_date,load_weight,unload_date,unload_weight,price,fee,receiver_pic,notes,doc_link,approved,pks,status,created_by,created_at) VALUES (:idpengiriman,:number,:date,:kebun,:divisi,:vehicle,:odo_start,:odo_end,:driver,:load_date,:load_weight,:unload_date,:unload_weight,:price,:fee,:receiver_pic,:notes,:doc_link,:approved,:pks,:status,:actor,:created_at) ON DUPLICATE KEY UPDATE number=VALUES(number),date=VALUES(date),kebun=VALUES(kebun),divisi=VALUES(divisi),vehicle=VALUES(vehicle),odo_start=VALUES(odo_start),odo_end=VALUES(odo_end),driver=VALUES(driver),load_date=VALUES(load_date),load_weight=VALUES(load_weight),unload_date=VALUES(unload_date),unload_weight=VALUES(unload_weight),price=VALUES(price),fee=VALUES(fee),receiver_pic=VALUES(receiver_pic),notes=VALUES(notes),doc_link=VALUES(doc_link),approved=VALUES(approved),pks=VALUES(pks),status=VALUES(status),updated_by=VALUES(created_by),updated_at=NOW()';
    Database::connect()->prepare($sql)->execute([
        ':idpengiriman'=>$body['idpengiriman'],':number'=>$body['number'],':date'=>$body['date'],':kebun'=>$body['kebun'],':divisi'=>$body['divisi'],':vehicle'=>$body['vehicle'],':odo_start'=>$body['odoStart'],':odo_end'=>$body['odoEnd'],':driver'=>$body['driver'],':load_date'=>$body['loadDate'],':load_weight'=>$body['loadWeight'],':unload_date'=>$body['unloadDate'],':unload_weight'=>$body['unloadWeight'],':price'=>$body['price'],':fee'=>$body['fee'],':receiver_pic'=>$body['receiverPic'],':notes'=>$body['notes']??null,':doc_link'=>$body['docLink']??null,':approved'=>!empty($body['approved'])?1:0,':pks'=>$body['pks']??null,':status'=>$body['status'],':actor'=>$user['email'],':created_at'=>$body['createdAt']??(new DateTime())->format('Y-m-d H:i:s')
    ]);
    Response::json(['success'=>true]);
}

function deleteTransactionByUuid(string $uuid): void
{
    authenticate();
    $stmt=Database::connect()->prepare('DELETE FROM transactions WHERE idpengiriman=:uuid');
    $stmt->execute([':uuid'=>$uuid]);
    Response::json(['success'=>true]);
}

function createTransaction(Request $request): void
{
    $user = authenticate();
    $body = applyTransactionPolicy($request->body(), $user);
    $validator = new Validator();
    $validator->required('idpengiriman', $body['idpengiriman'] ?? null)
              ->required('number', $body['number'] ?? null)
              ->required('date', $body['date'] ?? null)
              ->required('kebun', $body['kebun'] ?? null)
              ->required('divisi', $body['divisi'] ?? null)
              ->required('vehicle', $body['vehicle'] ?? null)
              ->required('odoStart', $body['odoStart'] ?? null)
              ->integer('odoStart', $body['odoStart'] ?? null)
              ->required('odoEnd', $body['odoEnd'] ?? null)
              ->integer('odoEnd', $body['odoEnd'] ?? null)
              ->required('driver', $body['driver'] ?? null)
              ->required('loadDate', $body['loadDate'] ?? null)
              ->required('loadWeight', $body['loadWeight'] ?? null)
              ->integer('loadWeight', $body['loadWeight'] ?? null)
              ->required('unloadDate', $body['unloadDate'] ?? null)
              ->required('unloadWeight', $body['unloadWeight'] ?? null)
              ->integer('unloadWeight', $body['unloadWeight'] ?? null)
              ->required('price', $body['price'] ?? null)
              ->numeric('price', $body['price'] ?? null)
              ->required('fee', $body['fee'] ?? null)
              ->numeric('fee', $body['fee'] ?? null)
              ->required('receiverPic', $body['receiverPic'] ?? null)
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Draft', 'Proses', 'Selesai']);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $stmt = Database::connect()->prepare('INSERT INTO transactions (idpengiriman, number, date, kebun, divisi, vehicle, odo_start, odo_end, driver, load_date, load_weight, unload_date, unload_weight, price, fee, receiver_pic, notes, doc_link, approved, pks, status, created_by, created_at) VALUES (:idpengiriman, :number, :date, :kebun, :divisi, :vehicle, :odo_start, :odo_end, :driver, :load_date, :load_weight, :unload_date, :unload_weight, :price, :fee, :receiver_pic, :notes, :doc_link, :approved, :pks, :status, :created_by, :created_at)');
    $stmt->execute([
        ':idpengiriman' => $body['idpengiriman'],
        ':number' => $body['number'],
        ':date' => $body['date'],
        ':kebun' => $body['kebun'],
        ':divisi' => $body['divisi'],
        ':vehicle' => $body['vehicle'],
        ':odo_start' => $body['odoStart'],
        ':odo_end' => $body['odoEnd'],
        ':driver' => $body['driver'],
        ':load_date' => $body['loadDate'],
        ':load_weight' => $body['loadWeight'],
        ':unload_date' => $body['unloadDate'],
        ':unload_weight' => $body['unloadWeight'],
        ':price' => $body['price'],
        ':fee' => $body['fee'],
        ':receiver_pic' => $body['receiverPic'],
        ':notes' => $body['notes'] ?? null,
        ':doc_link' => $body['docLink'] ?? null,
        ':approved' => !empty($body['approved']) ? 1 : 0,
        ':pks' => $body['pks'] ?? null,
        ':status' => $body['status'],
        ':created_by' => 'api',
        ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);

    Response::json(['success' => true, 'message' => 'Transaction created']);
}

function updateTransaction(int $id, Request $request): void
{
    $user = authenticate();
    $body = applyTransactionPolicy($request->body(), $user, $id);
    $validator = new Validator();
    $validator->required('idpengiriman', $body['idpengiriman'] ?? null)
              ->required('number', $body['number'] ?? null)
              ->required('date', $body['date'] ?? null)
              ->required('kebun', $body['kebun'] ?? null)
              ->required('divisi', $body['divisi'] ?? null)
              ->required('vehicle', $body['vehicle'] ?? null)
              ->required('odoStart', $body['odoStart'] ?? null)
              ->integer('odoStart', $body['odoStart'] ?? null)
              ->required('odoEnd', $body['odoEnd'] ?? null)
              ->integer('odoEnd', $body['odoEnd'] ?? null)
              ->required('driver', $body['driver'] ?? null)
              ->required('loadDate', $body['loadDate'] ?? null)
              ->required('loadWeight', $body['loadWeight'] ?? null)
              ->integer('loadWeight', $body['loadWeight'] ?? null)
              ->required('unloadDate', $body['unloadDate'] ?? null)
              ->required('unloadWeight', $body['unloadWeight'] ?? null)
              ->integer('unloadWeight', $body['unloadWeight'] ?? null)
              ->required('price', $body['price'] ?? null)
              ->numeric('price', $body['price'] ?? null)
              ->required('fee', $body['fee'] ?? null)
              ->numeric('fee', $body['fee'] ?? null)
              ->required('receiverPic', $body['receiverPic'] ?? null)
              ->required('status', $body['status'] ?? null)
              ->in('status', $body['status'] ?? null, ['Draft', 'Proses', 'Selesai']);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    $stmt = Database::connect()->prepare('UPDATE transactions SET idpengiriman = :idpengiriman, number = :number, date = :date, kebun = :kebun, divisi = :divisi, vehicle = :vehicle, odo_start = :odo_start, odo_end = :odo_end, driver = :driver, load_date = :load_date, load_weight = :load_weight, unload_date = :unload_date, unload_weight = :unload_weight, price = :price, fee = :fee, receiver_pic = :receiver_pic, notes = :notes, doc_link = :doc_link, approved = :approved, pks = :pks, status = :status, updated_by = :updated_by, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':idpengiriman' => $body['idpengiriman'],
        ':number' => $body['number'],
        ':date' => $body['date'],
        ':kebun' => $body['kebun'],
        ':divisi' => $body['divisi'],
        ':vehicle' => $body['vehicle'],
        ':odo_start' => $body['odoStart'],
        ':odo_end' => $body['odoEnd'],
        ':driver' => $body['driver'],
        ':load_date' => $body['loadDate'],
        ':load_weight' => $body['loadWeight'],
        ':unload_date' => $body['unloadDate'],
        ':unload_weight' => $body['unloadWeight'],
        ':price' => $body['price'],
        ':fee' => $body['fee'],
        ':receiver_pic' => $body['receiverPic'],
        ':notes' => $body['notes'] ?? null,
        ':doc_link' => $body['docLink'] ?? null,
        ':approved' => !empty($body['approved']) ? 1 : 0,
        ':pks' => $body['pks'] ?? null,
        ':status' => $body['status'],
        ':updated_by' => 'api',
        ':updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ':id' => $id,
    ]);

    Response::json(['success' => true, 'message' => 'Transaction updated']);
}

function deleteTransaction(int $id): void
{
    authenticate();
    $stmt = Database::connect()->prepare('DELETE FROM transactions WHERE id = :id');
    $stmt->execute([':id' => $id]);
    Response::json(['success' => true, 'message' => 'Transaction deleted']);
}

function listAuditLogs(): void
{
    authenticate();
    $stmt = Database::connect()->query('SELECT * FROM audit_logs ORDER BY created_at DESC');
    Response::json(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createAuditLog(Request $request): void
{
    $user=authenticate();$body=$request->body();$validator=new Validator();
    $validator->required('module',$body['module']??null)->required('action',$body['action']??null)->required('detail',$body['detail']??null)->required('via',$body['via']??null);
    if($validator->fails())Response::json(['success'=>false,'errors'=>$validator->getErrors()],422);
    $stmt=Database::connect()->prepare('INSERT INTO audit_logs (module,action,detail,actor,via,created_at) VALUES (:module,:action,:detail,:actor,:via,:created_at)');
    $stmt->execute([':module'=>$body['module'],':action'=>$body['action'],':detail'=>$body['detail'],':actor'=>$user['email'],':via'=>$body['via'],':created_at'=>(new DateTime())->format('Y-m-d H:i:s')]);
    Response::json(['success'=>true]);
}

function deleteAuditLog(int $id): void
{
    authenticate();
    $stmt=Database::connect()->prepare('DELETE FROM audit_logs WHERE id=:id');$stmt->execute([':id'=>$id]);
    Response::json(['success'=>true]);
}

function login(Request $request): void
{
    $body = $request->body();
    // `email` tetap diterima agar client versi lama tetap kompatibel.
    $identifier = (string)($body['identifier'] ?? $body['email'] ?? '');
    $validator = new Validator();
    $validator->required('identifier', $identifier)
              ->identifier('identifier', $identifier)
              ->required('password', $body['password'] ?? null);

    if ($validator->fails()) {
        Response::json(['success' => false, 'errors' => $validator->getErrors()], 422);
    }

    enforceLoginRateLimit($identifier);
    try {
        $user = Auth::login($identifier, $body['password']);
    } catch (\InvalidArgumentException) {
        recordFailedLogin($identifier);
        Response::error('Email/nomor HP atau password salah', 401);
    }
    clearFailedLogins($identifier);
    $sessionId = Auth::createSession((int) $user['id']);

    setcookie('transmore_session', $sessionId, [
        'expires' => time() + 28800,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);

    $permissions = permissionsForUser((int)$user['id'], $user['permissions']);
    $responseUser = [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'iduser' => $user['iduser'],
        'handphone' => $user['handphone'],
        'alamat' => $user['alamat'],
        'role' => $user['role'],
        'status' => $user['status'],
        'permissions' => $permissions,
        'mustChangePassword' => (bool)$user['must_change_password'],
        'createdBy' => $user['created_by'],
        'createdAt' => $user['created_at'],
        'updatedBy' => $user['updated_by'],
        'updatedAt' => $user['updated_at'],
    ];
    $offlineUser = $responseUser;
    $offlineUser['role'] = $offlineUser['role'] === 'driver' ? 'driver' : 'admin';
    foreach ($offlineUser['permissions'] as $page => &$actions) {
        if ($page === 'pengiriman') continue;
        $actions = ['create' => false, 'read' => (bool)($actions['read'] ?? false), 'update' => false, 'delete' => false];
    }
    unset($actions);

    $offlineGrant = null;
    try {
        $offlineGrant = OfflineGrant::issue($offlineUser);
    } catch (\Throwable $exception) {
        error_log(sprintf('[TransMore] Offline grant unavailable: %s', $exception->getMessage()));
    }

    Response::json([
        'success' => true,
        'user' => $responseUser,
        'offlineGrant' => $offlineGrant,
    ]);
}

function loginAttemptKey(string $identifier): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', Auth::normalizeIdentifier($identifier) . '|' . $ip);
}

function enforceLoginRateLimit(string $identifier): void
{
    $db = Database::connect();
    $db->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)')->execute();
    $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempt_key = :attempt_key AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $stmt->execute([':attempt_key' => loginAttemptKey($identifier)]);
    if ((int)$stmt->fetchColumn() >= 5) Response::error('Terlalu banyak percobaan login. Coba lagi dalam 15 menit.', 429);
}

function recordFailedLogin(string $identifier): void
{
    $stmt = Database::connect()->prepare('INSERT INTO login_attempts (attempt_key, attempted_at) VALUES (:attempt_key, NOW())');
    $stmt->execute([':attempt_key' => loginAttemptKey($identifier)]);
}

function clearFailedLogins(string $identifier): void
{
    $stmt = Database::connect()->prepare('DELETE FROM login_attempts WHERE attempt_key = :attempt_key');
    $stmt->execute([':attempt_key' => loginAttemptKey($identifier)]);
}

function logout(): void
{
    $sessionId = $_COOKIE['transmore_session'] ?? '';
    if ($sessionId) {
        Auth::destroySession($sessionId);
    }

    setcookie('transmore_session', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);

    Response::json(['success' => true]);
}

function me(): void
{
    $sessionId = $_COOKIE['transmore_session'] ?? '';
    if (!$sessionId) {
        Response::json(['success' => false, 'message' => 'Not authenticated'], 401);
    }

    $session = Auth::getSession($sessionId);
    if (!$session) {
        Response::json(['success' => false, 'message' => 'Session expired or invalid'], 401);
    }

    $db = Database::connect();
    $stmt = $db->prepare('SELECT id,iduser,email,name,handphone,alamat,role,status,permissions,must_change_password,created_by,created_at,updated_by,updated_at FROM users WHERE id = :id AND status="Aktif" LIMIT 1');
    $stmt->execute([':id' => $session['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        Response::json(['success' => false, 'message' => 'User not found'], 404);
    }

    $permissions=permissionsForUser((int)$user['id'],$user['permissions']);
    if(($user['role']??'')!=='superadmin'&&empty($permissions['profile']['read']))Response::error('Forbidden',403);

    Response::json([
        'success' => true,
        'user' => [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'iduser' => $user['iduser'],
            'handphone' => $user['handphone'],
            'alamat' => $user['alamat'],
            'role' => $user['role'],
            'status' => $user['status'],
            'permissions' => $permissions,
            'mustChangePassword' => (bool)$user['must_change_password'],
            'createdBy'=>$user['created_by'],'createdAt'=>$user['created_at'],'updatedBy'=>$user['updated_by'],'updatedAt'=>$user['updated_at'],
        ],
    ]);
}

function updateMe(Request $request): void
{
    $user=authenticate();$body=$request->body();$validator=new Validator();
    $validator->required('name',$body['name']??null)->minLength('name',$body['name']??null,3)->required('alamat',$body['alamat']??null);
    if($validator->fails())Response::json(['success'=>false,'errors'=>$validator->getErrors()],422);
    $stmt=Database::connect()->prepare('UPDATE users SET name=:name,alamat=:alamat,updated_by=:actor,updated_at=NOW() WHERE id=:id');
    $stmt->execute([':name'=>$body['name'],':alamat'=>$body['alamat'],':actor'=>$user['email'],':id'=>$user['id']]);
    Response::json(['success'=>true]);
}

function updateMyPassword(Request $request): void
{
    $user=authenticate();$body=$request->body();$validator=new Validator();
    $validator->required('current',$body['current']??null)->required('password',$body['password']??null)->minLength('password',$body['password']??null,8);
    if($validator->fails())Response::json(['success'=>false,'errors'=>$validator->getErrors()],422);
    if(!Auth::verifyPassword((string)$body['current'],$user['password_hash']))Response::error('Password saat ini salah',422);
    $stmt=Database::connect()->prepare('UPDATE users SET password_hash=:hash,must_change_password=0,updated_by=:actor,updated_at=NOW() WHERE id=:id');
    $stmt->execute([':hash'=>Auth::hashPassword($body['password']),':actor'=>$user['email'],':id'=>$user['id']]);
    Response::json(['success'=>true]);
}
