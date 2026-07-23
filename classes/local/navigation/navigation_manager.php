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

namespace report_assigngradingoverview\local\navigation;

use report_assigngradingoverview\local\access\access_manager;

/**
 * Lightweight navigation decisions.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation_manager {
    /**
     * Determine whether a course has at least one assignment the user can grade.
     *
     * @param int $courseid Course ID.
     * @return bool Whether a gradable assignment exists.
     */
    public function has_gradable_assignment(int $courseid): bool {
        $access = new access_manager();
        try {
            foreach (get_fast_modinfo($courseid)->get_instances_of('assign') as $cm) {
                if ($access->can_grade($cm)) {
                    return true;
                }
            }
        } catch (\moodle_exception $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
        }
        return false;
    }

    /**
     * Check whether the current user can grade an assignment in any course.
     *
     * @return bool Whether the global report should be available.
     */
    public function has_potential_access(): bool {
        if (is_siteadmin()) {
            return true;
        }

        $courses = get_user_capability_course('report/assigngradingoverview:view', null, true, '', 'id');
        foreach ($courses as $course) {
            if ($this->has_gradable_assignment($course->id)) {
                return true;
            }
        }
        return false;
    }
}
