<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Ra_library
 * @author     Chris Vaughan <ruby.tuesday@ramblers-webs.org.uk>
 * @copyright  2026 Chris Vaughan
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');

$wa->registerAndUseStyle(
        'com_ra_library.custom',
        'administrator/components/com_ra_library/assets/css/ra_library.css'
);
$wa->registerAndUseScript(
        'my.script',
        'media/com_ra_library/js/ra.js',
        [],
        ['defer' => true],
        ['core']
);
HTMLHelper::_('bootstrap.tooltip');
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->addInlineStyle('div.ra-form-section{
   background-color: #f2f2f2;
   border-radius: 5px;
   padding: 5px;
   margin-bottom:10px;
}');
$wa->useStyle('com_ra_library.filters');
$wa->useScript('com_ra_library.layoutcheck');
?>

<form
    action="<?php echo Route::_('index.php?option=com_ra_library&layout=edit&id=' . (int) $this->item->id); ?>"
    method="post" enctype="multipart/form-data" name="adminForm" id="librarydisplay-form" class="form-validate form-horizontal">

    <div class="main-card">
        <?php
        //     HTMLHelper::_('script', 'com_ra_library/fields/toggleDisplayoptionsnote.js', ['version' => 'auto', 'relative' => true]);
        HTMLHelper::_('script', 'com_ra_library/fields/toggleDisplayoptionsnote.js', ['relative' => true]);

        // $displayOption = $this->form->getValue('displayoption');
        $displayOption = $this->item->displayoption;
        $this->document->addScriptOptions('form', [
            'originalDisplayoption' => (string) $this->item->displayoption,
        ]);
        echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'option'));

        echo HTMLHelper::_('uitab.addTab', 'myTab', 'option', Text::_('Display Option'));

        echo $this->form->renderField('title');
        echo $this->form->renderField('displayoption');
        echo $this->form->renderField('displayoption_note');
        // How to add this display (and the self-contained tags) to a site.
        $helpId = (int) ($this->item->id ?? 0);
        $helpTag = htmlspecialchars('{ra_library:display:' . ($helpId > 0 ? $helpId : 'ID') . '}', ENT_QUOTES, 'UTF-8');
        $helpRef = static function (string $summaryKey, string $bodyKey, bool $open = false): string {
            return '<details class="mb-2"' . ($open ? ' open' : '') . '><summary class="fw-bold">' . Text::_($summaryKey)
                    . '</summary><div class="mt-2">' . Text::_($bodyKey) . '</div></details>';
        };
        echo '<div class="alert alert-info w-100"><h4>' . Text::_('COM_RA_LIBRARY_HELP_TITLE') . '</h4>'
        . Text::sprintf('COM_RA_LIBRARY_HELP_WAYS', $helpTag, Text::_($helpId > 0 ? 'COM_RA_LIBRARY_HELP_ID_KNOWN' : 'COM_RA_LIBRARY_HELP_ID_NEW'))
        . Text::_('COM_RA_LIBRARY_HELP_TAGS_INTRO')
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_WALKS', 'COM_RA_LIBRARY_HELP_REF_WALKS', $displayOption === 'future_display')
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_CALENDAR', 'COM_RA_LIBRARY_HELP_REF_CALENDAR', $displayOption === 'future_calendar')
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_ADDTOCALENDAR', 'COM_RA_LIBRARY_HELP_REF_ADDTOCALENDAR', $displayOption === 'future_addtocalendar')
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_PASTWALKS', 'COM_RA_LIBRARY_HELP_REF_PASTWALKS', \in_array($displayOption, ['pastwalks_blog', 'pastwalks_list', 'pastwalks_table', 'pastwalks_maptable', 'pastwalks_categorytree'], true))
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_ROUTES', 'COM_RA_LIBRARY_HELP_REF_ROUTES', \in_array($displayOption, ['routes_blog', 'routes_list', 'routes_table', 'routes_maptable', 'routes_categorytree'], true))
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_GPX', 'COM_RA_LIBRARY_HELP_REF_GPX', $displayOption === 'routes_display_single')
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_GPXFOLDER', 'COM_RA_LIBRARY_HELP_REF_GPXFOLDER', $displayOption === 'routes_display_multi')
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_PLOT', 'COM_RA_LIBRARY_HELP_REF_PLOT', $displayOption === 'routes_plot')
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_CSV', 'COM_RA_LIBRARY_HELP_REF_CSV', $displayOption === 'table_csv')
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_DOCUMENTS', 'COM_RA_LIBRARY_HELP_REF_DOCUMENTS', $displayOption === 'documents_folder')
        . $helpRef('COM_RA_LIBRARY_HELP_SUMMARY_NOTES', 'COM_RA_LIBRARY_HELP_NOTES')
        . '</div>';
        echo HTMLHelper::_('uitab.endTab');

        switch ($displayOption) {
            case 'future_display':
            case "future_nextwalks":
            case "future_blog":
            case "future_calendar":
            case "future_addtocalendar":
            case "future_table":
            case "future_list":
            case "future_map":
            case "future_fulldetails":
            case "future_BU51a":
            case "future_BU51b":
            case "future_BU51c":
            case "future_BU51d":
            case "future_MLa":
            case "future_NSa":
            case "future_SR02a":
            case "future_SR02b":
            case "future_SR02c":
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Define Walks/Events', Text::_('Walks/Events Source'));
                echo $this->form->renderFieldset('loadwalks');
                // 'Add to calendar' button option. Nothing saved yet => Yes for the tabbed display, No for the others
                if ($displayOption !== 'future_addtocalendar') {
                    $calLink = $this->form->getValue('show_calendar_link', 'options', null);
                    if ($calLink === null || $calLink === '') {
                        $this->form->setValue('show_calendar_link', 'options', $displayOption === 'future_display' ? '1' : '0');
                    }
                    echo $this->form->renderField('show_calendar_link', 'options');
                }
                echo HTMLHelper::_('uitab.endTab');
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'filters', 'Walk/event Filters');
                echo '<div class="row">';
                echo $this->form->renderFieldset('filters');
                echo '</div>';
                echo HTMLHelper::_('uitab.endTab');
        }
        switch ($displayOption) {
            case 'future_display':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo $this->form->renderField('custom_tabs', 'options');
                echo $this->form->renderField('custom_grades', 'options');
                echo $this->form->renderField('custom_table', 'options');
                echo $this->form->renderField('custom_list', 'options');
                echo $this->form->renderField('custom_calendar', 'options');
                echo $this->form->renderField('custom_title', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo $this->form->renderField('js_file', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case "future_blog":
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo '<div class="ra-form-section"><h3>Section titles</h3>';
                echo $this->form->renderField('blog_title_full', 'options');
                echo $this->form->renderField('blog_title_panel', 'options');
                echo $this->form->renderField('blog_title_links', 'options');
                echo $this->form->renderField('blog_title_level', 'options');
                echo '</div><div class="ra-form-section"><h3>Full walks</h3>';
                echo $this->form->renderField('blog_full_items', 'options');
                echo $this->form->renderField('blog_sections', 'options');
                echo $this->form->renderField('blog_full_map', 'options');
                echo $this->form->renderField('custom_blog_full', 'options');
                echo '</div><div class="ra-form-section"><h3>Panels</h3>';
                echo $this->form->renderField('blog_panel_items', 'options');
                echo $this->form->renderField('blog_panel_mode', 'options');
                echo $this->form->renderField('blog_panel_minwidth', 'options');
                echo $this->form->renderField('blog_panel_columns', 'options');
                echo $this->form->renderField('blog_panel_image', 'options');
                echo $this->form->renderField('blog_photo_height', 'options');
                echo $this->form->renderField('blog_default_image', 'options');
                echo $this->form->renderField('blog_panel_fields', 'options');
                echo $this->form->renderField('blog_panel_labels', 'options');
                echo $this->form->renderField('blog_panel_arrows', 'options');
                echo $this->form->renderField('custom_blog_panel', 'options');
                echo '</div><div class="ra-form-section"><h3>Links</h3>';
                echo $this->form->renderField('blog_link_items', 'options');
                echo $this->form->renderField('custom_blog_links', 'options');
                echo '</div>';
                echo $this->form->renderField('custom_title', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo $this->form->renderField('walks_class', 'options');
                echo $this->form->renderField('walk_class', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case "future_calendar":
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'CalOptions', Text::_('Calendar Options'));
                echo $this->form->renderField('calendar_size', 'options');
                echo $this->form->renderField('custom_title', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case "future_table":
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo $this->form->renderField('custom_table', 'options');
                echo $this->form->renderField('custom_title', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo $this->form->renderField('walks_class', 'options');
                echo $this->form->renderField('walk_class', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case "future_list":
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo $this->form->renderField('custom_simplelist', 'options');
                echo $this->form->renderField('custom_title', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo $this->form->renderField('walks_class', 'options');
                echo $this->form->renderField('walk_class', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case "future_nextwalks":
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo $this->form->renderField('custom_nextwalks', 'options');
                echo $this->form->renderField('custom_title', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo $this->form->renderField('walks_class', 'options');
                echo $this->form->renderField('walk_class', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case "future_fulldetails":
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo $this->form->renderField('custom_fulldetails', 'options');
                echo $this->form->renderField('custom_title', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo $this->form->renderField('walks_class', 'options');
                echo $this->form->renderField('walk_class', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
        }
        switch ($displayOption) {
            case 'routes_plot':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Plot Option', Text::_('Plot route options'));
                echo '<div class="ra-form-section">';
                echo '<h3>Define initial display area of map</h3>';
                echo $this->form->renderField('routes_latitude', 'options');
                echo $this->form->renderField('routes_longitude', 'options');
                echo $this->form->renderField('routes_zoomlevel', 'options');
                echo '</div>';
                echo '<div class="ra-form-section">';
                echo $this->form->renderField('routes_smartroutingnote', 'options');
                echo $this->form->renderField('routes_smartroutingkey', 'options');
                echo '</div>';
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'routes_display_single':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('routes_file', 'options');
                echo $this->form->renderField('routes_linecolour', 'options');
                echo $this->form->renderField('routes_download', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'routes_display_multi':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('routes_folder', 'options');
                echo $this->form->renderField('routes_linecolour', 'options');
                echo $this->form->renderField('routes_download', 'options');
                echo $this->form->renderField('routes_displayasprevious', 'options');
                echo $this->form->renderField('routes_displaytitle', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case'table_csv':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('table_file', 'options');
                echo $this->form->renderField('table_csv_optionsmode', 'options');
                echo $this->form->renderField('table_options', 'options');
                echo $this->form->renderField('table_markertype', 'options');
                echo $this->form->renderField('table_markercolumn', 'options');
                echo $this->form->renderField('table_textmarkerclass', 'options');
                echo $this->form->renderField('table_iconmarkers', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case'table_sql':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('table_sqlselect', 'options');
                echo $this->form->renderField('table_options', 'options');
                echo $this->form->renderField('table_markertype', 'options');
                echo $this->form->renderField('table_markercolumn', 'options');
                echo $this->form->renderField('table_textmarkerclass', 'options');
                echo $this->form->renderField('table_iconmarkers', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case'table_json':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('table_json', 'options');
                echo $this->form->renderField('table_options', 'options');
                echo $this->form->renderField('table_markertype', 'options');
                echo $this->form->renderField('table_markercolumn', 'options');
                echo $this->form->renderField('table_textmarkerclass', 'options');
                echo $this->form->renderField('table_iconmarkers', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case'documents_folder':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'document options', Text::_('Document options'));
                echo $this->form->renderFieldset('documents');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'pastwalks_blog':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('pastwalks_routes_note', 'options');
                echo $this->form->renderField('pw_catid', 'options');
                echo $this->form->renderField('pw_blog_full_items', 'options');
                echo $this->form->renderField('pw_blog_intro_items', 'options');
                echo $this->form->renderField('pw_blog_intro_columns', 'options');
                echo $this->form->renderField('pw_blog_link_items', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'pastwalks_list':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('pw_catid', 'options');
                echo $this->form->renderField('pw_items_per_page', 'options');
                echo HTMLHelper::_('uitab.endTab');
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo $this->form->renderField('custom_pastwalks_list', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'pastwalks_table':
            case 'pastwalks_maptable':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('table_note', 'options');
                echo $this->form->renderField('pw_catid', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'routes_blog':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('pastwalks_routes_note', 'options');
                echo $this->form->renderField('rt_catid', 'options');
                echo $this->form->renderField('rt_blog_full_items', 'options');
                echo $this->form->renderField('rt_blog_intro_items', 'options');
                echo $this->form->renderField('rt_blog_intro_columns', 'options');
                echo $this->form->renderField('rt_blog_link_items', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'routes_list':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('rt_catid', 'options');
                echo $this->form->renderField('rt_items_per_page', 'options');
                echo HTMLHelper::_('uitab.endTab');
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo $this->form->renderField('custom_routes_list', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'pastwalks_categorytree':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('categorytree_note', 'options');
                echo $this->form->renderField('pw_categorytree_rootcat', 'options');
                echo $this->form->renderField('pw_categorytree_style', 'options');
                echo $this->form->renderField('pw_categorytree_items_per_page', 'options');
                echo HTMLHelper::_('uitab.endTab');
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo $this->form->renderField('custom_pastwalks_categorytree_branch', 'options');
                echo $this->form->renderField('custom_pastwalks_categorytree_list', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'routes_categorytree':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('categorytree_note', 'options');
                echo $this->form->renderField('rt_categorytree_rootcat', 'options');
                echo $this->form->renderField('rt_categorytree_style', 'options');
                echo $this->form->renderField('rt_categorytree_items_per_page', 'options');
                echo HTMLHelper::_('uitab.endTab');
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Custom', Text::_('Customise'));
                echo $this->form->renderField('custom_routes_categorytree_branch', 'options');
                echo $this->form->renderField('custom_routes_categorytree_list', 'options');
                echo $this->form->renderField('css_file', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
            case 'routes_table':
            case 'routes_maptable':
                echo HTMLHelper::_('uitab.addTab', 'myTab', 'Options', Text::_('Options'));
                echo $this->form->renderField('table_note', 'options');
                echo $this->form->renderField('rt_catid', 'options');
                echo HTMLHelper::_('uitab.endTab');
                break;
        }
        echo HTMLHelper::_('uitab.addTab', 'myTab', 'Articles', Text::_('Articles'));
        echo $this->form->renderField('addarticles', 'options');
        echo $this->form->renderField('before', 'options');
        echo $this->form->renderField('after', 'options');
        echo HTMLHelper::_('uitab.endTab');

        echo HTMLHelper::_('uitab.endTabSet');
        ?>
    </div>

    <?php
    echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'programme'));

    if ($this->state->params->get('save_history', 1)) :
        ?>
        <div class="control-group">
            <div class="control-label"><?php echo $this->form->getLabel('version_note'); ?></div>
            <div class="controls"><?php echo $this->form->getInput('version_note'); ?></div>
        </div>
    <?php endif; ?>
</fieldset>
</div>
</div>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<input type="hidden" name="jform[id]" value="<?php echo isset($this->item->id) ? $this->item->id : ''; ?>" />

<input type="hidden" name="jform[state]" value="<?php echo isset($this->item->state) ? $this->item->state : ''; ?>" />

<?php echo $this->form->renderField('created_by'); ?>
<?php echo $this->form->renderField('modified_by'); ?>


<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

<input type="hidden" name="task" value=""/>
<?php echo HTMLHelper::_('form.token'); ?>

</form>
<?php
// The "Defining your own layout" help (syntax + available fields) is now
// shown via small Syntax/Fields buttons next to each relevant field itself
// (see administrator/src/Field/LayoutHelpTextareaField.php and
// LayoutHelpButtonsTrait), rather than one <details> block per Customise
// tab - layouthelp-syntax.html / layouthelp-fields-{type}.html are still
// the underlying content files, just read by that trait now instead of a
// displayLayoutHelp() call here.