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

use report_assigngradingoverview\local\dto\assignment_summary;

/**
 * Tests for assignment summary invariants.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_summary_test extends \advanced_testcase {
    /**
     * Verify that counts are bounded by the number submitted.
     *
     * @covers \report_assigngradingoverview\local\dto\assignment_summary::__construct
     */
    public function test_counts_are_normalised(): void {
        $record = (object)['cmid' => 1];
        $summary = new assignment_summary($record, -1, 2, 5);
        $this->assertSame(0, $summary->participants);
        $this->assertSame(2, $summary->submitted);
        $this->assertSame(2, $summary->pending);
        $this->assertSame(0, $summary->graded);
    }

    /**
     * Verify that graded is derived from submitted and pending.
     *
     * @covers \report_assigngradingoverview\local\dto\assignment_summary::__construct
     */
    public function test_graded_is_derived_from_pending(): void {
        $summary = new assignment_summary((object)['cmid' => 1], 10, 8, 3);
        $this->assertSame(5, $summary->graded);
    }
}
