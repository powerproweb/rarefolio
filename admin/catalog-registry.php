<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_config.php';

const RF_CATALOG_BAR_MAP = [
    1 => ['serial' => 'E101837', 'title' => 'Rarefolio Silver Bar I'],
    2 => ['serial' => 'E102528', 'title' => 'Rarefolio Silver Bar II'],
    3 => ['serial' => 'P154829', 'title' => 'Rarefolio Silver Bar III'],
];
const RF_CATALOG_DATE_SEGMENT = '05-2026';
const RF_CATALOG_BAR_WEIGHT_SEGMENT = '100oz';

function rf_catalog_auth_credentials(): array
{
    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW']   ?? '';
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (($u === '' || $p === '') && is_string($auth) && $auth !== '' && stripos($auth, 'basic ') === 0) {
        $decoded = base64_decode(substr($auth, 6));
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            [$u, $p] = explode(':', $decoded, 2);
        }
    }
    return [(string)$u, (string)$p];
}

function rf_catalog_require_basic_auth(): void
{
    [$u, $p] = rf_catalog_auth_credentials();
    if ($u !== ADMIN_USER || $p !== ADMIN_PASS) {
        header('WWW-Authenticate: Basic realm="Rarefolio Catalog Registry"');
        http_response_code(401);
        exit('Unauthorized');
    }
}

function rf_catalog_json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rf_catalog_parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    $in = json_decode((string)$raw, true);
    return is_array($in) ? $in : [];
}

function rf_catalog_missing_table(Throwable $e, string $tableName): bool
{
    $message = strtolower($e->getMessage());
    $table = strtolower($tableName);
    if (strpos($message, $table) !== false && strpos($message, 'doesn\'t exist') !== false) {
        return true;
    }
    if ($e instanceof PDOException && isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1146) {
        if (isset($e->errorInfo[2]) && stripos((string)$e->errorInfo[2], $tableName) !== false) {
            return true;
        }
    }
    return false;
}

function rf_catalog_bar_number_for_serial(?string $barSerial): ?int
{
    if ($barSerial === null || $barSerial === '') {
        return null;
    }
    foreach (RF_CATALOG_BAR_MAP as $barNum => $meta) {
        if (strcasecmp($barSerial, $meta['serial']) === 0) {
            return $barNum;
        }
    }
    return null;
}

function rf_catalog_normalize_bar_serial(?string $barSerial): ?string
{
    $v = strtoupper(trim((string)$barSerial));
    if ($v === '') {
        return null;
    }
    if (!preg_match('/^[A-Z0-9-]{2,32}$/', $v)) {
        return null;
    }
    return $v;
}

function rf_catalog_normalize_token_id(string $tokenId): ?string
{
    $v = strtolower(trim($tokenId));
    if ($v === '') {
        return null;
    }
    if (!preg_match('/^[a-z0-9][a-z0-9._:-]{1,95}$/', $v)) {
        return null;
    }
    return $v;
}

function rf_catalog_catalog_no_for_bar(int $barNumber, string $barSerial): string
{
    return sprintf(
        'RF-SLVBAR-%s-%s-%s-%02d',
        RF_CATALOG_DATE_SEGMENT,
        RF_CATALOG_BAR_WEIGHT_SEGMENT,
        strtoupper($barSerial),
        $barNumber
    );
}

function rf_catalog_normalize_block_number(mixed $blockNumber): ?int
{
    if ($blockNumber === null || $blockNumber === '') {
        return null;
    }
    $n = filter_var($blockNumber, FILTER_VALIDATE_INT);
    if ($n === false || $n < 0 || $n > 9999) {
        return null;
    }
    return (int)$n;
}

function rf_catalog_block_number_from_token_id(string $tokenId): ?int
{
    if (!preg_match('/(\d{7})$/', $tokenId, $m)) {
        return null;
    }
    $tokenNum = (int)$m[1];
    if ($tokenNum < 1) {
        return null;
    }
    $batchNum = intdiv($tokenNum - 1, 8) + 1;
    return $batchNum - 1;
}

function rf_catalog_default_collection_code_for_block(?int $blockNumber): string
{
    return $blockNumber === 88 ? 'FND' : 'GEN';
}

