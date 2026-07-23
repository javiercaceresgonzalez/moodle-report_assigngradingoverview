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

namespace report_assigngradingoverview;

use report_assigngradingoverview\local\data\assignment_grading_overview_service;
use report_assigngradingoverview\local\dto\assignment_summary;

/**
 * Assignment grading overview service tests.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_grading_overview_service_test extends \advanced_testcase {
    /**
     * Verify the default global course and assignment ordering.
     *
     * @covers \report_assigngradingoverview\local\data\assignment_grading_overview_service::sort
     */
    public function test_course_sort_uses_id_pending_and_assignment_name(): void {
        $summaries = [
            $this->summary(10, 'Newest course assignment', 9),
            $this->summary(2, 'Zulu assignment', 2),
            $this->summary(2, 'No pending work', 0),
            $this->summary(2, 'Alpha assignment', 2),
        ];

        (new assignment_grading_overview_service())->sort($summaries, 'course', 'asc');

        $actual = array_map(
            static fn(assignment_summary $summary): array => [
                $summary->record->courseid,
                $summary->record->assignmentname,
                $summary->pending,
            ],
            $summaries
        );
        $this->assertSame([
            [2, 'Alpha assignment', 2],
            [2, 'Zulu assignment', 2],
            [2, 'No pending work', 0],
            [10, 'Newest course assignment', 9],
        ], $actual);
    }

    /**
     * Create a summary for sorting tests.
     *
     * @param int $courseid Course ID.
     * @param string $assignmentname Assignment name.
     * @param int $pending Number of submissions awaiting grading.
     * @return assignment_summary
     */
    private function summary(int $courseid, string $assignmentname, int $pending): assignment_summary {
        $record = (object)[
            'courseid' => $courseid,
            'assignmentname' => $assignmentname,
            'duedate' => 0,
        ];
        return new assignment_summary($record, 10, 10, $pending);
    }
}
