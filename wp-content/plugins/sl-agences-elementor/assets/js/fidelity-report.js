(function () {
    'use strict';

    var form = document.querySelector('.slfd-report-form--v2');
    if (!form) return;

    var opening = form.querySelector('#slfd_opening');
    var enrollments = form.querySelector('#slfd_enrollments');
    var damaged = form.querySelector('#slfd_damaged');
    var closing = form.querySelector('#slfd_closing');
    var receivedOutput = form.querySelector('#slfd_received');
    var agency = form.querySelector('#slfd_agency, #slfd_agency_readonly');
    var expectedOutput = form.querySelector('#slfd_expected_output');
    var declaredOutput = form.querySelector('#slfd_declared_output');
    var deltaOutput = form.querySelector('#slfd_delta_output');
    var deltaBox = form.querySelector('#slfd_delta_box');
    var statusBox = form.querySelector('#slfd_summary_status');
    var statusText = form.querySelector('#slfd_status_text');
    var summaryAgency = form.querySelector('#slfd_summary_agency');
    var summaryOpening = form.querySelector('#slfd_summary_opening');
    var summaryEnrollments = form.querySelector('#slfd_summary_enrollments');
    var summaryDamaged = form.querySelector('#slfd_summary_damaged');

    if (!opening || !enrollments || !damaged || !closing || !receivedOutput || !expectedOutput || !declaredOutput || !deltaOutput || !deltaBox || !statusBox || !statusText || !summaryOpening || !summaryEnrollments || !summaryDamaged) return;

    function numberValue(field) {
        var value = parseInt(field.value, 10);
        return Number.isFinite(value) && value >= 0 ? value : 0;
    }

    function agencyLabel() {
        if (!agency) return '';
        if (agency.tagName === 'SELECT') {
            return agency.options[agency.selectedIndex] ? agency.options[agency.selectedIndex].text : '';
        }
        return agency.value;
    }

    function updateSummary() {
        var received = parseInt(receivedOutput.getAttribute('data-value'), 10) || 0;
        var start = numberValue(opening);
        var enrolled = numberValue(enrollments);
        var broken = numberValue(damaged);
        var declared = numberValue(closing);
        var expected = Math.max(0, start + received - enrolled - broken);
        var delta = declared - expected;
        var matches = delta === 0;

        summaryOpening.textContent = String(start);
        summaryEnrollments.textContent = String(enrolled);
        summaryDamaged.textContent = String(broken);
        if (summaryAgency) summaryAgency.textContent = agencyLabel();
        expectedOutput.textContent = String(expected);
        declaredOutput.textContent = String(declared);
        deltaOutput.textContent = delta > 0 ? '+' + delta : String(delta);

        deltaBox.classList.remove('slfd-delta--match', 'slfd-delta--warning');
        deltaBox.classList.add(matches ? 'slfd-delta--match' : 'slfd-delta--warning');
        statusBox.classList.toggle('slfd-summary-status--ok', matches);
        statusBox.classList.toggle('slfd-summary-status--warning', !matches);
        statusText.textContent = matches ? 'Écart conforme' : 'Écart à vérifier';
    }

    [opening, enrollments, damaged, closing].forEach(function (field) {
        field.addEventListener('input', updateSummary);
    });
    if (agency) agency.addEventListener('change', updateSummary);
    updateSummary();
}());
