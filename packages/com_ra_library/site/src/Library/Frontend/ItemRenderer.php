<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Ra_library
 * @author     Chris Vaughan <ruby.tuesday@ramblers-webs.org.uk>
 * @copyright  2026 Chris Vaughan
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_library\Site\Library\Frontend;

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Ramblers\Component\Ra_library\Administrator\Helper\ImageGalleryHelper;
use Ramblers\Component\Ra_library\Administrator\Helper\AttachmentHelper;
use Ramblers\Component\Ra_library\Administrator\Helper\RoutePointHelper;
use Ramblers\Component\Ra_library\Site\Helper\FrontendHelper;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\SimpleTemplate;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Map as LeafletMap;
use Ramblers\Component\Ra_library\Site\Library\Leaflet\Gpx\Map as GpxMap;
use Ramblers\Component\Ra_library\Site\Library\Load\Load;
use Joomla\CMS\Component\ComponentHelper;

/**
 * Turns one Past Walk / Route database row into the flat token array a
 * SimpleTemplate string is rendered against ({field} / {if:field}...{/if},
 * the same engine already used for the Walks Manager / Walks Editor
 * displays), and handles the "one global item layout, shared by Blog and
 * Single Item views via a {readmore} split" convention agreed with Chris.
 *
 * Available tokens (all record types):
 *   {title}            plain title text
 *   {title_link}       title, wrapped in a link to the single-item page when one is
 *                       configured (Blog context), otherwise plain text (Single/List context)
 *   {url}              the single-item page link on its own, empty if none configured
 *   {category}         category title (blank if uncategorised)
 *   {description}      the record's description field (HTML, as entered)
 *   {national_grade}   e.g. "Easy access", "Moderate" etc.
 *   {distance_miles}   e.g. "5.2 miles"
 *   {distance_km}      e.g. "8.4 km"
 *   {featured_image}   <img> of the featured photo (or first photo), empty if none
 *   {image_grid}        thumbnail grid of every photo, each linking to its full-size copy
 *   {gpx_list}          list of GPX download links, empty if none - each file's
 *                       link/visibility follows its own download permission
 *                       (per-file override on that GPX row, or else the
 *                       site-wide default), see resolveGpxDownloadPermission()
 *   {gpx_map}           the featured GPX file's route embedded inline on the page as a map (auto-picks the only file if there's just one), empty if none - its own "Download route" link follows the same per-file permission as {gpx_list}
 *   {gpx_map_list}      list of the OTHER GPX files (i.e. not the one {gpx_map} is already showing), each opening that route on a map (in a popup) when clicked, empty if none - same per-file download permission as {gpx_list}
 *   {document_list}     list of titled document download links, empty if none
 *
 * Past walk only:
 *   {walk_date}         e.g. "2 Aug 2026"
 *   {walk_date_dow}      e.g. "Sunday 2 Aug 2026"
 *   {walk_leader}
 *
 * Route only:
 *   {route_points_list}  ordered list of waypoints (title, grid reference, description)
 *
 * The image/GPX/document/route-point tokens are deliberately whole
 * pre-built HTML blocks rather than something a template author assembles
 * field by field - SimpleTemplate substitutes single scalar values, it has
 * no repeat/loop construct, so a collection like "every photo" is rendered
 * server-side into one HTML string and handed to the template as a single
 * token, the same way existing tokens like {gradeimg} already do elsewhere
 * in this component. Positioning a whole block on the page (float it,
 * place it above/below other blocks) is controllable via the template's
 * own HTML/CSS; positioning individual items *within* a block is not.
 *
 * The global item template (Components > Ra_library > Options) is split
 * into two separate fields, Intro and More, rather than one field with a
 * {readmore} marker the admin has to place correctly - Blog renders Intro
 * plus an automatic "Read more" link, Single renders Intro followed by
 * More. This means there's no way to put the split in an unsafe place
 * (e.g. inside an unclosed tag) - each field is simply its own complete
 * chunk of markup.
 *
 * @since  1.0.0
 */
class ItemRenderer
{
    private const KM_PER_MILE = 1.609344;

