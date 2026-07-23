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

use report_assigngradingoverview\local\dto\filter;

/**
 * Tests for report filter normalisation.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter_test extends \advanced_testcase {
    /**
     * Default values are omitted from canonical URLs.
     *
     * @covers \report_assigngradingoverview\local\dto\filter::get_url_params
     * @return void
     */
    public function test_get_url_params_omits_defaults(): void {
        $filter = new filter();

        $this->assertSame([], $filter->get_url_params(false, 25));
    }

    /**
     * Canonical URLs retain only values that change the report.
     *
     * @covers \report_assigngradingoverview\local\dto\filter::get_url_params
     * @return void
     */
    public function test_get_url_params_retains_active_filters(): void {
        $filter = new filter(4, 7, 'Essay', true, 'hidden', 'overdue', 50);

        $this->assertSame([
            'courseid' => 4,
            'groupid' => 7,
            'search' => 'Essay',
            'pendingonly' => 1,
            'filterset' => 1,
            'visibility' => 'hidden',
            'duedatestatus' => 'overdue',
            'perpage' => 50,
        ], $filter->get_url_params(false, 25));
    }
}