function rf_catalog_normalize_collection_code(?string $collectionCode, ?int $blockNumber): string
{
    $raw = strtoupper(trim((string)$collectionCode));
    if ($raw === '') {
        return rf_catalog_default_collection_code_for_block($blockNumber);
    }
    $raw = (string)preg_replace('/[^A-Z0-9]/', '', $raw);
    if ($raw === '') {
        return rf_catalog_default_collection_code_for_block($blockNumber);
    }
    return substr($raw, 0, 8);
}

function rf_catalog_extract_sequence_from_catalog_no(string $catalogNo): ?int
{
    if (!preg_match('/^RF-[A-Z0-9]+-\d{2}-\d{4}-B\d{1,4}-(\d{7})$/', strtoupper($catalogNo), $m)) {
        return null;
    }
    return (int)$m[1];
}

function rf_catalog_next_token_sequence(PDO $pdo): int
{
    $rows = $pdo->query("SELECT catalog_no FROM qd_catalog_registry WHERE record_type IN ('nft','ft')")->fetchAll();
    $max = 0;
    foreach ($rows as $row) {
        $catalogNo = (string)($row['catalog_no'] ?? '');
        $seq = rf_catalog_extract_sequence_from_catalog_no($catalogNo);
        if ($seq !== null && $seq > $max) {
            $max = $seq;
        }
    }
    return $max + 1;
}

function rf_catalog_catalog_no_for_token(string $collectionCode, int $blockNumber, int $sequence): string
{
    return sprintf('RF-%s-%s-B%02d-%07d', $collectionCode, RF_CATALOG_DATE_SEGMENT, $blockNumber, $sequence);
}

function rf_catalog_upsert_token(
    PDO $pdo,
    string $recordType,
    string $tokenId,
    ?string $barSerial,
    ?string $certId,
    ?string $title,
    ?string $notes,
    string $source,
    ?string $collectionCode = null,
    ?int $blockNumber = null
): string {
    $barNumber = rf_catalog_bar_number_for_serial($barSerial);
    $resolvedBlockNumber = $blockNumber ?? rf_catalog_block_number_from_token_id($tokenId);
    if ($resolvedBlockNumber === null) {
        $resolvedBlockNumber = 0;
    }
    $resolvedCollectionCode = rf_catalog_normalize_collection_code($collectionCode, $resolvedBlockNumber);

    $existingStmt = $pdo->prepare('SELECT catalog_no FROM qd_catalog_registry WHERE token_id = ? LIMIT 1');
    $existingStmt->execute([$tokenId]);
    $existingRow = $existingStmt->fetch();
    $existingCatalogNo = is_array($existingRow) ? (string)($existingRow['catalog_no'] ?? '') : '';
    $sequence = rf_catalog_extract_sequence_from_catalog_no($existingCatalogNo);
    if ($sequence === null) {
        $sequence = rf_catalog_next_token_sequence($pdo);
    }
    $catalogNo = rf_catalog_catalog_no_for_token($resolvedCollectionCode, $resolvedBlockNumber, $sequence);
    $sql = 'INSERT INTO qd_catalog_registry
      (catalog_no, record_type, bar_number, bar_serial, token_id, cert_id, title, source, notes)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        catalog_no = VALUES(catalog_no),
        record_type = VALUES(record_type),
        bar_number = VALUES(bar_number),
        bar_serial = VALUES(bar_serial),
        cert_id = COALESCE(VALUES(cert_id), cert_id),
        title = COALESCE(VALUES(title), title),
        source = VALUES(source),
        notes = COALESCE(VALUES(notes), notes),
        updated_at = CURRENT_TIMESTAMP';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $catalogNo,
        $recordType,
        $barNumber,
        $barSerial,
        $tokenId,
        $certId,
        $title,
        $source,
        $notes,
    ]);
    return $catalogNo;
}

