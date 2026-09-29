<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Ra_library
 * @author     Chris Vaughan <ruby.tuesday@ramblers-webs.org.uk>
 * @copyright  2026 Chris Vaughan
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_library\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Input\Files as FilesInput;

/**
 * Handles the GPX-track and PDF-document subforms shared by Past Walks and
 * Routes - one shared table (#__ra_library_attachments), distinguished by
 * attachment_type ('gpx' or 'document'), so this one helper serves both
 * subform fields rather than duplicating near-identical code twice.
 *
 * Deliberately much simpler than ImageGalleryHelper - these files are stored
 * as-is (no resizing/derived copies), just validated and moved into the
 * record's existing per-record storage folder.
 *
 * @since  1.0.0
 */
class AttachmentHelper
{
    /**
     * @since 1.0.0
     */
    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB

    /**
     * Fetch the attachment rows of one type for a record, in display order.
     *
     * @param   int     $recordId        The past walk / route id.
     * @param   string  $attachmentType  'gpx' or 'document'.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getAttachmentsForRecord(int $recordId, string $attachmentType): array
    {
        if ($recordId <= 0) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ra_library_attachments'))
            ->where($db->quoteName('record_id') . ' = ' . $recordId)
            ->where($db->quoteName('attachment_type') . ' = ' . $db->quote($attachmentType))
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Save the submitted subform for one attachment type: updates existing
     * rows, inserts new ones (processing any uploaded file), and removes
     * rows/files that were dropped from the submission. Mirrors
     * ImageGalleryHelper::saveImages() row-matching logic exactly, including
     * the two gotchas already debugged there:
     *  - subform rows must be matched to their $_FILES entry by the
     *    ORIGINAL submitted array key (e.g. "gpx0"), not a re-indexed
     *    integer, since that's how Joomla's repeatable-table subform
     *    actually names each row's controls.
     *  - the row's hidden "id" field must use filter="int" in the row XML,
     *    not filter="unset" (which silently nulls it during Form::filter()
     *    and makes every existing row look "new" on the next save).
     *
     * @param   int     $recordId        The past walk / route id (already saved).
     * @param   string  $recordType      'pastwalk' or 'route' - determines the storage subfolder.
     * @param   string  $attachmentType  'gpx' or 'document'.
     * @param   array   $submittedData   The submitted subform rows.
     *
     * @return  true|string  True on success, or a user-facing error message string on failure.
     *
     * @since   1.0.0
     */
    public static function saveAttachments(int $recordId, string $recordType, string $attachmentType, array $submittedData)
    {
        if ($recordId <= 0) {
            return true;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $existingById = [];

        foreach (self::getAttachmentsForRecord($recordId, $attachmentType) as $row) {
            $existingById[(int) $row->id] = $row;
        }

        $overwrittenFiles = [];

        $filesInput = new FilesInput();
        $allFiles = $filesInput->get('jform', [], 'raw');
        $fieldName = $attachmentType === 'gpx' ? 'gpx' : 'documents';
        $uploadFieldName = $attachmentType === 'gpx' ? 'gpx_upload' : 'document_upload';
        $rowFiles = (\is_array($allFiles) && isset($allFiles[$fieldName]) && \is_array($allFiles[$fieldName])) ? $allFiles[$fieldName] : [];

        $keptIds = [];
        $ordering = 1;
        $displayIndex = 0;
        $featuredAssigned = false;

        foreach ($submittedData as $rowKey => $row) {
            if (!\is_array($row)) {
                continue;
            }

            $displayIndex++;

            $id = (int) ($row['id'] ?? 0);
            $existingRow = ($id && isset($existingById[$id])) ? $existingById[$id] : null;

            $uploadedFile = $rowFiles[$rowKey][$uploadFieldName] ?? null;
            $hasNewUpload = \is_array($uploadedFile)
                && !empty($uploadedFile['tmp_name'])
                && (int) ($uploadedFile['error'] ?? \UPLOAD_ERR_NO_FILE) === \UPLOAD_ERR_OK;

            if (!$existingRow && !$hasNewUpload) {
                // A blank trailing row from the repeatable-table UI - skip.
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $filePath = $existingRow->file_path ?? '';
            $fileSize = $existingRow->file_size ?? null;

            if ($hasNewUpload) {
                $processed = self::processUpload($uploadedFile, $recordId, $recordType, $attachmentType);

                if ($processed === false) {
                    return Text::sprintf(
                        $attachmentType === 'gpx' ? 'COM_RA_LIBRARY_GPX_UPLOAD_ERROR' : 'COM_RA_LIBRARY_DOCUMENT_UPLOAD_ERROR',
                        $title !== '' ? $title : ('#' . $displayIndex)
                    );
                }

                $newFilePath = $processed['file_path'];

                // Replacing an existing file - remove the one it's
                // superseding, but only if the new upload didn't land on
                // exactly the same path. That happens when the original
                // filename is re-used (e.g. re-uploading a corrected
                // version of "walk.gpx" onto the same row) - processUpload()
                // has already overwritten it at that point, so deleting
                // "the old file" here would just delete the brand new one.
                if ($filePath !== '' && $filePath !== $newFilePath) {
                    self::deleteFileQuietly($filePath);
                }

                $filePath = $newFilePath;
                $fileSize = $processed['file_size'];

                if ($title === '') {
                    // No title given - default it to the file's own name
                    // (without extension) rather than leaving it blank.
                    $title = pathinfo($processed['file_name'], \PATHINFO_FILENAME);
                }

                if (!empty($processed['overwritten'])) {
                    $overwrittenFiles[] = $processed['file_name'];
                }
            }

            if ($filePath === '') {
                // No file at all, old or new - nothing sensible to store.
                continue;
            }

            // Only GPX rows can be featured (documents don't have a map to
            // embed) - enforce "only one featured per record" the same way
            // ImageGalleryHelper::saveImages() does, by keeping the first
            // row flagged featured in submission order and forcing any
            // others back to 0, rather than needing JS to keep checkboxes
            // in sync client-side.
            $wantsFeatured = $attachmentType === 'gpx' && !empty($row['featured']);
            $featured = ($wantsFeatured && !$featuredAssigned) ? 1 : 0;

            if ($featured) {
                $featuredAssigned = true;
            }

            // Per-file download override (gpxrow.xml only - document rows
            // have no such field, so $row['download_override'] is simply
            // absent for them and this stays blank/"use global setting").
            $downloadOverride = (string) ($row['download_override'] ?? '');

            if (!\in_array($downloadOverride, ['None', 'Users', 'Public'], true)) {
                $downloadOverride = '';
            }

            $columns = [
                'record_id' => $recordId,
                'attachment_type' => $attachmentType,
                'title' => $title,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'featured' => $featured,
                'ordering' => $ordering,
                'download_override' => $downloadOverride,
            ];

            if ($existingRow) {
                $query = $db->getQuery(true)->update($db->quoteName('#__ra_library_attachments'));

                foreach ($columns as $column => $value) {
                    $query->set($db->quoteName($column) . ' = ' . ($value === null ? 'NULL' : $db->quote($value)));
                }

                $query->where($db->quoteName('id') . ' = ' . (int) $existingRow->id);
                $db->setQuery($query)->execute();
                $keptIds[] = (int) $existingRow->id;
            } else {
                $query = $db->getQuery(true)->insert($db->quoteName('#__ra_library_attachments'));
                $query->columns(array_map(static fn ($col) => $db->quoteName($col), array_keys($columns)));
                $query->values(implode(',', array_map(
                    static fn ($value) => $value === null ? 'NULL' : $db->quote($value),
                    array_values($columns)
                )));
                $db->setQuery($query)->execute();
                $keptIds[] = (int) $db->insertid();
            }

            $ordering++;
        }

        // Anything that existed before but wasn't in this submission was
        // removed by the admin in the UI - delete its row and its file.
        foreach ($existingById as $id => $row) {
            if (!\in_array($id, $keptIds, true)) {
                self::deleteFileQuietly($row->file_path);

                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__ra_library_attachments'))
                    ->where($db->quoteName('id') . ' = ' . (int) $id);
                $db->setQuery($query)->execute();
            }
        }

        if (!empty($overwrittenFiles)) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf(
                    $attachmentType === 'gpx' ? 'COM_RA_LIBRARY_GPX_FILE_OVERWRITTEN' : 'COM_RA_LIBRARY_DOCUMENT_FILE_OVERWRITTEN',
                    implode(', ', array_unique($overwrittenFiles))
                ),
                'warning'
            );
        }

        return true;
    }

