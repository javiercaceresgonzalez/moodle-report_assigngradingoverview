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

namespace report_assigngradingoverview\output;

use report_assigngradingoverview\local\dto\assignment_summary;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Flexible table for assignment summaries.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_table extends \core_table\flexible_table {
    /** @var bool Whether to show the course column. */
    private bool $showcourse;

    /**
     * Initialise the assignment table.
     *
     * @param string $uniqueid Unique table ID.
     * @param \moodle_url $baseurl Report URL.
     * @param bool $showcourse Whether to show the course column.
     * @return void
     */
    public function __construct(string $uniqueid, \moodle_url $baseurl, bool $showcourse) {
        parent::__construct($uniqueid);
        $this->showcourse = $showcourse;
        $columns = $showcourse ? ['course'] : [];
        $columns = array_merge($columns, ['assignment', 'participants', 'submitted', 'graded', 'pending', 'duedate', 'actions']);
        $headers = $showcourse ? [get_string('course', 'report_assigngradingoverview')] : [];
        $headers = array_merge($headers, [
            get_string('assignment', 'report_assigngradingoverview'),
            get_string('participants', 'report_assigngradingoverview'),
            get_string('submitted', 'report_assigngradingoverview'),
            get_string('graded', 'report_assigngradingoverview'),
            get_string('pending', 'report_assigngradingoverview'),
            get_string('duedate', 'report_assigngradingoverview'),
            get_string('actions', 'report_assigngradingoverview'),
        ]);
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);
        $this->sortable(true, $showcourse ? 'course' : 'pending', $showcourse ? SORT_ASC : SORT_DESC);
        $this->no_sorting('participants');
        $this->no_sorting('actions');
        $this->collapsible(false);
        $this->set_attribute('aria-label', get_string('tablearia', 'report_assigngradingoverview'));
        $this->set_attribute('class', 'generaltable');
    }

    /**
     * Add one assignment summary row.
     *
     * @param assignment_summary $summary Assignment summary.
     * @return void
     */
    public function add_summary(assignment_summary $summary): void {
        global $OUTPUT;

        $record = $summary->record;
        $viewurl = new \moodle_url('/mod/assign/view.php', ['id' => $record->cmid]);
        $submissionsurl = new \moodle_url('/mod/assign/view.php', ['id' => $record->cmid, 'action' => 'grading']);
        $graderurl = new \moodle_url('/mod/assign/view.php', ['id' => $record->cmid, 'action' => 'grader']);
        $context = \context_module::instance($record->cmid);

        $row = [];
        if ($this->showcourse) {
            $courseurl = new \moodle_url('/report/assigngradingoverview/course.php', ['id' => $record->courseid]);
            $row[] = \html_writer::link($courseurl, format_string($record->coursename, true, [
                'context' => \context_course::instance($record->courseid),
            ]));
        }
        $row[] = \html_writer::link($viewurl, format_string($record->assignmentname, true, ['context' => $context]));
        $row[] = $summary->participants;
        $row[] = $summary->submitted;
        $row[] = $summary->graded;
        $pending = (string)$summary->pending;
        if ($summary->pending > 0) {
            $pending = \html_writer::link($submissionsurl, $pending, [
                'class' => 'badge bg-warning text-dark',
            ]);
        }
        $row[] = $pending;
        if ($record->duedate) {
            $due = userdate($record->duedate);
            if ($record->duedate < time()) {
                $due = \html_writer::span($due, 'text-muted');
            }
            $row[] = $due;
        } else {
            $row[] = get_string('never', 'report_assigngradingoverview');
        }
        $submissionslabel = $OUTPUT->pix_icon('t/viewdetails', '', 'moodle', ['class' => 'mr-1 me-1'])
            . get_string('viewsubmissions', 'report_assigngradingoverview');
        $graderlabel = $OUTPUT->pix_icon('i/grades', '', 'moodle', ['class' => 'mr-1 me-1'])
            . get_string('grade', 'report_assigngradingoverview');
        $actions = [
            \html_writer::link($submissionsurl, $submissionslabel),
            \html_writer::link($graderurl, $graderlabel),
        ];
        $row[] = implode('&nbsp;', $actions);
        $this->add_data($row, $summary->pending > 0 ? 'table-warning' : '');
    }
}
