<?php

declare(strict_types=1);

use VitrineExpress\App;

/** Application neuve sur une base SQLite en mémoire, migrations appliquées. */
function test_app(): App
{
    $root = dirname(__DIR__);
    return App::fromConfig(VitrineExpress\load_config([
        'db_path' => ':memory:',
        'uploads_path' => sys_get_temp_dir() . '/vx-test-uploads-' . getmypid(),
        'log_path' => sys_get_temp_dir() . '/vx-test.log',
        'debug' => true,
    ]));
}

function assert_true(mixed $value, string $message = 'La valeur devrait être vraie'): void
{
    if ($value !== true) {
        throw new RuntimeException($message . ' (obtenu : ' . var_export($value, true) . ')');
    }
}

function assert_false(mixed $value, string $message = 'La valeur devrait être fausse'): void
{
    if ($value !== false) {
        throw new RuntimeException($message . ' (obtenu : ' . var_export($value, true) . ')');
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message !== '' ? $message . ' — ' : '') . 'attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true)
        );
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException(($message !== '' ? $message . ' — ' : '') . "« {$needle} » introuvable dans : " . mb_substr($haystack, 0, 300));
    }
}

function assert_throws(string $class, callable $fn, string $message = ''): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        throw new RuntimeException("Exception {$class} attendue, " . get_class($e) . ' obtenue : ' . $e->getMessage());
    }
    throw new RuntimeException($message !== '' ? $message : "Exception {$class} attendue, aucune levée");
}
