<?php

namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std;

/**
 * Blog display of future led walks: some walks in full, then a row of
 * panels (flexible swipe row or fixed columns), then a list of links.
 * Walks are taken in date order: first the full walks, then the panels,
 * then the links, so a walk never appears twice.
 * Clicking a panel or link opens the standard walk details popup.
 *
 * @author Chris Vaughan
 */
// no direct access
defined("_JEXEC") or die("Restricted access");

use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Displaybase;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Walk;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Addschema;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Script;
use Ramblers\Component\Ra_library\Site\Library\Load\Load;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class Blog extends Displaybase {

    const SECTION_ORDER = ['Title', 'Photo', 'Description', 'Meeting', 'Start', 'Finish', 'Difficulty', 'Contact', 'Map', 'More'];
    // sections that are built from a text layout (and so can be replaced by a custom layout)
    const TEXT_SECTIONS = ['Title', 'Description', 'Meeting', 'Start', 'Finish', 'Difficulty', 'Contact'];
    // items that can be shown in a panel: key => [label, template]
    const PANEL_ITEMS = [
        'Title' => ['', '{title}'],
        'Date' => ['Date', '{dowddmm}{, ?startTime}'],
        'Group' => ['Group', '{group}'],
        'Difficulty' => ['Difficulty', '{nationalGrade}'],
        'LocalGrade' => ['Grade', '{localGrade}'],
        'Distance' => ['Distance', '{distance}'],
        'Ascent' => ['Ascent', '{ascent}'],
        'Type' => ['Type', '{shape}'],
        'Meeting' => ['Meeting', '{meet}'],
        'Start' => ['Start', '{start}'],
        'Leader' => ['Leader', '{contactname}'],
        'Description' => ['', '{description}'],
    ];
    const DEFAULT_PANEL_ITEMS = ['Title', 'Date', 'Difficulty', 'Distance'];
    const DEFAULT_LINK = '{gradeimg}{dowddmm}{, ?title}{, ?distance}';
    const SECTION_LAYOUTS = [
        'Title' => '<h3 class="ra-fw-title">{title}</h3><div class="ra-fw-date">{dowddmmyyyy}</div>',
        'Description' => '{if:description}<div class="ra-fw-description">{description}</div>{/if}'
        . '{if:additionalNotes}<div class="ra-fw-notes"><b>Additional notes</b>: {additionalNotes}</div>{/if}',
        'Meeting' => '{if:meet}<div class="ra-fw-place"><b>Meeting</b>: {meet}{, ?meetPC}{ ?meetDirections}</div>{/if}',
        'Start' => '{if:start}<div class="ra-fw-place"><b>Start</b>: {start}{, ?startPC}{ ?startDirections}</div>{/if}',
        'Finish' => '{if:finishPlace}<div class="ra-fw-place"><b>Finish</b>: {finishPlace}{, ?finishPC}</div>{/if}',
        'Difficulty' => '{if:difficulty}<div class="ra-fw-difficulty">{difficulty+}</div>{/if}',
        'Contact' => '{if:contact}<div class="ra-fw-contact">{contact}</div>{/if}',
    ];

    private $fullCount = 1;
    private $panelCount = 8;
    private $linkCount = 6;
    private $sections = [];
    private $mapMode = 'button';
    private $customFull = '';
    private $panelMode = 'flexible';
    private $panelMinWidth = 280;
    private $panelColumns = 3;
    private $panelImage = true;
    private $panelArrows = true;
    private $customPanel = '';
    private $panelItems = [];
    private $panelLabels = true;
    private $photoHeight = 300;
    private $defaultImage = 'none';
    private $titleFull = '';
    private $titlePanel = '';
    private $titleLinks = '';
    private $titleLevel = 2;
    private $customLinks = '';

    public function setFullCount($value) {
        $this->fullCount = max(0, (int) $value);
    }

    public function setPanelCount($value) {
        $this->panelCount = max(0, (int) $value);
    }

    public function setLinkCount($value) {
        $this->linkCount = max(0, (int) $value);
    }

    public function setSections($value) {
        $list = [];
        foreach ((array) $value as $item) {
            if (in_array($item, self::SECTION_ORDER, true) && !in_array($item, $list, true)) {
                $list[] = $item;
            }
        }
        $this->sections = $list;
    }

    public function setMapMode($value) {
        $this->mapMode = $value === 'always' ? 'always' : 'button';
    }

    public function setCustomFull($value) {
        $this->customFull = (string) $value;
    }

    public function setPanelMode($value) {
        $this->panelMode = $value === 'fixed' ? 'fixed' : 'flexible';
    }

    public function setPanelMinWidth($value) {
        $this->panelMinWidth = min(600, max(150, (int) $value));
    }

    public function setPanelColumns($value) {
        $this->panelColumns = min(6, max(1, (int) $value));
    }

    public function setPanelImage($value) {
        $this->panelImage = (bool) $value;
    }

    public function setPanelArrows($value) {
        $this->panelArrows = (bool) $value;
    }

    public function setPanelItems($value) {
        $list = [];
        foreach ((array) $value as $item) {
            if (isset(self::PANEL_ITEMS[$item]) && !in_array($item, $list, true)) {
                $list[] = $item;
            }
        }
        $this->panelItems = $list;
    }

    public function setPanelLabels($value) {
        $this->panelLabels = (bool) $value;
    }

    public function setPhotoHeight($value) {
        $this->photoHeight = min(600, max(100, (int) $value));
    }

    // picture shown in a panel for a walk that has no photo: none, path or logo
    public function setDefaultImage($value) {
        $this->defaultImage = in_array($value, ['path', 'logo'], true) ? $value : 'none';
    }

    public function setTitles($full, $panel, $links) {
        $this->titleFull = trim((string) $full);
        $this->titlePanel = trim((string) $panel);
        $this->titleLinks = trim((string) $links);
    }

    public function setTitleLevel($value) {
        $this->titleLevel = min(5, max(2, (int) $value));
    }

    public function setCustomPanel($value) {
        $this->customPanel = (string) $value;
    }

    public function setCustomLinks($value) {
        $this->customLinks = (string) $value;
    }

    #[\Override]
    public function DisplayWalks($walks) {
        try {
            $this->renderBlog($walks);
        } catch (\Throwable $e) {
            // never fail silently: show the problem in the Joomla message area
            echo "<!-- ra_library blog: error -->" . PHP_EOL;
            Factory::getApplication()->enqueueMessage('Walks blog could not be displayed: ' . $e->getMessage()
                    . ' (' . basename($e->getFile()) . ' line ' . $e->getLine() . ')', 'error');
        }
    }

    private function renderBlog($walks) {
        $walks->sort(Walk::SORT_DATE, Walk::SORT_TIME, Walk::SORT_DISTANCE);
        $items = array_values($walks->allWalks());
        echo "<!-- ra_library blog: " . count($items) . " walks, " . $this->fullCount . " full, "
        . $this->panelCount . " panels, " . $this->linkCount . " links -->" . PHP_EOL;

        $full = array_slice($items, 0, $this->fullCount);
        $panels = array_slice($items, $this->fullCount, $this->panelCount);
        $links = array_slice($items, $this->fullCount + $this->panelCount, $this->linkCount);

        echo "<div class='ra-walkblog " . $this->walksClass . "'>" . PHP_EOL;
        if (count($full) > 0) {
            echo $this->heading($this->titleFull);
            echo "<div class='ra-walkblog-full'>" . PHP_EOL;
            foreach ($full as $walk) {
                $this->displayFullWalk($walk);
            }
            echo "</div>" . PHP_EOL;
        }
        if (count($panels) > 0) {
            echo $this->heading($this->titlePanel);
            $this->displayPanels($panels);
        }
        if (count($links) > 0) {
            echo $this->heading($this->titleLinks);
            $this->displayLinks($links);
        }
        if (count($items) === 0) {
            echo "<div class='ra-walkblog-none'>No walks or events found</div>" . PHP_EOL;
        }
        echo "</div>" . PHP_EOL;

        // The popup, the 'show map' button and lazy maps all need the walks registered in the browser
        $shown = array_merge($full, $panels, $links);
        Script::registerWalks($shown);

        Load::addStyleSheet("media/com_ra_library/css/ra.walkblog.css");
        Load::addScript("media/com_ra_library/js/jsonwalks/blog/blog.js");

        $schema = new Addschema();
        $schema->display($walks);
    }

    // ---- full walks -------------------------------------------------------
    private function displayFullWalk($walk) {
        $id = $walk->getIntValue("admin", "id");
        $status = $walk->getIntValue("admin", "status");
        $sections = count($this->sections) > 0 ? $this->sections : self::SECTION_ORDER;
        $customDone = false;
        echo "<article class='ra-walkblog-fullwalk walk" . $status . "' data-walk-id='" . $this->attr($id) . "'>" . PHP_EOL;
        foreach ($sections as $section) {
            if (in_array($section, self::TEXT_SECTIONS, true) && strlen(trim($this->customFull)) > 0) {
                // one custom layout stands in for all the text sections, at the position of the first
                if (!$customDone) {
                    $customDone = true;
                    echo "<div class='ra-fw-custom'>" . $walk->getWalkText($this->customFull, false) . "</div>" . PHP_EOL;
                }
                continue;
            }
            switch ($section) {
                case 'Photo':
                    $img = $this->imageHtml($walk);
                    if ($img !== '') {
                        echo "<div class='ra-fw-photo'>" . $img . "</div>" . PHP_EOL;
                    }
                    break;
                case 'Map':
                    echo "<div class='ra-fw-map' data-map-mode='" . $this->mapMode . "'>";
                    if ($this->mapMode === 'button') {
                        echo "<button type='button' class='ra-fw-showmap'>Show map</button>";
                    }
                    echo "<div class='ra-fw-mapholder'></div></div>" . PHP_EOL;
                    break;
                case 'More':
                    echo "<div class='ra-fw-more'><button type='button' class='ra-fw-showdetails link-button mintcake'>More details</button></div>" . PHP_EOL;
                    break;
                default:
                    echo $walk->getWalkText(self::SECTION_LAYOUTS[$section], false) . PHP_EOL;
            }
        }
        echo "</article>" . PHP_EOL;
    }

    // ---- panels -----------------------------------------------------------
    private function displayPanels($panels) {
        $fixed = $this->panelMode === 'fixed';
        $style = $fixed ? "--ra-panel-cols:" . $this->panelColumns . ";--ra-panel-cols-sm:" . min(2, $this->panelColumns) . ";" : "--ra-panel-min:" . $this->panelMinWidth . "px;";
        $style .= "--ra-photo-h:" . $this->photoHeight . "px;";
        $arrows = !$fixed && $this->panelArrows;
        echo "<div class='ra-walkblog-panels " . ($fixed ? "ra-mode-fixed" : "ra-mode-flexible") . "'"
        . " data-arrows='" . ($arrows ? "1" : "0") . "' style='" . $style . "'>" . PHP_EOL;
        echo "<div class='ra-walkblog-track' role='region' aria-label='Upcoming walks and events'" . ($fixed ? "" : " tabindex='0'") . ">" . PHP_EOL;
        foreach ($panels as $walk) {
            $id = $walk->getIntValue("admin", "id");
            $status = $walk->getIntValue("admin", "status");
            $img = '';
            if ($this->panelImage) {
                $img = $this->imageHtml($walk);
            }
            echo "<div class='ra-blog-panel walk" . $status . "' role='button' tabindex='0' data-walk-id='" . $this->attr($id) . "'"
            . " title='Click to display walk details'>" . PHP_EOL;
            if ($img !== '') {
                echo "<div class='ra-panel-photo'>" . $img . "</div>" . PHP_EOL;
            } elseif ($this->panelImage && $this->defaultImage !== 'none') {
                echo "<div class='ra-panel-photo ra-panel-photo-default ra-photo-" . $this->defaultImage . "'>"
                . "<img class='ra-walkblog-img' src='" . Uri::root(true) . "/media/com_ra_library/images/blog/default-" . $this->defaultImage . ".png' alt='' loading='lazy'>"
                . "</div>" . PHP_EOL;
            }
            echo "<div class='ra-panel-body'>" . $walk->addTooltip($this->panelBody($walk)) . "</div>" . PHP_EOL;
            echo "</div>" . PHP_EOL;
        }
        echo "</div>" . PHP_EOL;
        echo "</div>" . PHP_EOL;
    }

    // the text of a panel: your own layout if you have set one, otherwise the chosen items, each with a label
    private function panelBody($walk) {
        if (strlen(trim($this->customPanel)) > 0) {
            return $walk->getWalkText($this->customPanel, false);
        }
        $keys = count($this->panelItems) > 0 ? $this->panelItems : self::DEFAULT_PANEL_ITEMS;
        $out = '';
        foreach ($keys as $key) {
            $label = self::PANEL_ITEMS[$key][0];
            $value = trim($walk->getWalkText(self::PANEL_ITEMS[$key][1], false));
            if (trim(strip_tags($value)) === '') {
                continue;  // nothing to show for this walk
            }
            $class = 'ra-panel-item ra-panel-' . strtolower($key);
            if ($key === 'Title') {
                $out .= "<div class='ra-panel-title'>" . $value . "</div>";
            } elseif ($label === '' || !$this->panelLabels) {
                $out .= "<div class='" . $class . "'><span class='ra-panel-value'>" . $value . "</span></div>";
            } else {
                $out .= "<div class='" . $class . "'><span class='ra-panel-label'>" . $label . ":</span> <span class='ra-panel-value'>" . $value . "</span></div>";
            }
        }
        return $out;
    }

    // the walk's first photo, using the medium size so it stays sharp when enlarged
    private function imageHtml($walk) {
        $media = $walk->getFirstMedia();
        if ($media === null) {
            return '';
        }
        $src = $media->medium !== '' ? $media->medium : $media->thumb;
        if ($src === '') {
            return '';
        }
        return "<img class='ra-walkblog-img' src='" . $this->attr($src) . "' alt='" . $this->attr($media->alt) . "' loading='lazy'>";
    }

    private function heading($text) {
        if ($text === '') {
            return '';
        }
        $tag = 'h' . $this->titleLevel;
        return "<" . $tag . " class='ra-walkblog-heading'>" . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</" . $tag . ">" . PHP_EOL;
    }

    // ---- links ------------------------------------------------------------
    private function displayLinks($links) {
        $layout = strlen(trim($this->customLinks)) > 0 ? $this->customLinks : self::DEFAULT_LINK;
        echo "<ul class='ra-walkblog-links'>" . PHP_EOL;
        $odd = true;
        foreach ($links as $walk) {
            $status = $walk->getIntValue("admin", "status");
            echo "<li class='ra-walkblog-link walk" . $status . " " . ($odd ? 'odd' : 'even') . "'>"
            . $walk->addTooltip($walk->getWalkText($layout)) . "</li>" . PHP_EOL;
            $odd = !$odd;
        }
        echo "</ul>" . PHP_EOL;
    }

    private function attr($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
