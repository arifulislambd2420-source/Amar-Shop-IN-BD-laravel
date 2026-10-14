<?php

/*
| One-off helper that drew the placeholder pictures of the demo landing pages
| (run: php database/seeders/demo-landing/generate-images.php). The PNGs it
| writes are committed next to it; the seeder never needs to run this.
|
| Simple flat product silhouettes, one per colour, on a soft background — real
| photos replace them from the admin. Names start with "demo-" so
| `php artisan demo:remove` deletes them with the rest of the demo data.
*/

error_reporting(E_ALL & ~E_DEPRECATED);
$catalog = require __DIR__.'/catalog.php';
$out = __DIR__;
$S = 1600; // drawn big, scaled down to 800 for smooth edges

function hex($im, string $hex, int $alpha = 0): int
{
    $hex = ltrim($hex, '#');

    return imagecolorallocatealpha($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
}

function shade(string $hex, float $f): string
{
    $hex = ltrim($hex, '#');
    $c = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    $c = array_map(fn ($v) => (int) max(0, min(255, $f >= 0 ? $v + (255 - $v) * $f : $v * (1 + $f))), $c);

    return sprintf('#%02x%02x%02x', ...$c);
}

function poly($im, array $pts, int $color): void
{
    imagefilledpolygon($im, $pts, $color);
}

function drawWatch($im, string $c, int $S): void
{
    $cx = $S / 2;
    $cy = $S / 2;
    $strap = hex($im, shade($c, -0.35));
    imagefilledrectangle($im, $cx - 150, 150, $cx + 150, $S - 150, $strap);
    imagefilledrectangle($im, $cx - 150, 150, $cx + 150, 330, hex($im, shade($c, -0.5)));
    imagefilledrectangle($im, $cx - 150, $S - 330, $cx + 150, $S - 150, hex($im, shade($c, -0.5)));
    imagefilledellipse($im, $cx, $cy, 760, 760, hex($im, $c));
    imagefilledellipse($im, $cx, $cy, 690, 690, hex($im, shade($c, 0.35)));
    imagefilledellipse($im, $cx, $cy, 620, 620, hex($im, '#14213d'));
    for ($i = 0; $i < 12; $i++) {
        $a = deg2rad($i * 30);
        imagesetthickness($im, $i % 3 === 0 ? 14 : 6);
        imageline($im, $cx + sin($a) * 250, $cy - cos($a) * 250, $cx + sin($a) * 290, $cy - cos($a) * 290, hex($im, '#f1f5f9'));
    }
    imagesetthickness($im, 18);
    imageline($im, $cx, $cy, $cx + 110, $cy - 130, hex($im, '#f8fafc'));
    imagesetthickness($im, 12);
    imageline($im, $cx, $cy, $cx - 60, $cy - 200, hex($im, '#f8fafc'));
    imagefilledellipse($im, $cx, $cy, 40, 40, hex($im, $c));
    imagesetthickness($im, 1);
}

function drawHijab($im, string $c, int $S): void
{
    $cx = $S / 2;
    poly($im, [$cx, 170, $cx + 430, 520, $cx + 560, 1400, $cx - 560, 1400, $cx - 430, 520], hex($im, $c));
    poly($im, [$cx, 170, $cx + 250, 420, $cx + 210, 1400, $cx - 210, 1400, $cx - 250, 420], hex($im, shade($c, -0.18)));
    imagefilledellipse($im, $cx, 560, 400, 500, hex($im, '#f0c9a8'));
    poly($im, [$cx - 230, 470, $cx, 300, $cx + 230, 470, $cx + 200, 400, $cx, 250, $cx - 200, 400], hex($im, $c));
    imagefilledellipse($im, $cx - 70, 560, 24, 24, hex($im, '#3b2a20'));
    imagefilledellipse($im, $cx + 70, 560, 24, 24, hex($im, '#3b2a20'));
    poly($im, [$cx - 560, 1400, $cx + 560, 1400, $cx + 400, 1250, $cx - 400, 1250], hex($im, shade($c, 0.22)));
}

function drawKurta($im, string $c, int $S): void
{
    $cx = $S / 2;
    poly($im, [$cx - 190, 210, $cx - 700, 520, $cx - 600, 700, $cx - 340, 600, $cx - 360, 1450, $cx + 360, 1450, $cx + 340, 600, $cx + 600, 700, $cx + 700, 520, $cx + 190, 210], hex($im, $c));
    poly($im, [$cx - 190, 210, $cx, 380, $cx + 190, 210, $cx + 110, 200, $cx, 280, $cx - 110, 200], hex($im, shade($c, -0.3)));
    imagefilledrectangle($im, $cx - 18, 380, $cx + 18, 900, hex($im, shade($c, -0.3)));
    foreach ([470, 600, 730, 860] as $y) {
        imagefilledellipse($im, $cx, $y, 34, 34, hex($im, shade($c, 0.55)));
    }
    imagefilledrectangle($im, $cx - 360, 1370, $cx + 360, 1450, hex($im, shade($c, -0.2)));
}

function drawBag($im, string $c, int $S): void
{
    $cx = $S / 2;
    imagesetthickness($im, 46);
    imagearc($im, $cx, 520, 620, 760, 180, 360, hex($im, shade($c, -0.4)));
    imagesetthickness($im, 1);
    poly($im, [$cx - 520, 560, $cx + 520, 560, $cx + 620, 1380, $cx - 620, 1380], hex($im, $c));
    poly($im, [$cx - 520, 560, $cx + 520, 560, $cx + 500, 700, $cx - 500, 700], hex($im, shade($c, -0.2)));
    imagefilledellipse($im, $cx, 760, 80, 80, hex($im, shade($c, 0.5)));
}

function drawShoe($im, string $c, int $S): void
{
    poly($im, [200, 1100, 260, 640, 520, 560, 700, 760, 1100, 860, 1420, 1020, 1440, 1160, 200, 1160], hex($im, $c));
    poly($im, [200, 1160, 1440, 1160, 1440, 1260, 200, 1260], hex($im, '#f1f5f9'));
    poly($im, [520, 560, 700, 760, 620, 800, 470, 640], hex($im, shade($c, -0.35)));
    foreach ([760, 860, 960] as $i => $x) {
        imagesetthickness($im, 22);
        imageline($im, $x - 60, 780 + $i * 55, $x + 60, 840 + $i * 55, hex($im, '#f8fafc'));
    }
    imagesetthickness($im, 1);
}

function drawTee($im, string $c, int $S): void
{
    $cx = $S / 2;
    poly($im, [$cx - 230, 230, $cx - 720, 440, $cx - 560, 740, $cx - 360, 640, $cx - 360, 1400, $cx + 360, 1400, $cx + 360, 640, $cx + 560, 740, $cx + 720, 440, $cx + 230, 230], hex($im, $c));
    imagefilledellipse($im, $cx, 235, 360, 200, hex($im, shade($c, -0.35)));
    imagefilledellipse($im, $cx, 215, 300, 150, hex($im, '#f1f5f9'));
}

$shapes = ['watch' => 'drawWatch', 'hijab' => 'drawHijab', 'kurta' => 'drawKurta', 'bag' => 'drawBag', 'shoe' => 'drawShoe', 'tee' => 'drawTee'];

foreach ($catalog as $page) {
    foreach ($page['colors'] as $color) {
        $im = imagecreatetruecolor($S, $S);
        imageantialias($im, true);
        imagefill($im, 0, 0, hex($im, $page['bg']));
        imagefilledellipse($im, $S / 2, $S * 0.55, $S * 0.95, $S * 0.95, hex($im, shade($page['bg'], 0.45)));
        $shapes[$page['shape']]($im, $color['hex'], $S);
        imagestring($im, 5, 24, 24, 'DEMO', hex($im, '#94a3b8'));

        $small = imagecreatetruecolor(800, 800);
        imagecopyresampled($small, $im, 0, 0, 0, 0, 800, 800, $S, $S);
        imagepng($small, $out.'/'.$page['slug'].'-'.$color['key'].'.png', 7);
    }
}

echo "done\n";
