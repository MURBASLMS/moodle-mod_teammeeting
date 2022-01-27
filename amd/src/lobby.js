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
 * Lobby.
 *
 * @module     mod_teammeeting/lobby
 * @copyright  2022 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import { get_string, get_strings } from 'core/str';
import Templates from 'core/templates';

const READY_CHECK_INTERVAL = 15 * 1000;

const askToBecomePresenter = (teammeetingid) => {
  ModalFactory.create({
    type: ModalFactory.types.SAVE_CANCEL,
    title: get_string('sessionnotready', 'mod_teammeeting'),
    body: get_strings([
      { key: 'sessionrequiresorganiserinstructions', component: 'mod_teammeeting' },
      { key: 'whatwouldyouliketodo', component: 'mod_teammeeting' },
    ]).then((strs) => {
      return `<p>${strs[0]}</p><p>${strs[1]}</p>`;
    }),
  }).then((modal) => {
    modal.setButtonText('cancel', get_string('waitinstead', 'mod_teammeeting'));
    modal.setButtonText('save', get_string('nominatemyself', 'mod_teammeeting'));
    modal.getRoot().on(ModalEvents.save, (e) => {
      e.preventDefault();

      // Disable the button and show spinner.
      const button = modal.getFooter().find(modal.getActionSelector('save'));
      button.attr('disabled', true);
      modal.asyncSet(Templates.render('core/loading', {}), button.html.bind(button));

      nominateOneselfAndGo(teammeetingid);
    });
    modal.show();
  });
};

const nominateOneselfAndGo = (teammeetingid) => {
  Ajax.call([
    {
      methodname: 'mod_teammeeting_nominate_organiser',
      args: { teammeetingid },
    },
  ])[0]
    .then((data) => {
      window.location.href = data.externalurl;
    })
    .catch(Notification.exception);
};

const setupReadyCheck = (teammeetingid) => {
  const readyCheck = () => {
    Ajax.call([
      {
        methodname: 'mod_teammeeting_is_meeting_ready',
        args: { teammeetingid: teammeetingid },
      },
    ])[0]
      .then(function (data) {
        if (data.isready) {
          window.location.href = data.externalurl;
          return;
        }
        setTimeout(readyCheck, READY_CHECK_INTERVAL);
      })
      .catch(Notification.exception);
  };
  setTimeout(readyCheck, READY_CHECK_INTERVAL);
};

export const init = (teammeetingid, canpresent) => {
  if (canpresent) {
    askToBecomePresenter(teammeetingid);
  }
  setupReadyCheck(teammeetingid);
};
