SELECT * FROM `form_sections_chek` WHERE admitted = 1;

UPDATE form_sections_chek 
SET 
admitted = 0, 
enrolled = 0, 
programme_awarded = NULL, 
programme_duration = NULL, 
level_admitted = NULL, 
shortlisted = 0, 
stream_admitted = NULL 
WHERE admitted  = 1;

ALTER TABLE acceptance_receipts ADD COLUMN `status` TINYINT(1) DEFAULT 0 AFTER `app_login`;
ALTER TABLE form_sections_chek ADD COLUMN `stream_admitted` VARCHAR(30) DEFAULT NULL AFTER `level_admitted`;
ALTER TABLE form_sections_chek ADD COLUMN `shortlisted` TINYINT(1) DEFAULT 0 AFTER `level_admitted`;
ALTER TABLE form_sections_chek 
ADD COLUMN `first_prog_qualified` TINYINT(1) DEFAULT 0 AFTER `emailed_letter`,
ADD COLUMN `second_prog_qualified` TINYINT(1) DEFAULT 0 AFTER `first_prog_qualified`;

ALTER TABLE broadsheets 
DROP COLUMN `any_one_core_passed`,
DROP COLUMN `total_core_score`,
DROP COLUMN `any_three_elective_passed`,
DROP COLUMN `any_two_elective_subjects`,
ADD COLUMN `mode` VARCHAR(20) AFTER `program_id`,
ADD COLUMN `required_core_subjects` TEXT AFTER `required_core_passed`,
ADD COLUMN `total_core_score` INT AFTER `required_core_subjects`,
ADD COLUMN `required_elective_passed` INT AFTER `required_core_subjects`,
ADD COLUMN `required_elective_subjects` TEXT AFTER `required_elective_passed`,
ADD COLUMN `any_elective_subjects` TEXT AFTER `required_elective_subjects`;

/*NOT DONE YET ON LIVE SERVER MAIN DB*/
ALTER TABLE `department` 
ADD COLUMN `hod` VARCHAR(10) AFTER `name`,
ADD CONSTRAINT `fk_department_hod` FOREIGN KEY (`hod`) REFERENCES `staff`(`number`);

ALTER TABLE `department` DROP FOREIGN KEY `fk_department_hod`; 
ALTER TABLE `department` ADD CONSTRAINT `fk_department_hod` FOREIGN KEY (`hod`) REFERENCES `staff`(`number`) ON DELETE SET NULL ON UPDATE CASCADE; 

ALTER TABLE `course` CHANGE `credits` `credit_hours` INT NOT NULL;
ALTER TABLE `course` ADD COLUMN `contact_hours` INT AFTER `credit_hours`;

CREATE TABLE course_index_code (
    `code` VARCHAR(4) PRIMARY KEY,
    `type` VARCHAR(15) NOT NULL
);
INSERT INTO course_index_code (`code`, `type`) VALUES 
('BNS', 'BSC'), ('BME', 'BSC'), ('BMT', 'BSC'), ('BCE', 'BSC'), ('BCS', 'BSC'), 
('BEE', 'BSC'), ('BIT', 'BSC'), ('BPS', 'BSC'), ('BLG', 'BSC'), ('BLM', 'BSC');

ALTER TABLE `section` CHANGE `credits` `credit_hours` INT(11) NOT NULL; 


CREATE TABLE `fee_structure_type` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `archived` TINYINT(1) DEFAULT 0
);
CREATE INDEX fee_structure_type_name_idx1 ON `fee_structure_type` (`name`);
CREATE INDEX fee_structure_type_created_at_idx1 ON `fee_structure_type` (`created_at`);
CREATE INDEX fee_structure_type_updated_at_idx1 ON `fee_structure_type` (`updated_at`);
CREATE INDEX fee_structure_type_archived_idx1 ON `fee_structure_type` (`archived`);
INSERT INTO `fee_structure_type` (`name`) VALUES ('fresher'), ('continue'), ('topup');

