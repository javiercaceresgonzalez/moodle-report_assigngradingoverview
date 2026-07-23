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

namespace report_assigngradingoverview\local\dto;

/**
 * Validated report filters.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter {
    /** @var int Course ID, or zero for all courses. */
    public int $courseid;
    /** @var int Group ID, or zero for all accessible groups. */
    public int $groupid;
    /** @var string Assignment name search. */
    public string $search;
    /** @var bool Whether only pending assignments are shown. */
    public bool $pendingonly;
    /** @var string Visibility filter. */
    public string $visibility;
    /** @var string Due date filter. */
    public string $duedatestatus;
    /** @var int Rows per page. */
    public int $perpage;

    /**
     * Create validated report filters.
     *
     * @param int $courseid Course ID, or zero for all courses.
     * @param int $groupid Group ID, or zero for all accessible groups.
     * @param string $search Assignment name search.
     * @param bool $pendingonly Whether to show only assignments awaiting grading.
     * @param string $visibility Visibility filter.
     * @param string $duedatestatus Due-date filter.
     * @param int $perpage Rows per page.
     * @return void
     */
    public function __construct(
        int $courseid = 0,
        int $groupid = 0,
        string $search = '',
        bool $pendingonly = false,
        string $visibility = 'all',
        string $duedatestatus = 'all',
        int $perpage = 25
    ) {
        $this->courseid = max(0, $courseid);
        $this->groupid = max(0, $groupid);
        $this->search = trim($search);
        $this->pendingonly = $pendingonly;
        $this->visibility = in_array($visibility, ['all', 'visible', 'hidden'], true) ? $visibility : 'all';
        $this->duedatestatus = in_array($duedatestatus, ['all', 'overdue', 'notoverdue', 'none'], true)
            ? $duedatestatus : 'all';
        $this->perpage = in_array($perpage, [25, 50, 100], true) ? $perpage : 25;
    }

    /**
     * Return only filter values that differ from their configured defaults.
     *
     * @param bool $defaultpendingonly Default pending-only state.
     * @param int $defaultperpage Default rows per page.
     * @return array<string, int|string> Canonical URL parameters.
     */
    public function get_url_params(bool $defaultpendingonly, int $defaultperpage): array {
        $params = [];
        if ($this->courseid) {
            $params['courseid'] = $this->courseid;
        }
        if ($this->groupid) {
            $params['groupid'] = $this->groupid;
        }
        if ($this->search !== '') {
            $params['search'] = $this->search;
        }
        if ($this->pendingonly !== $defaultpendingonly) {
            $params['pendingonly'] = (int)$this->pendingonly;
            $params['filterset'] = 1;
        }
        if ($this->visibility !== 'all') {
            $params['visibility'] = $this->visibility;
        }
        if ($this->duedatestatus !== 'all') {
            $params['duedatestatus'] = $this->duedatestatus;
        }
        if ($this->perpage !== $defaultperpage) {
            $params['perpage'] = $this->perpage;
        }
        return $params;
    }
}