    /**
     * Build the token values for one record. The link tokens ({url},
     * {title_link}) always point at that record's own fixed single-item
     * page (view=pastwalk / view=route) - that page needs no Display
     * Options row or menu item set up, so this never has anywhere to be
     * conditionally blank.
     *
     * @param   \stdClass  $row         A row from FrontendHelper::getPublishedItem(s)().
     * @param   string     $recordType  'pastwalk' or 'route'.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function buildValues(\stdClass $row, string $recordType): array
    {
        $id = (int) $row->id;
        $title = (string) ($row->title ?? '');
        $url = FrontendHelper::getItemUrl($recordType, $id);

        // Whether "view this item" links (title_link here, and the
        // Table/Map+Table row and marker links built elsewhere) open the
        // item in a popup instead of navigating to its own page - one
        // shared, site-wide preference (Components > Ra_library > Options
        // > Item Preview) rather than something set per display, so every
        // listing behaves the same way. media/js/ra.itempreview.js is what
        // actually intercepts clicks on this class.
        $openInModal = (int) ComponentHelper::getParams('com_ra_library')->get('open_items_in_modal', 0) === 1;
        $previewClass = $openInModal ? ' class="ra-item-preview-link"' : '';

        $values = [
            'title' => $title,
            'title_link' => '<a href="' . htmlspecialchars($url) . '"' . $previewClass . '>' . htmlspecialchars($title) . '</a>',
            'url' => htmlspecialchars($url),
            'category' => htmlspecialchars((string) ($row->category_title ?? '')),
            'description' => (string) ($row->description ?? ''),
            'national_grade' => htmlspecialchars((string) ($row->national_grade ?? '')),
        ];

        $distanceKm = isset($row->distance_km) ? (float) $row->distance_km : 0.0;

        if ($distanceKm > 0) {
            $values['distance_km'] = number_format($distanceKm, 1) . ' km';
            $values['distance_miles'] = number_format($distanceKm / self::KM_PER_MILE, 1) . ' miles';
        } else {
            $values['distance_km'] = '';
            $values['distance_miles'] = '';
        }

        $values['featured_image'] = self::buildFeaturedImage($id);
        $values['image_grid'] = self::buildImageGrid($id);
        $values['gpx_list'] = self::buildGpxList($id, $recordType);
        $values['gpx_map'] = self::buildGpxMap($id, $recordType);
        $values['gpx_map_list'] = self::buildGpxMapList($id, $recordType);
        $values['document_list'] = self::buildDocumentList($id);

        if ($recordType === 'pastwalk') {
            $walkDate = (string) ($row->walk_date ?? '');

            if ($walkDate !== '' && $walkDate !== '0000-00-00') {
                $ts = strtotime($walkDate);
                // Plain PHP date formatting rather than HTMLHelper::_('date', ...) -
                // sidesteps HTMLHelper's service-key resolution entirely, so this
                // can't be the source of any HTMLHelper-related deprecation notice.
                $values['walk_date'] = $ts ? date('j M Y', $ts) : '';
                $values['walk_date_dow'] = $ts ? date('l j M Y', $ts) : '';
            } else {
                $values['walk_date'] = '';
                $values['walk_date_dow'] = '';
            }

            $values['walk_leader'] = htmlspecialchars((string) ($row->walk_leader ?? ''));
        }

        if ($recordType === 'route') {
            $values['route_points_list'] = self::buildRoutePointsList($id);
        }

        return $values;
    }

    /**
     * Render a List row: a plain SimpleTemplate render, no {readmore} handling.
     *
     * @param   string  $templateString   The admin's custom_pastwalks_list / custom_routes_list value (may be empty).
     * @param   string  $defaultTemplate  Fallback template if $templateString is empty.
     * @param   array   $values           From buildValues().
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function renderList(string $templateString, string $defaultTemplate, array $values): string
    {
        return self::renderTemplate($templateString, $defaultTemplate, $values);
    }

    /**
     * Render one Blog entry: the Intro template, plus an automatic
     * "Read more" link (the single-item page always exists - see
     * buildValues() - so this is unconditional).
     *
     * @param   string  $introTemplate  The global pastwalk_item_template_intro / route_item_template_intro value.
     * @param   array   $values         From buildValues() - $values['url'] supplies the Read more link target.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function renderBlogIntro(string $introTemplate, array $values): string
    {
        $html = '<div class="ra-item-intro">'
            . self::renderTemplate($introTemplate, $introTemplate, $values)
            . '</div>';
        $html .= '<p class="ra-readmore"><a href="' . $values['url'] . '">'
            . htmlspecialchars(Text::_('COM_RA_LIBRARY_READ_MORE')) . '</a></p>';

        return $html;
    }

    /**
     * Render the Single Item view: the Intro template followed by the More
     * template.
     *
     * @param   string  $introTemplate  The global pastwalk_item_template_intro / route_item_template_intro value.
     * @param   string  $moreTemplate   The global pastwalk_item_template_more / route_item_template_more value.
     * @param   array   $values         From buildValues().
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function renderFull(string $introTemplate, string $moreTemplate, array $values): string
    {
        return '<div class="ra-item-intro">'
            . self::renderTemplate($introTemplate, $introTemplate, $values)
            . '</div>'
            . '<div class="ra-item-more">'
            . self::renderTemplate($moreTemplate, $moreTemplate, $values)
            . '</div>';
    }

    /**
     * Resolve which Intro/More template string actually applies to one
     * item: its own per-item override (template_intro_override /
     * template_more_override columns - see 1.0.10.sql, and the "Layout"
     * tab on the Past Walk / Route edit form) if it has set one,
     * otherwise the already-resolved site-wide template passed in (see
     * DisplayHelper::getGlobalItemTemplateIntro()/getGlobalItemTemplateMore()).
     * Blank/whitespace-only counts as "not set", same convention as the
     * site-wide Options fields falling back to the hardcoded defaults.
     *
     * @param   \stdClass  $item            The raw item row - must have the override column (even if NULL).
     * @param   string     $overrideField   'template_intro_override' or 'template_more_override'.
     * @param   string     $globalTemplate  The already-resolved site-wide template for this record type.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function resolveTemplate(\stdClass $item, string $overrideField, string $globalTemplate): string
    {
        $override = trim((string) ($item->{$overrideField} ?? ''));

        return $override !== '' ? $override : $globalTemplate;
    }

    /**
     * Render a page of the Blog display as three sections, mirroring
     * com_content's classic blog layout: full items (Intro + More, same
     * as the Single Item page) first, then intro-only items arranged in
     * columns, then a plain list of title links. $items is expected to
     * already be exactly the page's worth (numFull + numIntro + numLinks -
     * see DisplayHelper::displayRecordBlog(), which fetches that many and
     * uses the same total as the pagination page size), so this just
     * slices it into the three buckets in order - no separate
     * per-section pagination or requerying.
     *
     * @param   string  $recordType    'pastwalk' or 'route'.
     * @param   array   $items         The page's rows, from FrontendHelper::getPublishedItems().
     * @param   string  $introTemplate The global Intro template for this record type.
     * @param   string  $moreTemplate  The global More template for this record type.
     * @param   int     $numFull       How many of $items (from the start) are shown in full.
     * @param   int     $numIntro      How many after that are shown as Intro only.
     * @param   int     $introColumns  How many columns to arrange the intro items into.
     * @param   int     $numLinks      How many after that are shown as plain title links.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function renderBlogSections(
        string $recordType,
        array $items,
        string $introTemplate,
        string $moreTemplate,
        int $numFull,
        int $numIntro,
        int $introColumns,
        int $numLinks
    ): string {
        $fullItems = array_slice($items, 0, max(0, $numFull));
        $introItems = array_slice($items, max(0, $numFull), max(0, $numIntro));
        $linkItems = array_slice($items, max(0, $numFull) + max(0, $numIntro), max(0, $numLinks));

        $html = '<div class="ra-blog">';

        if (!empty($fullItems)) {
            $html .= '<div class="ra-blog-full">';

            foreach ($fullItems as $item) {
                $values = self::buildValues($item, $recordType);
                $itemIntro = self::resolveTemplate($item, 'template_intro_override', $introTemplate);
                $itemMore = self::resolveTemplate($item, 'template_more_override', $moreTemplate);
                $html .= '<div class="ra-blog-item ra-blog-item-full">'
                    . self::renderFull($itemIntro, $itemMore, $values)
                    . '</div>';
            }

            $html .= '</div>';
        }

        if (!empty($introItems)) {
            $columns = max(1, $introColumns);
            $html .= '<div class="ra-blog-intro-columns" style="--ra-blog-intro-columns: ' . $columns . ';">';

            foreach ($introItems as $item) {
                $values = self::buildValues($item, $recordType);
                $itemIntro = self::resolveTemplate($item, 'template_intro_override', $introTemplate);
                $html .= '<div class="ra-blog-item ra-blog-item-intro">'
                    . self::renderBlogIntro($itemIntro, $values)
                    . '</div>';
            }

            $html .= '</div>';
        }

        if (!empty($linkItems)) {
            $html .= '<ul class="ra-blog-links">';

            foreach ($linkItems as $item) {
                $values = self::buildValues($item, $recordType);
                $html .= '<li class="ra-blog-link-item">' . $values['title_link'] . '</li>';
            }

            $html .= '</ul>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build the category hierarchy breadcrumb shown just before the
     * rendered item on the Single Item page (root category first, the
     * item's own category last) - governed by two global Options toggles
     * per record type: whether it's shown at all, and whether each
     * category is a link (to that record type's Category Tree display -
     * see FrontendHelper::getCategoryTreeLinkUrl()) or plain text.
     *
     * Returns '' if the toggle is off, the item is uncategorised, or the
     * category can no longer be found/is unpublished.
     *
     * @param   string  $recordType  'pastwalk' or 'route'.
     * @param   int     $catid       The item's own catid.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function buildCategoryBreadcrumb(string $recordType, int $catid): string
    {
        if ($catid <= 0) {
            return '';
        }

        $params = ComponentHelper::getParams('com_ra_library');
        $prefix = $recordType === 'route' ? 'route_' : 'pastwalk_';

        if (!(int) $params->get($prefix . 'show_category_breadcrumb', 1)) {
            return '';
        }

        $chain = FrontendHelper::getCategoryBreadcrumbChain($catid);

        if (empty($chain)) {
            return '';
        }

        $useLinks = (bool) $params->get($prefix . 'category_breadcrumb_links', 1);

        $parts = [];
        foreach ($chain as $category) {
            $title = htmlspecialchars($category->title);

            if ($useLinks) {
                $url = FrontendHelper::getCategoryTreeLinkUrl($recordType, (int) $category->id);
                $parts[] = $url !== null ? '<a href="' . $url . '">' . $title . '</a>' : $title;
            } else {
                $parts[] = $title;
            }
        }

        return '<nav class="ra-category-breadcrumb" aria-label="'
            . htmlspecialchars(Text::_('COM_RA_LIBRARY_CATEGORY_BREADCRUMB_LABEL')) . '">'
            . '<span class="ra-category-breadcrumb-prefix">'
            . htmlspecialchars(Text::_('COM_RA_LIBRARY_CATEGORY_BREADCRUMB_PREFIX')) . '</span> '
            . implode('<span class="ra-category-breadcrumb-sep">&raquo;</span>', $parts)
            . '</nav>';
    }

    /**
     * Shared SimpleTemplate wrapper - falls back to the default template if
     * $templateString is blank, and again if $templateString turns out to
     * be malformed (a broken custom template shouldn't take the page down;
     * the admin sees the same validation error when they try to save it).
     *
     * @since   1.0.0
     */
    private static function renderTemplate(string $templateString, string $defaultTemplate, array $values): string
    {
        $templateString = trim($templateString) !== '' ? $templateString : $defaultTemplate;

        try {
            $template = new SimpleTemplate($templateString);

            return $template->render($values);
        } catch (\InvalidArgumentException $e) {
            $template = new SimpleTemplate($defaultTemplate);

            return $template->render($values);
        }
    }

