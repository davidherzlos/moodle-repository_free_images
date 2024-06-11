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
 * free_images class for communication with Free images Commons API
 *
 * @copyright  2024 David OC <davidherzlos@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package repository_free_images
 */

define('FREE_IMAGES_THUMBS_PER_PAGE', 25);
define('FREE_IMAGES_IMAGE_SIDE_LENGTH', 1024);
define('FREE_IMAGES_THUMB_SIZE', 135);


class free_images {

    const FREE_IMAGES_UNSPLASH_CLIENT_ID = 'znXXliTsULyM1kY-oiY37iKo4hdCKPzlYcoi-Lsq4oU';

    private $_conn  = null;
    
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
     * TODO: Add or automate docblocks.
     */
    public function __construct($url = '') {
        if (empty($url)) {
            $this->api = 'https://api.unsplash.com/search/photos';
        } else {
            $this->api = $url;
        }
        $this->_param['redirects'] = true; // NOTE: this seems to be not used.
        $this->_conn = new curl(['cache' => true, 'debug' => false]);
    }

    public function login($user, $pass) {
        $this->_param['action']   = 'login';
        $this->_param['lgname']   = $user;
        $this->_param['lgpassword'] = $pass;
        $content = $this->_conn->post($this->api, $this->_param);
        $result = unserialize($content);
        if (!empty($result['result']['sessionid'])) {
            $this->userid = $result['result']['lguserid'];
            $this->username = $result['result']['lgusername'];
            $this->token = $result['result']['lgtoken'];
            return true;
        } else {
            return false;
        }
    }

    public function logout() {
        $this->_param['action']   = 'logout';
        $this->_conn->post($this->api, $this->_param);
        return;
    }

    public function get_image_url($titles) {
        $imageurls = [];
        $this->_param['action'] = 'query';
        if (is_array($titles)) {
            foreach ($titles as $title) {
                $this->_param['titles'] .= ('|'.urldecode($title));
            }
        } else {
            $this->_param['titles'] = urldecode($titles);
        }
        $this->_param['prop']   = 'imageinfo';
        $this->_param['iiprop'] = 'url';
        $content = $this->_conn->post($this->api, $this->_param);
        $result = unserialize($content);
        foreach ($result['query']['pages'] as $page) {
            if (!empty($page['imageinfo'][0]['url'])) {
                $imageurls[] = $page['imageinfo'][0]['url'];
            }
        }
        return $imageurls;
    }

    public function get_images_by_page($title) {
        $imageurls = [];
        $this->_param['action'] = 'query';
        $this->_param['generator'] = 'images';
        $this->_param['titles'] = urldecode($title);
        $this->_param['prop']   = 'images|info|imageinfo';
        $this->_param['iiprop'] = 'url';
        $content = $this->_conn->post($this->api, $this->_param);
        $result = unserialize($content);
        if (!empty($result['query']['pages'])) {
            foreach ($result['query']['pages'] as $page) {
                $imageurls[$page['title']] = $page['imageinfo'][0]['url'];
            }
        }
        return $imageurls;
    }


    /**
     * Search for images and return photos array.
     *
     * NOTE: The user should be able to choose an orientation.
     * Also the licence for the image is wrong.
     *
     * @param string $keyword
     * @param int $page
     * @param array $params additional query params
     * @return array
     */
    public function search_images($keyword, $page = 0, $params = []) {
        global $OUTPUT;

        $images = [];

        $this->_param['query'] = $keyword;
        $this->_param['page'] = $page;
        $this->_param['perpage'] = FREE_IMAGES_THUMBS_PER_PAGE;
        $this->_param['client_id'] = self::FREE_IMAGES_UNSPLASH_CLIENT_ID;
        $this->_param += $params;

        $response = $this->_conn->get($this->api, $this->_param);
        $json = json_decode($response);

        if (empty($json->results)) {
            return $images;
        }

        foreach ($json->results as $record) {
            $attrs = $this->extract_image_attrs($record);
            $images[] = [
                'thumbnail' => $OUTPUT->image_url(file_extension_icon($record->slug))->out(false),
                'thumbnail_width' => FREE_IMAGES_THUMB_SIZE,
                'thumbnail_height' => FREE_IMAGES_THUMB_SIZE,
                'license' => 'cc-sa',
            ] + $attrs;
        }

        return $images;
    }

    /**
     * NOTE: There some required stuff to be ensured in this function.
     * Needs to have realistic widths and heights for icons.
     * Also the slug property can be localized by used lang code.
     */
    public function extract_image_attrs($record): array {
        return [
            'title' => $record->slug,
            'author' => $record->user->name,
            'source' => $record->urls->small,
            'url' => $record->urls->full,
            'image_width' => FREE_IMAGES_THUMB_SIZE,
            'image_height' => FREE_IMAGES_THUMB_SIZE,
            'realthumbnail' => $record->urls->thumb,
            'realicon' => $record->urls->thumb,
            'datemodified' => strtotime($record->updated_at),
        ];
    }
}
