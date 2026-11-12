<?php

//              Part of the RA_library package

namespace Ramblers\Plugin\Content\Ra_library\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Ramblers\Component\Ra_library\Site\Helper\PluginHelper;

final class Ra_library extends CMSPlugin implements SubscriberInterface {

    public static function getSubscribedEvents(): array {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    public function onContentPrepare(ContentPrepareEvent $event): void {
        $context = $event->getContext();
        $item = $event->getItem();

        // Only render on the public site. Saving an article in the back end makes Smart Search
        // (com_finder.*) run the content plugins inside the administrator application, where
        // relative folder/file paths do not resolve and feeds/statistics files would be hit needlessly.
        if (!$this->getApplication()->isClient('site') || str_starts_with((string) $context, 'com_finder.') || !isset($item->text)) {
            return;
        }

        $text = $item->text;

        // Self-contained tags that carry their own settings, no Library Display needed:
        //   {ra_library:documents folder="images/minutes" types="pdf,docx" sort="asc"}
        //   {ra_library:gpx file="images/routes/walk.gpx" colour="#782327" download="Public"}
        // 'Specialist tags' (map compare, places) are off unless the plugin setting turns them on
        $text = PluginHelper::replaceInlineTags($text, (bool) $this->params->get('specialist_tags', 0));
        $item->text = $text;

        // Log that we are in onContentPrepare (for debugging)
        //   $logger = \Joomla\CMS\Factory::getLogger();
        //   $logger->info('Ra_library onContentPrepare fired for context: ' . $context);
        // Match {ra_library:VIEW:ID}
        if (!preg_match_all('#\{ra\_library\:([a-z0-9_]+)\:([0-9]+)\}#', $text, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $viewName = $match[1];
            $displayId = (int) $match[2];

            if ($displayId <= 0) {
                continue;
            }

            $display = $this->loadDisplay($displayId);

            if (!$display) {
                $message = "RA Library Display not found ID:" . $displayId;
                $html = "<p class='alert-error'><b>ERROR: " . $message . "</b></p>";
                $this->getApplication()->enqueueMessage($message, 'error');
            } elseif ((int) $display->state !== 1) {
                // exists, but is unpublished, archived or trashed, so it must not be shown
                $message = "RA Library Display is not published ID:" . $displayId;
                $html = "<p class='alert-error'><b>ERROR: " . $message . "</b></p>";
                $this->getApplication()->enqueueMessage($message, 'error');
            } else {
                $html = PluginHelper::renderView($viewName, $display);
            }

            $text = preg_replace(
                    '#\{ra\_library\:' . preg_quote($viewName, '#') . '\:' . $displayId . '\}#i',
                    $html,
                    $text,
                    1
            );
        }

        $item->text = $text;
    }

    private function loadDisplay($id) {


        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ra_library_displays'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
        return $db->setQuery($query)->loadObject();
    }
}
