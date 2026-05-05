# =============================================================================
#  Founders Block 88, Bulk nav + CTA retargeting across static HTML pages
# =============================================================================
#  Rewrites the top-right "Prelaunch" button and the Collections dropdown
#  Inventors item across every *.html at the main-site root.
#
#  Uses exact-string multi-line replacement so it will only match the intended
#  nav block, canonical/og meta tags that reference the same URL are NOT
#  affected because their surrounding context differs.
#
#  Re-run safe: after the first run, the "before" strings won't match again.
#
#  Run from the main-site root:
#      powershell -File 01_md_plan_files\apply_founders_nav_update.ps1
# =============================================================================

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

# Files we never touch:
#  - index.html            (handled manually with richer edits)
#  - index_OLD.html        (backup)
#  - README / PLAN docs    (markdown in 01_md_plan_files)
$skip = @('index.html','index_OLD.html')

# ----- Exact-string replacement pairs -----
$before_cta = @"
    <!-- Prelaunch CTA Button (top-right) -->
    <a class="btn primary qd-prelaunch-cta"
       href="/collection-inventors-guild-prelaunch.html"
       title="Founders Prelaunch: Inventors Guild (Block 01)">
      Prelaunch
    </a>
"@

$after_cta = @"
    <!-- Founders Collection CTA Button (top-right) -->
    <a class="btn primary qd-prelaunch-cta"
       href="/collection/silverbar-01/founders?batch=89"
       title="Founders Collection (Block 88)">
      Founders
    </a>
"@

$before_nav_line = '<a href="/collection-inventors-guild-prelaunch.html" role="menuitem">Prelaunch: Inventors Guild (Block 01)</a>'
$after_nav_block = @'
<a href="/collection/silverbar-01/founders?batch=89" role="menuitem">Founders Collection (Block 88)</a>
          <a href="/collection-inventors-guild-prelaunch.html" role="menuitem">Inventors Guild Prelaunch (Block 01)</a>
'@

# ----- Apply -----
$files = Get-ChildItem -Path $root -Filter *.html -File |
    Where-Object { $skip -notcontains $_.Name }

$updatedCta   = 0
$updatedNav   = 0
$scanned      = 0

foreach ($f in $files) {
    $scanned++
    $content = Get-Content -Raw -Path $f.FullName
    $orig    = $content

    if ($content.Contains($before_cta)) {
        $content = $content.Replace($before_cta, $after_cta)
        $updatedCta++
    }

    if ($content.Contains($before_nav_line)) {
        $content = $content.Replace($before_nav_line, $after_nav_block)
        $updatedNav++
    }

    if ($content -ne $orig) {
        Set-Content -Path $f.FullName -Value $content -NoNewline
        Write-Host ("  updated  " + $f.Name) -ForegroundColor Green
    } else {
        Write-Host ("  unchanged  " + $f.Name) -ForegroundColor DarkGray
    }
}

Write-Host ""
Write-Host ("Scanned:        " + $scanned + " file(s)")   -ForegroundColor Cyan
Write-Host ("CTA rewritten:  " + $updatedCta + " file(s)") -ForegroundColor Cyan
Write-Host ("Nav rewritten:  " + $updatedNav + " file(s)") -ForegroundColor Cyan
