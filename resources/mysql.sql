-- #!mysql
-- #{xauth.players.init}
-- #>
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
-- #<

-- #{xauth.sessions.init}
-- #>
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
-- #<

-- #{xauth.players.get}
SELECT * FROM players WHERE name = :name;

-- #{xauth.players.is_registered}
SELECT name FROM players WHERE name = :name;

-- #{xauth.players.register}
INSERT INTO players (name, password, ip, registered_at, registration_ip, last_login_at) VALUES (:name, :password, :ip, :registered_at, :registration_ip, :last_login_at);

-- #{xauth.players.update_ip}
UPDATE players SET ip = :ip, last_login_at = :last_login_at WHERE name = :name;

-- #{xauth.players.change_password}
UPDATE players SET password = :password WHERE name = :name;

-- #{xauth.players.unregister}
DELETE FROM players WHERE name = :name;

-- #{xauth.players.set_locked}
UPDATE players SET locked = :locked WHERE name = :name;

-- #{xauth.players.is_locked}
SELECT locked FROM players WHERE name = :name;

-- #{xauth.players.set_blocked_until}
UPDATE players SET blocked_until = :timestamp WHERE name = :name;

-- #{xauth.players.get_blocked_until}
SELECT blocked_until FROM players WHERE name = :name;

-- #{xauth.players.set_must_change_password}
UPDATE players SET must_change_password = :required WHERE name = :name;

-- #{xauth.players.must_change_password}
SELECT must_change_password FROM players WHERE name = :name;

-- #{xauth.players.get_all_data}
SELECT * FROM players;

-- #{xauth.players.register_raw}
INSERT IGNORE INTO players (name, password, ip, locked, registered_at, registration_ip, last_login_at, blocked_until, must_change_password) VALUES (:name, :password, :ip, :locked, :registered_at, :registration_ip, :last_login_at, :blocked_until, :must_change_password);

-- #{xauth.sessions.create}
INSERT INTO sessions (session_id, player_name, ip_address, device_id, login_time, last_activity, expiration_time) VALUES (:session_id, :player_name, :ip_address, :device_id, :login_time, :last_activity, :expiration_time);

-- #{xauth.sessions.get}
SELECT * FROM sessions WHERE session_id = :session_id AND expiration_time > :current_time;

-- #{xauth.sessions.get_by_player}
SELECT * FROM sessions WHERE player_name = :player_name AND expiration_time > :current_time ORDER BY login_time DESC;

-- #{xauth.sessions.delete}
DELETE FROM sessions WHERE session_id = :session_id;

-- #{xauth.sessions.delete_all_for_player}
DELETE FROM sessions WHERE player_name = :player_name;

-- #{xauth.sessions.update_last_activity}
UPDATE sessions SET last_activity = :current_time WHERE session_id = :session_id;

-- #{xauth.sessions.refresh}
UPDATE sessions SET expiration_time = :expiration_time WHERE session_id = :session_id;

-- #{xauth.sessions.cleanup_expired}
DELETE FROM sessions WHERE expiration_time <= :current_time;

-- #{xauth.players.get_registration_count_by_ip}
SELECT COUNT(*) as count FROM players WHERE registration_ip = :ip;