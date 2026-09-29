-- Per-file GPX download override - lets each attached GPX track (the "gpx"
-- subform on a Past Walk/Route, administrator/forms/gpxrow.xml) override
-- whether IT can be downloaded, rather than the whole record. Only GPX rows
-- ever set this (document rows leave it blank) - same idea as this table's
-- existing 'featured' flag. Blank (the default for every existing row)
-- means "use the site-wide setting" - see
-- ItemRenderer::resolveGpxDownloadPermission() and Components > Ra_library
-- > Options > Past Walks Layout / Routes Layout ->
-- pastwalk_gpx_download / route_gpx_download (each None/Users/Public).

ALTER TABLE `#__ra_library_attachments`
	ADD COLUMN `download_override` VARCHAR(10) NOT NULL DEFAULT '' AFTER `featured`;
