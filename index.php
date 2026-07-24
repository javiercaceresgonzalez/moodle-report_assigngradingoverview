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
 * Global assignment grading overview.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use report_assigngradingoverview\form\filter_form;
use report_assigngradingoverview\local\access\access_manager;
use report_assigngradingoverview\local\data\assignment_repository;
use report_assigngradingoverview\local\data\assignment_grading_overview_service;
use report_assigngradingoverview\local\dto\filter;
use report_assigngradingoverview\output\assignment_table;

require_login();

// Read request values once and normalise them through the filter DTO.
$courseid = optional_param('courseid', 0, PARAM_INT);
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

$filter = new filter($courseid, $groupid, $search, $pendingonly, $visibility, $duedatestatus, $perpage);
$access = new access_manager();
$repository = new assignment_repository($access);

// Course and group options are capability-aware; reject selections outside those permitted sets.
$courses = $repository->get_course_options();
if ($filter->courseid && !array_key_exists($filter->courseid, $courses)) {
    throw new required_capability_exception(
        context_course::instance($filter->courseid),
        'report/assigngradingoverview:view',
        'nopermissions',
        ''
    );
}
$groups = $filter->courseid ? $access->get_course_groups($filter->courseid) : [];
$groupcontext = $filter->courseid ? context_course::instance($filter->courseid) : null;
$groupoptions = array_map(
    static fn($group): string => format_string($group->name, true, ['context' => $groupcontext]),
    $groups
);
if ($filter->groupid && !array_key_exists($filter->groupid, $groups)) {
    throw new moodle_exception('invalidgroup', 'report_assigngradingoverview');
}

$baseparams = $filter->get_url_params($pendingdefault, $configuredperpage);
if ($page > 0) {
    $baseparams['page'] = $page;
}
if ($filtersopen) {
    $baseparams['filtersopen'] = 1;
}
$baseurl = new moodle_url('/report/assigngradingoverview/index.php', $baseparams);
$PAGE->set_url($baseurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('globaltitle', 'report_assigngradingoverview'));
$PAGE->set_heading(get_string('globaltitle', 'report_assigngradingoverview'));

$formaction = new moodle_url('/report/assigngradingoverview/index.php');
$form = new filter_form($formaction, [
    'courses' => $courses,
    'groups' => $groupoptions,
    'selectedcourse' => $filter->courseid,
    'filtersopen' => $filtersopen,
    'reseturl' => new moodle_url('/report/assigngradingoverview/index.php', ['filtersopen' => 1]),
], 'get');
$form->set_data((object)[
    'courseid' => $filter->courseid,
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
$table = new assignment_table('report-assigngradingoverview-global', $baseurl, true);
$table->pagesize($filter->perpage, 0);
$table->setup();
$sortcolumns = $table->get_sort_columns();
$sort = $sortcolumns ? array_key_first($sortcolumns) : 'default';
$direction = $sortcolumns && reset($sortcolumns) === SORT_ASC ? 'asc' : 'desc';

// Calculate and sort live counters before slicing the rows for the current page.
$service = new assignment_grading_overview_service($repository, $access);
$summaries = $service->get_summaries($filter);
$service->sort($summaries, $sort, $direction);
$table->pagesize($filter->perpage, count($summaries));
$pagesummaries = array_slice($summaries, $page * $filter->perpage, $filter->perpage);
$service->load_participants($pagesummaries, $filter);

echo $OUTPUT->header();
$form->display();
if (!$summaries) {
    if (!$courses) {
        $message = get_string('nocourses', 'report_assigngradingoverview');
    } else {
        $message = get_string('noresults', 'report_assigngradingoverview');
    }
    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($pagesummaries as $summary) {
        $table->add_summary($summary);
    }
    $table->finish_output();
}
echo $OUTPUT->footer();