function rf_catalog_seed_bars(PDO $pdo): int
{
    $sql = 'INSERT INTO qd_catalog_registry
      (catalog_no, record_type, bar_number, bar_serial, token_id, cert_id, title, source)
      VALUES (?, \'silver_bar\', ?, ?, NULL, NULL, ?, \'seed\')
      ON DUPLICATE KEY UPDATE
        bar_number = VALUES(bar_number),
        bar_serial = VALUES(bar_serial),
        title = VALUES(title),
        source = \'seed\',
        updated_at = CURRENT_TIMESTAMP';
    $stmt = $pdo->prepare($sql);
    $count = 0;
    foreach (RF_CATALOG_BAR_MAP as $barNumber => $meta) {
        $stmt->execute([
            rf_catalog_catalog_no_for_bar($barNumber, $meta['serial']),
            $barNumber,
            $meta['serial'],
            $meta['title'],
        ]);
        $count++;
    }
    return $count;
}

function rf_catalog_sync_from_certificates(PDO $pdo): array
{
    $rows = $pdo->query('SELECT cert_id, bar_serial, cnft_id FROM qd_certificates ORDER BY id ASC')->fetchAll();
    $processed = 0;
    $upserted = 0;
    foreach ($rows as $row) {
        $processed++;
        $tokenId = rf_catalog_normalize_token_id((string)($row['cnft_id'] ?? ''));
        if ($tokenId === null) {
            continue;
        }
        $barSerial = rf_catalog_normalize_bar_serial((string)($row['bar_serial'] ?? ''));
        $certId = trim((string)($row['cert_id'] ?? ''));
        $blockNumber = rf_catalog_block_number_from_token_id($tokenId);
        $collectionCode = rf_catalog_default_collection_code_for_block($blockNumber);
        rf_catalog_upsert_token(
            $pdo,
            'nft',
            $tokenId,
            $barSerial,
            $certId !== '' ? $certId : null,
            $tokenId,
            null,
            'cert_sync',
            $collectionCode,
            $blockNumber
        );
        $upserted++;
    }
    return ['processed' => $processed, 'upserted' => $upserted];
}

function rf_catalog_sync_from_webhook_cache(PDO $pdo): array
{
    $cacheDir = __DIR__ . '/../uploads/webhook-cache';
    if (!is_dir($cacheDir)) {
        return ['processed' => 0, 'upserted' => 0];
    }

    $files = glob($cacheDir . '/*.json');
    if (!is_array($files)) {
        return ['processed' => 0, 'upserted' => 0];
    }

    $processed = 0;
    $upserted = 0;
    foreach ($files as $file) {
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            continue;
        }
        $processed++;
        $tokenId = rf_catalog_normalize_token_id((string)($payload['cnft_id'] ?? ''));
        if ($tokenId === null) {
            continue;
        }
        $barSerial = rf_catalog_normalize_bar_serial((string)($payload['bar_serial'] ?? ''));
        $blockNumber = rf_catalog_block_number_from_token_id($tokenId);
        $collectionCode = rf_catalog_default_collection_code_for_block($blockNumber);
        rf_catalog_upsert_token(
            $pdo,
            'nft',
            $tokenId,
            $barSerial,
            null,
            $tokenId,
            null,
            'webhook_sync',
            $collectionCode,
            $blockNumber
        );
        $upserted++;
    }
    return ['processed' => $processed, 'upserted' => $upserted];
}

