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
 * Base class responsible to communicate with image services. Each service is encapculated
 * on derived classes.
 *
 * @package repository_free_images
 * @copyright  2024 David OC <davidherzlos@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace repository_free_images\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/repository/free_images/lib.php');

use repository_free_images;

/**
 * Repository class to interact with external image services.
 *
 * @package    repository_free_images
 * @copyright  2024 David OC <davidherzlos@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {

    /** @var int Number of thumbs to show per page. */
    const THUMBS_PER_PAGE = 25;

    /** @var int Image side lenght. */
    const IMAGE_SIDE_LENGTH = 1024;

    /** @var int Image thumb size. */
    const THUMB_SIZE = 135;

    /** @var string client ID. */
    const CLIENT_ID = 'znXXliTsULyM1kY-oiY37iKo4hdCKPzlYcoi-Lsq4oU';

    /** @var object Curl connection object. */
    private $_conn  = null;

    /** @var mixed[] Params to configure the connection. */
    private $_param = [];

    /** @var string API URL. */
    protected $api;

    /** @var string user ID. */
    protected $userid;

    /** @var string username. */
    protected $username;

    /** @var string token key. */
    protected $token;

    /**
     * Class constructor.
     *
     * @param string $url API endpoint URL.
     */
    public function __construct($url = '') {
        $this->api = empty($url) ? 'https://api.unsplash.com/search/photos' : $url;
        $this->_conn = new \curl(['cache' => true, 'debug' => false]);
    }

    /**
     * Returns a list of image results from the service API given a search keyword,
     * the current page of results and an associative array of params.
     * on the passed keyword.
     *
     * NOTE: The user should be able to choose an orientation.
     * Also the licence for the image is wrong.
     *
     * @param string $keyword The keyword to search.
     * @param int $page The current page of results to fetch.
     * @param mixed[] $params The params to make the request.
     * @return mixed[] Results.
     */
    public function search_images($keyword, $page = 0, $params = []) {
        $images = [];

        $this->_param['query'] = $keyword;
        $this->_param['page'] = $page;
        $this->_param['perpage'] = self::THUMBS_PER_PAGE; $this->_param['client_id'] = self::CLIENT_ID; $this->_param += $params;

        $response = $this->_conn->get($this->api, $this->_param);
        $json = json_decode($response);

        if (empty($json->results)) {
            return $images;
        }

        foreach ($json->results as $record) {
            $images[] = $this->extract_image_attrs($record);
        }

        return $images;
    }

    /**
     * Extracts relevant image information and returns it as a record.i
     *
     * Needs to have realistic widths and heights for icons.
     * Also the slug property should be localized by the user lang.
     *
     * @param object $record The record containig the image data,
     * @return mixed[] Array of requested image properties.
     *
     */
    public function extract_image_attrs(object $record) {
        global $OUTPUT;

        $format = $this->get_file_format_from_url($record->urls->regular);
        // FIXME: This property returns nothing.
        $thumbnail = $OUTPUT->image_url(file_extension_icon($record->slug))->out(false);

        return [
            'title' => "{$record->slug}.{$format}",
            'author' => $record->user->name,
            'source' => $record->urls->regular, // NOTE: We could use raw for download.
            'url' => $record->urls->regular,
            'image_width' => self::THUMB_SIZE,
            'image_height' => self::THUMB_SIZE,
            'thumbnail' => $thumbnail,
            'thumbnail_width' => self::THUMB_SIZE,
            'thumbnail_height' => self::THUMB_SIZE,
            'license' => 'cc-sa',
            'realthumbnail' => $record->urls->thumb,
            'realicon' => $record->urls->thumb,
            'datemodified' => strtotime($record->updated_at),
        ];
    }

    /**
     * It determines the file extension from a given string with an url
     *
     * @param string $url The url to use for searching.
     * @return string File extension.
     */
    private function get_file_format_from_url(string $url = ''): string {
        if (empty($url)) {
            return new \moodle_exception('invalidurl');
        }
        $moodleurl = new \moodle_url($url);
        if (empty($moodleurl->out()) || empty($moodleurl->get_param('fm'))) {
            return new \moodle_exception('invalidfiletype');
        }
        return $moodleurl->get_param('fm');
    }

    /**
     * Checks if the user is loggedin on the external service.
     *
     * Unsplash doesn't require authentication for
     * searching and fetching. So we can always
     * return true.
     *
     * @param repository_free_images $repo object.
     * @return bool
     *
     */
    public function is_logged_in(repository_free_images $repo) {
        global $SESSION;

        $kparam = optional_param('free_images_keyword', '', PARAM_RAW);
        $sparam = optional_param('s', '', PARAM_RAW);
        $pparam = optional_param('page', '', PARAM_RAW);

        $repo->keyword = !empty($kparam) ? $kparam : $sparam;
        $sesskeyword = 'free_images_'.$repo->id.'_keyword';

        // This is the request of another page for the last search, retrieve the cached keyword.
        if (empty($repo->keyword) && !empty($pparam) && isset($SESSION->{$sesskeyword})) {
            $repo->keyword = $SESSION->{$sesskeyword};
        }

        // Save the search keyword in the session so we can retrieve it later.
        if (!empty($repo->keyword)) {
            $SESSION->{$sesskeyword} = $repo->keyword;
        }

        return !empty($repo->keyword);
    }

    /**
     * This get called in order to display the service login form.
     *
     * However this service doesn't require authtentication,
     * so we can return a search form instead.
     *
     * @package    repository_free_images
     * @copyright  2024 David OC <davidherzlos@gmail.com>
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    public function get_custom_form(): mixed {
        $form = [];
        $form['login'] = $this->get_form_definition();
        $form['nologin'] = true;
        $form['norefresh'] = true;
        $form['nosearch'] = true;
        // NOTE: login form CANNOT Be cached in filepicker.js
        // because (maxwidth and maxheight) are dynamic.
        $form['allowcaching'] = false;

        return $form;
    }

    /**
     * Returns the form defintion for the service custom form.
     * Notice the form is not defined in terms of the form API.
     *
     * @return mixed[] The form definition.
     *
     */
    protected function get_form_definition() {
        // The search keyword to fetch images.
        $keyword = [
            'id' => 'input_text_keyword',
            'label' => get_string('keyword', 'repository_free_images').': ',
            'type' => 'text',
            'name' => 'free_images_keyword',
            'value' => '',
        ];

        // Max image width in pixels.
        $maxwidth = [
            'label' => get_string('maxwidth', 'repository_free_images').': ',
            'type' => 'text',
            'name' => 'free_images_maxwidth',
            'value' => get_user_preferences('repository_free_images_maxwidth', self::IMAGE_SIDE_LENGTH),
        ];

        // Max image height in pixels.
        $maxheight = [
            'label' => get_string('maxheight', 'repository_free_images').': ',
            'type' => 'text',
            'name' => 'free_images_maxheight',
            'value' => get_user_preferences('repository_free_images_maxheight', self::IMAGE_SIDE_LENGTH),
        ];

        return [$keyword, $maxwidth, $maxheight];
    }

    /**
     * Returns the non ajax version of the service custom form.
     *
     * @return string template for custom non ajax form.
     */
    public function get_custom_nonajax_form() {
        global $OUTPUT;

        return $OUTPUT->render_from_template(
            'repository_free_images/unsplash_nonajax_form', [
                'keyword_label' => get_string('keyword', 'repository_free_images').': ',
                'keyword_name' => 'free_images_keyword',
            ]
        );
    }

    /**
     * Returns true if the image client supports global search.
     *
     * @return bool
     */
    public function supports_global_search() {
        return false;
    }

    /**
     * Returns the filetypes supported by this image client.
     *
     * @return int
     */
    public function get_file_types() {
        return (FILE_EXTERNAL);
    }
}

