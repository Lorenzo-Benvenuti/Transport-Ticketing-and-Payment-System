-- DUMP DATABASE: schema + seed (bcrypt hashed passwords)

-- SCHEMA SFT

CREATE TABLE IF NOT EXISTS p1_utenti (
  id_utente INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL,
  cognome VARCHAR(50) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  ruolo ENUM('passeggero','amministrazione','esercizio') NOT NULL DEFAULT 'passeggero'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_stazioni (
  id_stazione INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  km_progressivo DECIMAL(6,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_treni (
  id_treno INT AUTO_INCREMENT PRIMARY KEY,
  codice VARCHAR(20) NOT NULL UNIQUE,
  velocita_media DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  posti_totali INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_materiale_rotabile (
  id_mezzo INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('Locomotiva','Carrozza','Automotrice','Bagagliaio') NOT NULL,
  modello VARCHAR(50) NOT NULL,
  posti INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_treni_mezzi (
  id_treno INT NOT NULL,
  id_mezzo INT NOT NULL,
  quantita INT NOT NULL DEFAULT 1,
  PRIMARY KEY (id_treno,id_mezzo),
  CONSTRAINT fk_tm_treno FOREIGN KEY (id_treno) REFERENCES p1_treni(id_treno) ON DELETE RESTRICT ON UPDATE CASCADE,
CONSTRAINT fk_tm_mezzo FOREIGN KEY (id_mezzo) REFERENCES p1_materiale_rotabile(id_mezzo) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_tratte (
  id_tratta INT AUTO_INCREMENT PRIMARY KEY,
  id_stazione_partenza INT NOT NULL,
  id_stazione_arrivo INT NOT NULL,
  distanza_km DECIMAL(6,3) NOT NULL,
  CONSTRAINT fk_tratta_sp FOREIGN KEY (id_stazione_partenza) REFERENCES p1_stazioni(id_stazione),
  CONSTRAINT fk_tratta_sa FOREIGN KEY (id_stazione_arrivo) REFERENCES p1_stazioni(id_stazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_corse (
  id_corsa INT AUTO_INCREMENT PRIMARY KEY,
  id_treno INT NOT NULL,
  id_tratta INT NOT NULL,
  data DATE NOT NULL,
  ora_partenza TIME NOT NULL,
  ora_arrivo TIME NOT NULL,
  cancellata TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_corsa_treno FOREIGN KEY (id_treno) REFERENCES p1_treni(id_treno),
  CONSTRAINT fk_corsa_tratta FOREIGN KEY (id_tratta) REFERENCES p1_tratte(id_tratta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_biglietti (
  id_biglietto INT AUTO_INCREMENT PRIMARY KEY,
  id_utente INT NOT NULL,
  id_corsa INT NOT NULL,
  posto INT NOT NULL,
  prezzo DECIMAL(8,2) NOT NULL,
  pagato tinyint(1) NOT NULL DEFAULT '0',
  CONSTRAINT fk_bigl_utente FOREIGN KEY (id_utente) REFERENCES p1_utenti(id_utente),
  CONSTRAINT fk_bigl_corsa FOREIGN KEY (id_corsa) REFERENCES p1_corse(id_corsa),
  UNIQUE KEY uq_posto (id_corsa, posto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_richieste_admin (
  id_richiesta INT AUTO_INCREMENT PRIMARY KEY,
  data_richiesta DATETIME NOT NULL,
  tipo VARCHAR(30) NOT NULL,
  note VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_richieste_treni (
  id_richiesta INT AUTO_INCREMENT PRIMARY KEY,
  id_admin INT NOT NULL,
  messaggio TEXT NOT NULL,
  risposta TEXT DEFAULT NULL,
  stato ENUM('In attesa','Approvata','Rifiutata') DEFAULT 'In attesa',
  creata_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_admin) REFERENCES p1_utenti(id_utente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS p1_pagamenti (
  id_pagamento INT AUTO_INCREMENT PRIMARY KEY,
  id_corsa INT NOT NULL,
  importo DECIMAL(10,2) NOT NULL,
  valuta CHAR(3) NOT NULL DEFAULT 'EUR',
  provider VARCHAR(50) NOT NULL,
  provider_ref VARCHAR(100) NOT NULL,
  stato ENUM('CREATED','PENDING','SUCCEEDED','FAILED','CANCELLED') NOT NULL DEFAULT 'CREATED',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_pagamento_corsa FOREIGN KEY (id_corsa) REFERENCES p1_corse(id_corsa),
  UNIQUE KEY uq_provider_ref (provider, provider_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- SAFE RESEED p1_utenti (gestisce foreign keys)
SET @OLD_FK_CHECKS=@@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS=0;

-- Pulisce prima tabelle dipendenti
DELETE FROM p1_biglietti;

-- Pulisce tabella utenti e resetta interfaccia di autorizzazione
DELETE FROM p1_utenti;
ALTER TABLE p1_utenti AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=@OLD_FK_CHECKS;

-- SAFE RESEED p1_utenti (PLAIN PASSWORDS)
SET @OLD_FK_CHECKS=@@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM p1_biglietti;
DELETE FROM p1_utenti;
ALTER TABLE p1_utenti AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=@OLD_FK_CHECKS;

-- SEED SFT

INSERT INTO p1_utenti (nome,cognome,email,password,ruolo) VALUES
('Admin','', 'admin@sft.it',      '$2y$10$m8lRirpwUDkfLJvUnmSDpunF7JxtoQpWrEbFh8n7NnVP1OsfATTFe','amministrazione'),
('Responsabile','', 'responsabile@sft.it','$2y$10$f2dQ.uVebKtXLpYl37vp6eu.JtJ6hyqmiMlDghfqgbew2cUY3mZFy','esercizio'),
('Utente','', 'utente@example.com','$2y$10$3v.RFhr2aJ0w883VJC//0u4XPnYj0SWl06e4c3OvlWH11zv5nEPNm','passeggero');

INSERT INTO p1_stazioni (nome,km_progressivo) VALUES
('Torre Spaventa',0.000),
('Prato Terra',2.700),
('Rocca Pietrosa',7.580),
('Villa Pietrosa',12.680),
('Villa Santa Maria',16.900),
('Pietra Santa Maria',23.950),
('Castro Marino',31.500),
('Porto Spigola',39.500),
('Porto San Felice',46.000),
('Villa San Felice',54.680);

INSERT INTO p1_tratte (id_stazione_partenza,id_stazione_arrivo,distanza_km) VALUES
(1,10,54.680),
(1,5,16.900),
(5,10,37.780),
(3,7,23.920),
(2,4,9.980);

INSERT INTO p1_materiale_rotabile (tipo,modello,posti) VALUES
('Carrozza','B1 (1928)',36),
('Carrozza','B2 (1928)',36),
('Carrozza','B3 (1928)',36),
('Carrozza','C6 (1930)',48),
('Carrozza','C9 (1930)',48),
('Carrozza','C12 (1952)',52),
('Bagagliaio','CD1 (1910)',12),
('Bagagliaio','CD2 (1910)',12),
('Automotrice','AN56.2',56),
('Automotrice','AN56.4',56),
('Locomotiva','SFT3 Cavour',0),
('Locomotiva','SFT4 Vittorio Emanuele',0),
('Locomotiva','SFT6 Garibaldi',0);

INSERT INTO p1_treni (codice,velocita_media,posti_totali) VALUES
('T100',50.0,0),
('T200',50.0,0);

INSERT INTO p1_treni_mezzi (id_treno,id_mezzo,quantita) VALUES
(1,11,1),(1,1,1),(1,4,1),(1,6,1),
(2,9,1);

UPDATE p1_treni SET posti_totali= (SELECT COALESCE(SUM(m.posti*tm.quantita),0) FROM p1_treni_mezzi tm JOIN p1_materiale_rotabile m ON m.id_mezzo=tm.id_mezzo WHERE tm.id_treno=p1_treni.id_treno);

-- PATCH vincoli treni:
-- 1) Evita che un mezzo possa stare su più treni
ALTER TABLE p1_treni_mezzi
  ADD UNIQUE KEY u_mezzo_unico (id_mezzo);

-- 2) Indice utile per controlli "treno in corsa attiva"
CREATE INDEX idx_corse_attivita
  ON p1_corse (id_treno, cancellata, data, ora_partenza, ora_arrivo);

-- 3) Soft-delete treni
ALTER TABLE p1_treni
  ADD COLUMN disattivo TINYINT(1) NOT NULL DEFAULT 0 AFTER posti_totali;