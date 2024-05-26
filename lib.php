<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin is used to access free_images files
 *
 * @since Moodle 2.0
 * @package    repository_free_images
 * @copyright  2010 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/repository/lib.php');
require_once(__DIR__ . '/free_images.php');

/**
 * repository_free_images class
 * This is a class used to browse images from free_images
 *
 * @since Moodle 2.0
 * @package    repository_free_images
 * @copyright  2009 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_free_images extends repository {


    /** @var string keyword search. */
    protected $keyword;

    /**
     * Returns maximum width for images
     *
     * Takes the maximum width for images eithre from search form or from
     * user preferences, updates user preferences if needed
     *
     * @return int
     */
    private function get_maxwidth(): mixed {
        $param = optional_param('free_images_maxwidth', 0, PARAM_INT);
        $pref = get_user_preferences('repository_free_images_maxwidth', FREE_IMAGES_IMAGE_SIDE_LENGTH);
        if ($param > 0 && $param != $pref) {
            $pref = $param;
            set_user_preference('repository_free_images_maxwidth', $pref);
        }
        return $pref;
    }

    /**
     * Returns maximum height for images
     *
     * Takes the maximum height for images eithre from search form or from
     * user preferences, updates user preferences if needed
     *
     * @return int
     */
    private function get_maxheight(): mixed {
        $param = optional_param('free_images_maxheight', 0, PARAM_INT);
        $pref = get_user_preferences('repository_free_images_maxheight', FREE_IMAGES_IMAGE_SIDE_LENGTH);
        if ($param > 0 && $param != $pref) {
            $pref = $param;
            set_user_preference('repository_free_images_maxheight', $pref);
        }
        return $pref;
    }

    /**
     * NOTE: THIS METHOD IS PART OF THE SPECIFIC IMPLEMENTATION.
     *
     */
    public function get_listing($path = '', $page = ''): array {
        $client = new free_images;
        $list = [];
        $list['page'] = (int)$page;
        if ($list['page'] < 1) {
            $list['page'] = 1;
        }
        $list['list'] = $client->search_images($this->keyword, $list['page'] - 1,
                ['iiurlwidth' => $this->get_maxwidth(),
                    'iiurlheight' => $this->get_maxheight()]);
        $list['nologin'] = true;
        $list['norefresh'] = true;
        $list['nosearch'] = true;
        if (!empty($list['list'])) {
            $list['pages'] = -1; // Means we don't know exactly how many pages there are but we can always jump to the next page.
        } else if ($list['page'] > 1) {
            $list['pages'] = $list['page']; // No images available on this page, this is the last page.
        } else {
            $list['pages'] = 0; // No paging.
        }
        return $list;
    }

    /**
     * NOTE: THIS METHOD IS PART OF THE SPECIFIC IMPLEMENTATION.
     */
    public function check_login(): bool {
        global $SESSION;
        $this->keyword = optional_param('free_images_keyword', '', PARAM_RAW);
        if (empty($this->keyword)) {
            $this->keyword = optional_param('s', '', PARAM_RAW);
        }
        $sesskeyword = 'free_images_'.$this->id.'_keyword';
        if (empty($this->keyword) && optional_param('page', '', PARAM_RAW)) {
            // This is the request of another page for the last search, retrieve the cached keyword.
            if (isset($SESSION->{$sesskeyword})) {
                $this->keyword = $SESSION->{$sesskeyword};
            }
        } else if (!empty($this->keyword)) {
            // Save the search keyword in the session so we can retrieve it later.
            $SESSION->{$sesskeyword} = $this->keyword;
        }
        return !empty($this->keyword);
    }

    /**
     * NOTE: THIS METHOD IS PART OF THE SPECIFIC IMPLEMENTATION.
     * if check_login returns false,
     * this function will be called to print a login form.
     */
    public function print_login(): mixed {
        $keyword = new stdClass();
        $keyword->label = get_string('keyword', 'repository_free_images').': ';
        $keyword->id    = 'input_text_keyword';
        $keyword->type  = 'text';
        $keyword->name  = 'free_images_keyword';
        $keyword->value = '';
        $maxwidth = [
            'label' => get_string('maxwidth', 'repository_free_images').': ',
            'type' => 'text',
            'name' => 'free_images_maxwidth',
            'value' => get_user_preferences('repository_free_images_maxwidth', FREE_IMAGES_IMAGE_SIDE_LENGTH),
        ];
        $maxheight = [
            'label' => get_string('maxheight', 'repository_free_images').': ',
            'type' => 'text',
            'name' => 'free_images_maxheight',
            'value' => get_user_preferences('repository_free_images_maxheight', FREE_IMAGES_IMAGE_SIDE_LENGTH),
        ];
        if ($this->options['ajax']) {
            $form = [];
            $form['login'] = [$keyword, (object)$maxwidth, (object)$maxheight];
            $form['nologin'] = true;
            $form['norefresh'] = true;
            $form['nosearch'] = true;
            $form['allowcaching'] = false; // Indicates that login form can NOT.
            // Be cached in filepicker.js (maxwidth and maxheight are dynamic).
            return $form;
        } else {
            echo <<<EOD
<table>
<tr>
<td>{$keyword->label}</td><td><input name="{$keyword->name}" type="text" /></td>
</tr>
</table>
<input type="submit" />
EOD;
        }
    }

    /**
     * NOTE: THIS METHOD IS PART OF THE SPECIFIC IMPLEMENTATION.
     * search
     * if this plugin support global search, if this function return
     * true, search function will be called when global searching working
     */
    public function global_search(): bool {
        return false;
    }

    /**
     * NOTE: THIS METHOD IS PART OF THE SPECIFIC IMPLEMENTATION.
     */
    public function search($searchtext, $page = 0): mixed {
        $client = new free_images;
        $searchresult = [];
        $searchresult['list'] = $client->search_images($searchtext);
        return $searchresult;
    }

    /**
     * NOTE: THIS METHOD IS PART OF THE SPECIFIC IMPLEMENTATION.
     *
     * Return the source information
     *
     * @param stdClass $url
     * @return string|null
     */
    public function get_file_source_info($url): mixed {
        return $url;
    }

    /**
     * NOTE: THIS METHOD IS PART OF THE SPECIFIC IMPLEMENTATION.
     *
     * Is this repository accessing private data?
     *
     * @return bool
     */
    public function contains_private_data(): bool {

        return false;
    }
}