rf_catalog_require_basic_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = rf_catalog_parse_json_body();
    $action = (string)($in['action'] ?? '');

    try {
        $pdo = qd_pdo();
    } catch (Throwable $e) {
        rf_catalog_json_response(500, ['ok' => false, 'error' => 'Database connection failed.']);
    }

    if ($action === 'list') {
        $type = trim((string)($in['type'] ?? 'all'));
        $search = trim((string)($in['search'] ?? ''));
        if (!in_array($type, ['all', 'silver_bar', 'nft', 'ft'], true)) {
            $type = 'all';
        }

        $where = [];
        $params = [];
        if ($type !== 'all') {
            $where[] = 'record_type = ?';
            $params[] = $type;
        }
        if ($search !== '') {
            $where[] = '(catalog_no LIKE ? OR bar_serial LIKE ? OR token_id LIKE ? OR cert_id LIKE ? OR title LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT id, catalog_no, record_type, bar_number, bar_serial, token_id, cert_id, title, source, notes, created_at, updated_at
          FROM qd_catalog_registry';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY
          CASE record_type WHEN \'silver_bar\' THEN 0 WHEN \'nft\' THEN 1 ELSE 2 END,
          bar_number IS NULL,
          bar_number ASC,
          token_id ASC,
          id ASC
          LIMIT 2000';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            $counts = ['silver_bar' => 0, 'nft' => 0, 'ft' => 0];
            $countRows = $pdo->query('SELECT record_type, COUNT(*) AS c FROM qd_catalog_registry GROUP BY record_type')->fetchAll();
            foreach ($countRows as $r) {
                $k = (string)($r['record_type'] ?? '');
                if (array_key_exists($k, $counts)) {
                    $counts[$k] = (int)$r['c'];
                }
            }
            rf_catalog_json_response(200, [
                'ok' => true,
                'rows' => $rows,
                'counts' => $counts,
                'total' => $counts['silver_bar'] + $counts['nft'] + $counts['ft'],
            ]);
        } catch (Throwable $e) {
            if (rf_catalog_missing_table($e, 'qd_catalog_registry')) {
                rf_catalog_json_response(503, [
                    'ok' => false,
                    'error' => 'Missing table qd_catalog_registry. Run api/CATALOG_REGISTRY_DB_SCHEMA.sql first.',
                ]);
            }
            rf_catalog_json_response(500, ['ok' => false, 'error' => 'Failed to load catalog data.']);
        }
    }

    if ($action === 'seed_bars') {
        try {
            $seeded = rf_catalog_seed_bars($pdo);
            rf_catalog_json_response(200, ['ok' => true, 'seeded' => $seeded]);
        } catch (Throwable $e) {
            if (rf_catalog_missing_table($e, 'qd_catalog_registry')) {
                rf_catalog_json_response(503, [
                    'ok' => false,
                    'error' => 'Missing table qd_catalog_registry. Run api/CATALOG_REGISTRY_DB_SCHEMA.sql first.',
                ]);
            }
            rf_catalog_json_response(500, ['ok' => false, 'error' => 'Could not seed silver bar rows.']);
        }
    }

    if ($action === 'sync_all_nfts') {
        try {
            $cert = rf_catalog_sync_from_certificates($pdo);
            $webhook = rf_catalog_sync_from_webhook_cache($pdo);
            rf_catalog_json_response(200, [
                'ok' => true,
                'cert_processed' => $cert['processed'],
                'cert_upserted' => $cert['upserted'],
                'webhook_processed' => $webhook['processed'],
                'webhook_upserted' => $webhook['upserted'],
            ]);
        } catch (Throwable $e) {
            if (rf_catalog_missing_table($e, 'qd_catalog_registry')) {
                rf_catalog_json_response(503, [
                    'ok' => false,
                    'error' => 'Missing table qd_catalog_registry. Run api/CATALOG_REGISTRY_DB_SCHEMA.sql first.',
                ]);
            }
            if (rf_catalog_missing_table($e, 'qd_certificates')) {
                rf_catalog_json_response(503, [
                    'ok' => false,
                    'error' => 'Missing table qd_certificates. Run api/CERT_DB_SCHEMA.sql first.',
                ]);
            }
            rf_catalog_json_response(500, ['ok' => false, 'error' => 'NFT sync failed.']);
        }
    }

    if ($action === 'upsert_manual') {
        $recordType = trim((string)($in['recordType'] ?? ''));
        if (!in_array($recordType, ['nft', 'ft'], true)) {
            rf_catalog_json_response(400, ['ok' => false, 'error' => 'recordType must be nft or ft.']);
        }
        $tokenId = rf_catalog_normalize_token_id((string)($in['tokenId'] ?? ''));
        if ($tokenId === null) {
            rf_catalog_json_response(400, [
                'ok' => false,
                'error' => 'Invalid token ID. Use letters, numbers, dot, underscore, colon, or hyphen.',
            ]);
        }
        $barSerial = rf_catalog_normalize_bar_serial((string)($in['barSerial'] ?? ''));
        if ($barSerial === null) {
            rf_catalog_json_response(400, ['ok' => false, 'error' => 'Invalid bar serial.']);
        }
        $certId = trim((string)($in['certId'] ?? ''));
        if (strlen($certId) > 64) {
            rf_catalog_json_response(400, ['ok' => false, 'error' => 'certId is too long.']);
        }
        $title = trim((string)($in['title'] ?? ''));
        if (strlen($title) > 255) {
            rf_catalog_json_response(400, ['ok' => false, 'error' => 'title is too long.']);
        }
        $notes = trim((string)($in['notes'] ?? ''));
        if (strlen($notes) > 5000) {
            rf_catalog_json_response(400, ['ok' => false, 'error' => 'notes is too long.']);
        }
        $collectionCodeInput = trim((string)($in['collectionCode'] ?? ''));
        $blockNumber = rf_catalog_normalize_block_number($in['blockNumber'] ?? null);
        if ($blockNumber === null) {
            $blockNumber = rf_catalog_block_number_from_token_id($tokenId);
        }
        if ($blockNumber === null) {
            rf_catalog_json_response(400, [
                'ok' => false,
                'error' => 'Provide a block number when token ID does not end with a 7-digit number.',
            ]);
        }
        $collectionCode = rf_catalog_normalize_collection_code($collectionCodeInput, $blockNumber);

        try {
            $catalogNo = rf_catalog_upsert_token(
                $pdo,
                $recordType,
                $tokenId,
                $barSerial,
                $certId !== '' ? $certId : null,
                $title !== '' ? $title : $tokenId,
                $notes !== '' ? $notes : null,
                'manual',
                $collectionCode,
                $blockNumber
            );
            rf_catalog_json_response(200, [
                'ok' => true,
                'catalog_no' => $catalogNo,
            ]);
        } catch (Throwable $e) {
            if (rf_catalog_missing_table($e, 'qd_catalog_registry')) {
                rf_catalog_json_response(503, [
                    'ok' => false,
                    'error' => 'Missing table qd_catalog_registry. Run api/CATALOG_REGISTRY_DB_SCHEMA.sql first.',
                ]);
            }
            rf_catalog_json_response(500, ['ok' => false, 'error' => 'Failed to save manual catalog entry.']);
        }
    }

    if ($action === 'delete') {
        $id = filter_var($in['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            rf_catalog_json_response(400, ['ok' => false, 'error' => 'Invalid id.']);
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM qd_catalog_registry WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                rf_catalog_json_response(404, ['ok' => false, 'error' => 'Catalog row not found.']);
            }
            rf_catalog_json_response(200, ['ok' => true, 'deleted' => $id]);
        } catch (Throwable $e) {
            if (rf_catalog_missing_table($e, 'qd_catalog_registry')) {
                rf_catalog_json_response(503, [
                    'ok' => false,
                    'error' => 'Missing table qd_catalog_registry. Run api/CATALOG_REGISTRY_DB_SCHEMA.sql first.',
                ]);
            }
            rf_catalog_json_response(500, ['ok' => false, 'error' => 'Delete failed.']);
        }
    }

    rf_catalog_json_response(400, ['ok' => false, 'error' => 'Unknown action.']);
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Catalog Registry, Rarefolio Admin</title>
<style>
:root {
  --bg: #050a18;
  --surface: #0d1526;
  --surface2: #121e35;
  --border: #1e2d4a;
  --gold: #d9b46c;
  --text: #c8d4e8;
  --muted: #6a7a96;
  --ok: #4caf7d;
  --err: #e05252;
  --warn: #e3b64f;
  --cyan: #5dd5ff;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: system-ui, -apple-system, sans-serif;
  font-size: 14px;
  min-height: 100vh;
  padding: 20px 24px;
}
h1 { color: var(--gold); font-size: 22px; font-weight: 700; letter-spacing: -.01em; }
h2 { color: var(--gold); font-size: 17px; margin-bottom: 10px; }
a { color: var(--cyan); text-decoration: none; }
a:hover { text-decoration: underline; }
p { line-height: 1.55; }

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 16px;
}
.top-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px 18px;
  margin-bottom: 14px;
}
.muted { color: var(--muted); }
.section-label {
  color: var(--muted);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .07em;
  font-weight: 600;
  margin-bottom: 5px;
}

