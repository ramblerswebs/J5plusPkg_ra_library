<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Ra_library
 * @author     Chris Vaughan <ruby.tuesday@ramblers-webs.org.uk>
 * @copyright  2026 Chris Vaughan
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_library\Site\View\Pastwalk;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_library\Site\Helper\DisplayHelper;
use Ramblers\Component\Ra_library\Site\Library\Frontend\ItemRenderer;
use Ramblers\Component\Ra_library\Site\Library\Load\Load;

/**
 * View class for the fixed "Single Past Walk" page - see PastwalkModel for
 * why this doesn't need a Display Options row or menu item.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView {

    protected $item;
    protected $renderedItem;
    protected $categoryBreadcrumb;
    protected $params;

    /**
     * @since 1.0.0
     */
    public function display($tpl = null) {
        $app = Factory::getApplication();

        $this->item = $this->get('Item');
        $this->params = $app->getParams();

        if (empty($this->item)) {
            throw new \Exception(Text::_('COM_RA_LIBRARY_ITEM_NOT_LOADED'), 404);
        }

        $values = ItemRenderer::buildValues($this->item, 'pastwalk');
        $globalIntro = DisplayHelper::getGlobalItemTemplateIntro('pastwalk');
        $globalMore = DisplayHelper::getGlobalItemTemplateMore('pastwalk');
        $intro = ItemRenderer::resolveTemplate($this->item, 'template_intro_override', $globalIntro);
        $more = ItemRenderer::resolveTemplate($this->item, 'template_more_override', $globalMore);
        $this->renderedItem = ItemRenderer::renderFull($intro, $more, $values);
        $this->categoryBreadcrumb = ItemRenderer::buildCategoryBreadcrumb('pastwalk', (int) ($this->item->catid ?? 0));

        Load::addStyleSheet('media/com_ra_library/css/frontend.css');
        // See DisplayHelper::loadRecordDisplayAssets() for why ra.js and
        // ramblerslibrary.css are also loaded here - the lightbox plugs
        // into ra.js's ra.modals popup system rather than building its own.
        Load::addScript('media/com_ra_library/js/ra.js');
        Load::addStyleSheet('media/com_ra_library/css/ramblerslibrary.css');
        Load::addStyleSheet('media/com_ra_library/css/ra.lightbox.css');
        Load::addScript('media/com_ra_library/js/ra.lightbox.js');
        Load::addScript('media/com_ra_library/js/ra.imagegrid.js');

        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * @since 1.0.0
     */
    protected function _prepareDocument() {
        $app = Factory::getApplication();
        $title = $this->item->title ?? '';

        if ($title !== '') {
            if ($app->get('sitename_pagetitles', 0) == 1) {
                $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
            } elseif ($app->get('sitename_pagetitles', 0) == 2) {
                $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
            }

            $this->document->setTitle($title);
        }
    }
}
