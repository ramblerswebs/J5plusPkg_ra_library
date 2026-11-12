<?php

namespace Ramblers\Component\Ra_library\Site\Library\Leaflet;

/**
 * Displays all the meeting and starting locations that are valid, i.e. 1 star or better.
 * The points come from the places server named in L.Control.Places.js (some 20,000 of them),
 * and are clustered on the map.
 *
 * Not for general use: reached through {ra_library:places} when the
 * plugin's "Specialist tags" setting is on.
 *
 * @author Chris Vaughan
 */
use Ramblers\Component\Ra_library\Site\Library\Load\Load;

class Mapplaces extends Map {

    public function __construct() {
        parent::__construct();
    }

    public function display() {
        parent::setCommand('ra.display.places');

        $this->help_page = "places-help-home.html";
        $this->options->fullscreen = true;
        $this->options->mouseposition = true;
        $this->options->rightclick = true;
        $this->options->fitbounds = true;
        $this->options->displayElevation = false;
        // the clustering is done by the Places control itself (ra-display-places.js)
        $this->options->cluster = false;
        $this->options->mylocation = true;
        $this->options->settings = true;
        $this->options->print = true;

        parent::display();

        Load::addScript("media/com_ra_library/js/leaflet/ra-display-places.js");
        Load::addStyleSheet('media/com_ra_library/css/ramblerslibrary.css');
    }
}
