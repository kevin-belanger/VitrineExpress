-- Schéma initial (voir docs/specification-mvp.md, section Modèle de données).
-- Les dates sont au format 'YYYY-MM-DD HH:MM:SS', heure locale du fuseau configuré.

CREATE TABLE users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    display_name  TEXT NOT NULL DEFAULT '',
    created_at    TEXT NOT NULL,
    last_login_at TEXT
);

CREATE TABLE groups (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE COLLATE NOCASE,
    description TEXT NOT NULL DEFAULT ''
);

CREATE TABLE devices (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    name               TEXT NOT NULL,
    description        TEXT NOT NULL DEFAULT '',
    code               TEXT NOT NULL UNIQUE,
    token_hash         TEXT UNIQUE,
    connected_at       TEXT,
    last_seen_at       TEXT,
    last_message_id    INTEGER,
    current_message_id INTEGER,
    user_agent         TEXT,
    ip                 TEXT,
    created_at         TEXT NOT NULL
);

CREATE TABLE device_groups (
    device_id INTEGER NOT NULL REFERENCES devices (id) ON DELETE CASCADE,
    group_id  INTEGER NOT NULL REFERENCES groups (id) ON DELETE CASCADE,
    PRIMARY KEY (device_id, group_id)
);
CREATE INDEX idx_device_groups_group ON device_groups (group_id);

CREATE TABLE backgrounds (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    kind       TEXT NOT NULL CHECK (kind IN ('css', 'image')),
    css_value  TEXT,
    image_path TEXT,
    text_color TEXT NOT NULL DEFAULT '#ffffff',
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE messages (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    title            TEXT NOT NULL,
    type             TEXT NOT NULL CHECK (type IN ('image', 'text')),
    media_path       TEXT,
    media_mime       TEXT,
    text_html        TEXT,
    background_id    INTEGER REFERENCES backgrounds (id) ON DELETE SET NULL,
    duration_seconds INTEGER NOT NULL,
    start_at         TEXT NOT NULL,
    end_at           TEXT,
    all_devices      INTEGER NOT NULL DEFAULT 0,
    created_by       INTEGER REFERENCES users (id) ON DELETE SET NULL,
    created_at       TEXT NOT NULL,
    updated_at       TEXT NOT NULL
);
CREATE INDEX idx_messages_period ON messages (start_at, end_at);

CREATE TABLE message_groups (
    message_id INTEGER NOT NULL REFERENCES messages (id) ON DELETE CASCADE,
    group_id   INTEGER NOT NULL REFERENCES groups (id) ON DELETE CASCADE,
    PRIMARY KEY (message_id, group_id)
);
CREATE INDEX idx_message_groups_group ON message_groups (group_id);

CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
