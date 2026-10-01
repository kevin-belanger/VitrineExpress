-- Rôles des comptes (administrateur ou gestionnaire de groupes) et groupes confiés aux gestionnaires.
-- Les comptes existants deviennent administrateurs.

ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'admin' CHECK (role IN ('admin', 'manager'));

CREATE TABLE user_groups (
    user_id  INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    group_id INTEGER NOT NULL REFERENCES groups (id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, group_id)
);
CREATE INDEX idx_user_groups_group ON user_groups (group_id);