    private static function buildFeaturedImage(int $recordId): string
    {
        $images = ImageGalleryHelper::getImagesForRecord($recordId);
        $featured = self::findFeaturedImage($images);

        if ($featured === null) {
            return '';
        }

        $imgTag = self::buildFeaturedImageTag((string) $featured->thumbnail_path, (string) ($featured->caption ?? ''));

        if ($imgTag === '') {
            return '';
        }

        $largePath = (string) ($featured->large_path ?? '');

        if ($largePath === '') {
            return $imgTag;
        }

        // A single-photo "gallery" using the exact same .ra-image-grid /
        // .ra-image-grid-item markup {image_grid}'s thumbnails use, so
        // media/js/ra.lightbox.js's click delegation (which looks for the
        // nearest .ra-image-grid ancestor) picks it up automatically and
        // opens the same lightbox viewer - just with no Prev/Next since
        // there's only the one photo here. The extra ra-featured-image-grid
        // class lets frontend.css cancel the grid/thumbnail-box styling
        // that class would otherwise apply, so the featured image keeps
        // its own (larger, floated) appearance rather than shrinking to
        // thumbnail size.
        $caption = htmlspecialchars((string) ($featured->caption ?? ''));

        return '<span class="ra-image-grid ra-featured-image-grid">'
            . '<a class="ra-image-grid-item" href="' . htmlspecialchars(self::rootUrl($largePath)) . '" title="' . $caption . '">'
            . $imgTag
            . '</a>'
            . '</span>';
    }

