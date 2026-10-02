#!/usr/bin/env bash
# Copie de démonstration pour les captures du site : code du dépôt, base neuve, session d'administrateur
# prête pour le navigateur sans interface (identifiant de session passé dans l'URL, voir le serveur 8082).
set -euo pipefail
SRC=/home/kevin/VitrineExpress
DST=/tmp/vx-demo
rm -rf "$DST"
mkdir -p "$DST" "$DST/sessions"
rsync -a --exclude .git --exclude 'storage/*' --exclude 'config/config.local.php' "$SRC/" "$DST/"
mkdir -p "$DST/storage/uploads" "$DST/storage/logs"
cd "$DST"
php bin/install.php --username=marie --password=demo-demo-1 --name="Marie Lavoie"
printf 'user_id|i:1;' > "$DST/sessions/sess_0123456789abcdef0123456789abcdef"   # marie (administratrice)
printf 'user_id|i:3;' > "$DST/sessions/sess_abcdef0123456789abcdef0123456789"   # karim (gestionnaire), créé par le seed
echo "sessions prêtes"
