-- Ciblage direct d'un message sur des périphériques d'affichage, en plus (ou à la place) des groupes.

CREATE TABLE message_devices (
    message_id INTEGER NOT NULL REFERENCES messages (id) ON DELETE CASCADE,
    device_id  INTEGER NOT NULL REFERENCES devices (id) ON DELETE CASCADE,
    PRIMARY KEY (message_id, device_id)
);
CREATE INDEX idx_message_devices_device ON message_devices (device_id);
