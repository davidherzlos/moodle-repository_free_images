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

global $CFG;
require_once($CFG->dirroot . '/repository/lib.php');

use repository_free_images\local\client;

/**
 * repository_free_images class
 * This is a class used to browse images from free_images
 *
 * @since Moodle 2.0
 * @package    repository_free_images
 * @copyright  2010 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_free_images extends repository {

    /** @var client The API client for the repository. */
    public $client;

    /** @var string keyword search. */
    public $keyword;

    /**
     * Constructor for this repository type.
     *
     * @param int $repositoryid repository instance id.
     * @param int|stdClass $context a context id or context object.
     * @param mixed[] $options repository options.
     * @param int $readonly indicate this repo is readonly or not.
     */
    public function __construct(int $repositoryid, $context = SYSCONTEXTID, array $options = [], int $readonly = 0) {
        $this->client = new client();
        parent::__construct($repositoryid, $context, $options, $readonly);
    }

    /**
     * Returns maximum width for images
     *
     * Takes the maximum width for images eithre from search form or from
     * user preferences, updates user preferences if needed
     *
     * @return int
     */
    private function get_maxwidth() {

        $param = optional_param('free_images_maxwidth', 0, PARAM_INT);
        $pref = get_user_preferences('repository_free_images_maxwidth', $this->client::IMAGE_SIDE_LENGTH);
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
    private function get_maxheight() {
        $param = optional_param('free_images_maxheight', 0, PARAM_INT);
        $pref = get_user_preferences('repository_free_images_maxheight', $this->client::IMAGE_SIDE_LENGTH);
        if ($param > 0 && $param != $pref) {
            $pref = $param;
            set_user_preference('repository_free_images_maxheight', $pref);
        }
        return $pref;
    }

    /**
     * Given a path, and perhaps a search, get a list of files.
     *
     * See details on {@link https://moodledev.io/docs/apis/plugintypes/repository}
     *
     * @param string $path this parameter can a folder name, or a identification of folder
     * @param string $page the page number of file list
     * @return mixed[] $result the list of files, including meta infomation, containing the following keys
     *           manage, url to manage url
     *           client_id
     *           login, login form
     *           repo_id, active repository id
     *           login_btn_action, the login button action
     *           login_btn_label, the login button label
     *           total, number of results
     *           perpage, items per page
     *           page
     *           pages, total pages
     *           issearchresult, is it a search result?
     *           list, file list
     *           path, current path and parent path
     */
    public function get_listing($path = '', $page = '') {
        $list = [];
        $list['page'] = (int)$page;
        if ($list['page'] < 1) {
            $list['page'] = 1;
        }
        $list['list'] = $this->client->search_images($this->keyword, $list['page'] - 1,
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
     * To check whether the user is logged in.
     *
     * This gets called when the filepicker needs to display the repository
     * interface and process a search request.
     *
     * @return bool
     */
    public function check_login() {
        return $this->client->is_logged_in($this);
    }

    /**
     * Show the login screen, if required.
     *
     * @return string
     */
    public function print_login() {
        if ($this->options['ajax']) {
            return $this->client->get_custom_form();
        } else {
            echo $this->client->get_custom_nonajax_form();
            return "";
        }
    }

    /**
     * Returns if this repository supports global search.
     *
     * @return bool
     */
    public function global_search() {
        return $this->client->supports_global_search();
    }

    /**
     * Search files in repository
     * When doing global search, $searchtext will be used as keyword.
     *
     * @param string $searchtext search key word
     * @param int $page page
     * @return mixed see {@link repository::get_listing()}
     */
    public function search($searchtext, $page = 0) {
        $searchresult = [];
        $searchresult['list'] = $this->client->search_images($searchtext);
        return $searchresult;
    }

    /**
     * Return the full url of the image as a source of information,
     *
     * The result of the function is stored in files.source field for future reference.
     *
     * @param string $url url of the image
     * @return string|null
     */
    public function get_file_source_info($url) {
        return $url;
    }

    /**
     * What kind of files will be in this repository?
     *
     * @return string[] return '*' means this repository support any files, otherwise
     *               return mimetypes of files, it can be an array
     */
    public function supported_filetypes() {
        return ['image/gif', 'image/jpe', 'image/jpeg', 'image/jpg', 'image/png', 'image/svg'];
    }

    /**
     * Tells how the file can be picked from this repository
     *
     * Maximum value is FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE
     *
     * @return int
     */
    public function supported_returntypes() {
        return $this->client->get_file_types();
    }

    /**
     * Tells how the file can be picked from this repository
     *
     * Maximum value is FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE
     *
     * @return int
     */
    public function default_returntype() {
        return FILE_EXTERNAL;
    }
}
