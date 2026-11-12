<?php

namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks;

// no direct access
defined('_JEXEC') or die('Restricted access');

use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Walk\Items;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Walk\Flags;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Walk\Bookings;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\SimpleTemplate;
use Ramblers\Component\Ra_library\Site\Library\Html\Html;
use Joomla\CMS\Factory;

class Walk implements \JsonSerializable {

    private $admin = null;
    private $basics = null;
    private $bookings = null;
    private $walks = null;
    private $meeting = null;
    private $start = null;
    private $finish = null;
    private $contacts = null;
    private $flags = null;                  // flags to describe walk 
    private $media = [];                  // array of image information
    private $linkedWalks = [];
    private $linkedEvent = null;
    private $icsDayEvents = false;
    private static $withMonth = ["dowShortddmm", "dowddmm", "dowddmmyyyy", "datebadge", "datebadgedow"];
    private static $customValuesClass = null;
    private static $customValuesMethod = null;

    const DISPLAYWALKFUNCTION = "ra.walk.displayWalkID";
    const SORT_DATE = 0;
    const SORT_CONTACT = 1;
    const SORT_NATIONALGRADE = 2;
    const SORT_LOCALGRADE = 3;
    const SORT_DISTANCE = 4;
    const SORT_TELEPHONE1 = 5;
    const SORT_TELEPHONE2 = 6;
    const SORT_TIME = 7;
    const TIMEFORMAT = "Y-m-d\TH:i:s";

    public function __construct() {
        $this->walks = new Items();
        $this->meeting = new Items();
        $this->start = new Items();
        $this->finish = new Items();
        $this->contacts = new Items();
        $this->flags = new Flags();
    }

    public function addAdmin($admin) {
        $this->admin = $admin;
    }

    public function addBasics($basics) {
        $this->basics = $basics;
    }

    private function addBookings($bookings) {
        $this->bookings = $bookings;
    }

    public function addWalk($walk) {
        $this->walks->addItem($walk);
    }

    public function addMeeting($meet) {
        $this->meeting->addItem($meet);
        return;
    }

    public function addStart($start) {
        $this->start->addItem($start);
        return;
    }

    public function addFinish($finish) {
        $this->finish->addItem($finish);
        return;
    }

    public function addContact($contact) {
        $this->contacts->addItem($contact);
    }

    public function addMedia($media) {
        array_push($this->media, $media);
    }

    public function addLinkedWalks(?array $walksIds) {
        $this->linkedWalks = $walksIds;
    }

    public function addLinkedEvent(?string $eventId) {
        $this->linkedEvent = $eventId;
    }

    public function isWalk($walk) {
        return $walk->admin->isWalk($this->admin);
    }

    public function setFlags($flags) {
        $this->flags = $flags;
    }

    public function filterFlags($flags, $include = true) {
        $all = true;
        foreach ($flags as $flag) {
            $exists = $this->flags->flagExists($flag);
            if (!$exists) {
                $all = false;
            }
        }
        if ($all) {
            return !$include;
        } else {
            return $include;
        }
    }

    public function getSortValue($type) {
        switch ($type) {
            case self::SORT_CONTACT :
                return $this->contacts->getIntValue("contactName");
            case self::SORT_DATE:
                return $this->basics->getIntValue("walkDate");
            case self::SORT_DISTANCE:
                return $this->walks->getIntValue("distanceKm");
            case self::SORT_LOCALGRADE:
                return $this->walks->getIntValue("localGrade");
            case self::SORT_NATIONALGRADE:
                return $this->walks->getIntValue("nationalGrade");
            case self::SORT_TELEPHONE1:
                return $this->contacts->getIntValue("telephone1");
            case self::SORT_TELEPHONE2:
                return $this->contacts->getIntValue("telephone2");
            case self::SORT_TIME:
                $sortTime = $this->getIntValue("meeting", "time");
                if ($sortTime == "") {
                    $sortTime = $this->getIntValue("start", "time");
                }
                return $sortTime;
            default:
                return NULL;
        }
    }

    public function hasMeetPlace() {
        return !$this->meeting->isEmpty();
    }

    public function isCancelled() {
        return $this->admin->isCancelled();
    }

    public function setNewWalk(\DateTime $date) {
        $this->admin->setNewWalk($date);
    }

    public function setBookings($ids) {
        $enabled = $this->admin->isInArray($ids);
        $this->addBookings(new Bookings($enabled));
    }

