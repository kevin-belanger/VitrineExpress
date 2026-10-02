<?php

declare(strict_types=1);

use VitrineExpress\Devices;
use VitrineExpress\Groups;
use VitrineExpress\Kernel;
use VitrineExpress\Users;

// Listes de la gestion : toute la ligne ouvre la fiche ; « Supprimer » est dans la fiche, plus dans la liste.

function test_list_rows_open_the_record_where_delete_lives(): void
{
    $app = test_app();
    $admin = Users::create($app, 'admin', 'secret123');
    $other = Users::create($app, 'julie', 'secret123', '', Users::ROLE_MANAGER);
    $group = Groups::create($app, 'Accueil', '', []);
    $device = Devices::create($app, 'Télé hall', '', [$group]);
    $message = make_message($app, 'Bienvenue', [$group]);
    $_SESSION['user_id'] = $admin;
    $kernel = new Kernel($app);

    foreach ([
        '/admin/messages' => '/admin/messages/' . $message,
        '/admin/devices' => '/admin/devices/' . $device,
        '/admin/groups' => '/admin/groups/' . $group,
        '/admin/users' => '/admin/users/' . $other,
    ] as $list => $record) {
        $page = $kernel->handle('GET', $list)->body;
        assert_contains('data-href="' . $record . '/edit"', $page, "{$list} : ligne cliquable");
        assert_contains('class="row-link" href="' . $record . '/edit"', $page, "{$list} : le nom reste un lien");
        assert_false(str_contains($page, '/delete"'), "{$list} : plus de suppression dans la liste");

        $form = $kernel->handle('GET', $record . '/edit')->body;
        assert_contains('action="' . $record . '/delete"', $form, "{$record} : formulaire de suppression");
        assert_contains('form="delete-form"', $form, "{$record} : bouton Supprimer");
        assert_false(str_contains($kernel->handle('GET', $list . '/new')->body, 'form="delete-form"'), "{$list}/new : rien à supprimer");
    }

    assert_false(str_contains($kernel->handle('GET', '/admin/users/' . $admin . '/edit')->body, 'form="delete-form"'),
        'On ne supprime pas son propre compte');
}
