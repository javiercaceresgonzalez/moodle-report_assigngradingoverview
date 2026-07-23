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

/**
 * Callbacks for report_assigngradingoverview.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the report to course navigation when a gradable assignment exists.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return void
 */
function report_assigngradingoverview_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if ((int)$course->id === SITEID || !has_capability('report/assigngradingoverview:view', $context)) {
        return;
    }
    $manager = new \report_assigngradingoverview\local\navigation\navigation_manager();
    if (!$manager->has_gradable_assignment($course->id)) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'report_assigngradingoverview'),
        new moodle_url('/report/assigngradingoverview/course.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'report_assigngradingoverview_course',
        new pix_icon('i/report', '')
    );
}
