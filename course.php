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
 * Course assignment grading overview.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use core\report_helper;
use report_assigngradingoverview\form\filter_form;
use report_assigngradingoverview\local\access\access_manager;
use report_assigngradingoverview\local\data\assignment_repository;
use report_assigngradingoverview\local\data\assignment_grading_overview_service;
use report_assigngradingoverview\local\dto\filter;
use report_assigngradingoverview\output\assignment_table;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);

$access = new access_manager();
$access->require_course_access($course->id);

// Read request values only after course access has been enforced, then normalise them in the filter DTO.
$groupid = optional_param('groupid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$filterset = optional_param('filterset', 0, PARAM_BOOL);
$pendingdefault = (bool)get_config('report_assigngradingoverview', 'defaultpendingonly');
$pendingonly = $filterset ? optional_param('pendingonly', 0, PARAM_BOOL) : $pendingdefault;
$visibility = optional_param('visibility', 'all', PARAM_ALPHA);
$duedatestatus = optional_param('duedatestatus', 'all', PARAM_ALPHA);
$configuredperpage = (int)get_config('report_assigngradingoverview', 'defaultperpage') ?: 25;
$perpage = optional_param('perpage', $configuredperpage, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$filtersopen = optional_param('filtersopen', 0, PARAM_BOOL);
$filter = new filter($course->id, $groupid, $search, $pendingonly, $visibility, $duedatestatus, $perpage);

// Offer only groups available to this grader and reject forged or stale group selections.
$groups = $access->get_course_groups($course->id);
$coursecontext = context_course::instance($course->id);
$groupoptions = array_map(
    static fn($group): string => format_string($group->name, true, ['context' => $coursecontext]),
    $groups
);
if ($filter->groupid && !array_key_exists($filter->groupid, $groups)) {
    throw new moodle_exception('invalidgroup', 'report_assigngradingoverview');
}
$baseparams = ['id' => $course->id] + $filter->get_url_params($pendingdefault, $configuredperpage);
unset($baseparams['courseid']);
if ($page > 0) {
    $baseparams['page'] = $page;
}
if ($filtersopen) {
    $baseparams['filtersopen'] = 1;
}
$baseurl = new moodle_url('/report/assigngradingoverview/course.php', $baseparams);
$PAGE->set_url($baseurl);
$PAGE->set_context($coursecontext);
$PAGE->set_pagelayout('report');
$pluginname = get_string('pluginname', 'report_assigngradingoverview');
$PAGE->set_title($pluginname);
$PAGE->set_heading(format_string($course->fullname));

$formaction = new moodle_url('/report/assigngradingoverview/course.php');
$form = new filter_form($formaction, [
    'courseview' => true,
    'courseid' => $course->id,
    'groups' => $groupoptions,
    'filtersopen' => $filtersopen,
    'reseturl' => new moodle_url('/report/assigngradingoverview/course.php', ['id' => $course->id, 'filtersopen' => 1]),
], 'get');
$form->set_data((object)[
    'id' => $course->id,
    'groupid' => $filter->groupid,
    'search' => $filter->search,
    'pendingonly' => (int)$filter->pendingonly,
    'visibility' => $filter->visibility,
    'duedatestatus' => $filter->duedatestatus,
    'perpage' => $filter->perpage,
    'filtersopen' => $filtersopen,
]);
if ($form->get_data()) {
    redirect($baseurl);
}
$PAGE->requires->js_call_amd('report_assigngradingoverview/filter_state', 'init');

// Table setup resolves the requested or default sorting before the data service applies it.
$table = new assignment_table('report-assigngradingoverview-course-' . $course->id, $baseurl, false);
$table->pagesize($filter->perpage, 0);
$table->setup();
$sortcolumns = $table->get_sort_columns();
$sort = $sortcolumns ? array_key_first($sortcolumns) : 'default';
$direction = $sortcolumns && reset($sortcolumns) === SORT_ASC ? 'asc' : 'desc';

// Calculate sortable counters, slice the current page, then load participant counts only for visible rows.
$repository = new assignment_repository($access);
$service = new assignment_grading_overview_service($repository, $access);
$summaries = $service->get_summaries($filter);
$service->sort($summaries, $sort, $direction);
$table->pagesize($filter->perpage, count($summaries));
$pagesummaries = array_slice($summaries, $page * $filter->perpage, $filter->perpage);
$service->load_participants($pagesummaries, $filter);

echo $OUTPUT->header();
// Display the standard tertiary selector for course reports.
report_helper::print_report_selector($pluginname);
$form->display();
if (!$summaries) {
    echo $OUTPUT->notification(
        get_string('noresults', 'report_assigngradingoverview'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    foreach ($pagesummaries as $summary) {
        $table->add_summary($summary);
    }
    $table->finish_output();
}
echo $OUTPUT->footer();
