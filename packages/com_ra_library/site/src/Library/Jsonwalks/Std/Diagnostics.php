<?php
namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std;


defined("_JEXEC") or die("Restricted access");
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Displaybase;
//use Ramblers\Component\Ra_library\Site\Library\Jsonwalks;


class Diagnostics extends Displaybase {

    // Every value that Walk::getWalkValue can return. {meetOSMap}, {startOSMap} and {emailat}
    // are not implemented there (they would only print their own name), so they are not listed.
    private $options = ["{lf}", "<b>ADMIN</b>", "{group}",
        "<b>BASICS</b>", "{dowShortdd}", "{dowShortddmm}",
        "{dowShortddmmyyyy}", "{dowdd}", "{dowddmm}", "{dowddmmyyyy}",
        "{datebadge}", "{datebadgedow}", "{datebadgeday}", "{datebadgedowday}",
        "{title}", "{description}", "{additionalNotes}",
        "<b>MEETING</b>", "{meet}", "{meetTime}", "{meetPlace}", "{meetGR}", "{meetPC}", "{meetw3w}", "{meetDirections}",
        "<b>START</b>", "{start}", "{startTime}", "{startTimefull}", "{startPlace}", "{startGR}", "{startPC}", "{startw3w}",
        "{startDirections}",
        "<b>FINISH</b>", "{finishTime}", "{finishPlace}", "{finishGR}", "{finishPC}",
        "<b>WALK</b>", "{difficulty}", "{difficulty+}", "{type}","{shape}", "{distance}", "{distanceMi}", "{distanceKm}", "{gradeimg}", "{gradeimgRight}",
        "{grade}", "{grade+}", "{nationalGrade}", "{nationalGradeAbbr}", "{localGrade}",
        "{ascent}", "{ascentMetres}", "{ascentFeet}",
        "<b>CONTACTS</b>",  "{contact}", "{contactname}", "{contactperson}", "{telephone}",
        "{telephone1}", "{telephone2}", "{email}", "{emaillink}",
        "<b>MEDIA</b>",  "{mediathumbr}","{mediathumbl}"];

    // Every value that Walk::getIntValue(section, name) can return, by section.
    // meeting, start, finish and contacts give the first item only.
    private $intValues = [
        'admin' => ['groupCode', 'groupName', 'id', 'type', 'dateCreated', 'dateUpdated', 'status', 'cancellationReason', 'nationalUrl'],
        'basics' => ['walkDate', 'monthgroup', 'description', 'descriptionHtml', 'title', 'additionalNotes', 'finishDate'],
        'walks' => ['distanceKm', 'distanceMiles', 'localGrade', 'nationalGrade', 'shape', 'ascentMetres', 'ascentFeet',
            'schemaDistance', 'icsWalkDistance', '_icsWalkGrade'],
        'meeting' => ['type', 'time', 'lat/long', 'postcode', 'textTime', '_icsDescription'],
        'start' => ['type', 'time', 'lat/long', 'postcode', 'textTime', '_icsDescription'],
        'finish' => ['type', 'time', 'lat/long', 'postcode', 'textTime', '_icsDescription'],
        'contacts' => ['contactName', 'telephone1', 'telephone2', '_icsrecord']];

    public function DisplayWalks($walks) {

        $items = $walks->allWalks();
        foreach ($items as $walk) {
            echo "<table><tr><th>Item</th><th>Name</th><th>Value</th><th>HTML</th></tr>";
            $no = 0;
            foreach ($this->options as $option) {

                $html = "<tr>";
                $value = $walk->getWalkValue($option);
                $text = str_replace(["<", ">"], ["&lt;", ">"], $value);
                $html .= "<td>" . $no . "</td><td>" . $option . "</td><td>" . $value . "</td><td>" . $text . "</td>";
                $html .= "</tr>";
                echo $html;
                $no += 1;
            }
            echo "</table>";
            $this->displayInternalValues($walk);
        }

        return;
    }

    private function displayInternalValues($walk) {
        echo "<h4>Internal values</h4>";
        echo "<table><tr><th>Section / name</th><th>Value</th><th>HTML</th></tr>";
        foreach ($this->intValues as $section => $names) {
            foreach ($names as $name) {
                $value = $this->valueToText($walk->getIntValue($section, $name));
                $text = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                echo "<tr><td>" . $section . " / " . $name . "</td><td>" . $value . "</td><td>" . $text . "</td></tr>";
            }
        }
        echo "</table>";
    }

    // Internal values can be dates, arrays, objects, booleans or null, so make them printable.
    private function valueToText($value) {
        if ($value === null) {
            return "null";
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? "true" : "false";
        }
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR);
            return $json === false ? "(not printable)" : $json;
        }
        return (string) $value;
    }

}
