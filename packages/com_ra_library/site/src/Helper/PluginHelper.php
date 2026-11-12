<?php

namespace Ramblers\Component\Ra_library\Site\Helper;

\defined('_JEXEC') or die;

use Ramblers\Component\Ra_library\Site\Library\Directory\DirList;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Walkcsv;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Gpx\Map as GpxMap;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Mapcompare;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Mapplaces;
use Joomla\CMS\Factory;

class PluginHelper {

    /**
     * Folders (under the site root) that the inline tags may read from - the
     * same two roots the "Choose a folder" field in a Library Display offers.
     */
    private const ALLOWED_ROOTS = ['images', 'files'];

    /** File types listed by {ra_library:documents} when no types="..." is given. */
    private const DEFAULT_DOCUMENT_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    /** Never listed, whatever an article asks for (scripts / server config). */
    private const BLOCKED_TYPES = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'inc', 'pl', 'py', 'cgi', 'sh',
        'exe', 'bat', 'cmd', 'js', 'html', 'htm', 'htaccess', 'htpasswd', 'ini',
    ];

    /**
     * The component's site language file is only loaded automatically when the
     * page is a com_ra_library page; in an article or module it has to be asked for.
     */
    private static function loadLanguage(): void {
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_ra_library', JPATH_SITE . '/components/com_ra_library');
        $language->load('com_ra_library', JPATH_SITE);
    }

    public static function renderView($viewName, $display) {
        self::loadLanguage();

        switch ($viewName) {
            case 'display':
                ob_start();
                $display = new DisplayHelper($display->displayoption, $display->options);
                $display->Display();
                $output = ob_get_clean();
                return $output;

            default:
                return '<div class="ra-library-error">Unknown view: '
                        . htmlspecialchars($viewName, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }

    /**
     * Replace the self-contained tags that carry their own settings (no Library
     * Display record needed):
     *
     *   {ra_library:documents folder="images/minutes" types="pdf,docx" sort="asc"}
     *   {ra_library:gpx file="images/routes/walk.gpx" colour="#782327" download="Public"}
     *   {ra_library:walkcsv groups="ER05,ER06" text="Download walks CSV"}
     *
     * and two that are not for general use, and only work when the plugin's
     * "Specialist tags" setting is on ($specialist):
     *
     *   {ra_library:mapcompare latitude="52.89" longitude="-1.48" zoom="10"}
     *   {ra_library:places}
     *
     * @param   string  $text        The article / module text.
     * @param   bool    $specialist  True if the plugin allows the specialist tags.
     *
     * @return  string  The text with each valid tag replaced by its output
     *                  (or a short warning saying what is wrong with it).
     */
    public static function replaceInlineTags(string $text, bool $specialist = false): string {
        if (stripos($text, '{ra_library:') === false) {
            return $text;
        }

        return preg_replace_callback(
                '#\{ra_library:(documents|gpxfolder|gpx|plot|csv|walkcsv|walks|calendar|addtocalendar|pastwalks|routes|mapcompare|places)((?:\s|&nbsp;|\xC2\xA0)[^{}]*)?\}#i',
                static function (array $m) use ($specialist): string {
                    return self::renderInlineTag(strtolower($m[1]), self::parseAttributes($m[2] ?? ''), $specialist);
                },
                $text
        );
    }

    /**
     * Read name="value" pairs. Tolerates what a rich-text editor does to the
     * tag: curly quotes, &quot; / &nbsp; entities, stray <span> markup.
     *
     * @param   string  $raw  Everything between the tag name and the closing brace.
     *
     * @return  array<string,string>  lower-case name => value
     */
    public static function parseAttributes(string $raw): array {
        $raw = strip_tags($raw);
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $raw = str_replace(["\xC2\xA0", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xE2\x80\x98", "\xE2\x80\x99"], [' ', '"', '"', '"', "'", "'"], $raw);

        $attributes = [];

        if (preg_match_all('/([a-z_]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/i', $raw, $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $value = $pair[2] !== '' ? $pair[2] : (($pair[3] ?? '') !== '' ? $pair[3] : ($pair[4] ?? ''));
                $attributes[strtolower($pair[1])] = trim($value);
            }
        }

        return $attributes;
    }

    /**
     * Turn a site-relative path from an article into a safe one, or explain why not.
     * Only paths inside /images or /files are accepted; anything that climbs out
     * of them (.., symlinks, absolute paths, URLs) is refused.
     *
     * @param   string  $path      As typed in the article, or as the Media field stores it.
     * @param   bool    $isFolder  True for a folder, false for a file.
     *
     * @return  array  [clean relative path or '', error message or '']
     */
    public static function cleanSitePath(string $path, bool $isFolder): array {
        // The Media field stores "images/x/y.gpx#joomlaImage://local-images/x/y.gpx?width=0&height=0".
        if (strpos($path, '#') !== false) {
            $path = strstr($path, '#', true);
        }

        $path = trim(str_replace('\\', '/', $path));
        $path = rtrim(ltrim($path, '/'), '/');

        if ($path === '') {
            return ['', $isFolder ? 'no folder given' : 'no file given'];
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) || strpos($path, "\0") !== false) {
            return ['', 'use a path inside the site\'s images or files folder, not a web address'];
        }

        $parts = explode('/', $path);

        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return ['', 'the path may not contain "..", "." or empty parts'];
            }
        }

        if (!\in_array($parts[0], self::ALLOWED_ROOTS, true)) {
            return ['', 'the ' . ($isFolder ? 'folder' : 'file') . ' must be inside the images or files folder'];
        }

        $absolute = realpath(JPATH_ROOT . '/' . $path);
        $root = realpath(JPATH_ROOT . '/' . $parts[0]);

        if ($absolute === false || $root === false) {
            return ['', ($isFolder ? 'folder' : 'file') . ' not found: ' . $path];
        }

        if ($absolute !== $root && strpos($absolute, $root . DIRECTORY_SEPARATOR) !== 0) {
            return ['', 'the path leads outside the images or files folder'];
        }

        if ($isFolder !== is_dir($absolute)) {
            return ['', $isFolder ? 'that is a file, not a folder: ' . $path : 'that is a folder, not a file: ' . $path];
        }

        return [implode('/', $parts), ''];
    }

    /** Tags that only work when the plugin's "Specialist tags" setting is on. */
    private const SPECIALIST_TAGS = ['mapcompare', 'places'];

    private static function renderInlineTag(string $name, array $attributes, bool $specialist = false): string {
        if (!$specialist && \in_array($name, self::SPECIALIST_TAGS, true)) {
            return self::warning($name, 'this tag is switched off. Turn on "Specialist tags" in the settings of the RA Library content plugin to use it.');
        }

        try {
            switch ($name) {
                case 'walkcsv':
                    return self::renderWalkcsvTag($attributes);
                case 'mapcompare':
                    return self::renderMapcompareTag($attributes);
                case 'places':
                    return self::renderPlacesTag();
                case 'gpx':
                    return self::renderGpxTag($attributes);
                case 'gpxfolder':
                    return self::renderGpxFolderTag($attributes);
                case 'plot':
                    return self::renderPlotTag($attributes);
                case 'csv':
                    return self::renderCsvTag($attributes);
                case 'walks':
                    return self::renderWalksTag($attributes, 'future_display');
                case 'calendar':
                    return self::renderWalksTag($attributes, 'future_calendar');
                case 'addtocalendar':
                    return self::renderWalksTag($attributes, 'future_addtocalendar');
                case 'pastwalks':
                    return self::renderRecordsTag($attributes, 'pastwalk', $name);
                case 'routes':
                    return self::renderRecordsTag($attributes, 'route', $name);
                default:
                    return self::renderDocumentsTag($attributes);
            }
        } catch (\Throwable $e) {
            return self::warning($name, 'could not be displayed');
        }
    }

    private static function warning(string $name, string $message): string {
        return '<p class="alert alert-warning ra-library-error"><b>RA Library {' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . '}:</b> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    private static function renderDocumentsTag(array $attributes): string {
        [$folder, $error] = self::cleanSitePath($attributes['folder'] ?? '', true);

        if ($error !== '') {
            return self::warning('documents', $error);
        }

        $types = [];

        foreach (preg_split('/[\s,;]+/', strtolower($attributes['types'] ?? $attributes['type'] ?? $attributes['ext'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $type) {
            $type = ltrim($type, '.*');

            if (preg_match('/^[a-z0-9]{1,8}$/', $type) && !\in_array($type, self::BLOCKED_TYPES, true)) {
                $types[] = '.' . $type;
            }
        }

        $types = array_values(array_unique($types));

        if (!$types) {
            $types = array_map(static fn ($t) => '.' . $t, self::DEFAULT_DOCUMENT_TYPES);
        }

        $descending = \in_array(strtolower($attributes['sort'] ?? 'asc'), ['desc', 'descending', '1'], true);

        $list = new DirList($types);
        ob_start();
        $list->listItems($folder, $descending ? DirList::DESC : DirList::ASC);

        return ob_get_clean();
    }

    private static function renderGpxTag(array $attributes): string {
        [$file, $error] = self::cleanSitePath($attributes['file'] ?? '', false);

        if ($error === '' && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'gpx') {
            $error = 'the file must be a .gpx file';
        }

        if ($error !== '') {
            return self::warning('gpx', $error);
        }

        $map = new GpxMap();
        $map->linecolour = self::cleanColour($attributes);
        $map->addDownloadLink = self::cleanDownload($attributes);

        ob_start();
        $map->displayPath($file);

        return ob_get_clean();
    }

    /** Hex / rgb() colour as typed in a tag, or the default route colour. */
    private static function cleanColour(array $attributes): string {
        $colour = $attributes['colour'] ?? $attributes['color'] ?? '';

        return preg_match('/^(#[0-9a-f]{3,8}|rgba?\([0-9 ,.%]+\))$/i', $colour) ? $colour : '#782327';
    }

    /** None / Users / Public, defaulting to Public. */
    private static function cleanDownload(array $attributes): string {
        foreach (['None', 'Users', 'Public'] as $option) {
            if (strcasecmp($attributes['download'] ?? '', $option) === 0) {
                return $option;
            }
        }

        return 'Public';
    }

    /**
     * Run one of the standard Library Display options with the given settings,
     * exactly as a saved Library Display would, and return its output.
     */
    private static function runDisplay(string $displayOption, array $options): string {
        self::loadLanguage();
        $options += ['addarticles' => '0', 'before' => '', 'after' => '', 'css_file' => '', 'js_file' => ''];
        ob_start();

        try {
            $display = new DisplayHelper($displayOption, json_encode((object) $options));
            $display->Display();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }

    /** {ra_library:gpxfolder folder="images/routes" colour="#782327" download="Public"} */
    private static function renderGpxFolderTag(array $attributes): string {
        [$folder, $error] = self::cleanSitePath($attributes['folder'] ?? '', true);

        if ($error !== '') {
            return self::warning('gpxfolder', $error);
        }

        return self::runDisplay('routes_display_multi', [
                    'routes_folder' => $folder,
                    'routes_linecolour' => self::cleanColour($attributes),
                    'routes_download' => self::cleanDownload($attributes),
                    'routes_displaytitle' => '1',
                    'routes_displayasprevious' => '0',
        ]);
    }

    /** {ra_library:plot latitude="52.891" longitude="-1.234" zoom="10" key="..."} */
    private static function renderPlotTag(array $attributes): string {
        $key = $attributes['key'] ?? '';

        return self::runDisplay('routes_plot', [
                    'routes_latitude' => self::number($attributes['latitude'] ?? $attributes['lat'] ?? '', -90, 90, 52.891),
                    'routes_longitude' => self::number($attributes['longitude'] ?? $attributes['lon'] ?? $attributes['long'] ?? '', -180, 180, -1.234),
                    'routes_zoomlevel' => self::number($attributes['zoom'] ?? '', 5, 13, 10),
                    'routes_smartroutingkey' => preg_match('/^[A-Za-z0-9._=-]{8,200}$/', $key) ? $key : '',
        ]);
    }

    /** A number typed in a tag, or the default if it is missing or out of range. */
    private static function number(string $value, float $min, float $max, float $default): float {
        return is_numeric($value) && $value >= $min && $value <= $max ? (float) $value : $default;
    }

    /**
     * The group codes typed in a tag (groups="ER05, ER06" - commas, semicolons or spaces).
     *
     * @param   array   $attributes  The tag's attributes.
     * @param   string  $pattern     A code must match this (after upper-casing).
     *
     * @return  array  [list of different valid codes, list of invalid codes]
     */
    private static function groupCodes(array $attributes, string $pattern): array {
        $codes = [];
        $bad = [];

        foreach (preg_split('/[\s,;]+/', strtoupper($attributes['groups'] ?? $attributes['group'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $code) {
            if (preg_match($pattern, $code)) {
                $codes[$code] = $code;
            } else {
                $bad[] = $code;
            }
        }

        return [array_values($codes), $bad];
    }

    /**
     * {ra_library:walkcsv groups="ER05,ER06" text="Download walks CSV"}
     * A link that downloads the future walks and events of the groups as a CSV file.
     * The file is made when the link is followed (WalkcsvController), not when the page is shown.
     */
    private static function renderWalkcsvTag(array $attributes): string {
        // a 2 character code is an area, 4 a group
        [$codes, $bad] = self::groupCodes($attributes, '/^[A-Z0-9]{2}([A-Z0-9]{2})?$/');

        if ($bad) {
            return self::warning('walkcsv', 'not a valid group code: ' . implode(', ', $bad));
        }

        if (!$codes) {
            return self::warning('walkcsv', 'no group codes given, e.g. groups="ER05,ER06"');
        }

        if (count($codes) > 20) {
            return self::warning('walkcsv', 'at most 20 group codes can be given');
        }

        $text = trim($attributes['text'] ?? '');
        $text = $text !== '' ? mb_substr($text, 0, 80) : 'Download walks CSV';

        // just the link: the editor normally wraps the tag in a paragraph already
        return '<a class="ra-walkcsv link-button tiny button mintcake" href="'
                . htmlspecialchars(Walkcsv::url(implode(',', $codes)), ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    /** {ra_library:mapcompare latitude="52.89" longitude="-1.48" zoom="10"} - two maps that move together. */
    private static function renderMapcompareTag(array $attributes): string {
        $map = new Mapcompare();

        if (isset($attributes['latitude']) || isset($attributes['lat']) || isset($attributes['longitude'])
                || isset($attributes['lon']) || isset($attributes['long']) || isset($attributes['zoom'])) {
            $map->setCenter(
                    self::number($attributes['latitude'] ?? $attributes['lat'] ?? '', -90, 90, 52.89),
                    self::number($attributes['longitude'] ?? $attributes['lon'] ?? $attributes['long'] ?? '', -180, 180, -1.48),
                    (int) self::number($attributes['zoom'] ?? '', 3, 18, 10)
            );
        }

        ob_start();
        $map->display();

        return ob_get_clean();
    }

    /** {ra_library:places} - every meeting / starting place (clustered). */
    private static function renderPlacesTag(): string {
        $map = new Mapplaces();
        ob_start();
        $map->display();

        return ob_get_clean();
    }

    /** {ra_library:csv file="files/data/places.csv"} */
    private static function renderCsvTag(array $attributes): string {
        [$file, $error] = self::cleanSitePath($attributes['file'] ?? '', false);

        if ($error === '' && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'csv') {
            $error = 'the file must be a .csv file';
        }

        if ($error === '' && !is_readable(JPATH_ROOT . '/' . $file)) {
            $error = 'the file cannot be read: ' . $file;
        }

        if ($error !== '') {
            return self::warning('csv', $error);
        }

        return self::runDisplay('table_csv', [
                    'table_file' => JPATH_ROOT . '/' . $file,
                    'table_csv_optionsmode' => 'file',
                    'table_markertype' => 'default',
        ]);
    }

    /**
     * {ra_library:walks groups="SO03,SO05"}            tabbed display of future walks
     * {ra_library:calendar groups="SO03" size="250"}   sidebar calendar
     * {ra_library:addtocalendar groups="SO03"}        'Add to calendar' button only
     * Wellbeing walks (their groups are not in the normal group list):
     *   {ra_library:walks groups="WB01,WB02" wellbeing="yes"}
     *   {ra_library:walks wellbeing="yes" lat="52.89" long="-1.23" km="20"}   any wellbeing group in the area
     *   (lat / long / km with no wellbeing attribute gives led walks and events in the area)
     */
    private static function renderWalksTag(array $attributes, string $displayOption): string {
        $name = ['future_calendar' => 'calendar', 'future_addtocalendar' => 'addtocalendar'][$displayOption] ?? 'walks';
        $wellbeing = \in_array(strtolower(trim($attributes['wellbeing'] ?? '')), ['yes', 'true', '1', 'on'], true);
        $inArea = isset($attributes['lat']) || isset($attributes['latitude']) || isset($attributes['long']) || isset($attributes['longitude']) || isset($attributes['km']);

        if ($inArea) {
            $lat = $attributes['lat'] ?? $attributes['latitude'] ?? '';
            $long = $attributes['long'] ?? $attributes['longitude'] ?? '';
            $km = $attributes['km'] ?? '';

            if (!is_numeric($lat) || $lat < -90 || $lat > 90 || !is_numeric($long) || $long < -180 || $long > 180 || !is_numeric($km) || $km <= 0 || $km > 100) {
                return self::warning($name, 'an area needs lat (-90 to 90), long (-180 to 180) and km (1 to 100), e.g. lat="52.89" long="-1.23" km="20"');
            }

            $source = (object) [
                'load_type' => $wellbeing ? 'wmwellbeingarea' : 'wmarea',
                'arealatitude' => (float) $lat, 'arealongitude' => (float) $long, 'arearadius' => (int) $km,
            ];
        } else {
            [$list, $bad] = self::groupCodes($attributes, '/^[A-Z0-9]{2,6}$/');
            $codes = [];

            foreach ($list as $code) {
                $codes[$code] = (object) ['code' => $code];
            }

            if ($bad) {
                return self::warning($name, 'not a valid group code: ' . implode(', ', $bad));
            }

            if (!$codes) {
                return self::warning($name, 'no group codes given, e.g. groups="SO03,SO05"');
            }

            $source = $wellbeing ?
                    (object) ['load_type' => 'wmwellbeing', 'wellbeinggroups' => array_values($codes)] :
                    (object) ['load_type' => 'wm', 'ramblersgroups' => array_values($codes)];
        }

        $options = [
            'eventsources' => [$source],
            'filters' => [],
            'walks_class' => '', 'walk_class' => '',
            'custom_tabs' => [], 'custom_grades' => '', 'custom_list' => '', 'custom_calendar' => '',
            'custom_table' => [], 'custom_nextwalks' => '', 'custom_simplelist' => '', 'custom_fulldetails' => '',
            'calendar_size' => 0,
        ];

        if ($displayOption === 'future_calendar') {
            $size = (int) ($attributes['size'] ?? 0);
            $options['calendar_size'] = $size >= 150 && $size <= 600 ? $size : 0;
        }

        return self::runDisplay($displayOption, $options);
    }

    /**
     * {ra_library:pastwalks category="Cheshire" type="blog"}
     * {ra_library:routes category="12" type="map"}
     */
    private static function renderRecordsTag(array $attributes, string $recordType, string $name): string {
        $types = ['list' => 'list', 'blog' => 'blog', 'table' => 'table', 'map' => 'maptable', 'maptable' => 'maptable', 'tree' => 'categorytree'];
        $type = strtolower($attributes['type'] ?? 'list');

        if (!isset($types[$type])) {
            return self::warning($name, 'type must be one of tree, blog, list, table or map');
        }

        $catid = 0;
        $category = trim($attributes['category'] ?? '');

        if ($category !== '') {
            $catid = self::findCategory($category, $recordType);

            if ($catid === 0) {
                return self::warning($name, 'category not found: ' . $category);
            }
        }

        $prefix = $recordType === 'route' ? 'rt_' : 'pw_';
        $option = ($recordType === 'route' ? 'routes_' : 'pastwalks_') . $types[$type];
        $settings = [$prefix . 'catid' => $catid, $prefix . 'categorytree_rootcat' => $catid];

        return self::runDisplay($option, $settings);
    }

    /** Category id from an id, alias or title (published categories of that record type only). */
    private static function findCategory(string $category, string $recordType): int {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $extension = 'com_ra_library.' . $recordType;
        $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote($extension))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('id'));

        if (ctype_digit($category)) {
            $query->where($db->quoteName('id') . ' = ' . (int) $category);
        } else {
            $query->where('(' . $db->quoteName('alias') . ' = ' . $db->quote(strtolower($category))
                    . ' OR LOWER(' . $db->quoteName('title') . ') = ' . $db->quote(strtolower($category)) . ')');
        }

        $db->setQuery($query, 0, 1);

        return (int) $db->loadResult();
    }
}