    /**
     * Remove all attachments (both gpx and document rows/files) for a
     * record - used when the parent past walk / route is permanently
     * deleted.
     *
     * @param   int  $recordId  The past walk / route id being deleted.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function deleteAttachmentsForRecord(int $recordId): void
    {
        if ($recordId <= 0) {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('file_path')
            ->from($db->quoteName('#__ra_library_attachments'))
            ->where($db->quoteName('record_id') . ' = ' . $recordId);
        $db->setQuery($query);

        foreach ($db->loadColumn() ?: [] as $filePath) {
            self::deleteFileQuietly($filePath);
        }

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ra_library_attachments'))
            ->where($db->quoteName('record_id') . ' = ' . $recordId);
        $db->setQuery($query)->execute();
    }

    /**
     * Validate and move one uploaded GPX/PDF file into the record's storage
     * folder (the same per-record folder the image gallery already uses).
     *
     * @param   array   $uploadedFile    One entry from Joomla\Input\Files::get() - name/type/tmp_name/error/size.
     * @param   int     $recordId        The past walk / route id.
     * @param   string  $recordType      'pastwalk' or 'route'.
     * @param   string  $attachmentType  'gpx' or 'document'.
     *
     * @return  array|false  ['file_path' => ..., 'file_size' => ...] (root-relative path), or false on failure.
     *
     * @since   1.0.0
     */
    private static function processUpload(array $uploadedFile, int $recordId, string $recordType, string $attachmentType)
    {
        $tmpName = $uploadedFile['tmp_name'] ?? '';

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return false;
        }

