-- Paramètres par défaut et arrière-plans prédéfinis.

INSERT INTO settings (key, value) VALUES
    ('default_duration', '20'),
    ('heartbeat_interval', '60'),
    ('offline_after', '180'),
    ('max_upload_mb', '20'),
    ('org_name', 'VitrineExpress'),
    ('logo_path', ''),
    ('timezone', 'America/Toronto');

INSERT INTO backgrounds (name, kind, css_value, text_color, sort_order) VALUES
    ('Nuit',    'css', 'linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%)', '#ffffff', 10),
    ('Océan',   'css', 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)',              '#ffffff', 20),
    ('Forêt',   'css', 'linear-gradient(135deg, #0b3d2e 0%, #1f7a55 100%)',              '#ffffff', 30),
    ('Aurore',  'css', 'linear-gradient(135deg, #2f0743 0%, #41295a 100%)',              '#ffffff', 40),
    ('Braise',  'css', 'linear-gradient(135deg, #1f1c18 0%, #8e0e00 100%)',              '#ffffff', 50),
    ('Ardoise', 'css', 'linear-gradient(135deg, #232526 0%, #414345 100%)',              '#ffffff', 60),
    ('Soleil',  'css', 'linear-gradient(135deg, #fceabb 0%, #f8b500 100%)',              '#1a1a1a', 70),
    ('Papier',  'css', 'linear-gradient(135deg, #f5f5f0 0%, #e4e4dc 100%)',              '#222222', 80);
