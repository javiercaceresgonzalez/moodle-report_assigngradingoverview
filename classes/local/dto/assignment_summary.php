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
 * One assignment's grading summary.
 *
 * @package    report_assigngradingoverview
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_summary {
    /** @var \stdClass Structural assignment record. */
    public \stdClass $record;
    /** @var int Participant count. */
    public int $participants;
    /** @var int Submitted count. */
    public int $submitted;
    /** @var int Graded count. */
    public int $graded;
    /** @var int Pending count. */
    public int $pending;

    /**
     * Create an assignment summary.
     *
     * @param \stdClass $record Structural assignment record.
     * @param int $participants Number of participants.
     * @param int $submitted Number of submitted attempts.
     * @param int $pending Number of submissions awaiting grading.
     * @return void
     */
    public function __construct(\stdClass $record, int $participants, int $submitted, int $pending) {
        $this->record = $record;
        $this->participants = max(0, $participants);
        $this->submitted = max(0, $submitted);
        $this->pending = max(0, min($pending, $this->submitted));
        $this->graded = max(0, min($this->submitted, $this->submitted - $this->pending));
    }
}
