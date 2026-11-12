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

use Joomla\CMS\Form\Form;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Filter\OutputFilter;

/**
 * Librarydisplay model.
 *
 * @since  1.0.0
 */
class LibrarydisplayModel extends AdminModel {

    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_RA_LIBRARY';

    /**
     * @var    string  Alias to manage history control
     *
     * @since  1.0.0
     */
    public $typeAlias = 'com_ra_library.librarydisplay';

    /**
     * @var    null  Item data
     *
     * @since  1.0.0
     */
    protected $item = null;
    protected $moveProperties = ['filters', 'eventsources', 'document_sources'];

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string  $type    The table type to instantiate
     * @param   string  $prefix  A prefix for the table class name. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  Table    A database object
     *
     * @since   1.0.0
     */
    public function getTable($type = 'Librarydisplay', $prefix = 'Administrator', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      An optional array of data for the form to interogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \JForm|boolean  A \JForm object on success, false on failure
     *
     * @since   1.0.0
     */
    public function getForm($data = array(), $loadData = true) {
        // Initialise variables.
        $app = Factory::getApplication();

        // Get the form.
        $form = $this->loadForm(
                'com_ra_library.librarydisplay',
                'librarydisplay',
                array(
                    'control' => 'jform',
                    'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   1.0.0
     */
    protected function loadFormData() {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_ra_library.edit.librarydisplay.data', []);

        if (empty($data)) {
            $item = $this->getItem();
            $data = $this->preloadFormData($item);
        }

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed    Object on success, false on failure.
     *
     * @since   1.0.0
     */
    public function getItem($pk = null) {

        if ($item = parent::getItem($pk)) {
            // Do any procesing on fields here if needed
        }

        return $item;
    }

    /**
     * Method to duplicate an Librarydisplay
     *
     * @param   array  &$pks  An array of primary key IDs.
     *
     * @return  boolean  True if successful.
     *
     * @throws  Exception
     */
    public function duplicate(&$pks) {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $dispatcher = $this->getDispatcher();

        // Access checks.
        if (!$user->authorise('core.create', 'com_ra_library')) {
            throw new \Exception(Text::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
        }

        $context = $this->option . '.' . $this->name;

        // Include the plugins for the save events.
        PluginHelper::importPlugin($this->events_map['save']);

        $table = $this->getTable();

        foreach ($pks as $pk) {

            if ($table->load($pk, true)) {
                // Reset the id to create a new record.
                $table->id = 0;

                if (!$table->check()) {
                    throw new \Exception($table->getError());
                }


                // Create the before save event.
                $beforeSaveEvent = AbstractEvent::create(
                        $this->event_before_save,
                        [
                            'context' => $context,
                            'subject' => $table,
                            'isNew' => true,
                            'data' => $table,
                        ]
                );

                // Trigger the before save event.
                $dispatchResult = Factory::getApplication()->getDispatcher()->dispatch($this->event_before_save, $beforeSaveEvent);

                // Check if dispatch result is an array and handle accordingly
                $result = isset($dispatchResult['result']) ? $dispatchResult['result'] : [];

                // Proceed with your logic
                if (in_array(false, $result, true) || !$table->store()) {
                    throw new \Exception($table->getError());
                }

                // Trigger the after save event.
                Factory::getApplication()->getDispatcher()->dispatch(
                        $this->event_after_save,
                        AbstractEvent::create(
                                $this->event_after_save,
                                [
                                    'context' => $context,
                                    'subject' => $table,
                                    'isNew' => true,
                                    'data' => $table,
                                ]
                        )
                );
            } else {
                throw new \Exception($table->getError());
            }
        }

        // Clean cache
        $this->cleanCache();

        return true;
    }

    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param   Table  $table  Table Object
     *
     * @return  void
     *
     * @since   1.0.0
     */
    #[\Override]
    protected function prepareTable($table) {

        if (empty($table->id)) {
            if ($table->ordering === '') {
                $db = Factory::getContainer()->get(DatabaseInterface::class);
                $db->setQuery('SELECT MAX(ordering) FROM #__ra_library_displays');
                $max = $db->loadResult();
                $table->ordering = $max + 1;
            }
        }

        $jinput = Factory::getApplication()->input;
        $data = $jinput->get('jform', [], 'array');

        parent::prepareTable($table);
    }

    protected function preloadFormData($item) {

        $data = [];
        if ($item) {
            $data['id'] = $item->id;
            $data['title'] = $item->title;
            $data['displayoption'] = $item->displayoption;
            $data['options'] = json_decode($item->options ?? '', true) ?: [];
            // move items from options field
            $data = $this->moveFieldsOutofOptions($data);
        }
        return $data;
    }

    protected function preprocessForm(Form $form, $data, $group = 'content') {
        // Always call the parent first — this is what fires the normal
        // onContentPrepareForm event and loads any extra field/rule paths.
        parent::preprocessForm($form, $data, $group);

        // $data is usually an array here (sometimes an object depending
        // on how the form was bound), so handle both just in case.
        $storedValue = is_array($data) ? ($data['displayoption'] ?? '') : ($data->displayoption ?? '');

        if ($storedValue !== '') {
            $form->setFieldAttribute(
                    'displayoption_note',
                    'showon',
                    'displayoption!:' . $storedValue
            );
        }
    }

    public function save($data) {
        // move items into options rather than at base level
        $data = $this->moveFieldsIntoOptions($data);
        $this->scanAndDecode($data);
        return parent::save($data);
    }

    private function moveFieldsIntoOptions($data) {
        foreach ($this->moveProperties as $property) {
            if (array_key_exists($property, $data)) {
                $data['options'][$property] = $data[$property];
                unset($data[$property]);
            }
        }
        return $data;
    }

    private function moveFieldsOutofOptions($data) {
        foreach ($this->moveProperties as $property) {
            if (array_key_exists($property, $data['options'])) {
                $data[$property] = $data['options'][$property];
                unset($data['options'][$property]);
            }
        }
        return $data;
    }

    /**
     * Extract an array of integer IDs from a field inside the options object.
     * Field must be stored as an array, even for single values.
     *
     * Example:
     *   $coordIds = $this->flattenIds($options, 'programmecoords');
     *   // → [123] or [985, 976, 983]
     *
     * @param   array   $data    Decoded object
     * @param   string  $fieldName  Name of the field (e.g. 'programmecoords')
     *
     * @return  int[]
     */
    public function flattenIds(array $data, string $fieldName): array {
        // Handle draft_coords (or any multi-user field)
        if (isset($data[$fieldName]) && is_array($data[$fieldName])) {
            // Flatten: [["976"],["983"]] → ["976","983"] (handles subform nesting)
            $flatIds = [];
            foreach ($data[$fieldName] as $nested) {
                if (is_array($nested) && isset($nested[0])) {
                    $flatIds[] = (string) $nested[0];  // Single ID per sub-array
                }
            }
            return array_filter($flatIds);  // Clean empty
        }
        return [];
    }

    /**
     * scan & decode specific field names anywhere recursively
     * 
     */
    protected $targetDecodeFields = [
        'documents_exts', 'ramblersgroups', 'wellbeinggroups', 'draft_ragroups',
        'draft_localgrades', 'custom_table', 'custom_title', 'keep_groups',
        'table_options', 'table_iconmarkers'];  // Add your unique fields

    /**
     * Recursive scan: find & decode target fields
     */

    /**
     * Scan arrays only (Joomla form data) - recurse & decode target fields
     */
    protected function scanAndDecode(&$data) {
        foreach ($data as $k => &$v) {
            if (in_array($k, $this->targetDecodeFields) && is_string($v)) {
                $data[$k] = json_decode($v, true) ?: [];
            }
            if (is_array($v)) {
                $this->scanAndDecode($v);  // Recurse nested arrays
            }
        }
        unset($v);  // Critical: clear foreach ref leak
    }
}