    public function notInGroup($groups) {
        return $this->admin->notInGroup($groups);
    }

    public function notInDayList($days) {
        return $this->basics->notInDayList($days);
    }

    public function isStatus($status) {
        return $this->admin->isStatus($status);
    }

    public function titleIs($filter) {
        return $this->basics->titleIs($filter);
    }

    public function titleContains($filter) {
        return $this->basics->titleContains($filter);
    }

    public function notInGradeList($grades) {
        $result = true;
        $walks = $this->walks->getItems();
        foreach ($walks as $walk) {
            $notIn = $walk->notInGradeList($grades);
            if (!$notIn) {
                $result = false;
            }
        }
        return $result;
    }

    public function appendWalkTitle($group, $title) {
        $code = $this->admin->getIntValue("groupCode");
        If ($code === $group) {
            $this->basics->appendWalkTitle($title);
        }
    }

    public function flagsExists($flags) {
        $result = [];
        foreach ($flags as $item) {
            $result[] = $this->flags->flagExists($item);
        }
        return $result;
    }

    public function filterDateRange($fromdate, $todate) {
        return $this->basics->filterDateRange($fromdate, $todate);
    }

    public function filterDateBefore($fromdate) {
        return $this->basics->filterDateBefore($fromdate);
    }

    public function filterDateAfter($todate) {
        return $this->basics->filterDateAfter($todate);
    }

    public function _getWalkSchema() {
        $description = $this->getIntValue("basics", "description");
        $postcode = $this->getIntValue("start", "postcode");
        $date = $this->getIntValue("basics", "walkDate")->format('D, jS F');
        $performer = new Structuredperformer($this->getIntValue("admin", "groupName") . " - Ramblers");
        $location = !$this->start->isEmpty() ? new Structuredlocation($description, $postcode) : null;
        $schemawalk = new Structuredevent($performer, $location);
        $schemawalk->name = "Ramblers led walk on " . $date . ", " . $this->getIntValue("basics", "title");
        $schemawalk->description = $this->getIntValue("walks", "schemaDistance");

# Google don't like markup which doesn't appear on page so description must be as on page and image should  be excluded from next walks summary listing
        $schemawalk->startdate = $this->getIntValue("basics", "walkDate")->format('Y-m-d');
        $schemawalk->enddate = $schemawalk->startdate;
        $schemawalk->image = "https://www.ramblers-webs.org.uk/images/ra-images/2022brand/Logo%2090px.png";
        $schemawalk->url = $this->getIntValue("admin", "nationalUrl");
        return $schemawalk;
    }

    public function EventDate() {
        return $this->basics->getIntValue("walkDate");
    }

    public function EventDateYYYYMMDD() {
        return $this->basics->getIntValue("walkDate")->format('Y-m-d');
    }

    public function EventText() {
        //  $calendarFormat = ["{meetTime}", "{< meet, >startTime}", "{,title}", "{,distance}"];

        $calendarFormat = "{meetTime}{ meet, ?meetTime}{startTime}{, ?title}{, ?distance}";
        $text = $this->getWalkText($calendarFormat);
        return $text;
    }

    public function EventList($display, $class) {

        $text = $this->EventText();
        $out = "";
        $out .= "<div class='event-list-cal-event-single-link " . $class . " " . $this->EventStatus() . "'>" . PHP_EOL;
        $out .= $text . PHP_EOL;

        $out .= "</div>" . PHP_EOL;
        return $out;
    }

    public function EventLink($display, $text) {
        return $this->_addWalkLink($this->getIntValue("admin", "id"), $text);
    }

    public function EventStatus() {
        $status = $this->getIntValue("admin", "status");
        return "walk" . $status;
    }

