<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    @page {
        margin: 0;
    }

    body {
        font-family: montserrat, sans-serif;
        width: 297mm;
        height: 210mm;
        position: relative;
    }

    /* ── Background ─────────────────────────────────────────────────────── */
    .bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 297mm;
        height: 210mm;
        z-index: -1;
    }

    .bg img {
        width: 297mm;
        height: 210mm;
        display: block;
    }

    /* ── Name ────────────────────────────────────────────────────────────── */
    .name-block {
        position: fixed;
        left: 10mm;
        top: 97mm;
        width: 277mm;
        text-align: center;
        font-size: 24pt;
        letter-spacing: 1pt;
        color: #1a1a2e;
    }

    /* ── Citation body ───────────────────────────────────────────────────── */
    .citation-block {
        position: fixed;
        left: 30mm;
        top: 115mm;
        width: 237mm;
        font-size: 12pt;
        line-height: 1.6;
        color: #1a1a2e;
        text-align: justify;
    }

    /* ── Issuance line ───────────────────────────────────────────────────── */
    .issuance-block {
        position: fixed;
        left: 30mm;
        top: 154mm;
        width: 180mm;
        font-size: 12pt;
        line-height: 1.6;
        color: #1a1a2e;
    }

    /* ── QR Code ─────────────────────────────────────────────────────────── */
    .qr-block {
        position: fixed;
        left: 242mm;
        top: 158mm;
        width: 38mm;
        height: 38mm;
    }

    .qr-block img {
        width: 38mm;
        height: 38mm;
    }

    /* ── Footer note ─────────────────────────────────────────────────────── */
    .footer-note {
        position: fixed;
        left: 0;
        bottom: 4mm;
        width: 297mm;
        text-align: center;
        font-size: 7pt;
        color: #64748b;
        letter-spacing: 0.3pt;
    }

</style>
</head>
<body>

    <!-- Background -->
    <div class="bg"><img src="{{ $bgPath }}" alt="" /></div>

    <!-- Name -->
    <div class="name-block" style="font-family: montserrateb, sans-serif;">{{ strtoupper($name) }}</div>

    <!-- Citation -->
    <div class="citation-block">
        For actively participating in the
        <span style="font-family: montserrateb, sans-serif; font-style: italic;">{{ strtoupper($activity->title) }}</span>,
        held from <span style="font-family: montserrateb, sans-serif;">{{ strtoupper($dateStart) }}</span>
        to <span style="font-family: montserrateb, sans-serif;">{{ strtoupper($dateEnd) }}</span>
        at the <span style="font-family: montserrateb, sans-serif;">{{ strtoupper($activity->venue ?? 'PSHS-CRC CAMPUS') }}</span>,
        and for having completed a total of
        <span style="font-family: montserrateb, sans-serif;">{{ $hoursAttended }}</span> hour(s) of engagement in the activity.
    </div>

    <!-- Issuance -->
    <div class="issuance-block">
        Given this <span style="font-family: montserrateb, sans-serif;">{{ $dateOfIssuance }}</span>
        at <span style="font-family: montserrateb, sans-serif;">{{ $activity->venue ?? 'PSHS-CRC Campus' }}</span>.
    </div>

    <!-- QR Code -->
    <div class="qr-block">
        <img src="{{ $qrDataUrl }}" alt="QR" />
    </div>

    <!-- Footer note -->
    <div class="footer-note">
        This is a system generated certificate. Please scan the QR Code to verify.
    </div>

</body>
</html>
