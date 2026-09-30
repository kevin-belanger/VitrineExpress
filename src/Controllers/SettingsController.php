<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\Media;
use VitrineExpress\Messages;
use VitrineExpress\Response;
use VitrineExpress\ValidationException;

final class SettingsController extends Controller
{
    public function edit(): Response
    {
        return $this->form($this->current(), []);
    }

    public function update(): Response
    {
        $values = [
            'org_name' => input('org_name'),
            'default_duration' => input('default_duration'),
            'max_upload_mb' => input('max_upload_mb'),
            'timezone' => input('timezone'),
        ];
        $errors = [];

        if (mb_strlen($values['org_name']) > 100) {
            $errors['org_name'] = 'Maximum 100 caractères.';
        }
        $duration = filter_var($values['default_duration'], FILTER_VALIDATE_INT);
        if ($duration === false || $duration < Messages::MIN_DURATION || $duration > Messages::MAX_DURATION) {
            $errors['default_duration'] = 'Entre ' . Messages::MIN_DURATION . ' et ' . Messages::MAX_DURATION . ' secondes.';
        }
        $maxUpload = filter_var($values['max_upload_mb'], FILTER_VALIDATE_INT);
        if ($maxUpload === false || $maxUpload < 1 || $maxUpload > 500) {
            $errors['max_upload_mb'] = 'Entre 1 et 500 Mo.';
        }
        if (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
            $errors['timezone'] = 'Fuseau horaire inconnu.';
        }

        $logo = null;
        $hasFile = ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$errors && $hasFile) {
            try {
                $logo = Media::storeUploadedImage($this->app, $_FILES['logo']);
            } catch (ValidationException $e) {
                $errors['logo'] = $e->getMessage();
            }
        }
        if ($errors) {
            return $this->form($values + ['logo_path' => $this->app->setting('logo_path', '')], $errors, 422);
        }

        $oldLogo = (string) $this->app->setting('logo_path', '');
        foreach ($values as $key => $value) {
            $this->app->setSetting($key, (string) $value);
        }
        if ($logo !== null || !empty($_POST['remove_logo'])) {
            $this->app->setSetting('logo_path', $logo['path'] ?? '');
            Media::delete($this->app, $oldLogo !== '' ? $oldLogo : null);
        }

        flash('success', 'Paramètres enregistrés.');
        return $this->redirect('/admin/settings');
    }

    private function current(): array
    {
        return [
            'org_name' => $this->app->setting('org_name', ''),
            'default_duration' => $this->app->setting('default_duration', '20'),
            'max_upload_mb' => $this->app->setting('max_upload_mb', '20'),
            'timezone' => $this->app->setting('timezone', $this->app->config['timezone']),
            'logo_path' => $this->app->setting('logo_path', ''),
        ];
    }

    private function form(array $values, array $errors, int $status = 200): Response
    {
        return $this->view('settings/form', [
            'title' => 'Paramètres',
            'values' => $values,
            'errors' => $errors,
            'logoUrl' => Media::url($this->app, (string) $values['logo_path']),
            'timezones' => timezone_identifiers_list(),
            'serverUploadLimit' => ini_get('upload_max_filesize'),
        ], $status);
    }
}
