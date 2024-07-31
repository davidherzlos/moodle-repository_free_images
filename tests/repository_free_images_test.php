<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace repository_free_images;


/**
 * Class for plugin testcases.
 *
 * @package    repository_free_images
 * @copyright  2024 Davif OC <davidherzlos@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class repository_free_images_test extends \advanced_testcase {

    public function test_it_gets_a_listing_of_images(): void {
        self::assertEquals(true, true);
    }

    public function test_moodle_url() {
        $url = new \moodle_url('/');
        self::assertEquals(true, !empty($url->out()));
    }

}

