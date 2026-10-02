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

/**
 * Single source of truth for the friendly "Walks: Display of led walks
 * (tabbed layout)" style labels for each displayoption value.
 *
 * Used by:
 *  - administrator/tmpl/librarydisplays/default.php (the "Display Option"
 *    column in the Library Displays list)
 *  - Field/LibraryDisplayField.php (the picker shown when choosing a
 *    Library Display for a menu item's "Select Item" setting)
 *
 * Keep this in sync with the <option> values in administrator/forms/
 * librarydisplay.xml's "displayoption" (NestedPanels) field - it's a plain
 * value => label lookup, not derived from the XML, so a new option added
 * there needs an entry added here too.
 *
 * @since  1.0.0
 */
class DisplayoptionLabelHelper {

    /**
     * @return  array<string, string>  displayoption value => friendly label
     *
     * @since   1.0.0
     */
    public static function getLabels(): array {
        $disp = [];

        $disp['future_display'] = 'Led Walks: Display of led walks (tabbed layout)';
        $disp['future_nextwalks'] = 'Led Walks: Next walks';
        $disp['future_table'] = 'Led Walks: Table of led walks';
        $disp['future_list'] = 'Led Walks: List of led walks';
        $disp['future_map'] = 'Led Walks: Map of led walks';
        $disp['future_fulldetails'] = 'Led Walks: Full details';
        $disp['future_calendar'] = 'Led Walks: Calendar';
        $disp['future_BU51a'] = 'Led Walks: BU51:Fulldetails';
        $disp['future_BU51b'] = 'Led Walks: BU51:Groupstabs';
        $disp['future_BU51c'] = 'Led Walks: BU51:Microtabs';
        $disp['future_BU51d'] = 'Led Walks: BU51:Tabs';
        $disp['future_MLa'] = 'Led Walks: ML:Print';
        $disp['future_NSa'] = 'Led Walks: NS:Walksprinted';
        $disp['future_SR02a'] = 'Led Walks: SR02:Display';
        $disp['future_SR02b'] = 'Led Walks: SR02:Nextwalks';
        $disp['future_SR02c'] = 'Led Walks: SR02:Table2';

        $disp['routes_blog'] = 'Routes: Blog';
        $disp['routes_list'] = 'Routes: List';
        $disp['routes_table'] = 'Routes: Table';
        $disp['routes_maptable'] = 'Routes: Map & Table';
        $disp['routes_categorytree'] = 'Routes: List all Categories in a Category Tree';
        $disp['routes_display_single'] = 'Routes: Display single walking route(GPX)';
        $disp['routes_display_multi'] = 'Routes: Display mutliple walking routes(GPX)';
        $disp['routes_plot'] = 'Routes: Plot a walking route';

        $disp['pastwalks_blog'] = 'Past Walks: Blog';
        $disp['pastwalks_list'] = 'Past Walks: List';
        $disp['pastwalks_table'] = 'Past Walks: Table';
        $disp['pastwalks_maptable'] = 'Past Walks: Map & Table';
        $disp['pastwalks_categorytree'] = 'Past Walks: List all Categories in a Category Tree';

        $disp['table_csv'] = 'Table: Display data from a CSV file';
        $disp['table_sql'] = 'Table: Display data from an SQL table';
        $disp['table_json'] = 'Table: Display data from a JSON feed';

        $disp['documents_folder'] = 'Documents: Display list of documents in folder';

        return $disp;
    }

    /**
     * @param   string  $displayoption  The raw stored value, e.g. "table_csv"
     * @param   string  $default        Fallback when there's no mapping (e.g. an
     *                                  option removed from the form but still
     *                                  saved on some old records)
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function getLabel(string $displayoption, string $default = ''): string {
        $labels = static::getLabels();

        return $labels[$displayoption] ?? ($default !== '' ? $default : $displayoption);
    }
}
