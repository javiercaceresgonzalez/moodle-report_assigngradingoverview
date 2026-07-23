# Assignment grading overview

`report_assigngradingoverview` is a Moodle report plugin that gives teachers a clear overview of the grading status of Assignment activities (`mod_assign`). It helps graders quickly identify submitted work, pending grading and the assignments that need attention across one course or across all accessible courses.

The plugin is read-only. It links to Moodle's standard Assignment submission and grading pages, but it does not replace the grader, modify submissions or change grades.

## Plugin Information

| Item | Value |
| --- | --- |
| Plugin type | Report plugin |
| Moodle component | `report_assigngradingoverview` |
| Installation path | `report/assigngradingoverview` |
| Compatibility | Moodle 4.5 or later |
| Main scope | Assignment grading overview |
| Activity support | Standard Moodle Assignment activity (`mod_assign`) |
| License | GNU GPL v3 or later |

## Features

- Site-wide overview of assignments the current user can grade.
- Course report integrated into Moodle's standard **Reports** navigation and report selector.
- Live counts for participants, submitted work, graded work and submissions awaiting grading.
- Filters for course, group, assignment name, due date status, visibility and pending work.
- Sortable and paginated table based on Moodle's native table API.
- Direct links to view submissions and open the Assignment grader.
- Optional primary-navigation entry for the global report.
- English and Spanish language packs.

## Requirements

- Moodle 4.5 or later.
- A PHP version supported by the installed Moodle release.
- The standard Moodle Assignment activity (`mod_assign`).

The minimum required Moodle build is `2024100700`.

## Installation

1. Copy the plugin directory to `report/assigngradingoverview`.
2. Visit **Site administration > Notifications** or run:

   ```bash
   php admin/cli/upgrade.php
   ```

3. Review the plugin settings under **Site administration > Plugins > Reports > Assignment grading overview**.

The plugin does not create custom database tables and does not require a scheduled task.

## Access and Permissions

Access is controlled through Moodle capabilities. Users need `report/assigngradingoverview:view` in the course context and `mod/assign:grade` in the assignment context to see an assignment in the report.

By default, the report capability is granted to managers, editing teachers and teachers. Administrators can adjust this through Moodle's standard role and permission settings.

The course report is available from the course Reports area when the user has access and there is at least one gradable assignment. The global report can optionally be shown in the primary navigation.

## Settings

Administrators can configure whether the global report appears in primary navigation, the default number of assignments per page, whether hidden activities can be included, whether the report initially shows only assignments with pending grading and whether hidden courses can be included when the user has access to them.

The primary-navigation entry and hidden-course inclusion are disabled by default.

## Data and Privacy

The report reads information from Moodle when it is displayed. It stores no personal data, no report state and no user preferences. It does not list individual students or expose submission contents.

Counts are calculated using Moodle's Assignment APIs so they follow the standard behaviour for enrolments, groups, submissions and grading state.

## Performance

Assignment metadata is filtered through Moodle's database API, while participant and submission counters are calculated live through `mod_assign`. This preserves Moodle's grading semantics. Sites with very large teaching portfolios should test the report with representative data before enabling broad access.

## Scope

Version 1.0 focuses on Assignment activities only. It does not report on quizzes, forums, workshops, gradebook items or third-party activity modules.

## License

Copyright 2026 Javier Caceres Gonzalez.

This plugin is licensed under the GNU GPL v3 or later. See [LICENSE](LICENSE).