.controls {
  display: flex;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
}
.field { display: flex; flex-direction: column; gap: 5px; }

input, select, textarea {
  background: var(--bg);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 13px;
  outline: none;
}
textarea { min-height: 70px; resize: vertical; width: 100%; }
input:focus, select:focus, textarea:focus { border-color: var(--gold); }
input[type="search"] { min-width: 260px; }

.btn {
  background: var(--gold);
  color: #050a18;
  border: none;
  border-radius: 6px;
  padding: 8px 18px;
  cursor: pointer;
  font-weight: 700;
  font-size: 13px;
  transition: opacity .15s;
  white-space: nowrap;
}
.btn:hover { opacity: .86; text-decoration: none; }
.btn.secondary {
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--border);
}
.btn.secondary:hover { border-color: var(--gold); color: var(--gold); }
.btn.sm { padding: 7px 14px; font-size: 12px; }

.status {
  margin-top: 10px;
  font-size: 12px;
  padding: 7px 12px;
  border-radius: 6px;
  border: 1px solid transparent;
}
.status.ok { background: #0d2b1c; color: var(--ok); border-color: var(--ok); }
.status.err { background: #2b0d0d; color: var(--err); border-color: var(--err); }
.status.warn { background: #2d2209; color: var(--warn); border-color: var(--warn); }
.status.loading { background: var(--surface2); color: var(--muted); border-color: var(--border); }

.summary-grid {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 8px;
}
.pill {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 10px;
}
.pill strong {
  color: var(--gold);
  display: block;
  font-size: 11px;
  letter-spacing: .06em;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.table-wrap {
  margin-top: 10px;
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1020px;
}
th, td {
  border-bottom: 1px solid var(--border);
  padding: 9px 10px;
  text-align: left;
  vertical-align: top;
  font-size: 12px;
}
th {
  font-size: 11px;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .06em;
  position: sticky;
  top: 0;
  background: var(--surface2);
  z-index: 1;
}
tr:hover td { background: rgba(255,255,255,.02); }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.nowrap { white-space: nowrap; }
.row-actions { display: flex; gap: 6px; }
.empty {
  padding: 12px;
  color: var(--muted);
  font-size: 13px;
}

@media (max-width: 720px) {
  body { padding: 15px 14px; }
}
</style>
</head>
<body>

<div class="topbar">
  <h1>⬡ Rarefolio Catalog Registry</h1>
  <div class="top-actions">
    <a class="btn secondary sm" href="/admin/index.php">Admin Home</a>
    <a class="btn secondary sm" href="/admin/story-editor.php">Story Editor</a>
    <a class="btn secondary sm" href="/admin/wallet-dashboard.php">Wallet Dashboard</a>
  </div>
</div>

<section class="panel">
  <h2>Registry Sync</h2>
  <p class="muted">Seed the 3 silver bars, then sync every NFT discovered from certificates and webhook cache into one searchable catalog list using RF-SLVBAR-05-2026-100oz-{serial}-{item} and RF-{collection}-05-2026-B{block}-0000001 patterns.</p>
  <div class="controls">
    <button class="btn" id="btn-seed-bars" type="button">Seed 3 Silver Bars</button>
    <button class="btn secondary" id="btn-sync-nfts" type="button">Sync NFTs (Certificates + Cache)</button>
    <button class="btn secondary" id="btn-refresh" type="button">Refresh List</button>
  </div>
  <div class="status" id="sync-status">No sync action yet.</div>
  <div class="summary-grid">
    <div class="pill"><strong>Total</strong><span id="sum-total">0</span></div>
    <div class="pill"><strong>Silver Bars</strong><span id="sum-bars">0</span></div>
    <div class="pill"><strong>NFT</strong><span id="sum-nfts">0</span></div>
    <div class="pill"><strong>FT</strong><span id="sum-fts">0</span></div>
  </div>
</section>

<section class="panel">
  <h2>Add or Update NFT / FT Catalog Entry</h2>
  <p class="muted">Use this for manual entries that are not yet in certificate or webhook sources.</p>
  <div class="controls">
    <label class="field">
      <span class="section-label">Record Type</span>
      <select id="manual-record-type">
        <option value="nft">NFT</option>
        <option value="ft">FT</option>
      </select>
    </label>
    <label class="field">
      <span class="section-label">Token ID</span>
      <input id="manual-token-id" type="text" placeholder="qd-silver-0000705" />
    </label>
    <label class="field">
      <span class="section-label">Collection Code</span>
      <input id="manual-collection-code" type="text" placeholder="FND or GEN" />
    </label>
    <label class="field">
      <span class="section-label">Block Number</span>
      <input id="manual-block-number" type="number" min="0" max="9999" placeholder="88" />
    </label>
    <label class="field">
      <span class="section-label">Bar Serial</span>
      <input id="manual-bar-serial" type="text" list="known-bars" placeholder="E101837" />
      <datalist id="known-bars">
        <option value="E101837"></option>
        <option value="E102528"></option>
        <option value="P154829"></option>
      </datalist>
    </label>
    <label class="field">
      <span class="section-label">Certificate ID (optional)</span>
      <input id="manual-cert-id" type="text" placeholder="QDCERT-E101837-0000705" />
    </label>
    <label class="field" style="min-width:220px;">
      <span class="section-label">Title (optional)</span>
      <input id="manual-title" type="text" placeholder="Founders #1" />
    </label>
  </div>
  <div style="margin-top:10px;">
    <label class="field">
      <span class="section-label">Notes (optional)</span>
      <textarea id="manual-notes" placeholder="Context or metadata for admin use."></textarea>
    </label>
  </div>
  <div class="controls">
    <button class="btn" id="btn-save-manual" type="button">Save Entry</button>
  </div>
  <div class="status" id="manual-status">No manual save yet.</div>
</section>

<section class="panel">
  <h2>Catalog Listing</h2>
  <div class="controls">
    <label class="field">
      <span class="section-label">Filter Type</span>
      <select id="filter-type">
        <option value="all">All</option>
        <option value="silver_bar">Silver Bars</option>
        <option value="nft">NFT</option>
        <option value="ft">FT</option>
      </select>
    </label>
    <label class="field">
      <span class="section-label">Search</span>
      <input id="filter-search" type="search" placeholder="Catalog no, token, cert, serial, title" />
    </label>
    <button class="btn secondary" id="btn-apply-filter" type="button">Apply Filter</button>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Catalog Number</th>
          <th>Type</th>
          <th>Bar</th>
          <th>Token ID</th>
          <th>Cert ID</th>
          <th>Title</th>
          <th>Source</th>
          <th>Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="catalog-body">
        <tr><td colspan="9" class="empty">Loading catalog data.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<script>
(() => {
  const API_ENDPOINT = location.pathname;
  const $ = (id) => document.getElementById(id);

  function escHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setStatus(el, message, kind) {
    el.textContent = message;
    el.className = 'status' + (kind ? ' ' + kind : '');
  }

  async function postAction(payload) {
    const resp = await fetch(API_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok || !data.ok) {
      throw new Error(data.error || ('HTTP ' + resp.status));
    }
    return data;
  }

  function updateSummary(counts, total) {
    $('sum-total').textContent = String(total || 0);
    $('sum-bars').textContent = String(counts?.silver_bar || 0);
    $('sum-nfts').textContent = String(counts?.nft || 0);
    $('sum-fts').textContent = String(counts?.ft || 0);
  }

  function renderRows(rows) {
    const body = $('catalog-body');
    if (!Array.isArray(rows) || !rows.length) {
      body.innerHTML = '<tr><td colspan="9" class="empty">No catalog rows for this filter.</td></tr>';
      return;
    }
    body.innerHTML = rows.map((r) => {
      const barLabel = r.bar_number ? `Bar ${escHtml(r.bar_number)} (${escHtml(r.bar_serial || '')})` : escHtml(r.bar_serial || '');
      return `
        <tr>
          <td class="mono nowrap">${escHtml(r.catalog_no)}</td>
          <td class="nowrap">${escHtml(r.record_type)}</td>
          <td class="nowrap">${barLabel || '-'}</td>
          <td class="mono nowrap">${escHtml(r.token_id || '-')}</td>
          <td class="mono nowrap">${escHtml(r.cert_id || '-')}</td>
          <td>${escHtml(r.title || '-')}</td>
          <td class="nowrap">${escHtml(r.source || '-')}</td>
          <td class="nowrap">${escHtml(r.updated_at || '-')}</td>
          <td>
            <div class="row-actions">
              <button class="btn secondary sm" type="button" data-delete-id="${Number(r.id)}">Delete</button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  async function loadRows() {
    setStatus($('sync-status'), 'Loading catalog data.', 'loading');
    try {
      const data = await postAction({
        action: 'list',
        type: $('filter-type').value,
        search: $('filter-search').value.trim(),
      });
      renderRows(data.rows || []);
      updateSummary(data.counts || {}, data.total || 0);
      setStatus($('sync-status'), `Loaded ${data.rows.length} row(s).`, 'ok');
    } catch (e) {
      setStatus($('sync-status'), 'Load failed: ' + (e?.message || String(e)), 'err');
      $('catalog-body').innerHTML = '<tr><td colspan="9" class="empty">Could not load catalog rows.</td></tr>';
    }
  }

  $('btn-seed-bars').addEventListener('click', async () => {
    setStatus($('sync-status'), 'Seeding the 3 silver bars.', 'loading');
    try {
      const data = await postAction({ action: 'seed_bars' });
      setStatus($('sync-status'), `Seeded or updated ${data.seeded} silver bar row(s).`, 'ok');
      await loadRows();
    } catch (e) {
      setStatus($('sync-status'), 'Seed failed: ' + (e?.message || String(e)), 'err');
    }
  });

  $('btn-sync-nfts').addEventListener('click', async () => {
    setStatus($('sync-status'), 'Syncing NFTs from certificates and webhook cache.', 'loading');
    try {
      const data = await postAction({ action: 'sync_all_nfts' });
      setStatus(
        $('sync-status'),
        `Sync complete. Cert upserts: ${data.cert_upserted}/${data.cert_processed}, cache upserts: ${data.webhook_upserted}/${data.webhook_processed}.`,
        'ok'
      );
      await loadRows();
    } catch (e) {
      setStatus($('sync-status'), 'Sync failed: ' + (e?.message || String(e)), 'err');
    }
  });

  $('btn-refresh').addEventListener('click', loadRows);
  $('btn-apply-filter').addEventListener('click', loadRows);
  $('filter-search').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      loadRows();
    }
  });

  $('btn-save-manual').addEventListener('click', async () => {
    const payload = {
      action: 'upsert_manual',
      recordType: $('manual-record-type').value,
      tokenId: $('manual-token-id').value.trim(),
      collectionCode: $('manual-collection-code').value.trim(),
      blockNumber: $('manual-block-number').value.trim(),
      barSerial: $('manual-bar-serial').value.trim(),
      certId: $('manual-cert-id').value.trim(),
      title: $('manual-title').value.trim(),
      notes: $('manual-notes').value.trim(),
    };
    setStatus($('manual-status'), 'Saving manual catalog entry.', 'loading');
    try {
      const data = await postAction(payload);
      setStatus($('manual-status'), `Saved. Catalog number: ${data.catalog_no}`, 'ok');
      $('manual-token-id').value = '';
      $('manual-cert-id').value = '';
      $('manual-title').value = '';
      $('manual-notes').value = '';
      await loadRows();
    } catch (e) {
      setStatus($('manual-status'), 'Save failed: ' + (e?.message || String(e)), 'err');
    }
  });

  $('catalog-body').addEventListener('click', async (e) => {
    const btn = e.target instanceof HTMLElement ? e.target.closest('[data-delete-id]') : null;
    if (!btn) return;
    const id = Number(btn.getAttribute('data-delete-id') || 0);
    if (!Number.isFinite(id) || id < 1) return;
    if (!window.confirm(`Delete catalog row #${id}?`)) return;
    setStatus($('sync-status'), `Deleting row #${id}.`, 'loading');
    try {
      await postAction({ action: 'delete', id });
      setStatus($('sync-status'), `Deleted row #${id}.`, 'ok');
      await loadRows();
    } catch (err) {
      setStatus($('sync-status'), 'Delete failed: ' + (err?.message || String(err)), 'err');
    }
  });

  loadRows();
})();
</script>
</body>
</html>
