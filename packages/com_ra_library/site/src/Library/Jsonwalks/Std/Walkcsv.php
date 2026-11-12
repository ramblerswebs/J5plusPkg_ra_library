<?php

namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std;

/**
 * Walks and events as a CSV file (the columns are listed in header()).
 *
 * Nothing is written to the server. The CSV is built and sent when the download link is
 * followed (see Controller/WalkcsvController.php); the link itself is made by
 * {ra_library:walkcsv groups="..."} (see Helper/PluginHelper.php).
 *
 * @author Chris Vaughan
 */
// no direct access
defined("_JEXEC") or die("Restricted access");

class Walkcsv {

    /** Strip the html from descriptions. */
    public bool $removeHTML = false;

    /**
     * The link to the download. $groups is a list of group codes, e.g. "ER05,ER06".
     */
    public static function url(string $groups): string {
        return \Joomla\CMS\Uri\Uri::root() . 'index.php?option=com_ra_library&task=walkcsv.walkcsv&groups=' . rawurlencode($groups);
    }

    /**
     * Write the CSV for a Walks collection to an open stream.
     *
     * @param   resource  $handle  Where to write, e.g. fopen('php://output', 'w').
     * @param   object    $walks   A Walks collection, already sorted as required.
     *
     * @return  int  The number of walks/events written.
     */
    public function write($handle, $walks): int {
        fputcsv($handle, self::header());
        $count = 0;
        foreach ($walks->allWalks() as $walk) {
            fputcsv($handle, $this->row($walk));
            $count++;
        }
        return $count;
    }

    public function row($walk): array {
        $array = [];
        $array[] = $walk->getIntValue("basics", "walkDate")->format('d/m/Y');
        $array[] = $walk->getIntValue("basics", "title");
        $description = $walk->getIntValue("basics", "descriptionHtml");
        if ($this->removeHTML) {
            $array[] = html_entity_decode(strip_tags($description));
        } else {
            $array[] = $description;
        }
        $array[] = $walk->getIntValue("basics", "additionalNotes");
        $array[] = ""; // Website Link
        $array[] = $walk->getIntValue("contacts", "contactName");
        $array[] = $walk->getIntValue("walks", "shape");

        $array[] = $walk->getIntValue("start", "textTime");
        $array[] = $walk->getIntValue("start", "lat/long");
        $array[] = ""; // Starting postcode
        $array[] = ""; // Starting gridref
        $array[] = $walk->getWalkValue("{startPlace}");

        $array[] = $walk->getIntValue("meeting", "textTime");
        $array[] = $walk->getIntValue("meeting", "lat/long");
        $array[] = ""; // Meeting postcode
        $array[] = ""; // Meeting gridref
        $array[] = $walk->getWalkValue("{meetPlace}");

        $array[] = $walk->getIntValue("finish", "textTime");
        $array[] = $walk->getIntValue("finish", "lat/long");
        $array[] = ""; // Finishing postcode
        $array[] = ""; // Finishing gridref
        $array[] = $walk->getWalkValue("{finishPlace}");

        $array[] = $walk->getIntValue("walks", "nationalGrade");
        $array[] = $walk->getIntValue("walks", "distanceKm");
        $array[] = $walk->getIntValue("walks", "distanceMiles");
        $array[] = $walk->getIntValue("walks", "ascentMetres");
        $array[] = $walk->getIntValue("walks", "ascentFeet");

        foreach ($walk->flagsExists(self::flags()) as $set) {
            $array[] = $set ? "True" : "False";
        }
        return $array;
    }

    public static function header(): array {
        return ["Date", "Title", "Description", "Additional details", "Website Link", "Walk leaders", "Linear or Circular", "Start time", "Starting location", "Starting postcode", "Starting gridref", "Starting location details", "Meeting time", "Meeting location", "Meeting postcode", "Meeting gridref", "Meeting location details", "Est finish time", "Finishing location", "Finishing postcode", "Finishing gridref", "Finishing location details", "Difficulty", "Distance km", "Distance miles", "Ascent metres", "Ascent feet", "Dog friendly", "Introductory walk", "No stiles", "Family-friendly", "Wheelchair accessible", "Accessible by public transport", "Car parking available", "Car sharing available", "Coach trip", "Refreshments available (Pub/cafe)", "Toilets available"];
    }

    private static function flags(): array {
        return ["Dog friendly", "Introductory walk", "No stiles", "Family-friendly", "Wheelchair accessible", "Accessible by public transport", "Car parking available", "Car sharing available", "Coach trip", "Refreshments available (Pub/cafe)", "Toilets available"];
    }
}
