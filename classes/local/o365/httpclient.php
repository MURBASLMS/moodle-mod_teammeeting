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
 * HTTP client.
 *
 * @package    mod_teammeeting
 * @copyright  2024 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teammeeting\local\o365;

/**
 * HTTP client.
 *
 * @package    mod_teammeeting
 * @copyright  2024 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class httpclient extends \local_o365\httpclient {

    /**
     * Get the persitent headers.
     *
     * @return array Headers.
     */
    protected function get_persistent_headers() {
        return [
            'Prefer: include-unknown-enum-members'
        ];
    }

    /**
     * HTTP Request.
     *
     * @param string $url The URL to request
     * @param array $options
     * @return bool
     */
    protected function request($url, $options = array()) {
        $this->setHeader($this->get_persistent_headers());
        return parent::request($url, $options);
    }

}
