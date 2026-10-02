<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Access;
use VitrineExpress\Controller;
use VitrineExpress\Devices;
use VitrineExpress\Groups;
use VitrineExpress\HttpException;
use VitrineExpress\Media;
use VitrineExpress\Messages;
use VitrineExpress\Response;
use VitrineExpress\ValidationException;

/**
 * Messages. Les droits (administrateur, gestionnaire de groupes) viennent de Access :
 * modification complète pour l'administrateur et le créateur, « diffusion » seulement pour les autres.
 */
final class MessageController extends Controller
{
    public function index(): Response
    {
        $access = $this->access();
        $groups = $this->scoped(Groups::options($this->app), $access->groupIds());
        $devices = $this->scoped(Devices::options($this->app), $access->deviceIds());
        $filters = [
            'group' => isset($groups[(int) ($_GET['group'] ?? 0)]) ? (int) $_GET['group'] : 0,
            'device' => isset($devices[(int) ($_GET['device'] ?? 0)]) ? (int) $_GET['device'] : 0,
            'status' => array_key_exists((string) ($_GET['status'] ?? ''), Messages::FILTER_LABELS) ? (string) $_GET['status'] : '',
            // Gestionnaire : par défaut « Vos groupes » (son périmètre, comme les chiffres du tableau de bord) ;
            // « Tous les messages » montre aussi ceux des autres, qu'il peut ouvrir et diffuser chez lui.
            'all' => !$access->isAdmin() && ($_GET['group'] ?? '') === 'all',
        ];
        $scope = $filters['all'] ? null : $access->scope();
        return $this->view('messages/index', [
            'title' => 'Messages',
            'messages' => Messages::search($this->app, $filters + ($scope !== null ? ['scope' => $scope] : [])),
            'filters' => $filters,
            'groups' => $groups,
            'devices' => $devices,
            'access' => $access,
        ]);
    }

    public function create(): Response
    {
        $values = Messages::defaults($this->app); // type Image par défaut
        // « Tous les périphériques d'affichage » par défaut, quand le compte peut le choisir.
        $values['all_devices'] = $this->access()->canTargetAll();
        return $this->form($values, [], null);
    }

    public function store(): Response
    {
        $access = $this->access();
        $type = ($_POST['type'] ?? '') === Messages::TYPE_TEXT ? Messages::TYPE_TEXT : Messages::TYPE_IMAGE;
        [$values, $errors] = Messages::fromForm($this->app, $_POST, $type);
        $values = array_replace($values, $access->mergeTargets(null, $values['all_devices'], $values['group_ids'], $values['device_ids']));

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
        // Tout message s'ouvre : en entier pour l'administrateur et le créateur, sinon sa page Diffusion
        // (en lecture seule si le compte n'y peut rien, ex. un message « Tous les périphériques »).
        if ($this->access()->canEditContent($message)) {
            return $this->form(Messages::toForm($this->app, $message), [], $message);
        }
        return $this->targetsForm($message);
    }

    public function update(string $id): Response
    {
        $message = $this->findOr404('messages', (int) $id);
        $access = $this->access();

        // Message d'un autre : seulement la diffusion dans son périmètre.
        if (!$access->canEditContent($message)) {
            if (!$access->canEditTargets($message)) {
                throw new HttpException(403, 'Ce message est géré par quelqu’un d’autre.');
            }
            $requested = Messages::targetsFromForm($this->app, $_POST);
            $targets = $access->mergeTargets($message, false, $requested['group_ids'], $requested['device_ids']);
            $this->app->transaction(fn () => Messages::setTargets($this->app, (int) $message['id'], $targets));
            flash('success', 'Diffusion de « ' . $message['title'] . ' » modifiée.');
            return $this->redirect('/admin/messages');
        }

        [$values, $errors] = Messages::fromForm($this->app, $_POST, $message['type']);
        $values = array_replace($values, $access->mergeTargets($message, $values['all_devices'], $values['group_ids'], $values['device_ids']));

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
        if (!$this->access()->canDelete($message)) {
            throw new HttpException(403, 'Seul son créateur ou un administrateur peut supprimer ce message.');
        }
        Messages::delete($this->app, (int) $message['id']);
        flash('success', 'Message « ' . $message['title'] . ' » supprimé.');
        return $this->redirect('/admin/messages');
    }

    /** Formulaire complet (création, ou modification par l'administrateur ou le créateur). */
    private function form(array $values, array $errors, ?array $message, int $status = 200): Response
    {
        if (!isset($values['type']) || !array_key_exists($values['type'], Messages::TYPE_LABELS)) {
            throw new HttpException(400);
        }
        $access = $this->access();
        return $this->view('messages/form', [
            'title' => $message === null ? 'Nouveau message' : 'Modifier le message',
            'values' => $values,
            'errors' => $errors,
            'message' => $message,
            'canDelete' => $message !== null && $access->canDelete($message),
            'imageUrl' => $message !== null ? Media::url($this->app, $message['media_path']) : null,
            'maxBytes' => Media::limitBytes($this->app),
            'maxLabel' => Media::formatBytes(Media::limitBytes($this->app)),
            'backgrounds' => Messages::backgrounds($this->app),
            'styles' => [asset('/assets/vendor/quill/quill.snow.css')],
            'scripts' => [asset('/assets/vendor/quill/quill.js'), asset('/assets/message-form.js')],
        ] + $this->targetVars($access, $message), $status);
    }

    /** Message d'un autre : aperçu en lecture seule et diffusion dans son périmètre seulement. */
    private function targetsForm(array $message): Response
    {
        $access = $this->access();
        $details = Messages::search($this->app, ['id' => (int) $message['id']])[0];
        return $this->view('messages/targets', [
            'title' => 'Diffusion',
            'message' => $details,
            'values' => Messages::toForm($this->app, $message),
            'slide' => Messages::toSlide($this->app, $details),
        ] + $this->targetVars($access, $message));
    }

    /** Choix de cibles proposés au compte connecté, et cibles du message hors de son périmètre. */
    private function targetVars(Access $access, ?array $message): array
    {
        return [
            'groups' => $this->scoped(Groups::pickerItems($this->app), $access->groupIds()),
            'devices' => $this->scoped(Devices::targetItems($this->app), $access->deviceIds()),
            'canTargetAll' => $access->canTargetAll(),
            'canEditTargets' => $message === null || $access->canEditTargets($message),
            'otherTargets' => $message !== null ? $access->otherTargets($message) : ['names' => [], 'device_ids' => []],
        ];
    }

    /** Éléments d'une liste id => … limités aux identifiants permis. */
    private function scoped(array $items, array $allowedIds): array
    {
        return array_intersect_key($items, array_flip($allowedIds));
    }
}
