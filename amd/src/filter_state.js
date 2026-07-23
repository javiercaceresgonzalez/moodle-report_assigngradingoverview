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
 * Preserve the filter section state in report URLs.
 *
 * @module     report_assigngradingoverview/filter_state
 * @copyright  2026 Javier Caceres Gonzalez <javiercaceresgonzalez@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const stateParameter = 'filtersopen';
const headerSelector = '#id_filtersheader .fheader';
const stateInputSelector = 'input[name="filtersopen"]';
const canonicalParameterOrder = [
    'id',
    'courseid',
    'groupid',
    'search',
    'pendingonly',
    'visibility',
    'duedatestatus',
    'perpage',
    'filterset',
    'sort',
    'dir',
    'page',
    stateParameter,
];

/**
 * Keep known report parameters in a stable order when JavaScript updates links.
 *
 *  {URL} url Report URL.
 */
const canonicaliseUrl = url => {
    const orderedParams = new URLSearchParams();

    canonicalParameterOrder.forEach(name => {
        if (!url.searchParams.has(name)) {
            return;
        }
        url.searchParams.getAll(name).forEach(value => orderedParams.append(name, value));
        url.searchParams.delete(name);
    });
    url.searchParams.forEach((value, name) => orderedParams.append(name, value));
    url.search = orderedParams.toString();
};

/**
 * Determine whether the filter section is expanded.
 *
 * @param {HTMLElement} header Filter section toggle.
 * @returns {boolean}
 */
const isExpanded = header => header.getAttribute('aria-expanded') === 'true';

/**
 * Update the current URL and form field without reloading the page.
 *
 * @param {HTMLElement} header Filter section toggle.
 */
const synchroniseState = header => {
    const expanded = isExpanded(header);
    const url = new URL(window.location.href);
    const input = document.querySelector(stateInputSelector);

    if (expanded) {
        url.searchParams.set(stateParameter, '1');
    } else {
        url.searchParams.delete(stateParameter);
    }
    if (input) {
        input.value = expanded ? '1' : '0';
    }
    canonicaliseUrl(url);
    window.history.replaceState(window.history.state, '', url.toString());
};

/**
 * Carry the current state into sorting and pagination links.
 *
 * @param {MouseEvent} event Click event.
 * @param {HTMLElement} header Filter section toggle.
 */
const prepareReportLink = (event, header) => {
    const link = event.target.closest('.generaltable a, nav.pagination a');
    if (!link) {
        return;
    }

    const url = new URL(link.href, window.location.href);
    if (url.pathname !== window.location.pathname) {
        return;
    }
    if (isExpanded(header)) {
        url.searchParams.set(stateParameter, '1');
    } else {
        url.searchParams.delete(stateParameter);
    }
    canonicaliseUrl(url);
    link.href = url.toString();
};

/**
 * Initialise filter state handling.
 */
export const init = () => {
    const header = document.querySelector(headerSelector);
    if (!header) {
        return;
    }

    header.addEventListener('click', () => window.setTimeout(() => synchroniseState(header)));
    document.addEventListener('click', event => prepareReportLink(event, header), true);
};
