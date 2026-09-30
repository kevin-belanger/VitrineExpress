<?php
use VitrineExpress\Devices;

$badgeClass = [
    Devices::STATUS_ONLINE => 'badge-success',
    Devices::STATUS_OFFLINE => 'badge-danger',
    Devices::STATUS_DISCONNECTED => '',
][$status];
?>
<span class="badge <?= $badgeClass ?>"><?= e(Devices::STATUS_LABELS[$status]) ?></span>
