-- ================================================
-- Newsletter Database Setup Script
-- ================================================
-- This database handles: Newsletter archive
-- with uploaded .eml / .msg files
-- ================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
START TRANSACTION;
SET time_zone = "+00:00";

-- ================================================
-- TABLE: newsletters
-- ================================================
CREATE TABLE IF NOT EXISTS `newsletters` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)   NOT NULL,
    `month_year`  VARCHAR(20)    DEFAULT NULL    COMMENT 'Versandmonat/-jahr, z. B. März 2025',
    `file_path`   VARCHAR(500)   NOT NULL        COMMENT 'Gespeicherter Dateiname im Upload-Verzeichnis',
    `uploaded_by` INT UNSIGNED   DEFAULT NULL    COMMENT 'Intranet-Benutzer-ID des Hochladenden',
    `created_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_month_year`  (`month_year`),
    KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Internes Newsletter-Archiv (.eml / .msg Dateien)';

COMMIT;
