<?php
// Images du site : recadrage des captures, réduction et conversion en WebP (plus une image de partage JPEG).
declare(strict_types=1);

$in = '/tmp/vx-site-shots';
$posters = '/tmp/vx-demo-posters';
$out = '/tmp/vx-site/img';
@mkdir($out, 0775, true);

function convert(string $src, string $dst, ?array $crop, int $width, int $quality = 84): void
{
    $img = imagecreatefrompng($src);
    if ($crop !== null) {
        $img = imagecrop($img, ['x' => $crop[0], 'y' => $crop[1], 'width' => $crop[2], 'height' => $crop[3]]);
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($width < $w) {
        $nh = (int) round($h * $width / $w);
        $resized = imagecreatetruecolor($width, $nh);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $width, $nh, $w, $h);
        $img = $resized;
    }
    if (str_ends_with($dst, '.jpg')) {
        imagejpeg($img, $dst, $quality);
    } else {
        imagewebp($img, $dst, $quality);
    }
    printf("%-22s %4d x %4d  %4d Ko\n", basename($dst), imagesx($img), imagesy($img), (int) round(filesize($dst) / 1024));
}

convert("$in/dashboard.png", "$out/tableau-de-bord.webp", [0, 0, 2880, 1540], 2000);
convert("$in/messages.png", "$out/messages.webp", [0, 0, 2880, 1730], 2000);
convert("$in/devices.png", "$out/ecrans.webp", [0, 0, 2880, 1380], 2000);
convert("$in/message-texte.png", "$out/message.webp", [0, 0, 2880, 1330], 2000);
convert("$in/message-texte.png", "$out/cibles.webp", [270, 1520, 1580, 1120], 1580);
convert("$in/diffusion.png", "$out/diffusion.webp", [0, 0, 2880, 1420], 2000);
convert("$in/users.png", "$out/utilisateurs.webp", [0, 0, 2880, 700], 2000);
convert("$in/display-code.png", "$out/ecran-code.webp", null, 1600);
convert("$posters/yoga.png", "$out/affiche-yoga.webp", null, 1600);
convert("$posters/sang.png", "$out/affiche-sang.webp", null, 1600);
convert("$posters/noel.png", "$out/affiche-noel.webp", null, 1600);
convert("$in/dashboard.png", "$out/og.jpg", [0, 0, 2880, 1512], 1200, 85);
