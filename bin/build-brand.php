<?php

// Génère les images de marque (public/assets/brand/ et public/favicon.ico) à partir des fichiers sources.
// Usage : php bin/build-brand.php <dossier-source>
//   <dossier-source>/logo-dark-text.webp   logo, texte foncé (pour fond clair)
//   <dossier-source>/logo-light-text.webp  logo, texte blanc (pour fond foncé)
//   <dossier-source>/icon.webp             icône seule (contour marine)
// Les sources sont rognées (marges transparentes), redimensionnées et enregistrées en PNG,
// format lu par tous les navigateurs, y compris ceux des vieux téléviseurs.

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
    exit("Usage : php bin/build-brand.php <dossier-source>\n");
}
$source = rtrim($argv[1], '/');
$target = dirname(__DIR__) . '/public/assets/brand';
@mkdir($target, 0775, true);

/** Charge une image et la rogne à sa zone non transparente (+ marge). */
function load_trimmed(string $path, int $margin = 4): GdImage
{
    $im = imagecreatefromstring((string) file_get_contents($path));
    if ($im === false) {
        throw new RuntimeException("Image illisible : $path");
    }
    imagesavealpha($im, true);
    $w = imagesx($im);
    $h = imagesy($im);
    $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if ((imagecolorat($im, $x, $y) >> 24 & 0x7F) < 120) {
                if ($x < $minX) { $minX = $x; }
                if ($x > $maxX) { $maxX = $x; }
                if ($y < $minY) { $minY = $y; }
                if ($y > $maxY) { $maxY = $y; }
            }
        }
    }
    $minX = max(0, $minX - $margin);
    $minY = max(0, $minY - $margin);
    $maxX = min($w - 1, $maxX + $margin);
    $maxY = min($h - 1, $maxY + $margin);
    return imagecrop($im, ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1]);
}

/** Copie redimensionnée, transparence conservée, centrée dans un canevas $cw × $ch (fond facultatif). */
function resized(GdImage $src, int $cw, int $ch, ?array $background = null, float $fill = 1.0): GdImage
{
    $dst = imagecreatetruecolor($cw, $ch);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $bg = $background === null
        ? imagecolorallocatealpha($dst, 0, 0, 0, 127)
        : imagecolorallocate($dst, ...$background);
    imagefilledrectangle($dst, 0, 0, $cw - 1, $ch - 1, $bg);
    imagealphablending($dst, true);
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = min($cw * $fill / $sw, $ch * $fill / $sh);
    $w = (int) round($sw * $scale);
    $h = (int) round($sh * $scale);
    imagecopyresampled($dst, $src, intdiv($cw - $w, 2), intdiv($ch - $h, 2), 0, 0, $w, $h, $sw, $sh);
    return $dst;
}

/** Logo à hauteur fixe, largeur proportionnelle. */
function logo(GdImage $src, int $height): GdImage
{
    return resized($src, (int) round(imagesx($src) * $height / imagesy($src)), $height);
}

function save_png(GdImage $im, string $path): void
{
    imagesavealpha($im, true);
    imagepng($im, $path, 9);
    echo basename($path) . ' : ' . imagesx($im) . '×' . imagesy($im) . ', ' . round(filesize($path) / 1024, 1) . " Ko\n";
}

/** Fichier .ico contenant des images PNG (accepté par tous les navigateurs actuels). */
function save_ico(array $pngs, string $path): void
{
    $header = pack('vvv', 0, 1, count($pngs));
    $entries = '';
    $data = '';
    $offset = 6 + 16 * count($pngs);
    foreach ($pngs as $size => $png) {
        $entries .= pack('CCCCvvVV', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($png), $offset);
        $data .= $png;
        $offset += strlen($png);
    }
    file_put_contents($path, $header . $entries . $data);
    echo basename($path) . ' : ' . implode(', ', array_keys($pngs)) . ' px, ' . round(filesize($path) / 1024, 1) . " Ko\n";
}

function png_bytes(GdImage $im): string
{
    ob_start();
    imagepng($im, null, 9);
    return (string) ob_get_clean();
}

$dark = load_trimmed("$source/logo-dark-text.webp");
$light = load_trimmed("$source/logo-light-text.webp");
$icon = load_trimmed("$source/icon.webp");

// Logos : hauteur 2× la taille d'affichage (écrans haute densité).
save_png(logo($dark, 112), "$target/logo-dark.png");    // pages claires (connexion, installation), affiché ~56 px
save_png(logo($light, 64), "$target/logo-light.png");   // barre du haut, affiché 32 px
save_png(logo($light, 160), "$target/logo-light-large.png"); // écran de connexion des téléviseurs

// Icônes : carrées, l'icône centrée.
save_png(resized($icon, 512, 512, null, 0.92), "$target/icon-512.png");
save_png(resized($icon, 180, 180, [255, 255, 255], 0.82), "$target/apple-touch-icon.png"); // iOS : fond opaque
save_png(resized($icon, 32, 32, null, 1.0), "$target/favicon-32.png");
save_ico([
    16 => png_bytes(resized($icon, 16, 16, null, 1.0)),
    32 => png_bytes(resized($icon, 32, 32, null, 1.0)),
    48 => png_bytes(resized($icon, 48, 48, null, 1.0)),
], dirname(__DIR__) . '/public/favicon.ico');
