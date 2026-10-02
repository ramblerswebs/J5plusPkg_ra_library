<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Ra_library
 * @author     Chris Vaughan <ruby.tuesday@ramblers-webs.org.uk>
 * @copyright  2026 Chris Vaughan
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_library\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_library\Administrator\Helper\Ra_libraryHelper;
use Ramblers\Component\Ra_library\Administrator\Helper\DisplayoptionLabelHelper;
use \Joomla\Database\DatabaseInterface;

/**
 * Methods supporting a list of Librarydisplays records.
 *
 * @since  1.0.0
 */
class LibrarydisplaysModel extends ListModel {

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see        JController
     * @since      1.6
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'state', 'a.state',
                'ordering', 'a.ordering',
                'created_by', 'a.created_by',
                'modified_by', 'a.modified_by',
                'title', 'a.title',
                'displayoption', 'a.displayoption',
                'options', 'a.options',
            );
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   Elements order
     * @param   string  $direction  Order direction
     *
     * @return void
     *
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState('id', 'ASC');

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);

        // Split context into component and optional section
        if (!empty($context)) {
            $parts = FieldsHelper::extract($context);

            if ($parts) {
                $this->setState('filter.component', $parts[0]);
                $this->setState('filter.section', $parts[1]);
            }
        }
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string A store id.
     *
     * @since   1.0.0
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   1.0.0
     */
    protected function getListQuery() {
        // Create a new query object.
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
                $this->getState(
                        'list.select', 'DISTINCT a.*'
                )
        );
        $query->from('`#__ra_library_displays` AS a');

        // Join over the users for the checked out user
        $query->select("uc.name AS uEditor");
        $query->join("LEFT", "#__users AS uc ON uc.id=a.checked_out");

        // Filter by published state
        $published = $this->getState('filter.state');

        if (is_numeric($published)) {
            $query->where('a.state = ' . (int) $published);
        } elseif (empty($published)) {
            $query->where('(a.state IN (0, 1))');
        }

        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
            }
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'id');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $orderDirn = (strtoupper($orderDirn) === 'DESC') ? 'DESC' : 'ASC';

            if ($orderCol === 'a.displayoption') {
                // The list shows friendly labels (DisplayoptionLabelHelper), not the
                // stored values, so sort by the label to match what is displayed.
                $whens = '';
                foreach (DisplayoptionLabelHelper::getLabels() as $value => $label) {
                    $whens .= ' WHEN ' . $db->quote($value) . ' THEN ' . $db->quote($label);
                }
                // Unmapped values fall back to the raw value, as the list display does
                $labelExpr = 'CASE ' . $db->quoteName('a.displayoption') . $whens
                        . ' ELSE ' . $db->quoteName('a.displayoption') . ' END';

                // Selected as an alias because the query is SELECT DISTINCT, and
                // MySQL requires ORDER BY expressions to appear in the select list
                $query->select($labelExpr . ' AS ' . $db->quoteName('displayoption_label'));
                $query->order($db->quoteName('displayoption_label') . ' ' . $orderDirn);
                $query->order($db->quoteName('a.title') . ' ASC');
            } else {
                $query->order($db->escape($orderCol . ' ' . $orderDirn));
            }
        }

        return $query;
    }

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function getItems() {
        $items = parent::getItems();

        return $items;
    }
}
