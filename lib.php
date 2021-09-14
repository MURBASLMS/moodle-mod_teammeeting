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
 * Mandatory public API of teams module.
 *
 * @package    mod_teams
 * @copyright  2020 Université Clermont Auvergne
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_teams\manager;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/teams/classes/Office365.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/resourcelib.php');

/**
 * List of features supported in Folder module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function teams_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return false;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        default: return null;
    }
}

/**
 * Add teams instance.
 *
 * @param object $data The form data.
 * @param object $mform The form.
 * @return int The new teams instance id.
 */
function teams_add_instance($data, $mform) {
    global $DB, $USER, $COURSE;

    $context = context_course::instance($COURSE->id);
    $manager = manager::get_instance();
    $manager->require_is_available();
    $manager->require_is_o365_user($USER->id);

    // Fixing default display options.
    $data->display = RESOURCELIB_DISPLAY_NEW;
    $data->displayoptions = serialize([]);

    $data->name = $data->name;
    $data->intro = $data->intro;
    $data->introformat = $data->introformat;
    $data->timemodified = time();
    $data->population = $data->type == manager::TYPE_TEAM ? $data->population : "meeting";
    $data->enrol_managers = false;
    $data->other_owners = null;

    // Creating the meeting at Microsoft.
    $o365user = $manager->get_o365_user($USER->id);
    $api = $manager->get_api();
    $meetingdata = [
        'allowedPresenters' => 'organizer',
        'autoAdmittedUsers' => 'everyone',
        'lobbyBypassSettings' => [
            'scope' => 'everyone',
            'isDialInBypassEnabled' => true
        ],
        'participants' => [
            'organizer' => [
                'identity' => [
                    'user' => [
                        'id' => $o365user->objectid,
                    ],
                ],
                'upn' => $o365user->upn,
                'role' => 'presenter',
            ],
        ],
        'subject' => format_string($data->name, true, ['context' => $context])
    ];
    if (!$data->reuse_meeting) {
        $meetingdata = array_merge($meetingdata, [
            'startDateTime' => (new DateTimeImmutable("@{$data->opendate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'endDateTime' => (new DateTimeImmutable("@{$data->closedate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'isBroadcast' => true,
        ]);
    }

    $resp = $api->apicall('POST', '/users/' . $o365user->objectid . '/onlineMeetings', json_encode($meetingdata));
    $result = $api->process_apicall_response($resp, [
        'id' => null,
        'startDateTime' => null,
        'endDateTime' => null,
        'joinWebUrl' => null,
    ]);
    $meetingid = $result['id'];
    $joinurl = $result['joinWebUrl'];

    // Creating the activity.
    $data->resource_teams_id = $meetingid;
    $data->externalurl = $joinurl;
    $data->creator_id = $USER->id;
    $data->id = $DB->insert_record('teams', $data);

    // Send meeting link to the creator.
    if ((bool) get_config('mod_teams', 'notif_mail')) {
        $content = markdown_to_html(get_string('create_mail_content', 'mod_teams', [
            'name' => format_string($data->name, true, ['context' => $context]),
            'course' => format_string($COURSE->fullname, true, ['context' => $context]),
            'url' => $joinurl,
        ]));

        // Creation notification.
        $message = new \core\message\message();
        $message->courseid = $COURSE->id;
        $message->component = 'mod_teams';
        $message->name = 'meetingconfirm';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $USER;
        $message->subject = get_string('create_mail_title', 'mod_teams');
        $message->fullmessage = html_to_text($content);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $content;
        $message->smallmessage = get_string('create_mail_title', 'mod_teams');
        $message->notification = 1;
        message_send($message);
    }

    // Create the calendar events.
    teams_set_events($data);

    return $data->id;
}

/**
 * Update teams instance.
 *
 * @param object $data the form data.
 * @param object $mform the form.
 * @return bool true if update ok and false in other cases.
 */
function teams_update_instance($data, $mform) {
    global $DB, $COURSE;

    $context = context_course::instance($COURSE->id);
    $manager = manager::get_instance();
    $manager->require_is_available();

    // Fixing default display options.
    $data->display = RESOURCELIB_DISPLAY_NEW;
    $data->displayoptions = serialize([]);

    $data->name = $data->name;
    $data->intro = $data->intro;
    $data->introformat = $data->introformat;
    $data->timemodified = time();

    $team = $DB->get_record('teams', ['id' => $data->instance]);
    $requiresupdate = $team->opendate != $data->opendate || $team->closedate != $data->closedate || $team->name != $data->name;

    if ($requiresupdate) {
        $manager->require_is_o365_user($team->creator_id);

        // Updating the meeting at Microsoft.
        $o365user = $manager->get_o365_user($team->creator_id);
        $api = $manager->get_api();
        $meetingdata = [
            'subject' => format_string($data->name, true, ['context' => $context])
        ];
        if (!$data->reuse_meeting) {
            $meetingdata = array_merge($meetingdata, [
                'startDateTime' => (new DateTimeImmutable("@{$data->opendate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
                'endDateTime' => (new DateTimeImmutable("@{$data->closedate}", new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            ]);
        }

        $meetingid = $team->resource_teams_id;
        $resp = $api->apicall('PATCH', "/users/{$o365user->objectid}/onlineMeetings/{$meetingid}", json_encode($meetingdata));
        $result = $api->process_apicall_response($resp, [
            'id' => null,
            'startDateTime' => null,
            'endDateTime' => null,
            'joinWebUrl' => null,
        ]);
    }

    $data->id = $data->instance;
    $DB->update_record('teams', $data);

    // Update the calendar events.
    teams_set_events($data);

    return true;
}

/**
 * Delete teams instance.
 * @param int $id the id of the teams instance to delete
 * @return bool true.
 */
function teams_delete_instance($id) {
    global $DB;

    if (!$team = $DB->get_record('teams', array('id' => $id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('teams', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'teams', $id, null);

    // Note: all context files are deleted automatically.
    $DB->delete_records('teams', array('id' => $team->id));

    return true;
}

/**
 * Given a coursemodule object, this function returns the extra information needed to print this activity in various places.
 * Function adapted from the url_get_coursemodule_info function.
 * @param cm_info $coursemodule the course module.
 * @return cached_cm_info info
 */
function teams_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;
    require_once("$CFG->dirroot/mod/url/locallib.php");

    if (!$resource = $DB->get_record('teams', array('id' => $coursemodule->instance),
        'id, course, name, display, displayoptions, externalurl, intro, introformat, enrol_managers, population, selection,
            resource_teams_id, creator_id, opendate, closedate, type, reuse_meeting, other_owners')) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $resource->name;

    $display = url_get_final_display_type($resource);

    if ($display == RESOURCELIB_DISPLAY_POPUP) {
        $fullurl = "$CFG->wwwroot/mod/teams/view.php?id=$coursemodule->id&amp;redirect=1";
        $options = empty($resource->displayoptions) ? array() : unserialize($resource->displayoptions);
        $width  = empty($options['popupwidth'])  ? 620 : $options['popupwidth'];
        $height = empty($options['popupheight']) ? 450 : $options['popupheight'];
        $wh = "width=$width,height=$height,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,directories=no,scrollbars=yes,resizable=yes";
        $info->onclick = "window.open('$fullurl', '', '$wh'); return false;";
    } else if ($display == RESOURCELIB_DISPLAY_NEW) {
        $fullurl = "$CFG->wwwroot/mod/teams/view.php?id=$coursemodule->id&amp;redirect=1";
        $info->onclick = "window.open('$fullurl'); return false;";
    }

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('teams', $resource, $coursemodule->id, false);
    }

    $course = get_course($resource->course); // Get cached course.
    $info->customdata = array('fullurl' => str_replace('&amp;', '&', url_get_full_url($resource, $coursemodule, $course)));

    return $info;
}

/**
 * Returns object with the expected team members and used groups in the enrolment.
 * @param object $datas the datas of the team.
 * @param bool $form true if we are in the form context.
 * @return object
 * @throws dml_exception
 */
function teams_get_population($datas, $form = true)
{
    global $DB;
    $groups = [];
    $members = [];

    switch ($datas->population) {
        case "course":
            foreach (get_enrolled_users(context_course::instance($datas->course), 'mod/teams:view') as $member) {
                if (!in_array($member, $members)) {
                    $members[] = $member->email;
                }
            }
            break;

        case "students":
            foreach (get_enrolled_users(context_course::instance($datas->course), 'mod/teams:view') as $member) {
                if (!has_capability('mod/teams:addinstance', context_course::instance($datas->course), $member) && !in_array($member, $members)) {
                    $members[] = $member->email;
                }
            }
            break;

        case "groups":
            $datas->groups = ($form) ? $datas->groups : json_decode($datas->selection);
            foreach ($datas->groups as $group) {
                $groups[] = groups_get_group(intval($group));
                foreach (groups_get_members(intval($group)) as $member) {
                    if(!in_array($members, $member) && has_capability('mod/teams:view', context_course::instance($datas->course), $member)) {
                        $members[] = $member->email;
                    }
                }
            }
            break;

        case "users":
            $members = ($form) ? $datas->users : json_decode($datas->selection);
            foreach ($members as $key => $member) {
                $user = $DB->get_record('user', array('email' => $member));
                if (!$user || !has_capability('mod/teams:view', context_course::instance($datas->course), $user)) {
                    unset($members[$key]);
                }
            }
            break;

        default: break;
    }

    $response = new stdClass();
    $response->groups = $groups;
    $response->members = $members;

    return $response;
}

/**
 * Returns the selection in function of the teams population choice
 * @param $datas the datas of the team.
 * @return mixed|null list of selected groups or users, null in other cases.
 */
function teams_get_selection($datas)
{
    return ($datas->population == "groups") ? $datas->groups : ( ($datas->population == "users") ? $datas->users : null );
}

/**
 * Lists expected teams owners.
 * @param $course the course.
 * @param null $team the team if it has already been created.
 * @return array array of users/owners email.
 * @throws dml_exception
 */
function teams_get_owners($course, $team = null)
{
    global $CFG, $PAGE, $DB;
    require_once($CFG->dirroot.'/enrol/locallib.php');

    $managers = null;

    if ($team && !$team->enrol_managers)  {
        $managers = [core_user::get_user($team->creator_id, 'email')->email];
        if ($team->other_owners) {
            $managers = array_merge(array_values($managers), json_decode($team->other_owners));
        }
    } else {
        $manager = new course_enrolment_manager($PAGE, $course, 0, 1, '', 0, -1);
        $results = $manager->get_users_for_display($manager, 'lastname', 'ASC', 0, 100);

        if ($results) {
            $managers = [];
            foreach ($results as $key => $manager) {
                $user = $DB->get_record('user', array('id' => $manager['userid']));
                if ($user) {
                    $managers[] = $user->email;
                } else {
                    continue;
                }
            }
        }

        if ($team) {
            if (!in_array(core_user::get_user($team->creator_id, 'email')->email, $managers)) {
                $managers[] = core_user::get_user($team->creator_id, 'email')->email;
            }
        }
    }

    return is_array($managers) ? array_unique($managers) : $managers;
}

/**
 * Checks if the user given in params is an owner of the team given in params.
 * @param $team the team.
 * @param $user the user.
 * @return bool true if the user is a this team owner.
 * @throws dml_exception
 */
function teams_is_owner($team, $user)
{
    return in_array($user->email, teams_get_owners(get_course($team->course), $team));
}

/**
 * Return the Office365 object to do API calls.
 * @return Office365
 * @throws dml_exception
 */
function get_office()
{
    // TODO Do not use config.
    return new Office365(get_config('mod_teams', 'tenant_id'), get_config('mod_teams', 'client_id'), get_config('mod_teams', 'client_secret'));
}

/**
 * Add calendar events for the meeting.
 *
 * @param $team The team data.
 */
function teams_set_events($team) {
    global $DB;

    if ($events = $DB->get_records('event', array('modulename' => 'teams', 'instance' => $team->id))) {
        foreach ($events as $event) {
            $event = calendar_event::load($event);
            $event->delete();
        }
    }

    // We do not create events when we're missing an open or close date.
    if (!$team->opendate || !$team->closedate) {
        return;
    }

    // The open-event.
    $event = new stdClass;
    $event->name = $team->name;
    $event->description = $team->name;
    $event->courseid = $team->course;
    $event->groupid = 0;
    $event->userid = 0;
    $event->modulename = 'teams';
    $event->instance = $team->id;
    $event->eventtype = 'open';
    $event->timestart = $team->opendate;
    $event->visible = instance_is_visible('teams', $team);
    $event->timeduration = ($team->closedate - $team->opendate);
    calendar_event::create($event);
}

/**
 * Prints team info and link to the teams resource.
 * @param object $team the team.
 * @param object $cm the course module.
 * @param object $course the course.
 * @return does not return
 */
function teams_print_workaround($team, $cm, $course) {
    global $OUTPUT;

    url_print_header($team, $cm, $course);
    url_print_heading($team, $cm, $course, true);
    url_print_intro($team, $cm, $course, true);

    $fullurl = url_get_full_url($team, $cm, $course);

    $display = url_get_final_display_type($team);
    if ($display == RESOURCELIB_DISPLAY_POPUP) {
        $jsfullurl = addslashes_js($fullurl);
        $options = empty($team->displayoptions) ? array() : unserialize($team->displayoptions);
        $width  = empty($options['popupwidth'])  ? 620 : $options['popupwidth'];
        $height = empty($options['popupheight']) ? 450 : $options['popupheight'];
        $wh = "width=$width,height=$height,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,directories=no,scrollbars=yes,resizable=yes";
        $extra = "onclick=\"window.open('$jsfullurl', '', '$wh'); return false;\"";
    } else if ($display == RESOURCELIB_DISPLAY_NEW) {
        $extra = "onclick=\"this.target='_blank';\"";
    } else {
        $extra = '';
    }

   echo teams_print_details_dates($team);

    echo '<div class="urlworkaround">';
    print_string('clicktoopen', 'url', "<a id='teams_resource_url' href=\"$fullurl\" $extra>$fullurl</a>");
    echo '</div>';

    echo '<br><div id="teams_url_copydiv"><button class="btn btn-default" id="teams_url_copybtn">';
    echo html_writer::tag('img', '', array('src' => $OUTPUT->image_url('e/insert_edit_link', 'core'), 'style' => 'margin-right: 5px;'));
    echo get_string('copy_link', 'mod_teams'). '</button></div>';

    // Script to copy the link.
    echo '<script>
            var btn = document.getElementById(\'teams_url_copybtn\');
            btn.addEventListener(\'click\', function(event) {
            var div = document.querySelector(\'#teams_url_copydiv\');
            var input = div.appendChild(document.createElement("input"));
            input.value = document.querySelector(\'#teams_resource_url\').innerHTML;
            input.focus();
            input.select();
            document.execCommand(\'copy\');
            input.parentNode.removeChild(input);
            });
        </script>';

    echo $OUTPUT->footer();
    die;
}

/**
 * Prints information about the availability of the online meeting.
 * @param $team the teams instance.
 * @param string $format the format ('html' by default, 'text' can be used for notification).
 * @return string the information about the meeting.
 * @throws coding_exception
 */
function teams_print_details_dates($team, $format = 'html')
{
    global $OUTPUT;
    if ($team->type == 'meeting') {
        if ($team->opendate != 0) {
            $details = ($team->closedate != 0) ? sprintf(get_string('dates_between', 'mod_teams'), date('d/m/Y H:i', $team->opendate), date('d/m/Y H:i', $team->closedate))  : sprintf(get_string('dates_from', 'mod_teams'), date('d/m/Y H:i', $team->opendate));
        } else if ($team->closedate != 0) {
            $details = sprintf(get_string('dates_until', 'mod_teams'), date('d/m/Y H:i', $team->closedate));
        }
        if ($details) {
            $msg = sprintf(get_string('meetingavailable', 'mod_teams'), $details);
            $icon = html_writer::tag('img', '', array('src' => $OUTPUT->image_url('i/info'), 'style' => 'margin-right: 5px;'));
            return ($format == 'html') ? '<div>'. $icon . $msg .'</div><br/>' : $msg;
        }
    }

    return '';
}