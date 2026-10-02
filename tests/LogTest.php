<?php

declare(strict_types=1);

use VitrineExpress\Log;

function test_log_rotates_when_too_big(): void
{
    $dir = sys_get_temp_dir() . '/vx-log-test-' . getmypid();
    $path = $dir . '/app.log';
    @unlink($path);
    @unlink($path . '.1');

    Log::append($path, "première ligne\n");
    assert_same("première ligne\n", file_get_contents($path), 'Dossier créé, ligne écrite');

    file_put_contents($path, str_repeat('x', Log::MAX_BYTES + 1));
    Log::append($path, "nouvelle ligne\n");
    assert_same("nouvelle ligne\n", file_get_contents($path), 'Journal reparti de zéro');
    assert_same(Log::MAX_BYTES + 1, filesize($path . '.1'), 'Ancien journal gardé en .1');

    file_put_contents($path, str_repeat('y', Log::MAX_BYTES + 1));
    Log::append($path, "encore\n");
    assert_same('y', file_get_contents($path . '.1')[0], 'Le .1 précédent est écrasé : jamais plus de deux fichiers');

    unlink($path);
    unlink($path . '.1');
    rmdir($dir);
}
