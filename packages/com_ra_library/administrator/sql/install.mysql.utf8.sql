

CREATE TABLE IF NOT EXISTS `#__ra_library_displays` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`asset_id` int NOT NULL DEFAULT 0,
`state` TINYINT(1)  NULL  DEFAULT 1,
`ordering` INT(11)  NULL  DEFAULT 0,
`checked_out` INT(11)  UNSIGNED,
`checked_out_time` DATETIME NULL  DEFAULT NULL ,
`created_by` INT(11)  NULL  DEFAULT 0,
`modified_by` INT(11)  NULL  DEFAULT 0,
`title` VARCHAR(255)  NOT NULL ,
`displayoption` VARCHAR(255)  DEFAULT "none" ,
`options` MEDIUMTEXT NULL  DEFAULT "",
PRIMARY KEY (`id`)
,KEY `idx_state` (`state`)
,KEY `idx_checked_out` (`checked_out`)
,KEY `idx_created_by` (`created_by`)
,KEY `idx_modified_by` (`modified_by`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;


INSERT INTO `#__content_types` (`type_title`, `type_alias`, `table`, `rules`, `field_mappings`, `content_history_options`)
SELECT * FROM ( SELECT 'Library display','com_ra_library.librarydisplay','{"special":{"dbtable":"#__ra_library_displays","key":"id","type":"LibrarydisplayTable","prefix":"Ra_library\\Component\\Ra_library\\Administrator\\Table\\"}}', CASE 
                                    WHEN 'rules' is null THEN ''
                                    ELSE ''
                                    END as rules, CASE 
                                    WHEN 'field_mappings' is null THEN ''
                                    ELSE ''
                                    END as field_mappings, '{"formFile":"administrator\/components\/com_ra_library\/forms\/librarydisplay.xml", "hideFields":["checked_out","checked_out_time","params","language"], "ignoreChanges":["modified_by", "modified", "checked_out", "checked_out_time"], "convertToInt":["publish_up", "publish_down"], "displayLookup":[{"sourceColumn":"catid","targetTable":"#__categories","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"group_id","targetTable":"#__usergroups","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"created_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"access","targetTable":"#__viewlevels","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"modified_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"}]}') AS tmp
WHERE NOT EXISTS (
	SELECT type_alias FROM `#__content_types` WHERE (`type_alias` = 'com_ra_library.librarydisplay')
) LIMIT 1;

INSERT INTO `#__mail_templates` 
(`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) 
VALUES 
('com_ra_library.upload_notification', 'com_ra_library', '', 'COM_RA_LIBRARY_MAIL_UPLOAD_NOTIFICATION_SUBJECT', 'COM_RA_LIBRARY_MAIL_UPLOAD_NOTIFICATION_BODY', 'COM_RA_LIBRARY_MAIL_UPLOAD_NOTIFICATION_BODY_HTML', '', '{"tags":["sitename","filename","filepath","username"]}')
ON DUPLICATE KEY UPDATE 
`extension` = VALUES(`extension`);



-- Past Walks / Routes feature - consolidated schema (originally built up
-- across sql/updates/mysql/1.0.3.sql through 1.0.10.sql, including a table
-- rename from ra_library_past_walks to ra_library_routes along the way).
-- Folded into a single install-time definition here, in its final form,
-- because that incremental history is only meaningful for a database that
-- was already on an older version of this component - for a genuinely
-- fresh install, Joomla runs ONLY this file, never sql/updates/mysql/*.sql,
-- so those tables would otherwise not exist at all on a new install. The
-- table-rename step also permanently confuses Joomla's Database health
-- check screen (System > Manage > Database), which flags the pre-rename
-- file's CREATE TABLE target as "missing" forever after - folding
-- everything into one definition here avoids that too.

CREATE TABLE IF NOT EXISTS `#__ra_library_routes` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`record_type` VARCHAR(20) NOT NULL DEFAULT 'pastwalk',
`walks_manager_id` VARCHAR(50) NULL DEFAULT NULL,
`needs_review` TINYINT(1) NOT NULL DEFAULT 0,
`walk_date` DATE NULL DEFAULT NULL,
`title` VARCHAR(255) NOT NULL DEFAULT "",
`walk_leader` VARCHAR(255) NULL DEFAULT "",
`description` MEDIUMTEXT NULL,
`route_guide` MEDIUMTEXT NULL,
`template_intro_override` MEDIUMTEXT NULL,
`template_more_override` MEDIUMTEXT NULL,
`distance_km` DECIMAL(6,2) NULL DEFAULT NULL,
`national_grade` VARCHAR(50) NULL DEFAULT "",
`gpx_path` VARCHAR(500) NULL DEFAULT "",
`catid` INT(11) UNSIGNED NOT NULL DEFAULT 0,
`start_latitude` DECIMAL(9,6) NULL DEFAULT NULL,
`start_longitude` DECIMAL(9,6) NULL DEFAULT NULL,
`start_grid_reference` VARCHAR(20) NULL DEFAULT "",
`state` TINYINT(1) NOT NULL DEFAULT 0,
`ordering` INT(11) NULL DEFAULT 0,
`checked_out` INT(11) UNSIGNED,
`checked_out_time` DATETIME NULL DEFAULT NULL,
`created` DATETIME NULL DEFAULT NULL,
`created_by` INT(11) NULL DEFAULT 0,
`modified` DATETIME NULL DEFAULT NULL,
`modified_by` INT(11) NULL DEFAULT 0,
PRIMARY KEY (`id`)
,UNIQUE KEY `idx_walks_manager_id` (`walks_manager_id`)
,KEY `idx_state` (`state`)
,KEY `idx_walk_date` (`walk_date`)
,KEY `idx_catid` (`catid`)
,KEY `idx_checked_out` (`checked_out`)
,KEY `idx_created_by` (`created_by`)
,KEY `idx_modified_by` (`modified_by`)
,KEY `idx_record_type` (`record_type`)
,KEY `idx_needs_review` (`needs_review`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__content_types` (`type_title`, `type_alias`, `table`, `rules`, `field_mappings`, `content_history_options`)
SELECT * FROM ( SELECT 'Past walk','com_ra_library.pastwalk','{"special":{"dbtable":"#__ra_library_routes","key":"id","type":"PastwalkTable","prefix":"Ramblers\\Component\\Ra_library\\Administrator\\Table\\"}}', CASE
                                    WHEN 'rules' is null THEN ''
                                    ELSE ''
                                    END as rules, CASE
                                    WHEN 'field_mappings' is null THEN ''
                                    ELSE ''
                                    END as field_mappings, '{"formFile":"administrator\/components\/com_ra_library\/forms\/pastwalk.xml", "hideFields":["checked_out","checked_out_time"], "ignoreChanges":["modified_by", "modified", "checked_out", "checked_out_time"], "convertToInt":[], "displayLookup":[{"sourceColumn":"catid","targetTable":"#__categories","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"created_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"modified_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"}]}') AS tmp
WHERE NOT EXISTS (
	SELECT type_alias FROM `#__content_types` WHERE (`type_alias` = 'com_ra_library.pastwalk')
) LIMIT 1;

INSERT INTO `#__content_types` (`type_title`, `type_alias`, `table`, `rules`, `field_mappings`, `content_history_options`)
SELECT * FROM ( SELECT 'Route','com_ra_library.route','{"special":{"dbtable":"#__ra_library_routes","key":"id","type":"PastwalkTable","prefix":"Ramblers\\Component\\Ra_library\\Administrator\\Table\\"}}', CASE
                                    WHEN 'rules' is null THEN ''
                                    ELSE ''
                                    END as rules, CASE
                                    WHEN 'field_mappings' is null THEN ''
                                    ELSE ''
                                    END as field_mappings, '{"formFile":"administrator\/components\/com_ra_library\/forms\/route.xml", "hideFields":["checked_out","checked_out_time"], "ignoreChanges":["modified_by", "modified", "checked_out", "checked_out_time"], "convertToInt":[], "displayLookup":[{"sourceColumn":"catid","targetTable":"#__categories","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"created_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"modified_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"}]}') AS tmp
WHERE NOT EXISTS (
	SELECT type_alias FROM `#__content_types` WHERE (`type_alias` = 'com_ra_library.route')
) LIMIT 1;

CREATE TABLE IF NOT EXISTS `#__ra_library_images` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`record_id` int(11) UNSIGNED NOT NULL,
`caption` VARCHAR(255) NULL DEFAULT "",
`description` TEXT NULL,
`thumbnail_path` VARCHAR(500) NULL DEFAULT "",
`large_path` VARCHAR(500) NULL DEFAULT "",
`grid_reference` VARCHAR(20) NULL DEFAULT "",
`latitude` DECIMAL(9,6) NULL DEFAULT NULL,
`longitude` DECIMAL(9,6) NULL DEFAULT NULL,
`featured` TINYINT(1) NOT NULL DEFAULT 0,
`ordering` INT(11) NULL DEFAULT 0,
PRIMARY KEY (`id`)
,KEY `idx_record_id` (`record_id`)
,KEY `idx_featured` (`featured`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_library_attachments` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`record_id` int(11) UNSIGNED NOT NULL,
`attachment_type` VARCHAR(20) NOT NULL DEFAULT 'document',
`title` VARCHAR(255) NOT NULL DEFAULT "",
`file_path` VARCHAR(500) NOT NULL DEFAULT "",
`file_size` INT(11) UNSIGNED NULL DEFAULT NULL,
`ordering` INT(11) NULL DEFAULT 0,
`featured` TINYINT(1) NOT NULL DEFAULT 0,
PRIMARY KEY (`id`)
,KEY `idx_record_id` (`record_id`)
,KEY `idx_attachment_type` (`attachment_type`)
,KEY `idx_featured` (`featured`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_library_route_points` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`record_id` int(11) UNSIGNED NOT NULL,
`title` VARCHAR(255) NOT NULL DEFAULT "",
`description` MEDIUMTEXT NULL,
`grid_reference` VARCHAR(20) NULL DEFAULT "",
`latitude` DECIMAL(9,6) NULL DEFAULT NULL,
`longitude` DECIMAL(9,6) NULL DEFAULT NULL,
`ordering` INT(11) NULL DEFAULT 0,
PRIMARY KEY (`id`)
,KEY `idx_record_id` (`record_id`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;
