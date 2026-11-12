<?php

/**
 * Feed Checkboxes Field
 *
 * @package     Ramblers\Component\Ra_library
 * @subpackage  Administrator\Field
 * @since       5.0
 */

namespace Ramblers\Component\Ra_library\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die;

/**
 * Feed Checkboxes field.
 */
class FeedcheckboxesField extends ListField {

    /**
     * The form field type.
     *
     * @var    string
     */
    protected $type = 'Feedcheckboxes';

    /**
     * Method to get the field options.
     *
     * @return  array  The field option objects.
     */
    protected function getOptions() {
        $url = (string) $this->element['feedurl'] ?? '';

        if (empty($url)) {
            return parent::getOptions();
        }

        $cacheLifetime = (int) ($this->element['cache_lifetime'] ?? 3600);
        $cacheGroup = 'feedcheckboxes_' . md5($url);
        $cache = Factory::getCache($cacheGroup, 'callback');
        $cache->setLifeTime($cacheLifetime);

        $optionsData = $cache->get([$this, 'fetchJsonData'], [$url], md5($url));

        $options = parent::getOptions();

        if (is_array($optionsData)) {
            foreach ($optionsData as $item) {
                $class = match ((string) ($item['scope'] ?? '')) {
                    'A' => 'area',
                    'G' => 'group',
                    'W' => 'wellbeing',
                    default => ''
                };

                $options[] = (object) [
                            'value' => $item['groupCode'],
                            'text' => $item['groupCode'] . ': ' . $item['name'],
                            'class' => $class,
                            'inputClass' => 'group-input'
                ];
            }
        }

        return $options;
    }

    /**
     * Fetch JSON data from URL (public for cache callback).
     */
    public function fetchJsonData($url) {
        try {
            // Joomla 5: Simple HttpFactory::getHttp()
            $http = \Joomla\CMS\Http\HttpFactory::getHttp();
            $response = $http->get($url);

            if ($response->code === 200) {
                $data = json_decode($response->body, true);
                return is_array($data) ? $data : [];
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Feedcheckboxes fetch failed: ' . $e->getMessage(), 'warning');
        }

        return [];
    }

    /**
     * Method to get the field input markup.
     */

    /**
     * Method to get the field input markup.
     */
    protected function getInput() {
        HTMLHelper::_('script', 'com_ra_library/fields/feedcheckboxes.js', ['relative' => true]);
        HTMLHelper::_('stylesheet', 'com_ra_library/fields/feedcheckboxes.css', ['version' => 'auto', 'relative' => true]);

        $options = $this->getOptions();

        $codes = $this->value;

        if (is_string($codes)) {
            // The stored value is JSON text (from the DB / component params) -
            // decode it back into the array of {code, name} rows this field
            // works with, rather than discarding whatever was saved.
            $decoded = json_decode($codes, true);
            $codes = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($codes)) {
            $codes = [];
        }

        $storedData = json_encode($codes);
        $valueCodes = array_filter(array_column($codes, 'code'));

        $fieldId = str_replace('__', '_', $this->id);
        $fieldName = str_replace('[]', '', $this->name);

        $html = '<div class="feedcheckboxes-field" data-field-id="' . htmlspecialchars($fieldId) . '">';

        $html .= '<input type="hidden" name="' . htmlspecialchars($fieldName) . '" ';
        $html .= 'id="' . htmlspecialchars($fieldId) . '" ';
        $html .= 'value="' . htmlspecialchars($storedData ?: '[]') . '">';

        // Chips from stored data
        $html .= '<div class="feedcheckboxes-selected selected-container" id="' . $fieldId . '_selected">';
        foreach ($codes as $code) {
            $item = (array) $code;
            $html .= '<span class="selected-chip" data-code="' . htmlspecialchars($item['code'] ?? '') . '">';
            $html .= htmlspecialchars($item['name'] ?? $item['code'] ?? '') . ' <span class="remove-x">&times;</span>';
            $html .= '</span>';
        }
        $html .= '</div>';

        $html .= '<div class="feedcheckboxes-search">';
        $html .= '<input type="text" class="search-input no-hints" placeholder="Search groups..." autocomplete="off">';
        $html .= '</div>';

        // Checkboxes
        $html .= '<div class="feedcheckboxes-list" style="height:300px">';
        foreach ($options as $option) {
            $checked = in_array($option->value, $valueCodes) ? ' checked' : '';
            $html .= '<label class="feedcheckbox-item ' . htmlspecialchars($option->class ?? '') . '">';
            $html .= '<input type="checkbox" class="' . htmlspecialchars($option->inputClass ?? '') . '" ';
            $html .= 'value="' . htmlspecialchars($option->value) . '"' . $checked . '>';
            $html .= htmlspecialchars($option->text);
            $html .= '</label>';
        }
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
