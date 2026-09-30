<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Classe de base des contrôleurs : chaque action retourne une Response.
 */
abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    protected function view(string $template, array $vars = [], int $status = 200): Response
    {
        $vars += [
            'app' => $this->app,
            'user' => Auth::user($this->app),
            'flashes' => take_flashes(),
        ];
        return Response::html(View::render($template, $vars), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }

    /** Charge une ligne par identifiant ou lève une 404. */
    protected function findOr404(string $table, int $id): array
    {
        $st = $this->app->db->prepare("SELECT * FROM {$table} WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row === false) {
            throw new HttpException(404);
        }
        return $row;
    }

    protected function currentUserId(): ?int
    {
        return Auth::user($this->app)['id'] ?? null;
    }
}
