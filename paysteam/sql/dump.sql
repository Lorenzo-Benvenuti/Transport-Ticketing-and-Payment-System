-- DUMP DATABASE: schema + seed (PLAIN PASSWORDS enabled)

-- SCHEMA PAYSTEAM

SET @OLD_FK_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS p2_utenti (
  id_utente INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL,
  cognome VARCHAR(50) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  ruolo ENUM('consumatore','esercente') NOT NULL DEFAULT 'consumatore'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p2_conti (
  id_conto INT AUTO_INCREMENT PRIMARY KEY,
  id_utente INT NOT NULL,
  saldo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_p2_conti_utente FOREIGN KEY (id_utente) REFERENCES p2_utenti(id_utente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p2_movimenti (
  id_mov INT AUTO_INCREMENT PRIMARY KEY,
  id_utente INT NOT NULL,
  data_mov DATETIME NOT NULL,
  descrizione VARCHAR(255) NOT NULL,
  importo DECIMAL(10,2) NOT NULL,
  verso ENUM('ENTRATA','USCITA') NOT NULL,
  CONSTRAINT fk_p2_mov_utente FOREIGN KEY (id_utente) REFERENCES p2_utenti(id_utente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p2_carte (
  id_carta INT AUTO_INCREMENT PRIMARY KEY,
  id_utente INT NOT NULL,
  nome VARCHAR(100) NOT NULL,
  saldo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_p2_carte_utente FOREIGN KEY (id_utente) REFERENCES p2_utenti(id_utente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p2_transazioni (
  id_transazione INT AUTO_INCREMENT PRIMARY KEY,
  id_esercente INT NOT NULL,
  id_consumatore INT NOT NULL,
  external_tx_id VARCHAR(64) NOT NULL,
  descrizione VARCHAR(255) NOT NULL,
  importo DECIMAL(10,2) NOT NULL,
  return_url VARCHAR(255) NOT NULL,
  webhook_url VARCHAR(255) NOT NULL,
  tx_token VARCHAR(64) NOT NULL,
  stato ENUM('PENDING','SUCCEEDED','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_p2_tx_esercente FOREIGN KEY (id_esercente) REFERENCES p2_utenti(id_utente),
  CONSTRAINT fk_p2_tx_consumatore FOREIGN KEY (id_consumatore) REFERENCES p2_utenti(id_utente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- SEED PAYSTEAM

INSERT INTO p2_utenti (nome,cognome,email,password,ruolo) VALUES
('Utente','', 'utente@example.com', '$2y$10$ijI2LiUES3a8ha6V8zN1SuLjjNtlhhFQSte7bcWKNQo0vTnFJQVii', 'consumatore'),
('Admin','', 'esercente@paysteam.it', '$2y$10$xvPoYnQLUhewkRjxG9q8pOtIl.Y0ROXq1530h4eppyia4JksYp84O', 'esercente');

SET @uid_cons := (SELECT id_utente FROM p2_utenti WHERE email='utente@example.com' LIMIT 1);
SET @uid_mer  := (SELECT id_utente FROM p2_utenti WHERE email='esercente@paysteam.it' LIMIT 1);

INSERT INTO p2_conti (id_utente,saldo) VALUES (@uid_cons,50.00),(@uid_mer,0.00);

INSERT INTO p2_movimenti (id_utente,data_mov,descrizione,importo,verso) VALUES
(3,NOW(),'Saldo iniziale',50.00,'ENTRATA'),
(2,NOW(),'Saldo iniziale',0.00,'');

INSERT INTO p2_carte (id_carta, id_utente, nome, saldo) VALUES
(1,@uid_cons,'Carta Silver', 500.00),
(2,@uid_cons,'Carta Gold',1000.00),
(3,@uid_cons,'Carta Platinum',1500.00);
