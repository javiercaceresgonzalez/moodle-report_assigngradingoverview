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
 * @copyright  2026 Sergio Comerón <sergiocomeron@icloud.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_repository {
    /** @var access_manager Access checker. */
    private access_manager $access;
    /** @var \stdClass[][] Request-level candidate cache keyed by filter signature. */
    private array $candidates = [];

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
     * @return \stdClass[] Records keyed by course-module ID.
     */
    public function get_candidates(filter $filter): array {
        global $DB;

        // The same structural set is often needed more than once per request
        // (course options, validation and summaries); cache it per filter shape.
        $cachekey = $filter->courseid . '|' . $filter->search . '|' . $filter->visibility . '|' . $filter->duedatestatus;
        if (isset($this->candidates[$cachekey])) {
            return $this->candidates[$cachekey];
        }

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
                       a.id AS instanceid, a.name AS assignmentname, a.duedate, a.teamsubmission
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
        return $this->candidates[$cachekey] = $result;
    }

    /**
     * Count submitted and pending submissions for every candidate with aggregate SQL.
     *
     * The queries mirror assign::count_submissions_with_status() and
     * assign::count_submissions_need_grading() so the numbers match the ones
     * mod_assign itself reports, at a handful of queries per course instead of
     * several queries per assignment.
     *
     * @param \stdClass[] $candidates Candidate records keyed by course-module ID.
     * @return \stdClass[] Objects with submitted and pending counts keyed by course-module ID.
     */
    public function get_submission_counters(array $candidates): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $counters = [];
        $individual = [];
        $team = [];
        foreach ($candidates as $record) {
            $counters[$record->cmid] = (object)['submitted' => 0, 'pending' => 0];
            if ($record->teamsubmission) {
                $team[$record->instanceid] = $record->cmid;
            } else {
                $individual[$record->courseid][$record->instanceid] = $record->cmid;
            }
        }

        // Group submissions are stored against userid zero and are never counted
        // as needing grading by core, so one site-wide query covers them all.
        if ($team) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($team), SQL_PARAMS_NAMED);
            $params['substatus'] = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
            $teamsql = "SELECT s.assignment, COUNT(s.id)
                          FROM {assign_submission} s
                         WHERE s.assignment $insql AND s.userid = 0 AND s.latest = 1
                               AND s.timemodified IS NOT NULL AND s.status = :substatus
                      GROUP BY s.assignment";
            foreach ($DB->get_records_sql_menu($teamsql, $params) as $instanceid => $count) {
                $counters[$team[$instanceid]]->submitted = (int)$count;
            }
        }

        // Since Moodle 5.1 core counts distinct submitters across all enrolments,
        // including suspended ones; earlier branches count active enrolments only.
        $modern = method_exists(\assign::class, 'count_submissions_with_status_and_groups');
        foreach ($individual as $courseid => $instances) {
            $context = \context_course::instance($courseid);
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($instances), SQL_PARAMS_NAMED);

            [$esql, $eparams] = get_enrolled_sql($context, '', 0, !$modern);
            $countfield = $modern ? 'COUNT(DISTINCT s.userid)' : 'COUNT(s.userid)';
            $submittedsql = "SELECT s.assignment, $countfield
                               FROM {assign_submission} s
                               JOIN ($esql) e ON e.id = s.userid
                              WHERE s.assignment $insql AND s.latest = 1
                                    AND s.timemodified IS NOT NULL AND s.status = :substatus
                           GROUP BY s.assignment";
            $submittedparams = $inparams + $eparams + ['substatus' => ASSIGN_SUBMISSION_STATUS_SUBMITTED];
            foreach ($DB->get_records_sql_menu($submittedsql, $submittedparams) as $instanceid => $count) {
                $counters[$instances[$instanceid]]->submitted = (int)$count;
            }

            [$esql, $eparams] = get_enrolled_sql($context, '', 0, true);
            $pendingsql = "SELECT s.assignment, COUNT(s.userid)
                             FROM {assign_submission} s
                        LEFT JOIN {assign_grades} g ON g.assignment = s.assignment
                                                   AND g.userid = s.userid
                                                   AND g.attemptnumber = s.attemptnumber
                             JOIN ($esql) e ON e.id = s.userid
                             JOIN {assign} a ON a.id = s.assignment
                            WHERE s.assignment $insql AND s.latest = 1
                                  AND s.timemodified IS NOT NULL AND s.status = :substatus
                                  AND (s.timemodified >= g.timemodified OR g.timemodified IS NULL
                                       OR g.grade IS NULL OR (a.grade < 0 AND g.grade = -1))
                         GROUP BY s.assignment";
            $pendingparams = $inparams + $eparams + ['substatus' => ASSIGN_SUBMISSION_STATUS_SUBMITTED];
            foreach ($DB->get_records_sql_menu($pendingsql, $pendingparams) as $instanceid => $count) {
                $counters[$instances[$instanceid]]->pending = (int)$count;
            }
        }
        return $counters;
    }

    /**
     * Return courses represented by accessible candidates.
     *
     * @return string[] Course names keyed by course ID.
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
