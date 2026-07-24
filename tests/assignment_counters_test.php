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
use report_assigngradingoverview\local\data\assignment_grading_overview_service;
use report_assigngradingoverview\local\data\assignment_repository;
use report_assigngradingoverview\local\dto\assignment_summary;
use report_assigngradingoverview\local\dto\filter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Aggregated counters must match the mod_assign counting API.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_counters_test extends \advanced_testcase {
    /** @var \stdClass Course shared by the scenarios of one test. */
    private \stdClass $course;
    /** @var \stdClass Teacher used to run the report. */
    private \stdClass $teacher;

    /**
     * Create the shared course and grading teacher.
     *
     * @return void
     */
    private function create_course_with_teacher(): void {
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
    }

    /**
     * Create an assignment in the shared course.
     *
     * @param array $params Extra instance parameters.
     * @return \assign Assignment API instance.
     */
    private function create_assignment(array $params = []): \assign {
        $instance = $this->getDataGenerator()->create_module('assign', $params + ['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        return new \assign(\context_module::instance($cm->id), $cm, $this->course);
    }

    /**
     * Insert a submitted submission row.
     *
     * @param \assign $assignment Assignment.
     * @param int $userid Submitter (zero for a team submission).
     * @param int $time Submission time.
     * @param int $attempt Attempt number; earlier attempts lose their latest flag.
     * @param int $groupid Group for team submissions.
     * @return void
     */
    private function add_submission(\assign $assignment, int $userid, int $time, int $attempt = 0, int $groupid = 0): void {
        global $DB;
        $DB->set_field('assign_submission', 'latest', 0, [
            'assignment' => $assignment->get_instance()->id,
            'userid' => $userid,
            'groupid' => $groupid,
        ]);
        $DB->insert_record('assign_submission', (object)[
            'assignment' => $assignment->get_instance()->id,
            'userid' => $userid,
            'timecreated' => $time,
            'timemodified' => $time,
            'timestarted' => $time,
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'groupid' => $groupid,
            'attemptnumber' => $attempt,
            'latest' => 1,
        ]);
    }

    /**
     * Insert a grade row.
     *
     * @param \assign $assignment Assignment.
     * @param int $userid Graded user.
     * @param int $time Grading time.
     * @param float $grade Grade value.
     * @param int $attempt Attempt number.
     * @return void
     */
    private function add_grade(\assign $assignment, int $userid, int $time, float $grade, int $attempt = 0): void {
        global $DB;
        $DB->insert_record('assign_grades', (object)[
            'assignment' => $assignment->get_instance()->id,
            'userid' => $userid,
            'timecreated' => $time,
            'timemodified' => $time,
            'grader' => $this->teacher->id,
            'grade' => $grade,
            'attemptnumber' => $attempt,
        ]);
    }

    /**
     * Run the service as the teacher and return summaries keyed by cmid.
     *
     * @param filter|null $filter Report filter, or null for no filters.
     * @return array<int, assignment_summary> Summaries keyed by course-module ID.
     */
    private function get_summaries(?filter $filter = null): array {
        $filter = $filter ?? new filter();
        $this->setUser($this->teacher);
        $access = new access_manager();
        $service = new assignment_grading_overview_service(new assignment_repository($access), $access);
        $summaries = [];
        foreach ($service->get_summaries($filter) as $summary) {
            $summaries[$summary->record->cmid] = $summary;
        }
        $service->load_participants($summaries, $filter);
        return $summaries;
    }

    /**
     * Assert that a summary matches the mod_assign counting API exactly.
     *
     * @param assignment_summary $summary Summary produced by the service.
     * @param \assign $assignment Assignment to compare against.
     * @param int $groupid Group used for the API counts.
     * @return void
     */
    private function assert_matches_api(assignment_summary $summary, \assign $assignment, int $groupid = 0): void {
        $this->setUser($this->teacher);
        $this->assertSame(
            $assignment->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED, $groupid),
            $summary->submitted,
            'submitted mismatch for ' . $assignment->get_instance()->name
        );
        $this->assertSame(
            $assignment->count_submissions_need_grading($groupid),
            $summary->pending,
            'pending mismatch for ' . $assignment->get_instance()->name
        );
        $this->assertSame(
            $assignment->count_participants($groupid),
            $summary->participants,
            'participants mismatch for ' . $assignment->get_instance()->name
        );
    }

    /**
     * Individual submissions in every grading state match the API.
     *
     * @covers \report_assigngradingoverview\local\data\assignment_repository::get_submission_counters
     */
    public function test_individual_counters_match_mod_assign_api(): void {
        $this->create_course_with_teacher();
        $students = [];
        for ($i = 0; $i < 4; $i++) {
            $students[$i] = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($students[$i]->id, $this->course->id, 'student');
        }
        $assignment = $this->create_assignment(['name' => 'Individual']);
        $now = time();

        // Ungraded, graded-after (not pending) and re-submitted-after-grading (pending again).
        $this->add_submission($assignment, $students[0]->id, $now - 100);
        $this->add_submission($assignment, $students[1]->id, $now - 100);
        $this->add_grade($assignment, $students[1]->id, $now - 50, 70.0);
        $this->add_submission($assignment, $students[2]->id, $now - 100);
        $this->add_grade($assignment, $students[2]->id, $now - 150, 70.0);

        // A submission from a user who is not enrolled must be ignored, as core does.
        $stranger = $this->getDataGenerator()->create_user();
        $this->add_submission($assignment, $stranger->id, $now - 100);

        // A suspended enrolment: branch-dependent in core, absorbed by comparing to the API.
        $suspended = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($suspended->id, $this->course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->add_submission($assignment, $suspended->id, $now - 100);

        $summaries = $this->get_summaries();
        $this->assert_matches_api($summaries[$assignment->get_course_module()->id], $assignment);
    }

    /**
     * Reopened attempts count against their latest attempt only.
     *
     * @covers \report_assigngradingoverview\local\data\assignment_repository::get_submission_counters
     */
    public function test_multiple_attempts_match_mod_assign_api(): void {
        $this->create_course_with_teacher();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $assignment = $this->create_assignment(['name' => 'Attempts', 'attemptreopenmethod' => 'manual', 'maxattempts' => -1]);
        $now = time();

        $this->add_submission($assignment, $student->id, $now - 300, 0);
        $this->add_grade($assignment, $student->id, $now - 250, 40.0, 0);
        $this->add_submission($assignment, $student->id, $now - 100, 1);

        $summaries = $this->get_summaries();
        $summary = $summaries[$assignment->get_course_module()->id];
        $this->assert_matches_api($summary, $assignment);
        $this->assertSame(1, $summary->pending);
    }

    /**
     * Scale grading keeps unmarked scale grades pending.
     *
     * @covers \report_assigngradingoverview\local\data\assignment_repository::get_submission_counters
     */
    public function test_scale_grades_match_mod_assign_api(): void {
        $this->create_course_with_teacher();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $scale = $this->getDataGenerator()->create_scale();
        $assignment = $this->create_assignment(['name' => 'Scaled', 'grade' => -$scale->id]);
        $now = time();

        // Grade record newer than the submission but with no scale value chosen.
        $this->add_submission($assignment, $student->id, $now - 100);
        $this->add_grade($assignment, $student->id, $now - 50, -1.0);

        $summaries = $this->get_summaries();
        $summary = $summaries[$assignment->get_course_module()->id];
        $this->assert_matches_api($summary, $assignment);
        $this->assertSame(1, $summary->pending);
    }

    /**
     * Team submissions count groups and never report pending, as core does.
     *
     * @covers \report_assigngradingoverview\local\data\assignment_repository::get_submission_counters
     */
    public function test_team_submissions_match_mod_assign_api(): void {
        $this->create_course_with_teacher();
        $students = [];
        for ($i = 0; $i < 4; $i++) {
            $students[$i] = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($students[$i]->id, $this->course->id, 'student');
        }
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group1->id, 'userid' => $students[0]->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group1->id, 'userid' => $students[1]->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group2->id, 'userid' => $students[2]->id]);
        $assignment = $this->create_assignment([
            'name' => 'Team',
            'teamsubmission' => 1,
            'preventsubmissionnotingroup' => 0,
        ]);

        $this->add_submission($assignment, 0, time() - 100, 0, $group1->id);

        $summaries = $this->get_summaries();
        $summary = $summaries[$assignment->get_course_module()->id];
        $this->assert_matches_api($summary, $assignment);
        $this->assertSame(1, $summary->submitted);
        $this->assertSame(0, $summary->pending);
    }

    /**
     * The group-filtered path keeps matching the API for the selected group.
     *
     * @covers \report_assigngradingoverview\local\data\assignment_grading_overview_service::get_summaries
     */
    public function test_group_filter_matches_mod_assign_api(): void {
        $this->create_course_with_teacher();
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $students[$i] = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($students[$i]->id, $this->course->id, 'student');
        }
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group1->id, 'userid' => $students[0]->id]);
        $assignment = $this->create_assignment(['name' => 'Grouped', 'groupmode' => VISIBLEGROUPS]);
        $now = time();
        $this->add_submission($assignment, $students[0]->id, $now - 100);
        $this->add_submission($assignment, $students[1]->id, $now - 100);

        $summaries = $this->get_summaries(new filter($this->course->id, $group1->id));
        $summary = $summaries[$assignment->get_course_module()->id];
        $this->assert_matches_api($summary, $assignment, $group1->id);
        $this->assertSame(1, $summary->submitted);
    }

    /**
     * The pending-only filter keeps only assignments with pending work.
     *
     * @covers \report_assigngradingoverview\local\data\assignment_grading_overview_service::get_summaries
     */
    public function test_pendingonly_filter_uses_aggregated_counters(): void {
        $this->create_course_with_teacher();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $withpending = $this->create_assignment(['name' => 'With pending']);
        $withoutpending = $this->create_assignment(['name' => 'Without pending']);
        $this->add_submission($withpending, $student->id, time() - 100);

        $summaries = $this->get_summaries(new filter(0, 0, '', true));
        $this->assertArrayHasKey($withpending->get_course_module()->id, $summaries);
        $this->assertArrayNotHasKey($withoutpending->get_course_module()->id, $summaries);
    }
}
