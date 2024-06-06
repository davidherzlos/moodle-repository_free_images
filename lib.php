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
     * Given a path, and perhaps a search, get a list of files.
     *
     * See details on {@link https://moodledev.io/docs/apis/plugintypes/repository}
     *
     * @param string $path this parameter can a folder name, or a identification of folder
     * @param string $page the page number of file list
     * @return array the list of files, including meta infomation, containing the following keys
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
     * To check whether the user is logged in.
     *
     * @return bool
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
     * Show the login screen, if required
     *
     * @return string
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
     * Search files in repository
     * When doing global search, $search_text will be used as
     * keyword.
     *
     * @param string $search_text search key word
     * @param int $page page
     * @return mixed see {@link repository::get_listing()}
     */
    public function search($searchtext, $page = 0): mixed {
        $client = new free_images;
        $searchresult = [];
        $searchresult['list'] = $client->search_images($searchtext);
        return $searchresult;
    }

    /**
     * Return the source information
     *
     * The result of the function is stored in files.source field. It may be analysed
     * when the source file is lost or repository may use it to display human-readable
     * location of reference original.
     *
     * This method is called when file is picked for the first time only. When file
     * (either copy or a reference) is already in moodle and it is being picked
     * again to another file area (also as a copy or as a reference), the value of
     * files.source is copied.
     *
     * @param string $source source of the file, returned by repository as 'source' and received back from user (not cleaned)
     * @return string|null
     */
    public function get_file_source_info($url): mixed {
        return $url;
    }

    /**
     * Is this repository accessing private data?
     *
     * This function should return true for the repositories which access external private
     * data from a user. This is the case for repositories such as Dropbox, Google Docs or Box.net
     * which authenticate the user and then store the auth token.
     *
     * Of course, many repositories store 'private data', but we only want to set
     * contains_private_data() to repositories which are external to Moodle and shouldn't be accessed
     * to by the users having the capability to 'login as' someone else. For instance, the repository
     * 'Private files' is not considered as private because it's part of Moodle.
     *
     * You should not set contains_private_data() to true on repositories which allow different types
     * of instances as the levels other than 'user' are, by definition, not private. Also
     * the user instances will be protected when they need to.
     *
     * @return boolean True when the repository accesses private external data.
     * @since  Moodle 2.5
     */
    public function contains_private_data(): bool {
        return false;
    }

    /**
     * Repository method to make sure that user can access particular file.
     *
     * This is checked when user tries to pick the file from repository to deal with
     * potential parameter substitutions is request
     *
     * @param string $source source of the file, returned by repository as 'source' and received back from user (not cleaned)
     * @return bool whether the file is accessible by current user
     */
    public function file_is_accessible($source) {
        return parent::file_is_accessible($source);
    }

    /**
     * This function is used to copy a moodle file to draft area.
     *
     * It DOES NOT check if the user is allowed to access this file because the actual file
     * can be located in the area where user does not have access to but there is an alias
     * to this file in the area where user CAN access it.
     * {@link file_is_accessible} should be called for alias location before calling this function.
     *
     * @param string $source The metainfo of file, it is base64 encoded php serialized data
     * @param stdClass|array $filerecord contains itemid, filepath, filename and optionally other
     *      attributes of the new file
     * @param int $maxbytes maximum allowed size of file, -1 if unlimited. If size of file exceeds
     *      the limit, the file_exception is thrown.
     * @param int $areamaxbytes the maximum size of the area. A file_exception is thrown if the
     *      new file will reach the limit.
     * @return array The information about the created file
     */
    public function copy_to_area($source, $filerecord, $maxbytes = -1, $areamaxbytes = FILE_AREA_MAX_BYTES_UNLIMITED) {
        return parent::copy_to_area($source, $filerecord, $maxbytes, $areamaxbytes);
    }

    /**
     * Repository method to serve the referenced file
     *
     * @see send_stored_file
     *
     * @param stored_file $storedfile the file that contains the reference
     * @param int $lifetime Number of seconds before the file should expire from caches (null means $CFG->filelifetime)
     * @param int $filter 0 (default)=no filtering, 1=all files, 2=html files only
     * @param bool $forcedownload If true (default false), forces download of file rather than view in browser/plugin
     * @param array $options additional options affecting the file serving
     */
    public function send_file($storedfile, $lifetime=null , $filter=0, $forcedownload=false, $options = null) {
        return parent::send_file($storedfile, $lifetime, $filter, $forcedownload, $options);
    }

    /**
     * Return human readable reference information
     *
     * @param string $reference value of DB field files_reference.reference
     * @param int $filestatus status of the file, 0 - ok, 666 - source missing
     * @return string
     */
    public function get_reference_details($reference, $filestatus = 0) {
        return parent::get_reference_details($reference, $filestatus);
    }

    /**
     * reference_file_selected
     *
     * This function is called when a controlled link file is selected in a file picker and the form is
     * saved. The expected behaviour for repositories supporting controlled links is to
     * - copy the file to the moodle system account
     * - put it in a folder that reflects the context it is being used
     * - make sure the sharing permissions are correct (read-only with the link)
     * - return a new reference string pointing to the newly copied file.
     *
     * @param string $reference this reference is generated by
     *                          repository::get_file_reference()
     * @param context $context the target context for this new file.
     * @param string $component the target component for this new file.
     * @param string $filearea the target filearea for this new file.
     * @param string $itemid the target itemid for this new file.
     * @return string updated reference (final one before it's saved to db).
     */
    public function reference_file_selected($reference, $context, $component, $filearea, $itemid) {
        return parent::reference_file_selected($reference, $context, $component, $filearea, $itemid);
    }

    /**
     * Prepare file reference information
     *
     * @param string $source source of the file, returned by repository as 'source' and received back from user (not cleaned)
     * @return string file reference, ready to be stored
     */
    public function get_file_reference($source) {
        return parent::get_file_reference($source);
    }

    /**
     * Return file URL, for most plugins, the parameter is the original
     * url, but some plugins use a file id, so we need this function to
     * convert file id to original url.
     *
     * @param string $url the url of file
     * @return string
     */
    public function get_link($url) {
        return parent::get_link($url);
    }


    /**
     * Downloads a file from external repository and saves it in temp dir
     *
     * Function get_file() must be implemented by repositories that support returntypes
     * FILE_INTERNAL or FILE_REFERENCE. It is invoked to pick up the file and copy it
     * to moodle. This function is not called for moodle repositories, the function
     * {@link repository::copy_to_area()} is used instead.
     *
     * This function can be overridden by subclass if the files.reference field contains
     * not just URL or if request should be done differently.
     *
     * @see curl
     * @throws file_exception when error occured
     *
     * @param string $url the content of files.reference field, in this implementaion
     * it is asssumed that it contains the string with URL of the file
     * @param string $filename filename (without path) to save the downloaded file in the
     * temporary directory, if omitted or file already exists the new filename will be generated
     * @return array with elements:
     *   path: internal location of the file
     *   url: URL to the source (from parameters)
     */
    public function get_file($url, $filename = '') {
        return parent::get_file($url, $filename);
    }

    /**
     * What kind of files will be in this repository?
     *
     * @return array return '*' means this repository support any files, otherwise
     *               return mimetypes of files, it can be an array
     */
    public function supported_filetypes() {
        return parent::supported_filetypes();
    }

    /**
     * Tells how the file can be picked from this repository
     *
     * Maximum value is FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE
     *
     * @return int
     */
    public function supported_returntypes() {
        return parent::supported_returntypes();
    }

    /**
     * Tells how the file can be picked from this repository
     *
     * Maximum value is FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE
     *
     * @return int
     */
    public function default_returntype() {
        return parent::default_returntype();
    }

    /**
     * Save settings for repository instance
     * $repo->set_option(array('api_key'=>'f2188bde132', 'name'=>'dongsheng'));
     *
     * @param array $options settings
     * @return bool
     */
    public function set_option($options = []) {
        return parent::set_option($options);
    }


    /**
     * Get settings for repository instance.
     *
     * @param string $config a specific option to get.
     * @return mixed returns an array of options. If $config is not empty, then it returns that option,
     *               or null if the option does not exist.
     */
    public function get_option($config = '') {
        return parent::get_option($config);
    }

    /**
     * Logout from repository instance
     * By default, this function will return a login form
     *
     * @return string
     */
    public function logout() {
        return parent::logout();
    }

    /**
     * For oauth like external authentication, when external repository direct user back to moodle,
     * this function will be called to set up token and token_secret
     */
    public function callback() {
        return parent::callback();
    }

    /**
     * Edit/Create Admin Settings Moodle form
     *
     * @param MoodleQuickForm $mform Moodle form (passed by reference)
     * @param string $classname repository class name
     */
    public static function type_config_form($mform, $classname = 'repository') {
        return parent::type_config_form($mform, $classname);
    }

    /**
     * Edit/Create Instance Settings Moodle form
     *
     * @param moodleform $mform Moodle form (passed by reference)
     */
    public static function instance_config_form($mform) {
        return parent::instance_config_form($mform);
    }

    /**
     * Return names of the general options.
     * By default: no general option name
     *
     * @return array
     */
    public static function get_type_option_names() {
        return parent::get_type_option_names();
    }

    /** Performs synchronisation of an external file if the previous one has expired.
     *
     * This function must be implemented for external repositories supporting
     * FILE_REFERENCE, it is called for existing aliases when their filesize,
     * contenthash or timemodified are requested. It is not called for internal
     * repositories (see {@link repository::has_moodle_files()}), references to
     * internal files are updated immediately when source is modified.
     *
     * Referenced files may optionally keep their content in Moodle filepool (for
     * thumbnail generation or to be able to serve cached copy). In this
     * case both contenthash and filesize need to be synchronized. Otherwise repositories
     * should use contenthash of empty file and correct filesize in bytes.
     *
     * Note that this function may be run for EACH file that needs to be synchronised at the
     * moment. If anything is being downloaded or requested from external sources there
     * should be a small timeout. The synchronisation is performed to update the size of
     * the file and/or to update image and re-generated image preview. There is nothing
     * fatal if syncronisation fails but it is fatal if syncronisation takes too long
     * and hangs the script generating a page.
     *
     * Note: If you wish to call $file->get_filesize(), $file->get_contenthash() or
     * $file->get_timemodified() make sure that recursion does not happen.
     *
     * Called from {@link stored_file::sync_external_file()}
     *
     * @uses stored_file::set_missingsource()
     * @uses stored_file::set_synchronized()
     * @param stored_file $file
     * @return bool false when file does not need synchronisation, true if it was synchronised
     */
    public function sync_reference(stored_file $file) {
        return parent::sync_reference($file);
    }

}
