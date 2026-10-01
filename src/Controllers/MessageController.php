<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use PDO;
use VitrineExpress\Controller;
use VitrineExpress\Devices;
use VitrineExpress\Groups;
use VitrineExpress\HttpException;
use VitrineExpress\Media;
use VitrineExpress\Messages;
use VitrineExpress\Response;
use VitrineExpress\ValidationException;

final class MessageController extends Controller
{
    public function index(): Response
    {
        $filters = [
            'group' => (int) ($_GET['group'] ?? 0),
            'device' => (int) ($_GET['device'] ?? 0),
            'status' => array_key_exists((string) ($_GET['status'] ?? ''), Messages::FILTER_LABELS) ? (string) $_GET['status'] : '',
        ];
        return $this->view('messages/index', [
            'title' => 'Messages',
            'messages' => Messages::search($this->app, $filters),
            'filters' => $filters,
            'groups' => Groups::options($this->app),
            'devices' => $this->app->db->query('SELECT id, name FROM devices ORDER BY name COLLATE NOCASE')->fetchAll(PDO::FETCH_KEY_PAIR),
        ]);
    }

    public function create(): Response
    {
        $values = Messages::defaults($this->app);
        if (($_GET['type'] ?? '') === Messages::TYPE_TEXT) {
            $values['type'] = Messages::TYPE_TEXT;
        }
        return $this->form($values, [], null);
    }

    public function store(): Response
    {
        $type = ($_POST['type'] ?? '') === Messages::TYPE_TEXT ? Messages::TYPE_TEXT : Messages::TYPE_IMAGE;
        [$values, $errors] = Messages::fromForm($this->app, $_POST, $type);

        $hasFile = ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($type === Messages::TYPE_IMAGE && !$hasFile) {
            $errors['image'] = 'Choisissez une image.';
        }
        $media = null;
        if (!$errors && $type === Messages::TYPE_IMAGE) {
            try {
                $media = Media::storeUploadedImage($this->app, $_FILES['image']);
            } catch (ValidationException $e) {
                $errors['image'] = $e->getMessage();
            }
        }
        if ($errors) {
            return $this->form($values, $errors, null, 422);
        }

        $this->app->transaction(fn () => Messages::create($this->app, $values, $media, $this->currentUserId()));
        flash('success', 'Message « ' . $values['title'] . ' » ajouté.');
        return $this->redirect('/admin/messages');
    }

    public function edit(string $id): Response
    {
        $message = $this->findOr404('messages', (int) $id);
        return $this->form(Messages::toForm($this->app, $message), [], $message);
    }

    public function update(string $id): Response
    {
        $message = $this->findOr404('messages', (int) $id);
        [$values, $errors] = Messages::fromForm($this->app, $_POST, $message['type']);

        $media = null;
        $hasFile = ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$errors && $message['type'] === Messages::TYPE_IMAGE && $hasFile) {
            try {
                $media = Media::storeUploadedImage($this->app, $_FILES['image']);
            } catch (ValidationException $e) {
                $errors['image'] = $e->getMessage();
            }
        }
        if ($errors) {
            return $this->form($values, $errors, $message, 422);
        }

        $old = $this->app->transaction(fn () => Messages::update($this->app, (int) $message['id'], $values, $media));
        Media::delete($this->app, $old);
        flash('success', 'Message « ' . $values['title'] . ' » modifié.');
        return $this->redirect('/admin/messages');
    }

    public function delete(string $id): Response
    {
        $message = $this->findOr404('messages', (int) $id);
        Messages::delete($this->app, (int) $message['id']);
        flash('success', 'Message « ' . $message['title'] . ' » supprimé.');
        return $this->redirect('/admin/messages');
    }

    private function form(array $values, array $errors, ?array $message, int $status = 200): Response
    {
        if (!isset($values['type']) || !array_key_exists($values['type'], Messages::TYPE_LABELS)) {
            throw new HttpException(400);
        }
        return $this->view('messages/form', [
            'title' => $message === null ? 'Nouveau message' : 'Modifier le message',
            'values' => $values,
            'errors' => $errors,
            'message' => $message,
            'imageUrl' => $message !== null ? Media::url($this->app, $message['media_path']) : null,
            'maxBytes' => Media::limitBytes($this->app),
            'maxLabel' => Media::formatBytes(Media::limitBytes($this->app)),
            'groups' => Groups::pickerItems($this->app),
            'devices' => Devices::targetItems($this->app),
            'deviceNames' => Devices::options($this->app),
            'backgrounds' => Messages::backgrounds($this->app),
            'styles' => [asset('/assets/vendor/quill/quill.snow.css')],
            'scripts' => [asset('/assets/vendor/quill/quill.js'), asset('/assets/message-form.js')],
        ], $status);
    }
}