    public function Event_ics($icsfile) {

        $meetLocation = $this->getIntValue("meeting", "_icsDescription");
        if ($meetLocation !== "") {
            $meetLocation .= "; \\n";
        }
        $startLocation = $this->getIntValue("start", "_icsDescription");
        $before = $meetLocation . $startLocation . "\\nDescription: ";
        $after = "<br/>" . $this->getIntValue("contacts", "_icsrecord");
        $after .= $this->getIntValue("walks", "_icsWalkGrade");
        $after .= $this->getIntValue("admin", "nationalUrl");

        $after .= "<br/>Note: Finish times are very approximate!";
        $additionalNotes = $this->getIntValue("basics", "additionalNotes");
        if ($additionalNotes !== '') {
            $after .= "<br/>Notes: " . $additionalNotes;
        }
        $summary = $this->getIntValue("basics", "title");
        $summary .= $this->getIntValue("walks", "icsWalkDistance");

        $now = new \Datetime();
        $icsfile->addRecord("BEGIN:VEVENT");
        $this->addIcsTimes($icsfile);
        $icsfile->addRecord("LOCATION:", $startLocation);
        $icsfile->addRecord("TRANSP:OPAQUE");
        $icsfile->addSequence($this->getIntValue("admin", "dateUpdated"));
        // no blank after "UID:", so the UID is the same as in the old JS created files ('walk<id>-isc@...')
        $icsfile->addRecord("UID:walk" . $this->getIntValue("admin", "id") . "-isc@ramblers-webs.org.uk");
        $icsfile->addRecord("ORGANIZER;CN=" . $this->getIntValue("admin", "groupName") . ":mailto:ignore@ramblers-webs.org.uk");

        if ($this->isCancelled()) {
            $icsfile->addRecord("METHOD:CANCEL");
            $summary = " CANCELLED " . $summary;
            $cancellationReason = $this->getIntValue("admin", "cancellationReason");
            $description = "CANCELLED - REASON: " . $cancellationReason . " (" . $this->getIntValue("basics", "description") . ")";
            $altDescription = "CANCELLED - REASON: " . $cancellationReason . " (" . $this->getIntValue("basics", "descriptionHtml") . ")";
        } else {
            $description = $before . $this->getIntValue("basics", "description") . $after;
            $altDescription = $before . $this->getIntValue("basics", "descriptionHtml") . $after;
        }
        $icsfile->addRecord("SUMMARY:", Html::convertToText($summary));
        $icsfile->addRecord("DESCRIPTION:", $description);
        $icsfile->addRecord("X-ALT-DESC;FMTTYPE=text/html:", $altDescription, true);
        $icsfile->addRecord("CATEGORIES:", "Walk," . $this->getIntValue("admin", "groupName"));
        $icsfile->addRecord("DTSTAMP;VALUE=DATE-TIME:", $this->dateTimetoUTC($now));
        $icsfile->addRecord("CREATED;VALUE=DATE-TIME:", $this->dateTimetoUTC($this->getIntValue("admin", "dateCreated")));
        $icsfile->addRecord("LAST-MODIFIED;VALUE=DATE-TIME:", $this->dateTimetoUTC($this->getIntValue("admin", "dateUpdated")));
        $icsfile->addRecord("PRIORITY:1");
        $icsfile->addRecord("URL;VALUE=URI:", $this->getIntValue("admin", "nationalUrl"));
        $icsfile->addRecord("CLASS:PUBLIC");
        $icsfile->addRecord("END:VEVENT");
        return;
    }

    private function dateTimetoUTC($date) {
        $utcdate = new \DateTime($date->format('Ymd His'));
        $utcdate->setTimezone(new \DateTimeZone('UTC'));
        return $utcdate->format('Ymd\THis\Z');
    }

    private function addIcsTimes($icsfile) {
        if ($this->icsDayEvents) {
            $icsfile->addRecord("DTSTART;VALUE=DATE:" . $this->walkDate->format('Ymd'));
        } else {
            $time = $this->getFirstTime();
            if ($time <> null) {
                $icsfile->addRecord("DTSTART;VALUE=DATE-TIME:" . $time->format('Ymd\THis'));
                $time = $this->getFinishTime();
                if ($time <> null) {
                    $icsfile->addRecord("DTEND;VALUE=DATE-TIME:" . $time->format('Ymd\THis'));
                }
            } else {
                $icsfile->addRecord("DTSTART;VALUE=DATE:" . $this->getIntValue("basics", "walkDate")->format('Ymd'));
            }
        }
    }

    private function getFirstTime() {
        $starttime = $this->_firstTime($this->getIntValue("meeting", "time"), $this->getIntValue("start", "time"));
        if ($starttime == "") {
            $starttime = null;
        }
        return $starttime;
    }

