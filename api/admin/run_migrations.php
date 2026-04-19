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

// ---- Token auth (URL param) ----
$token = $_GET['token'] ?? '';
if ($token !== 'rf_migrate_2026') {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

function run(PDO $pdo, string $label, string $sql): void {
    try {
        $pdo->exec($sql);
        echo "OK    $label\n";
    } catch (PDOException $e) {
        $c = (int)$e->getCode();
        if (in_array($c, [1060, 1061, 1062, 23000], true)) {
            echo "SKIP  $label (already applied)\n";
        } else {
            throw $e;
        }
    }
}

try {
    $pdo = qd_pdo();

    // 1. Register Block 88 in qd_blocks
    run($pdo, 'qd_blocks Block 88',
        "INSERT INTO qd_blocks (block_id, bar_serial, batch_num, folder_slug, label, story_mode)
         VALUES ('block88','E101837',89,'scnft_founders','Founders','per_item')
         ON DUPLICATE KEY UPDATE
           folder_slug = VALUES(folder_slug),
           label       = VALUES(label),
           story_mode  = VALUES(story_mode),
           updated_at  = CURRENT_TIMESTAMP"
    );

    // 2. Shared story
    run($pdo, 'qd_stories shared',
        "INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
         ('block88', NULL, '<p><em>The Rarefolio Founders collection is the first eight pieces of Block 88, anchored to Silver Bar I (Serial E101837). Purchased by the founder at mint to bootstrap the secondary market and prove every link of the chain. Each piece enters the permanent archive with public provenance from day one.</em></p><p>Eight archetypes. One ledger. A permanent record of how Rarefolio began.</p>')
         ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP"
    );

    // 3. Per-item stories
    $items = [
        1 => ['The Archivist',    'Keeper of the First Ledger',         'Before a vault can hold anything of value, someone must decide what to record and how. The Archivist draws the first line in the ledger.'],
        2 => ['The Cartographer', 'Drafter of the Vault Map',           'Every collection needs an atlas. The Cartographer charts the territory of the archive.'],
        3 => ['The Sentinel',     'Warden of the Inaugural Seal',       'The Sentinel stands at the threshold between intent and permanence.'],
        4 => ['The Artisan',      'Forger of the Foundational Die',     'Every piece carries the shape of the one who made the mold. The Artisan carves the die.'],
        5 => ['The Scholar',      'Historian of the First Provenance',  'Provenance is not a feature. It is a discipline. The Scholar writes down where every piece came from.'],
        6 => ['The Ambassador',   'Emissary of the Original Charter',   'The Ambassador carries the charter outward to every early collector who trusts the archive.'],
        7 => ['The Mentor',       "Steward of the Collector's Path",   'The Mentor walks new collectors through Discover, Study, and Collect. Not a salesperson. A guide.'],
        8 => ['The Architect',    'Builder of the Permanent Vault',     'The final Founder. The Architect draws the walls of the vault itself.'],
    ];
    foreach ($items as $n => [$name, $sub, $body]) {
        $html = "<h3>Founders #$n \u2014 $name</h3><p class=\"lead\">$sub.</p><p>$body</p><p><em>Rarefolio Founders, Silver Bar I, Edition $n of 8.</em></p>";
        $stmt = $pdo->prepare(
            "INSERT INTO qd_stories (block_id, item_num, html_content) VALUES ('block88', ?, ?)
             ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$n, $html]);
        echo "OK    qd_stories item $n ($name)\n";
    }

    // 4. Add character_names column if missing
    $col = $pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'qd_blocks' AND COLUMN_NAME = 'character_names'"
    )->fetchColumn();
    if (!$col) {
        $pdo->exec("ALTER TABLE qd_blocks ADD COLUMN character_names TEXT NULL DEFAULT NULL AFTER story_mode");
        echo "OK    character_names column added\n";
    } else {
        echo "SKIP  character_names column (already exists)\n";
    }

    // 5. Seed character names
    $names = [
        'E101837-block0002' => json_encode(['Miss Nyla Vantress \u2014 The Stormglass Prodigy','Elowen Thrice \u2014 Mistress of Clockwork Nerves','Clara Penhalwick \u2014 The Brassheart Aeronaut','Edmund Vale \u2014 The Iron Wit of Gallowmere','Vivienne Sloane \u2014 Keeper of the Ember Circuit','Octavius Bellmere \u2014 The Grand Old Gearsmith','Thaddeus Crowle \u2014 The Furnace Baron','Ludorian Marrow \u2014 Architect of the Impossible Hour'], JSON_UNESCAPED_UNICODE),
        'E101837-block0004' => json_encode(['Alistair Valecourt','Edmund Aurellian','Theodore Valemont','Lucian Everford','Julian Ashcombe','Reginald Fairbourne','Augustin Wrenhall','Benedict Harrowvale'], JSON_UNESCAPED_UNICODE),
        'block88'           => json_encode(['Founders #1 \u2014 The Archivist','Founders #2 \u2014 The Cartographer','Founders #3 \u2014 The Sentinel','Founders #4 \u2014 The Artisan','Founders #5 \u2014 The Scholar','Founders #6 \u2014 The Ambassador','Founders #7 \u2014 The Mentor','Founders #8 \u2014 The Architect'], JSON_UNESCAPED_UNICODE),
    ];
    foreach ($names as $blockId => $json) {
        $s = $pdo->prepare("UPDATE qd_blocks SET character_names = ?, updated_at = CURRENT_TIMESTAMP WHERE block_id = ?");
        $s->execute([$json, $blockId]);
        echo "OK    character_names for $blockId\n";
    }

    echo "\nAll done. Delete this file: api/admin/run_migrations.php\n";

} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