    /**
     * Pick "the" featured image out of a record's photos - the one
     * explicitly flagged ->featured, or the first (by ordering) if none is
     * flagged. Shared by buildFeaturedImage() (what {featured_image} shows)
     * and buildImageGrid() (which excludes this same photo from {image_grid}
     * - it's already shown once via {featured_image}, so showing it again
     * as the grid's first thumbnail would just be a visible duplicate).
     *
     * @param   array  $images  From ImageGalleryHelper::getImagesForRecord().
     *
     * @return  \stdClass|null
     *
     * @since   1.0.0
     */
    private static function findFeaturedImage(array $images): ?\stdClass
    {
        foreach ($images as $image) {
            if (!empty($image->featured)) {
                return $image;
            }
        }

        return $images[0] ?? null;
    }

    /**
     * Shared <img> markup builder for a featured/thumbnail photo - used both
     * by buildFeaturedImage() (a single record's own gallery) and by
     * buildCategoryBranchValues() (a category's representative photo, found
     * across a whole branch by FrontendHelper::getCategoryBranchFeaturedImage()).
     *
     * @since   1.0.0
     */
    private static function buildFeaturedImageTag(string $thumbnailPath, string $caption): string
    {
        if ($thumbnailPath === '') {
            return '';
        }

        return '<img class="ra-featured-image" src="' . htmlspecialchars(self::rootUrl($thumbnailPath))
            . '" alt="' . htmlspecialchars($caption) . '" loading="lazy">';
    }