CREATE TABLE `fee_structure_category` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `archived` TINYINT(1) DEFAULT 0
);
CREATE INDEX fee_structure_category_name_idx1 ON `fee_structure_category` (`name`);
CREATE INDEX fee_structure_category_created_at_idx1 ON `fee_structure_category` (`created_at`);
CREATE INDEX fee_structure_category_updated_at_idx1 ON `fee_structure_category` (`updated_at`);
CREATE INDEX fee_structure_category_archived_idx1 ON `fee_structure_category` (`archived`);
INSERT INTO `fee_structure_category` (`name`) VALUES ('regular'), ('weekend');

CREATE TABLE `fee_item` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `value` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `archived` TINYINT(1) DEFAULT 0
);
CREATE INDEX fee_item_name_idx1 ON `fee_item` (`name`);
CREATE INDEX fee_item_member_value_idx1 ON `fee_item` (`value`);
CREATE INDEX fee_item_created_at_idx1 ON `fee_item` (`created_at`);
CREATE INDEX fee_item_updated_at_idx1 ON `fee_item` (`updated_at`);
CREATE INDEX fee_item_archived_idx1 ON `fee_item` (`archived`);

CREATE TABLE `fee_structure` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `currency` VARCHAR(5) DEFAULT 'USD',
    `fk_program_id` INT NOT NULL,
    `type` VARCHAR(15) NOT NULL,
    `category` VARCHAR(15) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `member_amount` DECIMAL(10,2) NOT NULL,
    `non_member_amount` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `archived` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`fk_program_id`) REFERENCES `programs`(`id`)
);
ALTER TABLE `fee_structure` 
ADD COLUMN `member_amount` DECIMAL(10,2) AFTER `name`,
ADD COLUMN `non_member_amount` DECIMAL(10,2) AFTER `member_amount`;
CREATE INDEX fee_structure_type_idx1 ON `fee_structure` (`type`);
CREATE INDEX fee_structure_category_idx1 ON `fee_structure` (`category`);
CREATE INDEX fee_structure_name_idx1 ON `fee_structure` (`name`);
CREATE INDEX fee_structure_member_amount_idx1 ON `fee_structure` (`member_amount`);
CREATE INDEX fee_structure_non_member_amount_idx1 ON `fee_structure` (`non_member_amount`);
CREATE INDEX fee_structure_created_at_idx1 ON `fee_structure` (`created_at`);
CREATE INDEX fee_structure_updated_at_idx1 ON `fee_structure` (`updated_at`);
CREATE INDEX fee_structure_archived_idx1 ON `fee_structure` (`archived`);

ALTER TABLE `fee_structure` ADD COLUMN `file` VARCHAR(255) DEFAULT NULL AFTER `non_member_amount`;
CREATE INDEX fee_structure_item_file_idx1 ON `fee_structure_item` (`file`);

CREATE TABLE `fee_structure_item` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `currency` VARCHAR(5) DEFAULT 'USD',
    `fk_fee_structure` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `member_amount` DECIMAL(10,2) NOT NULL,
    `non_member_amount` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `archived` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`fk_fee_structure`) REFERENCES `fee_structure`(`id`)
);
CREATE INDEX fee_structure_item_name_idx1 ON `fee_structure_item` (`name`);
CREATE INDEX fee_structure_item_member_amount_idx1 ON `fee_structure_item` (`member_amount`);
CREATE INDEX fee_structure_item_non_member_amount_idx1 ON `fee_structure_item` (`non_member_amount`);
CREATE INDEX fee_structure_item_created_at_idx1 ON `fee_structure_item` (`created_at`);
CREATE INDEX fee_structure_item_updated_at_idx1 ON `fee_structure_item` (`updated_at`);
CREATE INDEX fee_structure_item_archived_idx1 ON `fee_structure_item` (`archived`);

