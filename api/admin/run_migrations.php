<?php
declare(strict_types=1);
/**
 * One-time migration runner — Block 88 + character_names schema.
 * Run once via browser (Basic Auth required), then DELETE this file.
 *
 * Migrations run (in order):
 *   1. api/sql/seed_block88_blocks.sql
 *   2. api/sql/seed_block88_stories.sql
 *   3. api/sql/migrate_add_character_names.sql
 *   4. api/sql/seed_character_names.sql
 */

require_once __DIR__ . '/../_config.php';

// ---- Basic Auth ----
$u = $_SERVER['PHP_AUTH_USER'] ?? '';
$p = $_SERVER['PHP_AUTH_PW']   ?? '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (($u === '' || $p === '') && $auth !== '') {
    if (stripos($auth, 'basic ') === 0) {
        $decoded = base64_decode(substr($auth, 6));
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            [$u, $p] = explode(':', $decoded, 2);
        }
    }
}
if ($u !== ADMIN_USER || $p !== ADMIN_PASS) {
    header('WWW-Authenticate: Basic realm="Rarefolio Admin"');
    http_response_code(401);
    exit('Unauthorized');
}

header('Content-Type: text/plain; charset=utf-8');

$siteRoot = realpath(__DIR__ . '/../../');

$migrations = [
    'api/sql/seed_block88_blocks.sql',
    'api/sql/seed_block88_stories.sql',
    'api/sql/migrate_add_character_names.sql',
    'api/sql/seed_character_names.sql',
];

try {
    $pdo = qd_pdo();
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

    foreach ($migrations as $relPath) {
        $file = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        if (!file_exists($file)) {
            echo "SKIP  $relPath (file not found)\n";
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            echo "SKIP  $relPath (empty)\n";
            continue;
        }

        // Execute each statement individually, skipping comments
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => $s !== '' && !str_starts_with(ltrim($s), '--')
        );

        $count = 0;
        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
                $count++;
            } catch (PDOException $e) {
                // Ignore duplicate key / column already exists errors (safe re-run)
                $code = (int)$e->getCode();
                if ($code === 1060 || $code === 1062 || $code === 23000) {
                    // 1060 = duplicate column, 1062 = duplicate entry, 23000 = integrity constraint
                    echo "  skip (already applied): " . substr($e->getMessage(), 0, 80) . "\n";
                } else {
                    throw $e;
                }
            }
        }

        echo "OK    $relPath ($count statements)\n";
    }

    echo "\nAll migrations complete.\n";
    echo "** DELETE this file now: api/admin/run_migrations.php **\n";

} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}