    /**
     * Build the token values for one category "branch" row in the Category
     * Tree display - either a subcategory being browsed into, or (once a
     * leaf is reached and there are no subcategories) not used at all, since
     * leaf categories show their actual items via buildValues() instead.
     *
     * Available tokens:
     *   {title}              plain category title text
     *   {title_link}         title, wrapped in a link to browse into that category
     *   {url}                the browse-into link on its own
     *   {description}        the category's own description field (HTML, as entered)
     *   {item_count}         number of items directly in this category (not its subcategories)
     *   {total_item_count}   total items anywhere in this branch (this category plus every subcategory, however deep)
     *   {featured_image}     <img> of a representative photo - the featured (or first) photo
     *                        belonging to the first item anywhere in the branch that has one, empty if none
     *
     * @param   \stdClass  $category  A row from FrontendHelper::getChildCategories() - ->id, ->title,
     *                                ->description, ->item_count, ->total_item_count, ->featured_image_row.
     * @param   string     $url       The browse-into link for this category, from FrontendHelper::getCategoryTreeUrl().
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function buildCategoryBranchValues(\stdClass $category, string $url): array
    {
        $title = (string) ($category->title ?? '');
        $imageRow = $category->featured_image_row ?? null;

        return [
            'title' => $title,
            'title_link' => '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($title) . '</a>',
            'url' => htmlspecialchars($url),
            'description' => (string) ($category->description ?? ''),
            'item_count' => (string) (int) ($category->item_count ?? 0),
            'total_item_count' => (string) (int) ($category->total_item_count ?? 0),
            'featured_image' => $imageRow
                ? self::buildFeaturedImageTag((string) $imageRow->thumbnail_path, (string) ($imageRow->caption ?? ''))
                : '',
        ];
    }

    /**
     * Grid columns and thumbnail box shape are webmaster-configurable
     * (Components > Ra_library > Options > Image Gallery), applied as CSS
     * custom properties on the grid's own wrapper rather than baked into
     * frontend.css, so a single setting controls every grid on the site.
     * ComponentHelper::getParams() only returns what's actually been saved
     * - same gap as the Intro/More templates - so fall back to the same
     * defaults the config.xml fields themselves declare.
     *
     * The width/height settings are a RATIO, not an absolute pixel size -
     * the box's actual rendered size still comes from the grid column width
     * (so it stays responsive to Grid columns and screen width), only its
     * proportions come from here. Each photo is shown in full within that
     * shape via object-fit: contain (see frontend.css), never cropped.
     *
     * @since   1.0.0
     */
    private const DEFAULT_IMAGE_GRID_COLUMNS = 4;

    private const DEFAULT_IMAGE_GRID_THUMB_WIDTH = 140;

    private const DEFAULT_IMAGE_GRID_THUMB_HEIGHT = 110;

    private const DEFAULT_IMAGE_GRID_PAGE_SIZE = 12;

    private static function buildImageGrid(int $recordId): string
    {
        $images = ImageGalleryHelper::getImagesForRecord($recordId);

        if (empty($images)) {
            return '';
        }

        // Exclude the featured photo - it's already shown once via
        // {featured_image}, so the grid should only ever show the rest.
        $featured = self::findFeaturedImage($images);

        if ($featured !== null) {
            $images = array_values(array_filter(
                $images,
                static fn ($image) => (int) $image->id !== (int) $featured->id
            ));
        }

        if (empty($images)) {
            return '';
        }

        $params = ComponentHelper::getParams('com_ra_library');
        $columns = (int) $params->get('image_grid_columns', self::DEFAULT_IMAGE_GRID_COLUMNS);
        $thumbWidth = (int) $params->get('image_grid_thumb_width', self::DEFAULT_IMAGE_GRID_THUMB_WIDTH);
        $thumbHeight = (int) $params->get('image_grid_thumb_height', self::DEFAULT_IMAGE_GRID_THUMB_HEIGHT);
        $pageSize = (int) $params->get('image_grid_page_size', self::DEFAULT_IMAGE_GRID_PAGE_SIZE);
        $columns = $columns > 0 ? $columns : self::DEFAULT_IMAGE_GRID_COLUMNS;
        $thumbWidth = $thumbWidth > 0 ? $thumbWidth : self::DEFAULT_IMAGE_GRID_THUMB_WIDTH;
        $thumbHeight = $thumbHeight > 0 ? $thumbHeight : self::DEFAULT_IMAGE_GRID_THUMB_HEIGHT;
        $pageSize = max(0, $pageSize);

        $total = count($images);
        // 0 = "always show every photo on one page, however many there
        // are" (the admin's explicit way of disabling pagination).
        $paginate = $pageSize > 0 && $total > $pageSize;
        $totalPages = $paginate ? (int) ceil($total / $pageSize) : 1;

        $html = '<div class="ra-image-grid" style="--ra-grid-columns: ' . $columns
            . '; --ra-grid-thumb-ratio: ' . $thumbWidth . ' / ' . $thumbHeight . ';">';

        foreach ($images as $index => $image) {
            $caption = htmlspecialchars((string) ($image->caption ?? ''));
            // Every photo is still rendered up front (no AJAX round trip
            // for a "page 2") - media/js/ra.imagegrid.js just shows/hides
            // them by this data-page marker. That also means the lightbox
            // (media/js/ra.lightbox.js) can still cycle Prev/Next across
            // every photo in the gallery, not just the current page's.
            $pageAttr = $paginate ? (' data-page="' . ((int) floor($index / $pageSize) + 1) . '"') : '';
            $html .= '<a class="ra-image-grid-item"' . $pageAttr . ' href="' . htmlspecialchars(self::rootUrl($image->large_path))
                . '" title="' . $caption . '">'
                . '<img src="' . htmlspecialchars(self::rootUrl($image->thumbnail_path)) . '" alt="' . $caption . '" loading="lazy">'
                . '</a>';
        }

        $html .= '</div>';

        if ($paginate) {
            $html .= '<div class="ra-image-grid-pager" data-total-pages="' . $totalPages . '" data-current-page="1">'
                . '<button type="button" class="link-button tiny mintcake ra-image-grid-prev" disabled>'
                . '&laquo; ' . htmlspecialchars(Text::_('COM_RA_LIBRARY_IMAGE_GRID_PREV')) . '</button>'
                . '<span class="ra-image-grid-pager-status">'
                . htmlspecialchars(Text::sprintf('COM_RA_LIBRARY_IMAGE_GRID_PAGE_STATUS', 1, $totalPages)) . '</span>'
                . '<button type="button" class="link-button tiny mintcake ra-image-grid-next">'
                . htmlspecialchars(Text::_('COM_RA_LIBRARY_IMAGE_GRID_NEXT')) . ' &raquo;</button>'
                . '</div>';
        }

        return $html;
    }

