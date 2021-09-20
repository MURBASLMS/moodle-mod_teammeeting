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
 * Manager.
 *
 * @package    mod_teammeeting
 * @copyright  2021 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teammeeting;

use local_o365\obj\o365user;
use local_o365\rest\unified;
use local_o365\utils;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager.
 *
 * @package    mod_teammeeting
 * @copyright  2021 Murdoch University
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var static */
    protected static $instance;

    /**
     * Get the API.
     *
     * @return unified
     */
    public function get_api() {
        return utils::get_api();
    }

    /**
     * Get the O365 user object.
     *
     * @param int $userid The user ID.
     * @return o365user
     */
    public function get_o365_user($userid) {
        $user = o365user::instance_from_muserid($userid);
        if (!$user) {
            throw new \coding_exception('Not an O365 user.');
        }
        return $user;
    }

    /**
     * Whether the module is available.
     *
     * As in, it has been configured and can be used.
     *
     * @return bool
     */
    public function is_available() {
        return unified::is_configured();
    }

    /**
     * Whether the user is an O365 user.
     *
     * @param int $userid The user ID.
     * @return bool
     */
    public function is_o365_user($userid) {
        $user = o365user::instance_from_muserid($userid);
        return !empty($user);
    }

    /**
     * Requires the module to be available.
     *
     * @return bool
     */
    public function require_is_available() {
        if (!$this->is_available()) {
            throw new moodle_exception('apinotconfigured', 'mod_teammeeting');
        }
    }

    /**
     * Requires the module to be available.
     *
     * @param int $userid The user ID.
     * @return bool
     */
    public function require_is_o365_user($userid) {
        if (!$this->is_o365_user($userid)) {
            throw new moodle_exception('noto365user', 'mod_teammeeting');
        }
    }

    /**
     * Get an instance of the manager.
     *
     * @return static
     */
    public static function get_instance() {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

}
