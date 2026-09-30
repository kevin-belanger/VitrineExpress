<?php

declare(strict_types=1);

namespace VitrineExpress;

use RuntimeException;

final class View
{
    /**
     * Rend un gabarit de templates/ ; s'il est fourni, le gabarit englobant reçoit le contenu dans $content.
     */
    public static function render(string $template, array $vars = [], ?string $layout = 'layout'): string
    {
        $content = self::renderFile($template, $vars);
        if ($layout === null) {
            return $content;
        }
        return self::renderFile($layout, $vars + ['content' => $content]);
    }

    private static function renderFile(string $template, array $vars): string
    {
        $file = dirname(__DIR__) . '/templates/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Gabarit introuvable : {$template}");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
