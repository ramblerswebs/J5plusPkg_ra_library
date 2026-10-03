DROP TABLE IF EXISTS `#__ra_email_log`;
DROP TABLE IF EXISTS `#__ra_library_displays`;
-- Remove mail spool table
DROP TABLE IF EXISTS `#__ra_mail_spool`;

-- Past Walks / Routes tables (see install.mysql.utf8.sql)
DROP TABLE IF EXISTS `#__ra_library_routes`;
DROP TABLE IF EXISTS `#__ra_library_images`;
DROP TABLE IF EXISTS `#__ra_library_attachments`;
DROP TABLE IF EXISTS `#__ra_library_route_points`;
-- Pre-rename leftover - see 1.0.11.sql for why this can exist
DROP TABLE IF EXISTS `#__ra_library_past_walks`;