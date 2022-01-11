Team meeting
============

Create or schedule online meetings using Microsoft Teams.

Requirements
------------

- Moodle 3.9
- [Microsoft 365 Integration plugin](https://moodle.org/plugins/local_o365) (`local_o365`)

Installation
------------

1. Drop the content of this repository the folder `mod/teammeeting`.
2. Navigate to the site administration to trigger the upgrade process.

Microsoft 365 setup
-------------------

This plugin relies on the integration via the _Microsoft 365 Integration_ (`local_o365`) plugin. However, the app must be authorised to create online meetings on behalf of users.

Please see the following resources:

- [Online meeting permissions](https://docs.microsoft.com/en-us/graph/permissions-reference#online-meetings-permissions)
- [Allow applications to access online meetings on behalf of a user](https://docs.microsoft.com/en-us/graph/cloud-communication-online-meeting-application-access-policy)


Capabilities
------------

**mod/teammeeting:view**

Whether a user can view the activity and be redirected to the online meeting.

**mod/teammeeting:addinstance**

Whether a user can create an activity. They are permanently flagged as `organizer` of the online meeting.

**mod/teammeeting:presentmeeting**

Whether a user should be flagged as `presenter` of the online meeting.


Acknowledgment
--------------

This plugin is a fork of [Teams](https://github.com/UCA-Squad/moodle-mod_teams) (`mod_teams`) developed by [Université Clermont Auvergne](https://www.uca.fr).


License
-------

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html).