    private function getFinishTime() {
        $finishTime = $this->getIntValue("finish", "time");
        if ($finishTime != null) {
            return $finishTime;
        }
        // calculate end time
        $lasttime = $this->_lastTime($this->getIntValue("meeting", "time"), $this->getIntValue("start", "time"));
        $durationFullMins = ceil($this->getIntValue("walks", "distanceMiles") / 2) * 60;
        if ($this->getIntValue("start", "type") == "Rough") {
            $durationFullMins += 60; // add time to allow for travelling
        }
        $intervalFormat = "PT" . $durationFullMins . "M";
        $interval = new \DateInterval($intervalFormat);
        return $lasttime->add($interval);
    }

    public function _firstTime($time1, $time2) {
        if ($time1 === "") {
            return $time2;
        }
        if ($time2 === "") {
            return $time1;
        }
        return $time1;
    }

    public function _lastTime($time1, $time2) {
        if ($time1 === "") {
            return $time2;
        }
        if ($time2 === "") {
            return $time1;
        }
        return $time2;
    }

    public function filterDistance($distanceMin, $distanceMax) {
        $walks = $this->walks->getItems();
        $outside = false;
        foreach ($walks as $walk) {
            $out = $walk->filterDistance($distanceMin, $distanceMax);
            if ($out) {
                $outside = true;
            }
        }
        return $outside;
    }

    public function filterFeedDistances($distances) {
        // $distances will be elements of [0, 3, 5, 8, 10, 13, 15, 999];
        // remove items that are in each range
        $walks = $this->walks->getItems();
        foreach ($walks as $walk) {
            foreach ($distances as $distance) {
                $remove = $walk->filterDistance($distance[0], $distance[1]);
                if (!$remove) {
                    return true;
                }
            }
        }
        return false;
    }

    public function filterDistanceBelow($distanceMin) {
        $walks = $this->walks->getItems();
        $outside = false;
        foreach ($walks as $walk) {
            $out = $walk->filterDistanceBelow($distanceMin);
            if ($out) {
                $outside = true;
            }
        }
        return $outside;
    }

    public function filterDistanceAbove($distanceMax) {
        $walks = $this->walks->getItems();
        $outside = false;
        foreach ($walks as $walk) {
            $out = $walk->filterDistanceAbove($distanceMax);
            if ($out) {
                $outside = true;
            }
        }
        return $outside;
    }

    public function distanceFromLatLong($lat, $long) {
        $distance = 100000000; // Default of far away.
        $starts = $this->start->getItems();
        foreach ($starts as $start) {
            $dist = $start->distanceFromLatLong($lat, $long);
            if ($dist < $distance) {
                $distance = $dist;
            }
        }
        return $distance;
    }

    public function filterEvents() {
        return $this->admin->filterEvents();
    }

    public function filterWalks() {
        return $this->admin->filterWalks();
    }

    // first image of the walk, or null if it has none
    public function getFirstMedia() {
        return count($this->media) > 0 ? $this->media[0] : null;
    }

    public function getMedia($item) {
        $out = [];
        if (count($item->media) > 0) {
            foreach ($item->media as $value) {
                $out[] = $value;
            }
        }
        return $out;
    }

    public static function groupByMonth($template) {
        $temp = new SimpleTemplate($template);
        $fields = $temp->getFields();
        foreach (self::$withMonth as $value) {
            if (in_array($value, $fields)) {
                return false;
            }
        }
        return true;
    }

    public function getMonthGroup() {
        //   return $this->month . $this->addYear();
        return $this->getIntValue("basics", "monthgroup");
    }

    public static function setCustomValues($clss, $method) {
        self::$customValuesClass = $clss;
        self::$customValuesMethod = $method;
    }

    public function getWalkText($template, $link = true) {
        $temp = new SimpleTemplate($template);
        $fields = $temp->getFields();          // → ['name', 'company']
        $values = $this->fetchValues($fields); // your Joomla‑style fetch logic
        $out = $temp->render($values);
        if ($link) {
            return $this->addWalkLink($this->getIntValue("admin", "id"), $out);
        } else {
            return $out;
        }
    }

