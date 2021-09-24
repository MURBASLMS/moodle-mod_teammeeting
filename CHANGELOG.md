Changelog
=========

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