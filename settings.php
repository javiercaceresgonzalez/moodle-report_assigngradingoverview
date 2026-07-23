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
 * Administration settings for report_assigngradingoverview.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_configcheckbox(
        'report_assigngradingoverview/showinnavigation',
        get_string('showinnavigation', 'report_assigngradingoverview'),
        get_string('showinnavigation_desc', 'report_assigngradingoverview'),
        0
    ));
    $settings->add(new admin_setting_configselect(
        'report_assigngradingoverview/defaultperpage',
        get_string('defaultperpage', 'report_assigngradingoverview'),
        get_string('defaultperpage_desc', 'report_assigngradingoverview'),
        25,
        [25 => 25, 50 => 50, 100 => 100]
    ));
    $settings->add(new admin_setting_configcheckbox(
        'report_assigngradingoverview/showhidden',
        get_string('showhidden', 'report_assigngradingoverview'),
        get_string('showhidden_desc', 'report_assigngradingoverview'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'report_assigngradingoverview/defaultpendingonly',
        get_string('defaultpendingonly', 'report_assigngradingoverview'),
        get_string('defaultpendingonly_desc', 'report_assigngradingoverview'),
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'report_assigngradingoverview/includehiddencourses',
        get_string('includehiddencourses', 'report_assigngradingoverview'),
        get_string('includehiddencourses_desc', 'report_assigngradingoverview'),
        0
    ));
}
