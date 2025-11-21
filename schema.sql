CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    level ENUM('A','B','C','D') NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    date DATE NOT NULL,
    num_courts INT NOT NULL,
    status ENUM('setup','active','finished') NOT NULL DEFAULT 'setup',
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE tournament_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    player_id INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    games_played INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_tournament_player (tournament_id, player_id),
    KEY idx_tp_tournament (tournament_id),
    CONSTRAINT fk_tp_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE courts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    court_number INT NOT NULL,
    status ENUM('active','finished') NOT NULL DEFAULT 'active',
    UNIQUE KEY uniq_court (tournament_id, court_number),
    CONSTRAINT fk_court_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    court_id INT NOT NULL,
    status ENUM('active','completed') NOT NULL,
    team1_score INT NULL,
    team2_score INT NULL,
    created_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    KEY idx_games_tournament_status (tournament_id, status),
    CONSTRAINT fk_games_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_games_court FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE game_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    tournament_player_id INT NOT NULL,
    team TINYINT NOT NULL,
    KEY idx_game_players_game_team (game_id, team),
    CONSTRAINT fk_gp_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    CONSTRAINT fk_gp_tp FOREIGN KEY (tournament_player_id) REFERENCES tournament_players(id) ON DELETE CASCADE
) ENGINE=InnoDB;
