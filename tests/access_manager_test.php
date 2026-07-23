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

use report_assigngradingoverview\local\access\access_manager;

/**
 * Access manager tests.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_manager_test extends \advanced_testcase {
    /**
     * Verify that grading access follows capabilities.
     *
     * @covers \report_assigngradingoverview\local\access\access_manager::can_grade
     */
    public function test_assignment_access_uses_capabilities(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $teacherrole = $this->getDataGenerator()->create_role(['shortname' => 'overviewteacher']);
        assign_capability('report/assigngradingoverview:view', CAP_ALLOW, $teacherrole, \context_course::instance($course->id));
        assign_capability('mod/assign:grade', CAP_ALLOW, $teacherrole, \context_course::instance($course->id));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($assign->cmid);

        $this->setUser($teacher);
        $this->assertTrue((new access_manager())->can_grade($cm));
        $this->setUser($student);
        $this->assertFalse((new access_manager())->can_grade($cm));
    }
}
