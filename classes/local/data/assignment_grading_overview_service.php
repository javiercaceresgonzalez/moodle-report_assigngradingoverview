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
 * Builds grading summaries using aggregate counters and the mod_assign API where required.
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

        $candidates = $this->repository->get_candidates($filter);
        if ($filter->groupid) {
            return $this->get_group_summaries($candidates, $filter);
        }

        // Without a group filter the counters can be aggregated in a few
        // queries; participants are loaded later for the visible page only.
        $counters = $this->repository->get_submission_counters($candidates);
        $summaries = [];
        foreach ($candidates as $record) {
            $counter = $counters[$record->cmid];
            $summary = new assignment_summary($record, 0, $counter->submitted, $counter->pending);
            if (!$filter->pendingonly || $summary->pending > 0) {
                $summaries[] = $summary;
            }
        }
        return $summaries;
    }

    /**
     * Build summaries for a specific group through the mod_assign API.
     *
     * A group filter implies a single course, so the per-assignment cost of the
     * mod_assign counting API stays acceptable and its group semantics are kept.
     *
     * @param \stdClass[] $candidates Candidate records keyed by course-module ID.
     * @param filter $filter Validated report filters with a group selected.
     * @return array<int, assignment_summary> Matching assignment summaries.
     */
    private function get_group_summaries(array $candidates, filter $filter): array {
        $summaries = [];
        foreach ($candidates as $record) {
            if (!$this->access->is_group_allowed($record->cm, $filter->groupid)) {
                continue;
            }
            try {
                $assignment = $this->get_assignment($record);
                $submitted = $assignment->count_submissions_with_status(
                    ASSIGN_SUBMISSION_STATUS_SUBMITTED,
                    $filter->groupid
                );
                $pending = $assignment->count_submissions_need_grading($filter->groupid);
                $summary = new assignment_summary($record, 0, $submitted, $pending);
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
     * Fill in participant counts for the summaries about to be displayed.
     *
     * count_participants() honours module overrides, availability restrictions
     * and suspended-user visibility, so it is kept as the single source of
     * truth and only paid for the rows on the current page.
     *
     * @param assignment_summary[] $summaries Summaries of the visible page.
     * @param filter $filter Validated report filters.
     * @return void
     */
    public function load_participants(array $summaries, filter $filter): void {
        foreach ($summaries as $summary) {
            try {
                $summary->set_participants($this->get_assignment($summary->record)->count_participants($filter->groupid));
            } catch (\moodle_exception $exception) {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Instantiate the mod_assign API object for a candidate record.
     *
     * @param \stdClass $record Candidate record with cm, cmid and courseid.
     * @return \assign Assignment API instance.
     */
    private function get_assignment(\stdClass $record): \assign {
        $course = get_course($record->courseid);
        $context = \context_module::instance($record->cmid);
        return new \assign($context, $record->cm, $course);
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
