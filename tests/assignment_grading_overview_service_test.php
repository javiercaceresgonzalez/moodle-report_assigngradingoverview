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
    public function test_course_sort_uses_localised_name_pending_and_assignment_name(): void {
        $summaries = [
            $this->summary(2, 'Zulu assignment', 2, 'Química'),
            $this->summary(2, 'No pending work', 0, 'Química'),
            $this->summary(10, 'Newest course assignment', 9, 'Álgebra'),
            $this->summary(2, 'Alpha assignment', 2, 'Química'),
        ];

        (new assignment_grading_overview_service())->sort($summaries, 'course', 'asc');

        $actual = array_map(
            static fn(assignment_summary $summary): array => [
                $summary->record->coursename,
                $summary->record->assignmentname,
                $summary->pending,
            ],
            $summaries
        );
        // Byte-wise ordering would put Química first ('Q' sorts before the
        // multibyte 'Á'); the collator must order course names naturally.
        $this->assertSame([
            ['Álgebra', 'Newest course assignment', 9],
            ['Química', 'Alpha assignment', 2],
            ['Química', 'Zulu assignment', 2],
            ['Química', 'No pending work', 0],
        ], $actual);
    }

    /**
     * Verify assignment names sort with natural locale-aware collation.
     *
     * @covers \report_assigngradingoverview\local\data\assignment_grading_overview_service::sort
     */
    public function test_assignment_sort_uses_natural_locale_collation(): void {
        $summaries = [
            $this->summary(2, 'Tema 10', 0),
            $this->summary(2, 'Álbum de fotos', 0),
            $this->summary(2, 'Tema 2', 0),
            $this->summary(2, 'artes escénicas', 0),
        ];
        $service = new assignment_grading_overview_service();

        $service->sort($summaries, 'assignment', 'asc');
        $ascending = array_map(
            static fn(assignment_summary $summary): string => $summary->record->assignmentname,
            $summaries
        );
        // Byte-wise ordering would produce Tema 10, Tema 2, artes, Álbum.
        $this->assertSame(['Álbum de fotos', 'artes escénicas', 'Tema 2', 'Tema 10'], $ascending);

        $service->sort($summaries, 'assignment', 'desc');
        $descending = array_map(
            static fn(assignment_summary $summary): string => $summary->record->assignmentname,
            $summaries
        );
        $this->assertSame(array_reverse($ascending), $descending);
    }

    /**
     * Create a summary for sorting tests.
     *
     * @param int $courseid Course ID.
     * @param string $assignmentname Assignment name.
     * @param int $pending Number of submissions awaiting grading.
     * @param string $coursename Course name.
     * @return assignment_summary
     */
    private function summary(
        int $courseid,
        string $assignmentname,
        int $pending,
        string $coursename = 'Course'
    ): assignment_summary {
        $record = (object)[
            'courseid' => $courseid,
            'coursename' => $coursename,
            'assignmentname' => $assignmentname,
            'duedate' => 0,
        ];
        return new assignment_summary($record, 10, 10, $pending);
    }
}
