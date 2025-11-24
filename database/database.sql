--
-- Struktur Tabel untuk `Users` (Pengguna Admin)
--

CREATE TABLE `Users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `role` VARCHAR(50) NOT NULL DEFAULT 'Editor',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Struktur Tabel untuk `Causes` (Tujuan Amal)
--

CREATE TABLE `Causes` (
  `cause_id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `goal_amount` DECIMAL(10, 2) NOT NULL,
  `raised_amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `image_url` VARCHAR(255) DEFAULT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`cause_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Struktur Tabel untuk `Donations` (Donasi)
--

CREATE TABLE `Donations` (
  `donation_id` INT(11) NOT NULL AUTO_INCREMENT,
  `cause_id` INT(11) NULL,
  `donor_name` VARCHAR(100) DEFAULT 'Anonim',
  `donor_email` VARCHAR(100) DEFAULT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `donation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_method` VARCHAR(50) NOT NULL,
  `frequency` VARCHAR(50) DEFAULT NULL,
  `is_anonymous` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`donation_id`),
  FOREIGN KEY (`cause_id`) REFERENCES `Causes`(`cause_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Struktur Tabel untuk `Volunteers` (Relawan)
--

CREATE TABLE `Volunteers` (
  `volunteer_id` INT(11) NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone_number` VARCHAR(20) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `registration_date` DATE NOT NULL DEFAULT CURRENT_DATE,
  `is_approved` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`volunteer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Struktur Tabel untuk `News` (Berita/Artikel Blog)
--

CREATE TABLE `News` (
  `news_id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `author` VARCHAR(100) DEFAULT NULL,
  `publish_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `image_url` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`news_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Struktur Tabel untuk `ContactMessages` (Pesan Kontak)
--

CREATE TABLE `ContactMessages` (
  `message_id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(255) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `sent_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Struktur Tabel untuk `Subscribers` (Pelanggan Buletin)
--

CREATE TABLE `Subscribers` (
  `subscriber_id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `subscription_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`subscriber_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;