        $size = (int) ($uploadedFile['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            return false;
        }

        if ($attachmentType === 'gpx') {
            if (!self::looksLikeGpx($tmpName)) {
                return false;
            }

            $extension = 'gpx';
            $prefix = 'gpx';
        } else {
            if (!self::looksLikePdf($tmpName)) {
                return false;
            }

            $extension = 'pdf';
            $prefix = 'doc';
        }

        $subfolder = ($recordType === 'route') ? 'routes' : 'pastwalks';
        $relativeFolder = 'images/com_ra_library/' . $subfolder . '/' . $recordId;
        $absoluteFolder = JPATH_ROOT . '/' . $relativeFolder;

        if (!is_dir($absoluteFolder)) {
            try {
                Folder::create($absoluteFolder);
            } catch (\Exception $e) {
                return false;
            }
        }

        // Keep the file's own original name (sanitised) rather than a
        // generated one, per Chris - falls back to a generated name only
        // if the original has no usable name/extension at all (e.g. a
        // browser that submitted no filename).
        $safeName = File::makeSafe(basename((string) ($uploadedFile['name'] ?? '')));

        if ($safeName === '' || strpos($safeName, '.') === false) {
            $safeName = $prefix . '_' . substr(md5(uniqid((string) mt_rand(), true)), 0, 12) . '.' . $extension;
        }

        $relativePath = $relativeFolder . '/' . $safeName;
        $absolutePath = JPATH_ROOT . '/' . $relativePath;

        // Same name already present in this item's folder (e.g. a
        // different attachment row, or a re-import of the same file) -
        // overwrite it and flag that so the caller can tell the admin.
        // The stale destination is explicitly unlinked first rather than
        // relying on move_uploaded_file() to replace it - on Windows,
        // rename()-based moves (which move_uploaded_file() uses internally)
        // fail outright if the destination already exists.
        $overwritten = is_file($absolutePath);

        if ($overwritten) {
            @unlink($absolutePath);
        }

        // A plain move_uploaded_file() rather than Joomla\Filesystem\File::upload()
        // here - that method exists mainly for image-upload workflows with
        // built-in image-type checks that don't apply to GPX/PDF files, and
        // the GPX/PDF content has already been validated above by sniffing
        // its actual bytes.
        if (!@move_uploaded_file($tmpName, $absolutePath)) {
            return false;
        }

        @chmod($absolutePath, 0644);

        return [
            'file_path' => $relativePath,
            'file_size' => $size,
            'overwritten' => $overwritten,
            'file_name' => $safeName,
        ];
    }

    /**
     * Sniff a temp file's content for a GPX root element, rather than
     * trusting mime_content_type() (which frequently reports plain XML
     * files as text/xml or application/octet-stream depending on server
     * config, not a distinct "GPX" mime type).
     *
     * @param   string  $path  Filesystem path to the temp upload.
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    private static function looksLikeGpx(string $path): bool
    {
        $head = @file_get_contents($path, false, null, 0, 4096);

        if ($head === false) {
            return false;
        }

        return stripos($head, '<gpx') !== false;
    }

    /**
     * Sniff a temp file's content for the PDF magic bytes, rather than
     * relying solely on mime_content_type() (which needs the fileinfo
     * extension enabled to be reliable).
     *
     * @param   string  $path  Filesystem path to the temp upload.
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    private static function looksLikePdf(string $path): bool
    {
        $head = @file_get_contents($path, false, null, 0, 5);

        return $head === '%PDF-';
    }

    /**
     * Best-effort delete of a stored attachment file - failures are
     * swallowed (e.g. the file was already missing).
     *
     * @param   string  $relativePath  Root-relative path as stored in the DB.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private static function deleteFileQuietly(string $relativePath): void
    {
        if ($relativePath === '') {
            return;
        }

        $fullPath = JPATH_ROOT . '/' . ltrim($relativePath, '/');

        if (is_file($fullPath)) {
            try {
                File::delete($fullPath);
            } catch (\Exception $e) {
                // Ignore - best-effort cleanup only.
            }
        }
    }
}
