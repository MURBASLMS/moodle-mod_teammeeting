Changelog
=========

v1.3.0
------

- Added CLI to debug an meeting instance
- Support restricting activity to an individual group
- Support group mode to enable meetings per group
- Students included as attendees

v1.2.1
------

- Online meeting could not be created with presenters in some cases

v1.2.0
------

- Online meeting instance no longer created when instance is added to course
- Online meeting organiser defined on-demand on first access to activity
- Backup & restore re-implemented without restoring online meeting information

v1.1.0
------

- Introduce capability `mod/meeting:presentmeeting` to flag who can be a presenter
- Flag and synchronise users with above capability as meeting attendees with role `presenter`

v1.0.2
------

- Temporarily disable backup/restore functionality

v1.0.1
------

- Time slot meetings no longer create broadcast events

v1.0.0
------

- Renamed plugin from `mod_teams` to `mod_teammeeting`
- Added support for backup and restore
- Added support for `course_module_instance_list_viewed` event
- Renamed `meeting_default_duration` to `meetingdefaultduration` (admin setting)
- Renamed `reuse_meeting` to `reusemeeting` (database field, language string)
- Removed built-in French language strings
- Removed notification sent to creator when a new activity is created
- Removed dependencies on Composer and Microsoft Graph in favour of `local_o365`
- Removed code that was not specific to online meetings (Microsoft Teams)
- Forked [https://github.com/UCA-Squad/moodle-mod_teams](mod_teams) by Université Clermont Auvergne