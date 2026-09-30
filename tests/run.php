<?php

// Mini-exécuteur de tests : charge tests/*Test.php et exécute chaque fonction test_*.
// Usage : php tests/run.php [filtre]

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/support.php';

$filter = $argv[1] ?? '';
$before = get_defined_functions()['user'];
foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}
$tests = array_filter(
    array_diff(get_defined_functions()['user'], $before),
    static fn (string $name): bool => str_starts_with($name, 'test_') && ($filter === '' || str_contains($name, strtolower($filter)))
);

$failures = [];
foreach ($tests as $test) {
    $_SESSION = [];
    $_POST = [];
    try {
        $test();
        echo '.';
    } catch (Throwable $e) {
        echo 'F';
        $failures[] = [$test, $e];
    }
}

echo "\n\n" . count($tests) . ' tests, ' . count($failures) . " échec(s)\n";
foreach ($failures as [$test, $e]) {
    echo "\n✗ {$test}\n  " . $e->getMessage() . "\n  " . $e->getFile() . ':' . $e->getLine() . "\n";
}
exit($failures ? 1 : 0);