    /**
     * Resolves the effective GPX-download permission for one attached GPX
     * file: its own download_override (gpxrow.xml row field) if set,
     * otherwise the site-wide pastwalk_gpx_download / route_gpx_download
     * default (Components > Ra_library > Options > Past Walks Layout /
     * Routes Layout) - same override-over-global convention as
     * resolveTemplate()/getGlobalItemTemplateIntro(), just per file rather
     * than per record, so e.g. a re-published/copyright-cleared route's GPX
     * can be opened up for download while a sibling file on the same
     * record stays restricted.
     *
     * @param   \stdClass  $file        An attachment row from AttachmentHelper::getAttachmentsForRecord().
     * @param   string     $recordType  'pastwalk' or 'route'.
     *
     * @return  string  'None', 'Users' or 'Public'.
     *
     * @since   1.0.0
     */
    private static function resolveGpxDownloadPermission(\stdClass $file, string $recordType): string
    {
        $override = (string) ($file->download_override ?? '');

        if (in_array($override, ['None', 'Users', 'Public'], true)) {
            return $override;
        }

        $paramKey = $recordType === 'route' ? 'route_gpx_download' : 'pastwalk_gpx_download';
        $global = (string) ComponentHelper::getParams('com_ra_library')->get($paramKey, 'Public');

        return in_array($global, ['None', 'Users', 'Public'], true) ? $global : 'Public';
    }

    /**
     * Whether the current visitor may download a GPX file under the given
     * resolved permission ('None'/'Users'/'Public' - see
     * resolveGpxDownloadPermission()). Same semantics as the older
     * Leaflet\Gpx\Map/Maplist::addDownloadLink switch used by the
     * standalone single-route map display, just shared here so
     * {gpx_list}/{gpx_map_list} follow the same rule.
     *
     * @param   string  $permission  'None', 'Users' or 'Public'.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    private static function gpxDownloadAllowed(string $permission): bool
    {
        switch ($permission) {
            case 'Public':
                return true;
            case 'Users':
                return Factory::getApplication()->getIdentity()->id != 0;
            default:
                return false;
        }
    }

    /**
     * Picks which GPX attachment {gpx_map} embeds inline: whichever one is
     * flagged featured (admin's "Featured" checkbox on the GPX subform row -
     * only one can be set, enforced in AttachmentHelper::saveAttachments()),
     * or the first by ordering if none is flagged - which means a record
     * with only a single GPX file (the common case) needs no configuration
     * at all for {gpx_map} to pick it up automatically.
     *
     * @param   int  $recordId  The past walk / route id.
     *
     * @return  \stdClass|null
     *
     * @since   1.0.0
     */
    private static function getPrimaryGpxFile(int $recordId): ?\stdClass
    {
        $files = AttachmentHelper::getAttachmentsForRecord($recordId, 'gpx');

        if (empty($files)) {
            return null;
        }

        foreach ($files as $file) {
            if (!empty($file->featured)) {
                return $file;
            }
        }

        return $files[0];
    }

