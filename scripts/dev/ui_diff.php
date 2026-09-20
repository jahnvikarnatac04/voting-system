<?php
/**
 * Screenshot differ (development tool - not part of the application).
 *
 * Compares two directories produced by ui_screenshots.php and reports, for each
 * page/width, the share of pixels that changed and the bounding box of the
 * change. Use it to confirm a CSS refactor is visually neutral, or to see
 * exactly which region a deliberate change moved.
 *
 *   php scripts/dev/ui_diff.php before after
 *
 * Requires the GD extension.
 */

if (!extension_loaded('gd')) {
    fwrite(STDERR, "The GD extension is required.\n");
    exit(2);
}

$dirA = $argv[1] ?? 'before';
$dirB = $argv[2] ?? 'after';
$base = sys_get_temp_dir() . '/ui-shots/';
$A = rtrim($base . $dirA, '/');
$B = rtrim($base . $dirB, '/');

if (!is_dir($A) || !is_dir($B)) {
    fwrite(STDERR, "Expected both $A and $B to exist. Run ui_screenshots.php first.\n");
    exit(2);
}

$files = glob($A . '/*.png');
sort($files);

$identical = 0;
$changed   = [];
$missing   = [];

foreach ($files as $fa) {
    $name = basename($fa);
    $fb   = $B . '/' . $name;

    if (!is_file($fb)) {
        $missing[] = $name;
        continue;
    }

    $ia = @imagecreatefrompng($fa);
    $ib = @imagecreatefrompng($fb);
    if (!$ia || !$ib) {
        $missing[] = $name . ' (unreadable)';
        continue;
    }

    $w = min(imagesx($ia), imagesx($ib));
    $h = min(imagesy($ia), imagesy($ib));

    $diff = 0;
    $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;

    // Step over blocks of 2px: a screenshot diff only needs to find regions,
    // and this keeps a 34-page comparison near-instant.
    for ($y = 0; $y < $h; $y += 2) {
        for ($x = 0; $x < $w; $x += 2) {
            $a = imagecolorat($ia, $x, $y);
            $b = imagecolorat($ib, $x, $y);
            if ($a === $b) {
                continue;
            }
            // Tolerate 1-bit anti-aliasing noise on any channel.
            $same = true;
            for ($shift = 0; $shift <= 16; $shift += 8) {
                if (abs((($a >> $shift) & 0xFF) - (($b >> $shift) & 0xFF)) > 2) {
                    $same = false;
                    break;
                }
            }
            if ($same) {
                continue;
            }
            $diff++;
            if ($x < $minX) $minX = $x;
            if ($y < $minY) $minY = $y;
            if ($x > $maxX) $maxX = $x;
            if ($y > $maxY) $maxY = $y;
        }
    }

    $total = (int)ceil($w / 2) * (int)ceil($h / 2);
    $pct   = $total > 0 ? ($diff / $total) * 100 : 0.0;

    if ($diff === 0) {
        $identical++;
        continue;
    }

    $changed[] = [$name, $pct, "$minX,$minY - $maxX,$maxY"];
}

/* ------------------------------------------------------------------ report */

printf("%-28s %8s  %s\n", 'page', 'changed', 'region');
echo str_repeat('-', 78), "\n";

usort($changed, fn($x, $y) => $y[1] <=> $x[1]);

foreach ($changed as [$name, $pct, $region]) {
    printf("%-28s %7.3f%%  %s\n", $name, $pct, $region);
}

echo "\n";
printf("identical: %d\n", $identical);
printf("changed:   %d\n", count($changed));
if ($missing) {
    printf("missing:   %d  (%s)\n", count($missing), implode(', ', array_slice($missing, 0, 5)));
}
