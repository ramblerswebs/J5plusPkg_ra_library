<?php

namespace Ramblers\Component\Ra_library\Site\Helper;

/*
 * copyright: Chris Vaughan
 * email: ruby.tuesday@ramblers-webs.org.uk
 */

/**
 * Description of DisplayHelper
 *
 * @author chris
 */
use Ramblers\Component\Ra_library\Site\Library\Load\Load;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Feedoptions;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Feed;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Areawalks;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Display;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Fulldetails;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Nextwalks;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Blog;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Calendar;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\IcsDownload;
use Ramblers\Component\Ra_library\Site\Library\Ics\Calendarbutton;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Simplelist;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Walktable;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Diagnostics;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Leaflet\Mapmarker;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Mapdraw;
use Ramblers\Component\Ra_library\Site\Library\License\License;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Bu51\Fulldetails as BU01Fulldetails;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Bu51\Groupstabs as BU01Groupstabs;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Bu51\Micronextwalks as BU01Micronextwalks;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Bu51\Tabs as BU01Tabs;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Ml\MLPrint as MLPrint;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Ns\Walksprinted as NSWalksprinted;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Sr02\Display as SR02Display;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Sr02\Sr02nextwalks;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Sr02\Table2 as SR02Table2;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Gpx\Map as GpxMap;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Gpx\Maplist as GpxMaplist;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Table\Csv as CsvList;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Table\Json as JsonList;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Table\Sql as SqlList;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Table\Columns as TableColumns;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Table\Column as TableColumn;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Map as LeafletMap;
use Ramblers\Component\Ra_library\Site\Library\Directory\DirList;
use Ramblers\Component\Ra_library\Site\Helper\FrontendHelper;
use Ramblers\Component\Ra_library\Site\Library\Frontend\ItemRenderer;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class DisplayHelper {

    private $displayoption;
//private $options;
    private $data;

    public function __construct($displayoption, $options) {
        $this->displayoption = $displayoption;
        $this->data = json_decode($options);
        // Render css and js if set
        $css = $this->data->css_file ?? '';
        if (strlen($css) > 0) {
            Load::addStyleSheet($css);
        }
        $js = $this->data->js_file ?? '';
        if (strlen($js) > 0) {
            Load::addScript($js);
        }
    }

    Public function Display() {
        $data = $this->data;
        if ($data->addarticles === '1' && $data->before) {
            echo $data->before;
        }
        switch ($this->displayoption) {
            case "routes_plot":
                $this->displayPlot();
                break;
            case "routes_display_single":
                $this->displayRoutesSingle();
                break;
            case "routes_display_multi":
                $this->displayRoutesMulti();
                break;
            case "table_csv":
                $this->displayTableCSV();
                break;
            case "table_sql":
                $this->displayTableSQL();
                break;
            case "table_json":
                $this->displayTableJson();
                break;
            case "documents_folder":
                $this->displayDocumentsFolders();
                break;
            case "pastwalks_blog":
                $this->displayRecordBlog('pastwalk');
                break;
            case "pastwalks_list":
                $this->displayRecordList('pastwalk');
                break;
            case "routes_blog":
                $this->displayRecordBlog('route');
                break;
            case "routes_list":
                $this->displayRecordList('route');
                break;
            case "pastwalks_categorytree":
                $this->displayRecordCategoryTree('pastwalk');
                break;
            case "routes_categorytree":
                $this->displayRecordCategoryTree('route');
                break;
            case "pastwalks_table":
                $this->displayRecordTable('pastwalk', false);
                break;
            case "pastwalks_maptable":
                $this->displayRecordTable('pastwalk', true);
                break;
            case "routes_table":
                $this->displayRecordTable('route', false);
                break;
            case "routes_maptable":
                $this->displayRecordTable('route', true);
                break;
            default: // assume it is one of the many led walks options
                $this->displayEvents();
                break;
        }
        if ($data->addarticles === '1' && $data->after) {
            echo $data->after;
        }
    }

    private function displayEvents() {
        $data = $this->data;
        $feedoptions = $this->getFutureEventFeedOptions($data->eventsources);
        $feed = new Feed($feedoptions);
        $this->filtersEvents($feed, $data->filters);
        $display = $this->getEventsDisplay($this->displayoption);
        $this->setClasses($this->displayoption, $display, $data);
        $this->customise_title($feed, $data);
        $this->customiseWalksDisplay($display, $this->displayoption, $data);
        // Calendar link
        $showLink = $this->showCalendarLink();
        if ($this->displayoption === 'future_display') {
            $display->setCalendarLink($showLink);  // button is inside the tabs
        }
        $feed->Display($display);
        if ($showLink && $this->displayoption !== 'future_display' && $this->displayoption !== 'future_addtocalendar') {
            // groups and walk ids come from the walks this display actually shows
            $options = Calendarbutton::getOptions($feed->getWalks());
            echo Calendarbutton::render($options);
        }
    }

    // Should this display show the 'Add to calendar' button?
    // Saved value wins; with no saved value only the tabbed display has it on (so existing displays are unchanged).
    private function showCalendarLink(): bool {
        if ($this->displayoption === 'future_addtocalendar') {
            return true;
        }
        if (isset($this->data->show_calendar_link) && $this->data->show_calendar_link !== '') {
            return (string) $this->data->show_calendar_link === '1';
        }
        return $this->displayoption === 'future_display';
    }

    private function getFutureEventFeedoptions($sources) {
        $feedoptions = new Feedoptions();
        foreach ($sources as $source) {
            // a source switched off in the form is skipped (a missing value counts as on)
            if (isset($source->enabled) && !$source->enabled) {
                continue;
            }
            switch ($source->load_type) {
                case 'wm':
                    $codes = array_column($source->ramblersgroups, 'code');
                    $groups = implode(",", $codes);
                    $feedoptions->addWalksManagerGroupWalks($groups);
                    break;
                case 'wmarea':
                    $lat = $source->arealatitude;
                    $long = $source->arealongitude;
                    $dist = $source->arearadius;
                    $feedoptions->addWalksManagerGroupsInArea($lat, $long, $dist);
                    break;
                case 'wmwellbeing':
                    $codes = array_column($source->wellbeinggroups, 'code');
                    $feedoptions->addWalksManagerWellbeingWalks(implode(",", $codes));
                    break;
                case 'wmwellbeingarea':
                    $feedoptions->addWalksManagerWellbeingInArea($source->arealatitude, $source->arealongitude, $source->arearadius);
                    break;
                default:
                    throw new \RuntimeException('Invalid source of events');
            }
        }
        return $feedoptions;
    }

    private function filtersEvents($feed, $filters) {
        foreach ($filters as $filter) {
            if (!$filter->enabled) {
                continue;
            }
            switch ($filter->filter_type) {
                case 'by_number':
                    $feed->limitNumberWalks($filter->by_number);
                    break;
                case 'by_days_of_week':
                    $feed->filterDayofweek($filter->days_of_week);
                    break;
                case 'by_grades':
                    $feed->filterNationalGrade($filter->grades);
                    break;
                case 'by_before_date':
                    $feed->filterDateBefore($filter->before_date);
                    break;
                case 'by_after_date':
                    $feed->filterDateAfter($filter->after_date);
                    break;
                case 'by_time_period':
                    $dateto = new \DateTime(); // set date to today
                    $dateto->add(new \DateInterval('P' . $filter->time_period . 'D')); // add one month, 'P3M' is 3 months, 'P30D' is 30 days
                    $feed->filterDateAfter($dateto);
                    break;
                case 'by_walks':
                    $feed->filterWalks();  // filter/remove walks
                    break;
                case 'by_events':
                    $feed->filterEvents();  // filter/remove events
                    break;
                case 'by_groups':
                    $groups = array_column($filter->keep_groups, 'code');
                    $feed->filterGroups($groups); // removes any walks for groups not in the list
                    break;
                case 'by_cancelled':
                    $feed->filterCancelled(); // removes walks that have been cancelled  
                    break;
                case 'by_outside_area':
                    $feed->filterDistanceFromLatLong($filter->filterlatitude, $filter->filterlongitude, $filter->filterradius);
                    break;
                case 'by_dist_shorter':
                    $feed->filterWalksDistanceBelow($filter->dist_shorter);
                    break;
                case 'by_dist_longer':
                    $feed->filterWalksDistanceAbove($filter->dist_longer);
                    break;
                case 'by_title':
                    if ($filter->titlecontains === 'contains') {
                        $feed->filterTitleContains($filter->titletext, $filter->titlekeep);
                    } else {
                        $feed->filterTitle($filter->titletext, $filter->titlekeep);
                    }
                    break;
                case 'by_flags':
                    $feed->filterFlags($filter->flags, $filter->flagskeep === 'keep');
                    break;
            }
        }
    }

    private function getEventsDisplay($type) {
        switch ($type) {
            case 'future_display':
                $display = new Display();
                break;
            case 'future_nextwalks':
                $display = new Nextwalks();
                break;
            case 'future_blog':
                $display = new Blog();
                break;
            case "future_calendar":
                $display = new Calendar();
                break;
            case "future_addtocalendar":
                $display = new IcsDownload();
                break;
            case 'future_table':
                $display = new Walktable();
                break;
            case 'future_list':
                $display = new Simplelist();
                break;

            case 'future_map':
                $display = new Mapmarker();
                break;
            case 'future_fulldetails':
                $display = new Fulldetails();
                break;
            case 'future_BU51a':
                $display = new BU01Fulldetails();
                break;
            case 'future_BU51b':
                $display = new BU01Groupstabs();
                break;
            case 'future_BU51c':
                $display = new BU01Micronextwalks();
                break;
            case 'future_BU51d':
                $display = new BU01Tabs();
                break;
            case 'future_MLa':
                $display = new MLPrint();
                break;
            case 'future_NSa':
                $display = new NSWalksprinted();
                break;
            case 'future_SR02a':
                $display = new SR02Display();
                break;
            case 'future_SR02b':
                $display = new Sr02nextwalks();
                break;
            case 'future_SR02c':
                $display = new SR02Table2();
                break;
            default:
                return new Diagnostics();
        }
        return $display;
    }

    private function setClasses($type, $display, $data) {
        switch ($type) {
            case 'future_nextwalks':
            case 'future_blog':
            case 'future_table':
            case 'future_list':
            case 'future_fulldetails':
                if (strlen($data->walks_class ?? '') > 0) {
                    $display->setWalksClass($data->walks_class);
                }
                if (strlen($data->walk_class ?? '') > 0) {
                    $display->setWalkClass($data->walk_class);
                }
                break;
        }
    }

    private function customise_title($feed, $data) {
        if (property_exists($data, 'custom_title')) {
            foreach ($data->custom_title as $item) {
                $feed->appendWalkTitle($item->code, $item->text);
            }
        }
    }

    private function customiseWalksDisplay($display, $option, $data) {
        switch ($option) {
            case 'future_nextwalks':
                if (strlen($data->custom_nextwalks) > 0) {
                    $display->customFormat($data->custom_nextwalks);
                }
                break;
            case 'future_blog':
                $display->setFullCount($data->blog_full_items ?? 1);
                $display->setSections($data->blog_sections ?? []);
                $display->setMapMode($data->blog_full_map ?? 'button');
                $display->setCustomFull($data->custom_blog_full ?? '');
                $display->setPanelCount($data->blog_panel_items ?? 8);
                $display->setPanelMode($data->blog_panel_mode ?? 'flexible');
                $display->setPanelMinWidth($data->blog_panel_minwidth ?? 280);
                $display->setPanelColumns($data->blog_panel_columns ?? 3);
                $display->setPanelImage((string) ($data->blog_panel_image ?? '1') !== '0');
                $display->setPanelArrows((string) ($data->blog_panel_arrows ?? '1') !== '0');
                $display->setCustomPanel($data->custom_blog_panel ?? '');
                $display->setPanelItems($data->blog_panel_fields ?? []);
                $display->setPanelLabels((string) ($data->blog_panel_labels ?? '1') !== '0');
                $display->setDefaultImage($data->blog_default_image ?? 'none');
                $display->setPhotoHeight($data->blog_photo_height ?? 300);
                $display->setTitles($data->blog_title_full ?? '', $data->blog_title_panel ?? '', $data->blog_title_links ?? '');
                $display->setTitleLevel($data->blog_title_level ?? 2);
                $display->setLinkCount($data->blog_link_items ?? 6);
                $display->setCustomLinks($data->custom_blog_links ?? '');
                break;
            case 'future_display':
                if (property_exists($data, 'custom_tabs') && count($data->custom_tabs) > 0) {
                    $display->setTabOrder($data->custom_tabs);
                }
                if ($data->custom_grades && strlen($data->custom_grades) > 0) {
                    $display->setCustomGradesFormat($data->custom_grades);
                }
                if ($data->custom_list && strlen($data->custom_list) > 0) {
                    $display->setCustomListFormat($data->custom_list);
                }
                if ($data->custom_calendar && strlen($data->custom_calendar) > 0) {
                    $display->setCustomCalendarFormat($data->custom_calendar);
                }
                if ($data->custom_table && count($data->custom_table) > 0) {
                    // rename property
                    foreach ($data->custom_table as $item) {
                        $item->title = $item->heading;
                        unset($item->heading);
                        $item->items = $item->layout;
                        unset($item->layout);
                    }
                    $display->setCustomTableFormat($data->custom_table);
                }
                break;

            case 'future_table':
                if (count($data->custom_table) > 0) {
                    // rename property
                    $temp = json_encode($data->custom_table);
                    $temp2 = json_decode($temp, true);
                    foreach ($temp2 as $key => $item) {
                        $temp2[$key]['title'] = $temp2[$key]['heading'];
                        $temp2[$key]['items'] = $temp2[$key]['layout'];
                        unset($temp2[$key]['heading']);
                        unset($temp2[$key]['layout']);
                    }
                    $display->customFormat($temp2);
                }
                break;
            case 'future_list':
                if (strlen($data->custom_simplelist) > 0) {
                    $display->customFormat($data->custom_simplelist);
                }
                break;
            case 'future_fulldetails':
                if (strlen($data->custom_fulldetails) > 0) {
                    $display->customFormat($data->custom_fulldetails);
                }
                break;
            case 'future_calendar':
                if ($data->calendar_size) {
                    $display->setSize(intval($data->calendar_size));
                }
                break;
        }
    }

    private function displayPlot() {
        $data = $this->data;
        $latitude = $data->routes_latitude;
        $longitude = $data->routes_longitude;
        $zoomlevel = $data->routes_zoomlevel;
        $routingkey = $data->routes_smartroutingkey;
        if ($routingkey) {
            License::OpenRoutingServiceKey($routingkey);
        }
        $display = new Mapdraw();
        $display->setCenter($latitude, $longitude, $zoomlevel); // lat, long, zoom
        $display->display();
    }

    private function displayRoutesSingle() {
        $data = $this->data;
        $gpx = $data->routes_file;
        $linecolour = $data->routes_linecolour;
        $download = $data->routes_download;

        $map = new GpxMap();
        $map->linecolour = $linecolour;
        $map->addDownloadLink = $download; //  "None" no download link, "Users" link if registered user; "Public" link for public
        $map->displayPath($gpx);
    }

    private function displayRoutesMulti() {
        $data = $this->data;
        $gpx = $data->routes_folder;
        $linecolour = $data->routes_linecolour;
        $download = $data->routes_download;
        $displaytitle = (boolean) $data->routes_displaytitle;
        $previouswalks = (boolean) $data->routes_displayasprevious;
        $map = new GpxMaplist(); // standard software to list GPX files from folder.
        $map->addDownloadLink = $download; //  "None" no download link, "Users" link if registered user; "Public" link for public
        $map->linecolour = $linecolour; // optional - specify colour to be used for the route line
        $map->displayTitle = $displaytitle;
        $map->displayAsPreviousWalks = $previouswalks;

        $map->folder = $gpx;
        $map->display();
    }

    private function displayTableCSV() {
        $data = $this->data;
        $folder = $data->table_file;
        $list = new CsvList($folder);
        $this->setTableMarkerOptions($list);

        if (($data->table_csv_optionsmode ?? 'file') === 'form') {
            $options = $data->table_options;
// convert from object to arrays
            $options = json_encode($options);
            $options = json_decode($options, true) ?: [];
            $list->setOptions($options);
        }

        $list->display();
    }

    private function displayTableSQL() {
        $data = $this->data;
        $select = $data->table_sqlselect;
        $options = $data->table_options;
// convert from object to arrays
        $options = json_encode($options);
        $options = json_decode($options, true) ?: [];
        $list = new SqlList($select);
        $this->setTableMarkerOptions($list);
        $list->setOptions($options);
        $list->display();
    }

    private function displayTableJson() {

        $data = $this->data;
        $json = $data->table_json;
        $options = $data->table_options;
// convert from object to arrays
        $options = json_encode($options);
        $options = json_decode($options, true) ?: [];
        $list = new JsonList($json);
        $this->setTableMarkerOptions($list);
        $list->setOptions($options);
        $list->display();
    }

    private function setTableMarkerOptions($list) {
        $data = $this->data;
        switch ($data->table_markertype) {
            case "text":
                $markerOptions = (object) [
                            'icons' => (object) [
                                'type' => 'text',
                                'column' => $data->table_markercolumn,
                                'class' => $data->table_textmarkerclass
                            ]
                ];
                break;
            case 'icons':
                $markerOptions = (object) [
                            'icons' => (object) [
                                'type' => 'icon',
                                'column' => $data->table_markercolumn,
                                'values' => new \stdClass()
                            ]
                ];
                foreach ($data->table_iconmarkers as $value) {
                    $prop = $value->value;
                    $val = $value->icon;
                    $markerOptions->icons->values->$prop = $val;
                }
                break;
            default:
                return;
        }
        $list->setMarkerOptions($markerOptions);
    }

    private function displayDocumentsFolders() {
        foreach ($this->data->document_sources as $source) {
            if (!$source->enabled) {
                continue;
            }

            if ($source->introduction === '1' && $source->introductiontext) {
                echo $source->introductiontext;
            }
//    $exts = $source->documents_exts;
            $exts = array_column($source->documents_exts, 'Extension');
            $folder = $source->document_folder;
            $asc = $source->sort === "0";
            $dir = new DirList($exts);
            if ($asc) {
                $dir->listItems($folder, DirList::ASC);
            } else {
                $dir->listItems($folder, DirList::DESC);
            }
        }
    }

    /**
     * Past Walks / Routes - Blog: a paginated, category-filterable listing
     * of records, each rendered with the Intro template, plus an automatic
     * "Read more" link to that record's own fixed single-item page.
     *
     * @param   string  $recordType  'pastwalk' or 'route'.
     *
     * @since   1.0.0
     */
    private function displayRecordBlog(string $recordType): void {
        $this->loadRecordDisplayAssets();
        $data = $this->data;
        $prefix = $recordType === 'route' ? 'rt_' : 'pw_';

        $catid = (int) ($data->{$prefix . 'catid'} ?? 0);

// Three-section "article blog" layout (mirrors com_content's
// classic Blog: leading/full items, then intro items in columns,
// then plain links) - the page size is simply the sum of the
// three counts, there's no separate "items per page" for Blog.
        $numFull = (int) ($data->{$prefix . 'blog_full_items'} ?? 1);
        $numIntro = (int) ($data->{$prefix . 'blog_intro_items'} ?? 4);
        $introColumns = (int) ($data->{$prefix . 'blog_intro_columns'} ?? 2);
        $numLinks = (int) ($data->{$prefix . 'blog_link_items'} ?? 4);

        $limit = max(1, $numFull + $numIntro + $numLinks);
        $limitstart = (int) Factory::getApplication()->input->getUint('limitstart', 0);

        $items = FrontendHelper::getPublishedItems($recordType, $catid, $limitstart, $limit);
        $total = FrontendHelper::getPublishedItemCount($recordType, $catid);

        if (empty($items)) {
            echo '<p class="ra-no-items">' . htmlspecialchars(Text::_('COM_RA_LIBRARY_NO_ITEMS_FOUND')) . '</p>';
            return;
        }

        $introTemplate = self::getGlobalItemTemplateIntro($recordType);
        $moreTemplate = self::getGlobalItemTemplateMore($recordType);

        echo ItemRenderer::renderBlogSections(
                $recordType,
                $items,
                $introTemplate,
                $moreTemplate,
                $numFull,
                $numIntro,
                $introColumns,
                $numLinks
        );

        $this->renderPagination($total, $limitstart, $limit);
    }

    /**
     * Past Walks / Routes - List: a paginated, category-filterable, compact
     * one-line-per-item listing, using the per-display custom_*_list
     * template (falls back to a sensible default if left blank). Each row
     * links to that record's own fixed single-item page.
     *
     * @param   string  $recordType  'pastwalk' or 'route'.
     *
     * @since   1.0.0
     */
    private function displayRecordList(string $recordType): void {
        $this->loadRecordDisplayAssets();
        $data = $this->data;
        $prefix = $recordType === 'route' ? 'rt_' : 'pw_';

        $catid = (int) ($data->{$prefix . 'catid'} ?? 0);
        $limit = (int) ($data->{$prefix . 'items_per_page'} ?? 10);
        $limitstart = (int) Factory::getApplication()->input->getUint('limitstart', 0);

        $items = FrontendHelper::getPublishedItems($recordType, $catid, $limitstart, $limit);
        $total = FrontendHelper::getPublishedItemCount($recordType, $catid);

        if ($recordType === 'route') {
            $templateString = (string) ($data->custom_routes_list ?? '');
            $default = '{title_link}{, ?category}{, ?national_grade}{, ?distance_miles}';
        } else {
            $templateString = (string) ($data->custom_pastwalks_list ?? '');
            $default = '{walk_date}: {title_link}{, ?national_grade}{, ?distance_miles}';
        }

        if (empty($items)) {
            echo '<p class="ra-no-items">' . htmlspecialchars(Text::_('COM_RA_LIBRARY_NO_ITEMS_FOUND')) . '</p>';
            return;
        }

        echo '<div class="ra-list">';

        foreach ($items as $item) {
            $values = ItemRenderer::buildValues($item, $recordType);
            echo '<div class="ra-list-item">' . ItemRenderer::renderList($templateString, $default, $values) . '</div>';
        }

        echo '</div>';

        $this->renderPagination($total, $limitstart, $limit);
    }

    /**
     * Past Walks / Routes - Table, and Map+Table: a sortable table of the
     * basic details, each row (and, for Map+Table, each marker) linking to
     * the full item - reuses the same "sortable table with an optional Map
     * tab" JS component (ra.display.tableList, in ramblerstable.js) the
     * component's existing generic SQL/JSON/CSV table display options
     * already use, built here from ordinary PHP data rather than a raw SQL
     * string, so a real "view this item" link can use Joomla's own SEF
     * routing (FrontendHelper::getItemUrl()) - something only PHP can
     * compute, not the SQL query itself.
     *
     * @param   string    $recordType     'pastwalk' or 'route'.
     * @param   bool      $withMap        True for Map+Table, false for Table only.
     * @param   int|null  $catidOverride  Used when called from the Category Tree leaf
     *                                    (its own current category, not the pw_catid/rt_catid
     *                                    field the standalone Table/Map+Table options use) -
     *                                    null falls back to that field.
     *
     * @since   1.0.0
     */
    private function displayRecordTable(string $recordType, bool $withMap, ?int $catidOverride = null): void {
        $this->loadRecordDisplayAssets();
        $data = $this->data;
        $prefix = $recordType === 'route' ? 'rt_' : 'pw_';
        $catid = $catidOverride ?? (int) ($data->{$prefix . 'catid'} ?? 0);

        $items = FrontendHelper::getPublishedItems($recordType, $catid, 0, 0);

        if (empty($items)) {
            echo '<p class="ra-no-items">' . htmlspecialchars(Text::_('COM_RA_LIBRARY_NO_ITEMS_FOUND')) . '</p>';

            return;
        }

        if (!$withMap) {
            $this->renderItemsTable($recordType, $items, false);

            return;
        }

// Split mapped/unmapped BEFORE rendering - the "unmapped" group is
// rendered as its own plain table underneath (no latitude/longitude
// columns, so it never gets a Map tab of its own), rather than
// trying to make one table cope with rows that can't have a
// marker. See Table/Sql.php (the generic version of this same
// component) for why this couldn't just be one table with some
// rows silently missing a marker - Columns/Column has no concept
// of a "this row has no location" state within a single table.
        $mapped = [];
        $unmapped = [];

        foreach ($items as $item) {
            if ($item->start_latitude !== null && $item->start_longitude !== null) {
                $mapped[] = $item;
            } else {
                $unmapped[] = $item;
            }
        }

        if (!empty($mapped)) {
            $this->renderItemsTable($recordType, $mapped, true);
        }

        if (!empty($unmapped)) {
            echo '<h3 class="ra-table-unmapped-heading">' . htmlspecialchars(Text::_('COM_RA_LIBRARY_TABLE_UNMAPPED_HEADING')) . '</h3>';
            $this->renderItemsTable($recordType, $unmapped, false);
        }
    }

    /**
     * Builds and displays one sortable table (+ Map tab, if $withMap) from
     * an already-fetched list of items - the shared rendering engine behind
     * displayRecordTable(), called once for a normal Table display, and up
     * to twice for Map+Table (once for the mapped items with their marker
     * columns, once more - list-only, no map columns at all - for anything
     * with no usable location).
     *
     * @param   string  $recordType  'pastwalk' or 'route'.
     * @param   array   $items       Rows from FrontendHelper::getPublishedItems().
     * @param   bool    $withMap     Whether to add latitude/longitude columns (i.e. offer a Map tab).
     *
     * @since   1.0.0
     */
    private function renderItemsTable(string $recordType, array $items, bool $withMap): void {
        $openInModal = (int) ComponentHelper::getParams('com_ra_library')->get('open_items_in_modal', 0) === 1;

        $columns = new TableColumns();

        $titleColumn = new TableColumn('Title');
        $titleColumn->addOptions('table popup sort left');
        $columns->addColumn($titleColumn);

        $dateColumn = null;
        $leaderColumn = null;
        $categoryColumn = null;

        if ($recordType === 'pastwalk') {
            $dateColumn = new TableColumn('Date');
            $dateColumn->addOptions('table popup sort date left');
            $columns->addColumn($dateColumn);

            $leaderColumn = new TableColumn('Leader');
            $leaderColumn->addOptions('table popup left');
            $columns->addColumn($leaderColumn);
        } else {
            $categoryColumn = new TableColumn('Category');
            $categoryColumn->addOptions('table popup sort left');
            $columns->addColumn($categoryColumn);
        }

        $gradeColumn = new TableColumn('Grade');
        $gradeColumn->addOptions('table popup sort left');
        $columns->addColumn($gradeColumn);

        $distanceColumn = new TableColumn('Distance Miles');
        $distanceColumn->addOptions('table popup sort real left');
        $columns->addColumn($distanceColumn);

        $latColumn = null;
        $longColumn = null;

        if ($withMap) {
            $latColumn = new TableColumn('Latitude');
            $latColumn->addOptions('latitude');
            $columns->addColumn($latColumn);

            $longColumn = new TableColumn('Longitude');
            $longColumn->addOptions('longitude');
            $columns->addColumn($longColumn);
        }

        foreach ($items as $item) {
            $id = (int) $item->id;
            $url = FrontendHelper::getItemUrl($recordType, $id);
            $previewAttrs = $openInModal ? ' class="ra-item-preview-link"' : '';
            $titleColumn->addValue(
                    '<a href="' . htmlspecialchars($url) . '"' . $previewAttrs . '>' . htmlspecialchars((string) ($item->title ?? '')) . '</a>'
            );

            if ($recordType === 'pastwalk') {
                $walkDate = (string) ($item->walk_date ?? '');
                $ts = ($walkDate !== '' && $walkDate !== '0000-00-00') ? strtotime($walkDate) : false;
                $dateColumn->addValue($ts ? date('j M Y', $ts) : '');
                $leaderColumn->addValue(htmlspecialchars((string) ($item->walk_leader ?? '')));
            } else {
                $categoryColumn->addValue(htmlspecialchars((string) ($item->category_title ?? '')));
            }

            $gradeColumn->addValue(htmlspecialchars((string) ($item->national_grade ?? '')));

            $distanceKm = isset($item->distance_km) ? (float) $item->distance_km : 0.0;
            $distanceColumn->addValue($distanceKm > 0 ? number_format($distanceKm / 1.609344, 1) : '');

            if ($withMap) {
                $latColumn->addValue($item->start_latitude !== null ? (string) $item->start_latitude : '');
                $longColumn->addValue($item->start_longitude !== null ? (string) $item->start_longitude : '');
            }
        }

        $map = new LeafletMap();

// \stdClass rather than an anonymous class ("new class {}") - both
// work as an arbitrary bag of properties for setDataObject() to
// json_encode(), but an anonymous class doesn't declare any of the
// properties being set below, which PHP 8.2+ deprecates ("Creation
// of dynamic property ... is deprecated"). stdClass is explicitly
// exempt from that deprecation.
        $data = new \stdClass();
        $data->markerOptions = null;
        $data->list = $columns;
        $data->paginationDefault = 15;

        if ($withMap) {
// ra.display.tableList (ramblerstable.js) - the Map+Table tabs
// component also used by the existing generic SQL/JSON/CSV
// table display options.
            $map->options->cluster = true;
            $map->options->fullscreen = true;
            $map->options->mouseposition = true;
            $map->options->settings = true;
            $map->options->mylocation = true;
            $map->options->rightclick = true;
            $map->options->fitbounds = true;
            $map->options->print = true;
            $map->help_page = 'listofitems.html';

            $map->setCommand('ra.display.tableList.display');
            $map->setDataObject($data);
            $map->display();

            Load::addScript('media/com_ra_library/js/leaflet/table/ramblerstable.js');
            Load::addStyleSheet('media/com_ra_library/js/leaflet/table/style.css');
            Load::addStyleSheet('media/com_ra_library/css/ramblerslibrary.css');
            Load::addScript('media/com_ra_library/js/ra.tabs.js');
            Load::addStyleSheet('media/com_ra_library/css/ra.tabs.css');
        } else {
// ra.display.plainTable (ra.plaintable.js) - a plain sortable
// table, no Map tab at all. NOT ra.display.tableList: that
// component's load() unconditionally tries to build a Leaflet
// map into the Map tab's container even when there's no
// location data and that tab is disabled - and a disabled
// tab's container is never actually created, so it fails
// trying to build a map inside a container that doesn't exist
// ("root is null"). Sidestepping that entirely is simpler and
// safer than changing the shared component both options use.
            $map->setCommand('ra.display.plainTable');
            $map->setDataObject($data);
            $map->display();

            Load::addScript('media/com_ra_library/js/ra.plaintable.js');
            Load::addStyleSheet('media/com_ra_library/js/leaflet/table/style.css');
        }

        Load::addStyleSheet('media/com_ra_library/js/vendors/cvList/cvList.css');
        Load::addScript('media/com_ra_library/js/vendors/cvList/cvList.js');
    }

    /**
     * Past Walks / Routes - List all Categories in a Category Tree: mirrors
     * com_content's menu type of the same name. Starts at a configured root
     * category (or the true top level) and lets the visitor click down
     * through subcategories one level at a time. Once a category with no
     * subcategories of its own is reached, its items are shown as a List or
     * a Blog (admin's choice), exactly like the plain List/Blog displays.
     *
     * @param   string  $recordType  'pastwalk' or 'route'.
     *
     * @since   1.0.0
     */
    private function displayRecordCategoryTree(string $recordType): void {
        $this->loadRecordDisplayAssets();
        $data = $this->data;
        $prefix = $recordType === 'route' ? 'rt_' : 'pw_';

        $rootCatid = (int) ($data->{$prefix . 'categorytree_rootcat'} ?? 0);
        $style = (string) ($data->{$prefix . 'categorytree_style'} ?? 'list');
        $limit = (int) ($data->{$prefix . 'categorytree_items_per_page'} ?? 10);

        $app = Factory::getApplication();
        $catid = $app->input->getInt('catid', $rootCatid);

        $categoryInfo = $catid > 0 ? FrontendHelper::getCategoryInfo($catid) : null;
        $heading = $categoryInfo ? $categoryInfo->title : Text::_('COM_RA_LIBRARY_CATEGORYTREE_ALL_CATEGORIES');

        echo '<div class="ra-categorytree">';
        echo '<h2 class="ra-categorytree-heading">' . htmlspecialchars($heading) . '</h2>';

// Breadcrumb trail (root down to here) - not shown once we're back
// at the configured root. Replaces the old single "Up one level"
// link with a full clickable trail, each ancestor a link except
// the current category itself.
        if ($catid > 0 && $catid !== $rootCatid) {
            echo $this->buildCategoryTreeBreadcrumb($recordType, $catid, $rootCatid);
        }

        $children = FrontendHelper::getChildCategories($recordType, $catid);

        if (!empty($children)) {
            if ($recordType === 'route') {
                $branchTemplateString = (string) ($data->custom_routes_categorytree_branch ?? '');
            } else {
                $branchTemplateString = (string) ($data->custom_pastwalks_categorytree_branch ?? '');
            }

            echo '<div class="ra-categorytree-branches">';

            foreach ($children as $child) {
                $childUrl = FrontendHelper::getCategoryTreeUrl((int) $child->id);
                $values = ItemRenderer::buildCategoryBranchValues($child, $childUrl);
                echo '<div class="ra-categorytree-item">'
                . ItemRenderer::renderList($branchTemplateString, self::DEFAULT_CATEGORYTREE_BRANCH_TEMPLATE, $values)
                . '</div>';
            }

            echo '</div>';
            echo '</div>';

            return;
        }

// Leaf category (or top level with no categories at all) - show its
// items. Table/Map+Table are handled separately, before the
// paginated fetch below, since they fetch and paginate everything
// themselves (via displayRecordTable()/renderItemsTable(), the
// same methods the standalone Table/Map+Table display options
// use) rather than using this method's server-side pagination.
        if ($style === 'table' || $style === 'maptable') {
            $this->displayRecordTable($recordType, $style === 'maptable', $catid);
            echo '</div>';

            return;
        }

        $limitstart = (int) $app->input->getUint('limitstart', 0);
        $items = FrontendHelper::getPublishedItems($recordType, $catid, $limitstart, $limit);
        $total = FrontendHelper::getPublishedItemCount($recordType, $catid);

        if (empty($items)) {
            echo '<p class="ra-no-items">' . htmlspecialchars(Text::_('COM_RA_LIBRARY_NO_ITEMS_FOUND')) . '</p>';
            echo '</div>';

            return;
        }

        if ($style === 'blog') {
            $introTemplate = self::getGlobalItemTemplateIntro($recordType);
            echo '<div class="ra-blog">';
            foreach ($items as $item) {
                $values = ItemRenderer::buildValues($item, $recordType);
                $itemIntro = ItemRenderer::resolveTemplate($item, 'template_intro_override', $introTemplate);
                echo '<div class="ra-blog-item">' . ItemRenderer::renderBlogIntro($itemIntro, $values) . '</div>';
            }
            echo '</div>';
        } else {
            if ($recordType === 'route') {
                $templateString = (string) ($data->custom_routes_categorytree_list ?? '');
                $default = '{title_link}{, ?category}{, ?national_grade}{, ?distance_miles}';
            } else {
                $templateString = (string) ($data->custom_pastwalks_categorytree_list ?? '');
                $default = '{walk_date}: {title_link}{, ?national_grade}{, ?distance_miles}';
            }

            echo '<div class="ra-list">';
            foreach ($items as $item) {
                $values = ItemRenderer::buildValues($item, $recordType);
                echo '<div class="ra-list-item">' . ItemRenderer::renderList($templateString, $default, $values) . '</div>';
            }
            echo '</div>';
        }

        $this->renderPagination($total, $limitstart, $limit);

        echo '</div>';
    }

    /**
     * The Category Tree display's own breadcrumb trail (its "you are
     * here" navigation) - the configured root category (or "All
     * Categories" if the root is 0, i.e. the whole real tree) down through
     * every ancestor to the current category, each a link to that
     * position on THIS SAME page (via FrontendHelper::getCategoryTreeUrl()
     * - unlike the Single Item page's breadcrumb, this one is always
     * self-referencing since it's already on the Category Tree display).
     * The current category itself is shown as plain text, not a link.
     *
     * @param   string  $recordType  'pastwalk' or 'route'.
     * @param   int     $catid       The current (leaf/branch) category id.
     * @param   int     $rootCatid   This display's configured root - 0 means the whole real tree.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private function buildCategoryTreeBreadcrumb(string $recordType, int $catid, int $rootCatid): string {
        $chain = FrontendHelper::getCategoryBreadcrumbChain($catid);

        if ($rootCatid > 0) {
// Drop any ancestors above the configured root - they're not
// part of this display's browsable subtree.
            $startIndex = null;

            foreach ($chain as $index => $category) {
                if ((int) $category->id === $rootCatid) {
                    $startIndex = $index;
                    break;
                }
            }

            $chain = $startIndex !== null ? array_slice($chain, $startIndex) : $chain;
        } else {
// Whole real tree - prepend a synthetic "All Categories" crumb
// linking back to the top (catid=0).
            $allCategories = new \stdClass();
            $allCategories->id = 0;
            $allCategories->title = Text::_('COM_RA_LIBRARY_CATEGORYTREE_ALL_CATEGORIES');
            array_unshift($chain, $allCategories);
        }

        if (empty($chain)) {
            return '';
        }

        $lastIndex = count($chain) - 1;
        $parts = [];

        foreach ($chain as $index => $category) {
            $title = htmlspecialchars($category->title);

            if ($index === $lastIndex) {
// Current position - plain text, not a link.
                $parts[] = $title;
            } else {
                $url = FrontendHelper::getCategoryTreeUrl((int) $category->id);
                $parts[] = '<a href="' . htmlspecialchars($url) . '">' . $title . '</a>';
            }
        }

        return '<nav class="ra-categorytree-breadcrumb" aria-label="'
                . htmlspecialchars(Text::_('COM_RA_LIBRARY_CATEGORY_BREADCRUMB_LABEL')) . '">'
                . implode('<span class="ra-category-breadcrumb-sep">&raquo;</span>', $parts)
                . '</nav>';
    }

    /**
     * Fallback text - identical to the "default" attribute on the
     * pastwalk_item_template_intro/_more / route_item_template_intro/_more
     * fields in administrator/config.xml. ComponentHelper::getParams()
     * only returns what's actually been SAVED into #__extensions.params -
     * it does not retroactively apply a form field's XML default for a key
     * that has never been saved, so until an admin opens Components >
     * Ra_library > Options and saves at least once, these keys simply
     * don't exist in storage yet. Falling back to '' in that gap would
     * silently render nothing - fall back to the same template text the
     * Options screen would show instead.
     *
     * @since   1.0.0
     */

    /**
     * Default layout for one category "branch" row in the Category Tree
     * display - shown for every subcategory while browsing down through
     * the tree, and overridable per Display Option row via
     * custom_pastwalks_categorytree_branch / custom_routes_categorytree_branch.
     *
     * @since   1.0.0
     */
    private const DEFAULT_CATEGORYTREE_BRANCH_TEMPLATE = '<div class="ra-categorytree-featured">{featured_image}</div><h3>{title_link}</h3><p class="ra-categorytree-meta">{item_count} here, {total_item_count} in this branch</p>{if:description}<div class="ra-categorytree-desc">{description}</div>{/if}';
    private const DEFAULT_PASTWALK_INTRO_TEMPLATE = '<div class="ra-featured-float">{featured_image}</div><h2>{title_link}</h2><p class="ra-meta">{walk_date_dow}{, ?walk_leader}{, ?national_grade}{, ?distance_miles}</p><div class="ra-description">{description}</div>';
    private const DEFAULT_PASTWALK_MORE_TEMPLATE = '{if:custom_fields}{custom_fields}{/if}
{if:image_grid}<h3>Photos</h3>{image_grid}{/if}
{if:document_list}<h3>Documents</h3>{document_list}{/if}
{if:gpx_map_list}<h3>Additional routes</h3>{gpx_map_list}{/if}
{if:gpx_map}<h3>Route</h3>{gpx_map}{/if}';
    private const DEFAULT_ROUTE_INTRO_TEMPLATE = '<div class="ra-featured-float">{featured_image}</div><h2>{title_link}</h2><p class="ra-meta">{national_grade}{, ?distance_miles}</p><div class="ra-description">{description}</div>';
    private const DEFAULT_ROUTE_MORE_TEMPLATE = '{if:custom_fields}{custom_fields}{/if}
{if:image_grid}<h3>Photos</h3>{image_grid}{/if}
{if:route_points_list}<h3>Route points</h3>{route_points_list}{/if}
{if:document_list}<h3>Documents</h3>{document_list}{/if}
{if:gpx_map_list}<h3>Additional routes</h3>{gpx_map_list}{/if}
{if:gpx_map}<h3>Route</h3>{gpx_map}{/if}';

    /**
     * The Intro half of the global item template for a record type -
     * shared, unmodified, by Blog (the whole of what's shown there) and
     * Single Item (the first part of what's shown there). Public + static
     * so the fixed Pastwalk/Route single-item views (see PastwalkModel)
     * can call it directly without going through a DisplayHelper instance.
     *
     * @since   1.0.0
     */
    public static function getGlobalItemTemplateIntro(string $recordType): string {
        $params = ComponentHelper::getParams('com_ra_library');

        if ($recordType === 'route') {
            $template = (string) $params->get('route_item_template_intro', '');

            return trim($template) !== '' ? $template : self::DEFAULT_ROUTE_INTRO_TEMPLATE;
        }

        $template = (string) $params->get('pastwalk_item_template_intro', '');

        return trim($template) !== '' ? $template : self::DEFAULT_PASTWALK_INTRO_TEMPLATE;
    }

    /**
     * The More half of the global item template for a record type - only
     * ever shown on the Single Item page, after the Intro half.
     *
     * @since   1.0.0
     */
    public static function getGlobalItemTemplateMore(string $recordType): string {
        $params = ComponentHelper::getParams('com_ra_library');

        if ($recordType === 'route') {
            $template = (string) $params->get('route_item_template_more', '');

            return trim($template) !== '' ? $template : self::DEFAULT_ROUTE_MORE_TEMPLATE;
        }

        $template = (string) $params->get('pastwalk_item_template_more', '');

        return trim($template) !== '' ? $template : self::DEFAULT_PASTWALK_MORE_TEMPLATE;
    }

    /**
     * Shared pagination footer for the Blog/List displays.
     *
     * @since   1.0.0
     */
    private function renderPagination(int $total, int $limitstart, int $limit): void {
// Work out "is there more than one page" with plain arithmetic
// from values already in hand, rather than asking the Pagination
// object to introspect itself via a CMSObject-style get('pages.total')
// accessor - that generic accessor pattern is being removed from
// Joomla core as part of the Joomla 6 CMSObject cleanup, so this
// stays correct regardless of what Pagination's own API looks like.
        if ($limit > 0 && $total > $limit) {
            $pagination = new Pagination($total, $limitstart, $limit);
            echo '<div class="ra-pagination">' . $pagination->getPagesLinks() . '</div>';
        }
    }

    /**
     * Loads the shared stylesheet for the Past Walks / Routes Blog, List and
     * Single Item displays - only registered when one of those three is
     * actually being shown, not unconditionally for every display type.
     *
     * @since   1.0.0
     */
    private function loadRecordDisplayAssets(): void {
        Load::addStyleSheet('media/com_ra_library/css/frontend.css');
// Image gallery lightbox (see media/js/ra.lightbox.js) - its own
// dedicated stylesheet, loaded wherever frontend.css is, since a
// custom template could embed {image_grid} in a Blog/List/Category
// Tree layout too, not just the Single Item view's default More
// template. Kept as its own file (rather than folded into
// frontend.css) so the Walks Manager display (which also uses this
// same lightbox now - see ra.walk.js) can load just this, without
// pulling in Past Walks/Routes-specific layout CSS it doesn't need.
//
// The lightbox plugs into ra.js's existing ra.modals popup system
// rather than building its own overlay, so ra.js (behaviour) and
// ramblerslibrary.css (the modal's own chrome) both need to be
// loaded here too - Load::addScript()/addStyleSheet() key each
// asset by its file path, so this is safe to call even on a page
// that also loads them some other way (e.g. a Leaflet map display),
// it won't be included twice.
        Load::addScript('media/com_ra_library/js/ra.js');
        Load::addStyleSheet('media/com_ra_library/css/ramblerslibrary.css');
        Load::addStyleSheet('media/com_ra_library/css/ra.lightbox.css');
        Load::addScript('media/com_ra_library/js/ra.lightbox.js');
// Image grid pagination (see media/js/ra.imagegrid.js) - only ever
// does anything on a grid that actually has a pager (see
// ItemRenderer::buildImageGrid()), so harmless to load unconditionally.
        Load::addScript('media/com_ra_library/js/ra.imagegrid.js');

// "Open items in a popup" (Components > Ra_library > Options >
// Item Preview) - loaded here rather than only when this specific
// page happens to render a .ra-item-preview-link itself, since
// every one of Blog/List/Category Tree/Table/Map+Table routes
// through this method and any of them can contain one.
        if ((int) ComponentHelper::getParams('com_ra_library')->get('open_items_in_modal', 0) === 1) {
            Load::addScript('media/com_ra_library/js/ra.itempreview.js');

// Primes the full Leaflet/ra.js/ra.gpxlightbox.js stack even
// if THIS page never itself shows a map - a modal-fetched item
// might have its own {gpx_map} (needs ra.leafletmap /
// ra.bootstrapper already defined before its bootstrap script
// runs) or {gpx_map_list} (needs ra.gpxlightbox.js's click
// handling already registered) - reuses
// ItemRenderer::loadGpxMapAssets() rather than a separate,
// narrower priming call, so both are covered the same way
// {gpx_map}/{gpx_map_list} already prime themselves on a page
// that renders one directly.
            ItemRenderer::loadGpxMapAssets();
        }
    }
}