    /**
     * {gpx_map} - the featured (or only) GPX file's route, embedded inline
     * on the item page as a real map, not behind a click. Unlike
     * {gpx_map_list} (built for possibly-several alternate files, so it
     * only loads a map when a visitor actually clicks one), this is meant
     * to always render eagerly - so it reuses Leaflet\Gpx\Map::displayPath()
     * outright, exactly as DisplayHelper::displayRoutesSingle() already
     * does for the standalone single-route map page, rather than the
     * lazy/click-to-load machinery built for {gpx_map_list}.
     *
     * displayPath() writes its HTML via echo (map container div, details
     * div, optional download line) rather than returning it, so this
     * captures that with output buffering to fit the token-substitution
     * convention every other {token} here follows.
     *
     * addDownloadLink follows this file's own resolved GPX-download
     * permission (its download_override, or the site-wide default -
     * resolveGpxDownloadPermission()) exactly as the standalone
     * single-route map page (DisplayHelper::displayRoutesSingle()) already
     * does - GpxMap::displayPath() itself renders the "Download route:"
     * link (or the "please log in" message) when allowed, so this is the
     * only place that link needs building for the embedded map.
     *
     * @param   int     $recordId    The past walk / route id.
     * @param   string  $recordType  'pastwalk' or 'route'.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private static function buildGpxMap(int $recordId, string $recordType): string
    {
        $file = self::getPrimaryGpxFile($recordId);

        if ($file === null) {
            return '';
        }

        $map = new GpxMap();
        $map->addDownloadLink = self::resolveGpxDownloadPermission($file, $recordType);

        ob_start();
        $map->displayPath($file->file_path);

        return (string) ob_get_clean();
    }

    /**
     * @param   int     $recordId    The past walk / route id.
     * @param   string  $recordType  'pastwalk' or 'route'.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private static function buildGpxList(int $recordId, string $recordType): string
    {
        $files = AttachmentHelper::getAttachmentsForRecord($recordId, 'gpx');

        if (empty($files)) {
            return '';
        }

        $items = '';
        $anyLoginGated = false;

        foreach ($files as $file) {
            $permission = self::resolveGpxDownloadPermission($file, $recordType);
            $title = htmlspecialchars((string) ($file->title ?: 'GPX file'));

            if (self::gpxDownloadAllowed($permission)) {
                $items .= '<li><a href="' . htmlspecialchars(self::rootUrl($file->file_path)) . '" download>' . $title . '</a></li>';
            } elseif ($permission === 'Users') {
                // Logged-in-only and this visitor is a guest - list the file
                // (so its existence isn't simply hidden) but without a link,
                // plus one combined login prompt after the loop.
                $items .= '<li class="ra-gpx-login-required">' . $title . '</li>';
                $anyLoginGated = true;
            }
            // permission === 'None' (for this visitor) - omit entirely.
        }

        if ($items === '') {
            return '';
        }

        $html = '<ul class="ra-gpx-list">' . $items . '</ul>';

        if ($anyLoginGated) {
            $html .= '<p class="ra-gpx-login-required">' . htmlspecialchars(Text::_('COM_RA_LIBRARY_GPX_LOGIN_TO_DOWNLOAD')) . '</p>';
        }

        return $html;
    }

    /**
     * {gpx_map_list} - style 1 of the agreed GPX display approaches: a
     * plain list of file titles, each opening that one route on a map (in
     * a ra.modals popup, same stacking system as the photo lightbox) when
     * clicked - rather than {gpx_list}'s plain file download links.
     *
     * The click handling and map-building itself is media/js/ra.gpxlightbox.js
     * reusing ra.display.gpxSingle (the exact same JS class
     * DisplayHelper::displayRoutesSingle() / GpxMap::displayPath() already
     * use for the standalone single-route map page) unchanged - this only
     * needs to render the trigger links and make sure the (fairly heavy)
     * Leaflet stack + licensed map options are on the page for that JS to
     * use, once, only when there's actually a GPX file to show.
     *
     * @param   int     $recordId    The past walk / route id.
     * @param   string  $recordType  'pastwalk' or 'route'.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private static function buildGpxMapList(int $recordId, string $recordType): string
    {
        $files = AttachmentHelper::getAttachmentsForRecord($recordId, 'gpx');

        if (empty($files)) {
            return '';
        }

        // {gpx_map} already embeds this one inline - exclude it here so it
        // isn't offered again in the list, the same way buildImageGrid()
        // excludes whichever photo {featured_image} is already showing.
        $primary = self::getPrimaryGpxFile($recordId);

        if ($primary !== null) {
            $files = array_values(array_filter(
                $files,
                static fn ($file) => (int) $file->id !== (int) $primary->id
            ));
        }

        if (empty($files)) {
            return '';
        }

        $items = '';
        $anyLoginGated = false;

        foreach ($files as $file) {
            $permission = self::resolveGpxDownloadPermission($file, $recordType);
            $title = htmlspecialchars((string) ($file->title ?: 'GPX file'));

            if (self::gpxDownloadAllowed($permission)) {
                // href is a normal, fully-qualified download link (right-click/no-JS
                // fallback). data-gpx-path is the same file but as the bare,
                // root-relative path ra.display.gpxSingle actually needs - it
                // prefixes this itself with ra.baseDirectory() (see maplist.js),
                // so passing the already-domain-qualified href there would double
                // up the site URL and produce a bad request.
                $items .= '<li><a class="ra-gpx-map-link" href="' . htmlspecialchars(self::rootUrl($file->file_path))
                    . '" data-gpx-path="' . htmlspecialchars($file->file_path) . '">' . $title . '</a></li>';
            } elseif ($permission === 'Users') {
                $items .= '<li class="ra-gpx-login-required">' . $title . '</li>';
                $anyLoginGated = true;
            }
            // permission === 'None' (for this visitor) - omit entirely.
        }

        if ($items === '') {
            return '';
        }

        self::loadGpxMapAssets();

        $html = '<ul class="ra-gpx-map-list">' . $items . '</ul>';

        if ($anyLoginGated) {
            $html .= '<p class="ra-gpx-login-required">' . htmlspecialchars(Text::_('COM_RA_LIBRARY_GPX_LOGIN_TO_DOWNLOAD')) . '</p>';
        }

        return $html;
    }

    /**
     * Registers the Leaflet mapping stack (leaflet.js, the elevation
     * profile plugin, leaflet-gpx, ra.leafletmap.js etc - see
     * Leaflet\Script::addScriptsandStyles(), which this ultimately calls)
     * and the licensed map options (Ordnance Survey/other API keys from
     * Components > Ra_library > Options) exactly once per page, however
     * many {gpx_map_list} tokens/items end up rendering.
     *
     * This deliberately does NOT set a command via setCommand(), so
     * Leaflet\Script::add() skips echoing a map container and
     * ra.bootstrapper() (see media/js/ra.js) skips auto-building a map -
     * it only decodes the map options into the JS-side ra.defaultMapOptions
     * global (licence keys included) for media/js/ra.gpxlightbox.js to
     * build its own map from later, on demand, when a link is actually
     * clicked - nothing is pre-built or pre-fetched for files nobody
     * clicks.
     *
     * Public (not just called from within this class) so
     * DisplayHelper::loadRecordDisplayAssets() can also call it, to prime
     * ra.gpxlightbox.js on any page the "open items in a popup" setting is
     * active on - a popup can fetch an item with its own {gpx_map_list}
     * even if nothing on the CURRENT page has one itself.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function loadGpxMapAssets(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        Load::addScript('media/com_ra_library/js/leaflet/gpx/maplist.js');
        Load::addStyleSheet('media/com_ra_library/css/ramblerslibrary.css');

        $map = new LeafletMap();
        $map->options->cluster = false;
        $map->options->displayElevation = true;
        $map->options->fullscreen = true;
        $map->options->mouseposition = true;
        $map->options->settings = true;
        $map->options->mylocation = true;
        $map->options->rightclick = true;
        $map->options->fitbounds = true;
        $map->options->print = true;
        $map->help_page = 'singleroute.html';
        $map->display();

        Load::addScript('media/com_ra_library/js/ra.gpxlightbox.js');
    }

    private static function buildDocumentList(int $recordId): string
    {
        $files = AttachmentHelper::getAttachmentsForRecord($recordId, 'document');

        if (empty($files)) {
            return '';
        }

        $html = '<ul class="ra-document-list">';

        foreach ($files as $file) {
            $title = htmlspecialchars((string) ($file->title ?: 'Document'));
            $html .= '<li><a href="' . htmlspecialchars(self::rootUrl($file->file_path)) . '" download>' . $title . '</a></li>';
        }

        $html .= '</ul>';

        return $html;
    }

    private static function buildRoutePointsList(int $recordId): string
    {
        $points = RoutePointHelper::getPointsForRecord($recordId);

        if (empty($points)) {
            return '';
        }

        $html = '<ol class="ra-route-points-list">';

        foreach ($points as $point) {
            $title = htmlspecialchars((string) ($point->title ?? ''));
            $gridRef = trim((string) ($point->grid_reference ?? ''));
            $html .= '<li><strong>' . $title . '</strong>';

            if ($gridRef !== '') {
                $html .= ' <span class="ra-route-point-gr">(' . htmlspecialchars($gridRef) . ')</span>';
            }

            if (!empty($point->description)) {
                // Description is stored via a safehtml-filtered textarea (see
                // RoutePointHelper) - already safe to output as-is.
                $html .= '<div class="ra-route-point-desc">' . $point->description . '</div>';
            }

            $html .= '</li>';
        }

        $html .= '</ol>';

        return $html;
    }

    private static function rootUrl(string $relativePath): string
    {
        if ($relativePath === '') {
            return '';
        }

        return Uri::root() . ltrim($relativePath, '/');
    }
}
