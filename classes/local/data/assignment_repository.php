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

namespace report_assigngradingoverview\local\data;

use report_assigngradingoverview\local\access\access_manager;
use report_assigngradingoverview\local\dto\filter;

/**
 * Locates assignment candidates using portable Moodle DML.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_repository {
    /** @var access_manager Access checker. */
    private access_manager $access;

    /**
     * Create an assignment repository.
     *
     * @param access_manager|null $access Access checker, or null to create the default.
     * @return void
     */
    public function __construct(?access_manager $access = null) {
        $this->access = $access ?? new access_manager();
    }

    /**
     * Get structural records for assignments the current user can grade.
     *
     * Counter-based filtering and sorting are deliberately handled by the service.
     *
     * @param filter $filter Validated filters.
     * @return array<int, \stdClass> Records keyed by course-module ID.
     */
    public function get_candidates(filter $filter): array {
        global $DB;

        $params = ['assignmodule' => 'assign'];
        $where = ['cm.deletioninprogress = 0'];
        if ($filter->courseid) {
            $where[] = 'c.id = :courseid';
            $params['courseid'] = $filter->courseid;
        }
        if ($filter->search !== '') {
            $where[] = $DB->sql_like('a.name', ':search', false, false);
            $params['search'] = '%' . $DB->sql_like_escape($filter->search) . '%';
        }
        $showhidden = (bool)get_config('report_assigngradingoverview', 'showhidden');
        if (!$showhidden || $filter->visibility === 'visible') {
            $where[] = 'cm.visible = 1';
        } else if ($filter->visibility === 'hidden') {
            $where[] = 'cm.visible = 0';
        }
        if (!get_config('report_assigngradingoverview', 'includehiddencourses')) {
            $where[] = 'c.visible = 1';
        }

        $now = time();
        if ($filter->duedatestatus === 'overdue') {
            $where[] = 'a.duedate > 0 AND a.duedate < :now';
            $params['now'] = $now;
        } else if ($filter->duedatestatus === 'notoverdue') {
            $where[] = 'a.duedate >= :now';
            $params['now'] = $now;
        } else if ($filter->duedatestatus === 'none') {
            $where[] = 'a.duedate = 0';
        }

        $sql = "SELECT cm.id AS cmid, cm.course AS courseid,
                       c.fullname AS coursename,
                       a.name AS assignmentname, a.duedate
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :assignmodule
                  JOIN {assign} a ON a.id = cm.instance
                  JOIN {course} c ON c.id = cm.course
                 WHERE " . implode(' AND ', $where) . '
              ORDER BY c.id, a.name, a.id';

        $records = $DB->get_records_sql($sql, $params);
        $result = [];
        $modinfos = [];
        foreach ($records as $record) {
            try {
                $modinfos[$record->courseid] ??= get_fast_modinfo($record->courseid);
                $cm = $modinfos[$record->courseid]->get_cm($record->cmid);
                if ($this->access->can_grade($cm)) {
                    $record->cm = $cm;
                    $result[$record->cmid] = $record;
                }
            } catch (\moodle_exception $exception) {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return $result;
    }

    /**
     * Return courses represented by accessible candidates.
     *
     * @return array<int, string> Course names keyed by course ID.
     */
    public function get_course_options(): array {
        $filter = new filter(0, 0, '', false);
        $courses = [];
        foreach ($this->get_candidates($filter) as $record) {
            $courses[$record->courseid] = format_string($record->coursename, true, [
                'context' => \context_course::instance($record->courseid),
            ]);
        }
        return $courses;
    }
}
