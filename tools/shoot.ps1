# Captures des pages de gestion de la copie de démonstration (serveur 8082, session dans l'URL).
$chrome = "C:\Program Files\Google\Chrome\Application\chrome.exe"
$profile = "$env:TEMP\vx-headless"
$out = "$env:TEMP\vx-site\shots"
$base = "http://127.0.0.1:8082"
$sid = "0123456789abcdef0123456789abcdef"   # marie, administratrice
$sid2 = "abcdef0123456789abcdef0123456789"  # karim, gestionnaire de groupes
New-Item -ItemType Directory -Force $out | Out-Null

function Shot($name, $path, $w, $h, $scale) {
    $url = "$base$path"
    Start-Process -FilePath $chrome -ArgumentList @(
        "--headless=new", "--disable-gpu", "--no-first-run", "--hide-scrollbars",
        "--user-data-dir=$profile", "--window-size=$w,$h", "--force-device-scale-factor=$scale",
        "--screenshot=$out\$name.png", $url
    ) -Wait -WindowStyle Hidden
}

Shot "dashboard" "/admin?vx_session=$sid" 1440 900 2
Shot "messages" "/admin/messages?vx_session=$sid" 1440 900 2
Shot "message-texte" "/admin/messages/5/edit?vx_session=$sid" 1440 1500 2
Shot "message-image" "/admin/messages/2/edit?vx_session=$sid" 1440 1500 2
Shot "devices" "/admin/devices?vx_session=$sid" 1440 900 2
Shot "users" "/admin/users?vx_session=$sid" 1440 900 2
Shot "diffusion" "/admin/messages/1/edit?vx_session=$sid2" 1440 900 2
Shot "display-code" "/display" 1920 1080 1

Get-ChildItem "$out\*.png" | ForEach-Object { "$($_.Name) $($_.Length)" }
