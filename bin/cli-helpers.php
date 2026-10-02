<?php

// Fonctions communes aux scripts de bin/ (saisie au terminal, arrêt sur erreur).

declare(strict_types=1);

/** Demande une valeur au terminal ; $hidden masque la saisie (mot de passe) quand le terminal le permet. */
function cli_ask(string $label, bool $hidden = false): string
{
    echo $label;
    if ($hidden && DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN)) {
        shell_exec('stty -echo');
        $value = fgets(STDIN);
        shell_exec('stty echo');
        echo "\n";
    } else {
        $value = fgets(STDIN);
    }
    return trim((string) $value);
}

/** Affiche l'erreur et termine le script avec un code d'échec. */
function cli_fail(string $message): never
{
    fwrite(STDERR, "Erreur : {$message}\n");
    exit(1);
}
