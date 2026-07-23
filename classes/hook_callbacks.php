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

use core\hook\navigation\primary_extend;
use report_assigngradingoverview\local\navigation\navigation_manager;

/**
 * Hook callbacks for report_assigngradingoverview.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Add the global report to primary navigation when enabled.
     *
     * This setting does not affect direct report access or course navigation.
     *
     * @param primary_extend $hook Primary navigation hook.
     * @return void
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        global $PAGE;

        if (!get_config('report_assigngradingoverview', 'showinnavigation')) {
            return;
        }

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $manager = new navigation_manager();
        if (!$manager->has_potential_access()) {
            return;
        }

        $node = $hook->get_primaryview()->add(
            get_string('pluginname', 'report_assigngradingoverview'),
            new \moodle_url('/report/assigngradingoverview/index.php'),
            \navigation_node::TYPE_ROOTNODE,
            null,
            'report_assigngradingoverview',
            new \pix_icon('i/report', '')
        );

        if (in_array($PAGE->pagetype, ['report-assigngradingoverview-index', 'report-assigngradingoverview-course'], true)) {
            $node->make_active();
        }
    }
}
