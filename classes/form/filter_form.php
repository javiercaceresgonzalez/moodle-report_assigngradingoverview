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

namespace report_assigngradingoverview\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Reusable report filter form.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter_form extends \moodleform {
    /**
     * Defines the report filter fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        // The same form serves the global report and the course-scoped report.
        $courses = $this->_customdata['courses'] ?? [];
        $groups = $this->_customdata['groups'] ?? [];
        $courseview = !empty($this->_customdata['courseview']);
        $filtersopen = !empty($this->_customdata['filtersopen']);
        $mform->setAttributes(['class' => 'mform report-assigngradingoverview-toolbar mb-2']);

        // Keep Moodle's collapsible header in sync with the state carried by the report URL.
        $mform->addElement('header', 'filtersheader', get_string('filters', 'report_assigngradingoverview'));
        $mform->setExpanded('filtersheader', $filtersopen, true);

        // Course selection is available globally; the course report keeps its fixed course ID hidden.
        if (!$courseview) {
            $mform->addElement(
                'select',
                'courseid',
                get_string('course', 'report_assigngradingoverview'),
                [0 => get_string('allcourses', 'report_assigngradingoverview')] + $courses
            );
            $mform->addHelpButton('courseid', 'course', 'report_assigngradingoverview');
        } else {
            $mform->addElement('hidden', 'id', $this->_customdata['courseid']);
            $mform->setType('id', PARAM_INT);
        }

        $mform->addElement('advcheckbox', 'pendingonly', get_string('pendingonly', 'report_assigngradingoverview'));
        $mform->addHelpButton('pendingonly', 'pendingonly', 'report_assigngradingoverview');

        $mform->addElement('select', 'duedatestatus', get_string('duedatestatus', 'report_assigngradingoverview'), [
            'all' => get_string('dueall', 'report_assigngradingoverview'),
            'overdue' => get_string('dueoverdue', 'report_assigngradingoverview'),
            'notoverdue' => get_string('duenotoverdue', 'report_assigngradingoverview'),
            'none' => get_string('duenone', 'report_assigngradingoverview'),
        ]);
        $mform->addHelpButton('duedatestatus', 'duedatestatus', 'report_assigngradingoverview');

        $mform->addElement(
            'select',
            'groupid',
            get_string('group', 'report_assigngradingoverview'),
            [0 => get_string('allgroups', 'report_assigngradingoverview')] + $groups
        );
        $mform->addHelpButton('groupid', 'group', 'report_assigngradingoverview');
        // A group cannot be selected globally until its course establishes the accessible group set.
        if (!$courseview && empty($this->_customdata['selectedcourse'])) {
            $mform->freeze('groupid');
        }

        $mform->addElement('text', 'search', get_string('searchassignment', 'report_assigngradingoverview'));
        $mform->setType('search', PARAM_TEXT);
        $mform->addHelpButton('search', 'searchassignment', 'report_assigngradingoverview');

        $visibilityoptions = [
            'all' => get_string('all', 'report_assigngradingoverview'),
            'visible' => get_string('visible', 'report_assigngradingoverview'),
        ];
        if (get_config('report_assigngradingoverview', 'showhidden')) {
            $visibilityoptions['hidden'] = get_string('hidden', 'report_assigngradingoverview');
        }
        $mform->addElement('select', 'visibility', get_string('visibility', 'report_assigngradingoverview'), $visibilityoptions);
        $mform->addHelpButton('visibility', 'visibility', 'report_assigngradingoverview');

        $mform->addElement(
            'select',
            'perpage',
            get_string('perpage', 'report_assigngradingoverview'),
            [25 => 25, 50 => 50, 100 => 100]
        );
        $mform->addHelpButton('perpage', 'perpage', 'report_assigngradingoverview');

        // Keep form submissions canonical and preserve the collapsible state across reloads.
        $mform->addElement('hidden', 'page', 0);
        $mform->setType('page', PARAM_INT);
        $mform->addElement('hidden', 'filterset', 1);
        $mform->setType('filterset', PARAM_BOOL);
        $mform->addElement('hidden', 'filtersopen', (int)$filtersopen);
        $mform->setType('filtersopen', PARAM_BOOL);
        $resetlink = \html_writer::link(
            $this->_customdata['reseturl'],
            get_string('resetfilters', 'report_assigngradingoverview'),
            ['class' => 'btn btn-secondary']
        );
        $buttonarray = [
            $mform->createElement('submit', 'submitbutton', get_string('applyfilters', 'report_assigngradingoverview')),
            $mform->createElement('static', 'resetlink', '', $resetlink),
        ];
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }
}
