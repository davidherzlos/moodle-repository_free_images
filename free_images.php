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
 * free_images class
 * class for communication with Free images Commons API
 *
 * @author Dongsheng Cai <dongsheng@moodle.com>, Raul Kern <raunator@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package repository_free_images
 */

define('FREE_IMAGES_THUMBS_PER_PAGE', 24);
define('FREE_IMAGES_FILE_NS', 6);
define('FREE_IMAGES_IMAGE_SIDE_LENGTH', 1024);
define('FREE_IMAGES_THUMB_SIZE', 120);

class free_images {
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

    public function __construct($url = '') {
        if (empty($url)) {
            $this->api = 'https://commons.wikimedia.org/w/api.php';
        } else {
            $this->api = $url;
        }
        $this->_param['format'] = 'php';
        $this->_param['redirects'] = true;
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
     * Generate thumbnail URL from image URL.
     *
     * @param string $image_url
     * @param int $orig_width
     * @param int $orig_height
     * @param int $thumb_width
     * @param bool $force When true, forces the generation of a thumb URL.
     * @return string
     */
    public function get_thumb_url($imageurl, $origwidth, $origheight, $thumbwidth = 75, $force = false) {
        global $OUTPUT;

        if (!$force && $origwidth <= $thumbwidth && $origheight <= $thumbwidth) {
            return $imageurl;
        } else {
            $thumburl = '';
            $commonsmaindir = 'https://upload.wikimedia.org/wikipedia/commons/';
            if ($imageurl) {
                $shortpath = str_replace($commonsmaindir, '', $imageurl);
                $extension = strtolower(pathinfo($shortpath, PATHINFO_EXTENSION));
                if (strcmp($extension, 'gif') == 0) {  // No thumb for gifs.
                    return $OUTPUT->image_url(file_extension_icon('.gif'))->out(false);
                }
                $dirparts = explode('/', $shortpath);
                $filename = end($dirparts);
                if ($origheight > $origwidth) {
                    $thumbwidth = round($thumbwidth * $origwidth / $origheight);
                }
                $thumburl = $commonsmaindir . 'thumb/' . implode('/', $dirparts) . '/'. $thumbwidth .'px-' . $filename;
                if (strcmp($extension, 'svg') == 0) {  // Png thumb for svg-s.
                    $thumburl .= '.png';
                }
            }
            return $thumburl;
        }
    }

    /**
     * Search for images and return photos array.
     *
     * @param string $keyword
     * @param int $page
     * @param array $params additional query params
     * @return array
     */
    public function search_images($keyword, $page = 0, $params = []) {
        global $OUTPUT;
        $filesarray = [];
        $this->_param['action'] = 'query';
        $this->_param['generator'] = 'search';
        $this->_param['gsrsearch'] = $keyword;
        $this->_param['gsrnamespace'] = FREE_IMAGES_FILE_NS;
        $this->_param['gsrlimit'] = FREE_IMAGES_THUMBS_PER_PAGE;
        $this->_param['gsroffset'] = $page * FREE_IMAGES_THUMBS_PER_PAGE;
        $this->_param['prop']   = 'imageinfo';
        $this->_param['iiprop'] = 'url|dimensions|mime|timestamp|size|user';
        $this->_param += $params;
        $this->_param += ['iiurlwidth' => FREE_IMAGES_IMAGE_SIDE_LENGTH,
            'iiurlheight' => FREE_IMAGES_IMAGE_SIDE_LENGTH];
        // Didn't work with POST.
        $content = $this->_conn->get($this->api, $this->_param);
        $result = unserialize($content);
        if (!empty($result['query']['pages'])) {
            foreach ($result['query']['pages'] as $page) {
                $title = $page['title'];
                $filetype = $page['imageinfo'][0]['mime'];
                $imagetypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
                if (in_array($filetype, $imagetypes)) {  // Is image.
                    $extension = pathinfo($title, PATHINFO_EXTENSION);
                    $issvg = strcmp($extension, 'svg') == 0;

                    // Get PNG equivalent to SVG files.
                    if ($issvg) {
                        $title .= '.png';
                    }

                    // The thumbnail (max size requested) is smaller than the original size, we will use the thumbnail.
                    if ($page['imageinfo'][0]['thumbwidth'] < $page['imageinfo'][0]['width']) {
                        $attrs = [
                            // Upload scaled down image.
                            'source' => $page['imageinfo'][0]['thumburl'],
                            'image_width' => $page['imageinfo'][0]['thumbwidth'],
                            'image_height' => $page['imageinfo'][0]['thumbheight'],
                        ];
                        if ($attrs['image_width'] <= FREE_IMAGES_THUMB_SIZE && $attrs['image_height'] <= FREE_IMAGES_THUMB_SIZE) {
                            $attrs['realthumbnail'] = $attrs['source'];
                        }
                        if ($attrs['image_width'] <= 24 && $attrs['image_height'] <= 24) {
                            $attrs['realicon'] = $attrs['source'];
                        }

                        // We use the original file.
                    } else {
                        $attrs = [
                            // Upload full size image.
                            'image_width' => $page['imageinfo'][0]['width'],
                            'image_height' => $page['imageinfo'][0]['height'],
                            'size' => $page['imageinfo'][0]['size'],
                        ];

                        // We cannot use the source when the file is SVG.
                        if ($issvg) {
                            // So we generate a PNG thumbnail of the file at its original size.
                            $attrs['source'] = $this->get_thumb_url($page['imageinfo'][0]['url'], $page['imageinfo'][0]['width'],
                                $page['imageinfo'][0]['height'], $page['imageinfo'][0]['width'], true);
                        } else {
                            $attrs['source'] = $page['imageinfo'][0]['url'];
                        }
                    }
                    $attrs += [
                        'realthumbnail' => $this->get_thumb_url(
                            $page['imageinfo'][0]['url'],
                            $page['imageinfo'][0]['width'],
                            $page['imageinfo'][0]['height'],
                            FREE_IMAGES_THUMB_SIZE
                        ),
                        'realicon' => $this->get_thumb_url(
                            $page['imageinfo'][0]['url'],
                            $page['imageinfo'][0]['width'],
                            $page['imageinfo'][0]['height'], 24
                        ),
                        'author' => $page['imageinfo'][0]['user'],
                        'datemodified' => strtotime($page['imageinfo'][0]['timestamp']),
                        ];
                } else {  // Other file types.
                    $attrs = ['source' => $page['imageinfo'][0]['url']];
                }
                $filesarray[] = [
                    'title' => substr($title, 5),         // Chop off 'File:'.
                    'thumbnail' => $OUTPUT->image_url(file_extension_icon(substr($title, 5)))->out(false),
                    'thumbnail_width' => FREE_IMAGES_THUMB_SIZE,
                    'thumbnail_height' => FREE_IMAGES_THUMB_SIZE,
                    'license' => 'cc-sa',
                    // The accessible url of the file.
                    'url' => $page['imageinfo'][0]['descriptionurl'],
                ] + $attrs;
            }
        }
        return $filesarray;
    }

}
