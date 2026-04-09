<?php
declare(strict_types=1);

/**
 * Rarefolio – Showcased Artist Application Endpoint
 *
 * POST /api/artist-application.php
 * Accepts multipart/form-data, validates, saves uploads, inserts into MySQL.
 * Returns JSON { success: bool, message: string, app_ref?: string }.
 */

require_once __DIR__ . '/_config.php';

// ── CORS & method gate ───────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Upload directory (create if missing) ─────────────────────────────
const UPLOAD_DIR = __DIR__ . '/../uploads/artist_applications';
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// ── Helpers ──────────────────────────────────────────────────────────
function txt(string $key): ?string {
    $v = trim($_POST[$key] ?? '');
    return $v !== '' ? $v : null;
}

function require_txt(string $key, string $label, array &$errors): string {
    $v = txt($key);
    if ($v === null) { $errors[] = "$label is required."; return ''; }
    return $v;
}

function generate_app_ref(): string {
    return 'RF-' . strtoupper(bin2hex(random_bytes(6))) . '-' . date('Ymd');
}

function save_upload(string $fieldName, string $subDir, bool $multiple = false): array|string|null {
    if ($multiple) {
        $paths = [];
        if (empty($_FILES[$fieldName]['name'][0])) return null;
        $count = count($_FILES[$fieldName]['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES[$fieldName]['error'][$i] !== UPLOAD_ERR_OK) continue;
            $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES[$fieldName]['name'][$i]));
            $dest = $subDir . '/' . time() . '_' . $i . '_' . $safe;
            move_uploaded_file($_FILES[$fieldName]['tmp_name'][$i], UPLOAD_DIR . '/' . $dest);
            $paths[] = $dest;
        }
        return $paths ?: null;
    }

    if (empty($_FILES[$fieldName]['name']) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return null;
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES[$fieldName]['name']));
    $dest = $subDir . '/' . time() . '_' . $safe;
    move_uploaded_file($_FILES[$fieldName]['tmp_name'], UPLOAD_DIR . '/' . $dest);
    return $dest;
}

// ── Validate required fields ─────────────────────────────────────────
$errors = [];

$full_name            = require_txt('full_name',            'Full Name',            $errors);
$email                = require_txt('email',                'Primary Email',        $errors);
$artist_bio           = require_txt('artist_bio',           'Artist Bio',           $errors);
$primary_medium       = require_txt('primary_medium',       'Primary Medium',       $errors);
$artist_statement     = require_txt('artist_statement',     'Artist Statement',     $errors);
$signature_difference = require_txt('signature_difference', 'What makes your work distinct', $errors);
$portfolio_url        = require_txt('portfolio_url',        'Primary Portfolio Link', $errors);
$best_work_links      = require_txt('best_work_links',     'Showcase Pieces Links', $errors);
$why_rarefolio        = require_txt('why_rarefolio',        'Why RareFolio',        $errors);

if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please provide a valid email address.';
}

if (empty($_POST['consent_review'])) {
    $errors[] = 'You must agree to the review consent to submit.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

// ── Generate unique reference & file sub-folder ──────────────────────
$app_ref = generate_app_ref();
$fileDir = $app_ref;
if (!is_dir(UPLOAD_DIR . '/' . $fileDir)) {
    mkdir(UPLOAD_DIR . '/' . $fileDir, 0755, true);
}

// ── Save uploaded files ──────────────────────────────────────────────
$headshot_path      = save_upload('headshot',      $fileDir);
$portfolio_pdf_path = save_upload('portfolio_pdf',  $fileDir);
$sample_works_paths = save_upload('sample_works',   $fileDir, true);

// ── Collect optional fields ──────────────────────────────────────────
$practice_tags = $_POST['practice_tags'] ?? [];
if (!is_array($practice_tags)) $practice_tags = [];

// ── Insert into database ─────────────────────────────────────────────
try {
    $pdo = qd_pdo();

    $sql = "INSERT INTO qd_artist_applications (
        app_ref, full_name, artist_name, email, location, website, years_creating, artist_bio,
        primary_medium, style_keywords, artist_statement, signature_difference, collector_appeal,
        portfolio_url, social_url, best_work_links, series_info, presentation_readiness,
        availability, exclusive_interest, practice_tags, why_rarefolio, professional_notes,
        headshot_path, portfolio_pdf_path, sample_works_paths, file_notes,
        consent_review, consent_contact, ip_address
    ) VALUES (
        :app_ref, :full_name, :artist_name, :email, :location, :website, :years_creating, :artist_bio,
        :primary_medium, :style_keywords, :artist_statement, :signature_difference, :collector_appeal,
        :portfolio_url, :social_url, :best_work_links, :series_info, :presentation_readiness,
        :availability, :exclusive_interest, :practice_tags, :why_rarefolio, :professional_notes,
        :headshot_path, :portfolio_pdf_path, :sample_works_paths, :file_notes,
        :consent_review, :consent_contact, :ip_address
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':app_ref'                => $app_ref,
        ':full_name'              => $full_name,
        ':artist_name'            => txt('artist_name'),
        ':email'                  => $email,
        ':location'               => txt('location'),
        ':website'                => txt('website'),
        ':years_creating'         => txt('years_creating'),
        ':artist_bio'             => $artist_bio,
        ':primary_medium'         => $primary_medium,
        ':style_keywords'         => txt('style_keywords'),
        ':artist_statement'       => $artist_statement,
        ':signature_difference'   => $signature_difference,
        ':collector_appeal'       => txt('collector_appeal'),
        ':portfolio_url'          => $portfolio_url,
        ':social_url'             => txt('social_url'),
        ':best_work_links'        => $best_work_links,
        ':series_info'            => txt('series_info'),
        ':presentation_readiness' => txt('presentation_readiness'),
        ':availability'           => txt('availability'),
        ':exclusive_interest'     => txt('exclusive_interest'),
        ':practice_tags'          => json_encode($practice_tags),
        ':why_rarefolio'          => $why_rarefolio,
        ':professional_notes'     => txt('professional_notes'),
        ':headshot_path'          => $headshot_path,
        ':portfolio_pdf_path'     => $portfolio_pdf_path,
        ':sample_works_paths'     => $sample_works_paths ? json_encode($sample_works_paths) : null,
        ':file_notes'             => txt('file_notes'),
        ':consent_review'         => 1,
        ':consent_contact'        => !empty($_POST['consent_contact']) ? 1 : 0,
        ':ip_address'             => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully.',
        'app_ref' => $app_ref,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error. Please try again later.',
    ]);
    error_log('Artist application DB error: ' . $e->getMessage());
}
