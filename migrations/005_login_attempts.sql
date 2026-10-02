-- Limite de tentatives : échecs récents de connexion par code (périphériques) et de connexion des comptes,
-- par adresse IP. Les lignes de plus de 15 minutes sont effacées au fil de l'eau.

CREATE TABLE login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    kind         TEXT NOT NULL,
    ip           TEXT NOT NULL,
    subject      TEXT NOT NULL DEFAULT '',
    attempted_at TEXT NOT NULL
);
CREATE INDEX idx_login_attempts_lookup ON login_attempts (kind, ip, attempted_at);
