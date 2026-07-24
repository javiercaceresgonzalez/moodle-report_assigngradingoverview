# Changelog

## 1.1.0 - Unreleased

### Improved

- Improved global report performance with aggregate submitted and pending counters.
- Loaded participant counts only for the rows displayed on the current page.
- Reused assignment candidate discovery within the same request.
- Cached the primary navigation access check per session.

### Added

- Added integration tests to compare aggregate counters against Moodle Assignment API results.

## 1.0.0 - 2026-07-22

### Added

- Initial public release as a Moodle report plugin for Assignment grading overview.
- Global and course-level reports for assignments the user can grade.
- Live grading status counts for participants, submitted work, graded work and submissions awaiting grading.
- Filters, sorting, pagination and direct links to standard Moodle grading screens.
- Optional primary-navigation entry and administrator settings for default behaviour.
