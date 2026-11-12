<?php

namespace Ramblers\Component\Ra_library\Site\Library\Leaflet;

/**
 * Two maps that move together, so different map styles can be compared.
 * Not for general use: reached through {ra_library:mapcompare} when the
 * plugin's "Specialist tags" setting is on.
 *
 * @author Chris Vaughan
 */
use Ramblers\Component\Ra_library\Site\Library\Load\Load;

class Mapcompare extends Map {

    public function __construct() {
        parent::__construct();
        // where the maps start; change with setCenter()
        $this->options->setinitialView(52.89, -1.48, 10);
    }

    public function setCenter($lat, $long, $zoom) {
        $this->options->setinitialView($lat, $long, $zoom);
    }

    public function display() {
        parent::setCommand('ra.display.mapCompare');

        $this->help_page = "map-compare.html";
        $this->options->fullscreen = false;

        $this->options->mouseposition = true;
        $this->options->rightclick = true;
        $this->options->fitbounds = true;
        $this->options->resizer = false;
        $this->options->displayElevation = false;
        $this->options->cluster = null;
        $this->options->mylocation = true;
        $this->options->settings = true;
        $this->options->print = true;

        parent::display();

        Load::addScript("media/com_ra_library/js/leaflet/ra.display.mapCompare.js");
        Load::addStyleSheet('media/com_ra_library/css/ramblerslibrary.css');
    }
}
