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

namespace report_assigngradingoverview\local\access;

/**
 * Access and group checks for the report.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_manager {
    /**
     * Check access to one assignment.
     *
     * @param \cm_info $cm Course-module information.
     * @return bool Whether the current user can access the report and grade the assignment.
     */
    public function can_grade(\cm_info $cm): bool {
        $coursecontext = \context_course::instance($cm->course);
        $modulecontext = \context_module::instance($cm->id);
        return has_capability('report/assigngradingoverview:view', $coursecontext)
            && has_capability('mod/assign:grade', $modulecontext);
    }

    /**
     * Require access to the course report.
     *
     * @param int $courseid Course ID.
     * @return void
     */
    public function require_course_access(int $courseid): void {
        require_capability('report/assigngradingoverview:view', \context_course::instance($courseid));
    }

    /**
     * Return groups the current user may use in course assignments.
     *
     * @param int $courseid Course ID.
     * @return \stdClass[] Groups keyed by group ID.
     */
    public function get_course_groups(int $courseid): array {
        $groups = [];
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_instances_of('assign') as $cm) {
            if (!$this->can_grade($cm)) {
                continue;
            }
            foreach (groups_get_activity_allowed_groups($cm) as $group) {
                $groups[$group->id] = $group;
            }
        }
        \core_collator::asort_objects_by_property($groups, 'name', \core_collator::SORT_NATURAL);
        return $groups;
    }

    /**
     * Validate a selected group against an activity.
     *
     * @param \cm_info $cm Course-module information.
     * @param int $groupid Group ID, or zero for all accessible groups.
     * @return bool Whether the group can be used for the activity.
     */
    public function is_group_allowed(\cm_info $cm, int $groupid): bool {
        if ($groupid === 0 || groups_get_activity_groupmode($cm) == NOGROUPS) {
            return true;
        }
        return array_key_exists($groupid, groups_get_activity_allowed_groups($cm));
    }
}
