-- #!mysql
-- # { xauth.init
CREATE TABLE IF NOT EXISTS players (
    name VARCHAR(255) PRIMARY KEY,
    password VARCHAR(255),
    ip VARCHAR(255),
    locked BOOLEAN DEFAULT FALSE,
    registered_at INT,
    registration_ip VARCHAR(255),
    last_login_at INT,
    blocked_until INT DEFAULT 0,
    must_change_password BOOLEAN DEFAULT FALSE
);
CREATE TABLE IF NOT EXISTS sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    player_name VARCHAR(255) NOT NULL,
    ip_address VARCHAR(255),
    device_id VARCHAR(255),
    login_time INT,
    last_activity INT,
    expiration_time INT,
    FOREIGN KEY (player_name) REFERENCES players(name) ON DELETE CASCADE
);
-- # }

-- # { xauth.players.get
-- # :name string
SELECT * FROM players WHERE name = :name;
-- # }

-- # { xauth.players.is_registered
-- # :name string
SELECT name FROM players WHERE name = :name;
-- # }

-- # { xauth.players.register
-- # :name string
-- # :password string
-- # :ip string
-- # :registered_at int
-- # :registration_ip string
-- # :last_login_at int
INSERT INTO players (name, password, ip, registered_at, registration_ip, last_login_at) VALUES (:name, :password, :ip, :registered_at, :registration_ip, :last_login_at);
-- # }

-- # { xauth.players.update_ip
-- # :name string
-- # :ip string
-- # :last_login_at int
UPDATE players SET ip = :ip, last_login_at = :last_login_at WHERE name = :name;
-- # }

-- # { xauth.players.change_password
-- # :name string
-- # :password string
UPDATE players SET password = :password WHERE name = :name;
-- # }

-- # { xauth.players.unregister
-- # :name string
DELETE FROM players WHERE name = :name;
-- # }

-- # { xauth.players.set_locked
-- # :name string
-- # :locked bool
UPDATE players SET locked = :locked WHERE name = :name;
-- # }

-- # { xauth.players.is_locked
-- # :name string
SELECT locked FROM players WHERE name = :name;
-- # }

-- # { xauth.players.set_blocked_until
-- # :name string
-- # :timestamp int
UPDATE players SET blocked_until = :timestamp WHERE name = :name;
-- # }

-- # { xauth.players.get_blocked_until
-- # :name string
SELECT blocked_until FROM players WHERE name = :name;
-- # }

-- # { xauth.players.set_must_change_password
-- # :name string
-- # :required bool
UPDATE players SET must_change_password = :required WHERE name = :name;
-- # }

-- # { xauth.players.must_change_password
-- # :name string
SELECT must_change_password FROM players WHERE name = :name;
-- # }

-- # { xauth.players.get_all_data
SELECT * FROM players;
-- # }

-- # { xauth.players.register_raw
-- # :name string
-- # :password string
-- # :ip string
-- # :locked bool
-- # :registered_at int
-- # :registration_ip string
-- # :last_login_at int
-- # :blocked_until int
-- # :must_change_password bool
INSERT IGNORE INTO players (name, password, ip, locked, registered_at, registration_ip, last_login_at, blocked_until, must_change_password) VALUES (:name, :password, :ip, :locked, :registered_at, :registration_ip, :last_login_at, :blocked_until, :must_change_password);
-- # }

-- # { xauth.sessions.create
-- # :session_id string
-- # :player_name string
-- # :ip_address string
-- # :device_id string
-- # :login_time int
-- # :last_activity int
-- # :expiration_time int
INSERT INTO sessions (session_id, player_name, ip_address, device_id, login_time, last_activity, expiration_time) VALUES (:session_id, :player_name, :ip_address, :device_id, :login_time, :last_activity, :expiration_time);
-- # }

-- # { xauth.sessions.get
-- # :session_id string
-- # :current_time int
SELECT * FROM sessions WHERE session_id = :session_id AND expiration_time > :current_time;
-- # }

-- # { xauth.sessions.get_by_player
-- # :player_name string
-- # :current_time int
SELECT * FROM sessions WHERE player_name = :player_name AND expiration_time > :current_time ORDER BY login_time DESC;
-- # }

-- # { xauth.sessions.delete
-- # :session_id string
DELETE FROM sessions WHERE session_id = :session_id;
-- # }

-- # { xauth.sessions.delete_all_for_player
-- # :player_name string
DELETE FROM sessions WHERE player_name = :player_name;
-- # }

-- # { xauth.sessions.update_last_activity
-- # :session_id string
-- # :current_time int
UPDATE sessions SET last_activity = :current_time WHERE session_id = :session_id;
-- # }

-- # { xauth.sessions.refresh
-- # :session_id string
-- # :expiration_time int
UPDATE sessions SET expiration_time = :expiration_time WHERE session_id = :session_id;
-- # }

-- # { xauth.sessions.cleanup_expired
-- # :current_time int
DELETE FROM sessions WHERE expiration_time <= :current_time;
-- # }

-- # { xauth.players.get_registration_count_by_ip
-- # :ip string
SELECT COUNT(*) as count FROM players WHERE registration_ip = :ip;
-- # }
