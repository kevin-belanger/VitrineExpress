<?php

declare(strict_types=1);

namespace VitrineExpress;

use Throwable;

/**
 * Traite une requête : session, routage, authentification, CSRF, erreurs.
 */
final class Kernel
{
    private Router $router;

    public function __construct(private readonly App $app)
    {
        $this->router = new Router();
        (require __DIR__ . '/routes.php')($this->router);
    }

    public function handle(string $method, string $path): Response
    {
        $isApi = str_starts_with($path, '/api/');
        try {
            if (!$isApi && !str_starts_with($path, '/media/')) {
                $this->startSession();
            }

            [$handler, $params] = $this->router->match($method, $path);

            if ((str_starts_with($path, '/admin/') || $path === '/admin') && Auth::user($this->app) === null) {
                return Response::redirect(url('/login'));
            }
            if ($method === 'POST' && !$isApi && !hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
                throw new HttpException(419);
            }

            [$class, $action] = $handler;
            return (new $class($this->app))->$action(...$params);
        } catch (HttpException $e) {
            return $this->error($e->status, $e->getMessage(), $isApi);
        } catch (Throwable $e) {
            $this->log($e);
            $message = !empty($this->app->config['debug'])
                ? get_class($e) . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')'
                : 'Une erreur inattendue est survenue.';
            return $this->error(500, $message, $isApi);
        }
    }

    private function error(int $status, string $message, bool $json): Response
    {
        if ($json) {
            return Response::json(['error' => $message], $status);
        }
        $user = session_status() === PHP_SESSION_ACTIVE ? Auth::user($this->app) : null;
        return Response::html(View::render('error', [
            'app' => $this->app,
            'user' => $user,
            'flashes' => [],
            'status' => $status,
            'message' => $message,
            'title' => 'Erreur ' . $status,
        ]), $status);
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('vx_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path() === '' ? '/' : base_path() . '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    private function log(Throwable $e): void
    {
        $path = $this->app->config['log_path'];
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %s: %s in %s:%d\n%s\n", date('c'), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        error_log($e->getMessage());
    }
}