    private function fetchValues($fields) {
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $this->getWalkValue("{" . $field . "}");
        }
        return $values;
    }

    public function getWalkValues($items, $link = true) {
        $out = "";
        $lastItem = '';
        foreach ($items as $item) {
            $options = $this->getPrefix($item);
            $thisItem = $this->getWalkValue($options->walkValue);
            if ($lastItem !== '' && $thisItem !== '') {
                $out .= $options->previousPrefix;
            }
            if ($thisItem !== "") {
                $out .= $options->prefix;
            }
            $out .= $thisItem;
            $lastItem = $thisItem;
        }
        if ($out === '') {
            return $out;
        }
        if ($link) {
            return $this->addWalkLink($this->getIntValue("admin", "id"), $out);
        } else {
            return $out;
        }
    }

    public function getWalkValue($option) {
        $BR = "<br/>";
        $out = "";
        switch ($option) {
            case "{lf}":
                $out = $BR;
                break;
            case "{group}":
                $out = $this->admin->getValue($option);
                break;
            case "{dowShortdd}":
            case "{dowShortddmm}":
            case "{dowShortddmmyyyy}":
            case "{dowdd}":
            case "{dowddmm}":
            case "{dowddmmyyyy}":
            case "{datebadge}":
            case "{datebadgedow}":
            case "{datebadgeday}":
            case "{datebadgedowday}":
            case "{title}":
            case "{description}":
            case "{additionalNotes}":
            case "{finishTime}":
                $out = $this->basics->getValue($option);
                break;
            case "{meet}":
            case "{meetTime}":
            case "{meetPlace}":
            case "{meetGR}":
            case "{meetPC}":
            case "{meetw3w}":
            case "{meetDirections}":
                $out = $this->meeting->getValue(str_replace("meet", "", $option));
                break;
            case "{start}":
            case "{startTime}":
            case "{startTimefull}":
            case "{startPlace}":
            case "{startGR}":
            case "{startPC}":
            case "{startw3w}":
            case "{startDirections}":
                $out = $this->start->getValue(str_replace("start", "", $option));
                break;
            case "{finishPlace}":
            case "{finishGR}":
            case "{finishPC}":
                $out = $this->finish->getValue(str_replace("finish", "", $option));
                break;
            case "{difficulty}":
            case "{difficulty+}":
            case "{type}":
            case "{distance}":
            case "{distanceMi}":
            case "{distanceKm}":
            case "{gradeimg}":
            case "{gradeimgRight}":
            case "{grade}":
            case "{grade+}":
            case "{nationalGrade}":
            case "{nationalGradeAbbr}":
            case "{localGrade}":
            case "{shape}":
            case "{ascent}":
            case "{ascentMetres}":
            case "{ascentFeet}":
            case "{type}":
                $out = $this->walks->getValue($option);
                break;

            case "{contact}":
            case "{contactname}":
            case "{contactperson}":
            case "{telephone}":
            case "{telephone1}":
            case "{telephone2}":
            case "{email}":
            case "{emaillink}":
                $out = $this->contacts->getValue($option);
                break;
            case "{mediathumbr}":
                $out = '';
                if (count($this->media) > 0) {
                    $out = "<img class='mediathumbr' src='" . $this->media[0]->thumb . "' alt='" . $this->media[0]->alt . "'>";
                }
                break;
            case "{mediathumbl}":
                $out = '';
                if (count($this->media) > 0) {
                    $out = "<img class='mediathumbl' src='" . $this->media[0]->thumb . "' alt='" . $this->media[0]->alt . "'>";
                }
                break;

            default:
                $found = false;
                if (self::$customValuesClass !== null) {
                    $response = call_user_func(array(self::$customValuesClass, self::$customValuesMethod), $option, $this);
                    $found = $response->found;
                }
                if ($found) {
                    $out .= $response->out;
                } else {
                    $option = str_replace("{", "", $option);
                    $out = str_replace("}", "", $option);
                }
        }
        return $out;
    }

    public function getIntValue($section, $option) {
        switch ($section) {
            case "admin":
                return $this->admin->getIntValue($option);
            case "basics":
                return $this->basics->getIntValue($option);
            case "walks":
                return $this->walks->getIntValue($option);
            case "meeting":
                return $this->meeting->getIntValue($option);
            case "start":
                return $this->start->getIntValue($option);
            case "finish":
                return $this->finish->getIntValue($option);
            case "contacts":
                return $this->contacts->getIntValue($option);
        }
        $app = Factory::getApplication();
        $app->enqueueMessage("Internal error, invalid Walk/Event section: " . $section);
        return null;
    }

    private function getPrefix($walkOption) {
        $options = new \StdClass();
        $options->previousPrefix = "";
        $options->prefix = "";
        $options->walkValue = $walkOption;
        $loop = true;
        do {
            switch (substr($options->walkValue, 0, 2)) {
                case "{;":
                    $options->prefix .= "<br/>";
                    $options->walkValue = str_replace("{;", "{", $options->walkValue);
                    break;
                case "{,":
                    $options->prefix .= ", ";
                    $options->walkValue = str_replace("{,", "{", $options->walkValue);
                    break;
                case "{[":
                    $close = strpos($options->walkValue, "]");
                    if ($close !== false) {
                        $options->prefix .= substr($options->walkValue, 2, $close - 2);
                        $options->walkValue = "{" . substr($options->walkValue, $close + 1);
                    } else {
                        $options->prefix .= $options->walkValue;
                        $options->walkValue = "{}";
                    }

                    break;
                case "{<":
                    $close = strpos($options->walkValue, ">");
                    if ($close !== false) {
                        $options->previousPrefix .= substr($options->walkValue, 2, $close - 2);
                        $options->walkValue = "{" . substr($options->walkValue, $close + 1);
                    } else {
                        $options->previousPrefix .= $options->walkValue;
                        $options->walkValue = "{}";
                    }

                    break;
                default:
                    $loop = false;
            }
        } while ($loop);
        return $options;
    }

    private function _addWalkLink($id, $text, $class = "") {
        if ($text !== '') {
            return "<span class='pointer " . $class . "' onclick=\"javascript:" . self::DISPLAYWALKFUNCTION . "(event,'" . $id . "')\">" . $text . "</span>";
        }
        return $text;
    }

    /*
     * Position just after the </span> that closes the <span> starting at $st.
     * Spans can be nested (e.g. the date badge), so the first </span> found is not always the right one.
     * If the span is never closed the end of the text is returned.
     */
    private function endOfSpan(string $text, int $st): int {
        $depth = 0;
        $pos = $st;
        $len = strlen($text);
        while ($pos < $len) {
            $open = strpos($text, "<span", $pos);
            $close = strpos($text, "</span>", $pos);
            if ($close === false) {
                return $len;
            }
            if ($open !== false && $open < $close) {
                $depth++;
                $pos = $open + 5;
            } else {
                $depth--;
                $pos = $close + 7;
                if ($depth === 0) {
                    return $pos;
                }
            }
        }
        return $len;
    }

    private function addWalkLink($id, $text, $class = "") {
        // split into text before and after span
        $start = '';
        $st = strpos($text, "<span");
        $en = $st === false ? 0 : $this->endOfSpan($text, $st);
        if ($st !== false && $en > $st) {
            if ($st > 0) {
                $start = $this->_addWalkLink($id, substr($text, 0, $st), $class);
            }
            $middle = substr($text, $st, $en - $st);
//  var _end = $text.substr(en);
            $end = $this->addWalkLink($id, substr($text, $en), $class);
            return $start . $middle . $end;
        } else {
            return $this->_addWalkLink($id, $text, $class);
        }
    }

    public function addTooltip($text) {
        if ($this->admin->isCancelled()) {
            return "<span data-descr='Walk Cancelled' class=' walkCancelled'>" . $text . "</span>";
        }
        if ($this->admin->isNew()) {
            $dateCreated = $this->getIntValue("admin", "dateCreated");
            $status = $this->getIntValue("admin", "status");
            return "<span class='new' data-descr='New Walk/Event created " . $dateCreated->format('D, jS M') . "' class=' walk" . $status . "'>" . $text . "</span>";
        }
        if ($this->admin->isUpdated()) {
            $dateUpdated = $this->getIntValue("admin", "dateUpdated");
            $status = $this->getIntValue("admin", "status");
            return "<span class='updated' data-descr='Walk/Event updated " . $dateUpdated->format('D, jS M') . "' class=' walk" . $status . "'>" . $text . "</span>";
        }
        return $text;
    }

    private function contains($needle, $haystack) {
        return strpos($haystack, $needle) !== false;
    }

    #[\Override]
    public function jsonSerialize(): mixed {

        return [
            'admin' => $this->admin,
            'basics' => $this->basics,
            'walks' => $this->walks,
            'meeting' => $this->meeting,
            'start' => $this->start,
            'finish' => $this->finish,
            'contact' => $this->contacts,
            'flags' => $this->flags,
            'media' => $this->media,
            'bookings' => $this->bookings,
            'linkedEvent' => $this->linkedEvent,
            'linkedWalks' => $this->linkedWalks
        ];
    }
}
