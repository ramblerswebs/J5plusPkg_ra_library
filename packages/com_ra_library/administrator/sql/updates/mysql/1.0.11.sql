-- The mail-spool feature (SpoolMailer, #__ra_mail_spool) that 1.0.2.sql
-- introduced for com_ra_library was later dropped in favour of the
-- separate, standalone pkg_ra_ramailspooler package - no code in this
-- component references #__ra_mail_spool any more. install.mysql.utf8.sql
-- was never updated to create this table (so a fresh install never gets
-- it), but any site that installed through 1.0.2.sql along the way is
-- left with the orphaned, unused table. Clean it up here so upgrading
-- sites end up in the same state as a fresh install.
DROP TABLE IF EXISTS `#__ra_mail_spool`;

-- Before this consolidation, install.mysql.utf8.sql's own CREATE TABLE
-- target was still the pre-rename #__ra_library_past_walks (the table
-- 1.0.4.sql later renamed to #__ra_library_routes). Joomla's System >
-- Manage > Database health check compares that declared target against
-- the live database, saw the renamed-away table as "missing", and
-- clicking its "Fix" button simply re-ran that CREATE TABLE IF NOT
-- EXISTS - recreating an empty, disconnected #__ra_library_past_walks
-- table alongside the real, in-use #__ra_library_routes. Nothing in this
-- component has ever read from or written to that table under its old
-- name once the rename happened, so if it exists at all today it is only
-- ever that empty leftover - safe to drop unconditionally.
DROP TABLE IF EXISTS `#__ra_library_past_walks`;