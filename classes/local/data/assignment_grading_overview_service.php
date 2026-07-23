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
use report_assigngradingoverview\local\dto\assignment_summary;
use report_assigngradingoverview\local\dto\filter;

/**
 * Builds grading summaries from the official mod_assign API.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_grading_overview_service {
    /** @var assignment_repository Candidate repository. */
    private assignment_repository $repository;
    /** @var access_manager Access checker. */
    private access_manager $access;

    /**
     * Create the assignment grading overview service.
     *
     * @param assignment_repository|null $repository Candidate repository, or null to create the default.
     * @param access_manager|null $access Access checker, or null to create the default.
     * @return void
     */
    public function __construct(?assignment_repository $repository = null, ?access_manager $access = null) {
        $this->access = $access ?? new access_manager();
        $this->repository = $repository ?? new assignment_repository($this->access);
    }

    /**
     * Build all summaries matching the filter.
     *
     * @param filter $filter Validated report filters.
     * @return array<int, assignment_summary> Matching assignment summaries.
     */
    public function get_summaries(filter $filter): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $summaries = [];
        foreach ($this->repository->get_candidates($filter) as $record) {
            if (!$this->access->is_group_allowed($record->cm, $filter->groupid)) {
                continue;
            }
            try {
                $course = get_course($record->courseid);
                $context = \context_module::instance($record->cmid);
                $assignment = new \assign($context, $record->cm, $course);
                $participants = $assignment->count_participants($filter->groupid);
                $submitted = $assignment->count_submissions_with_status(
                    ASSIGN_SUBMISSION_STATUS_SUBMITTED,
                    $filter->groupid
                );
                $pending = $assignment->count_submissions_need_grading($filter->groupid);
                $summary = new assignment_summary($record, $participants, $submitted, $pending);
                if (!$filter->pendingonly || $summary->pending > 0) {
                    $summaries[] = $summary;
                }
            } catch (\moodle_exception $exception) {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return $summaries;
    }

    /**
     * Sort summaries using a validated field and direction.
     *
     * @param assignment_summary[] $summaries Summaries to sort in place.
     * @param string $sort Sort field.
     * @param string $direction Sort direction.
     * @return void
     */
    public function sort(array &$summaries, string $sort, string $direction): void {
        $allowed = ['course', 'assignment', 'submitted', 'graded', 'pending', 'duedate'];
        $sort = in_array($sort, $allowed, true) ? $sort : 'default';
        $factor = $direction === 'asc' ? 1 : -1;
        usort($summaries, static function (assignment_summary $left, assignment_summary $right) use ($sort, $factor): int {
            if ($sort === 'default') {
                return [$left->record->courseid, -$left->pending, $left->record->assignmentname]
                    <=> [$right->record->courseid, -$right->pending, $right->record->assignmentname];
            }
            if ($sort === 'course') {
                $coursecomparison = $factor * ($left->record->courseid <=> $right->record->courseid);
                if ($coursecomparison !== 0) {
                    return $coursecomparison;
                }
                return [-$left->pending, $left->record->assignmentname]
                    <=> [-$right->pending, $right->record->assignmentname];
            }
            $values = [
                'assignment' => [$left->record->assignmentname, $right->record->assignmentname],
                'submitted' => [$left->submitted, $right->submitted],
                'graded' => [$left->graded, $right->graded],
                'pending' => [$left->pending, $right->pending],
                'duedate' => [$left->record->duedate, $right->record->duedate],
            ];
            return $factor * ($values[$sort][0] <=> $values[$sort][1]);
        });
    }
}
