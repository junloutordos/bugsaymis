<?php

return [
    // Wi-Fi SSIDs that only exist inside the campus building - connection to
    // any of these is treated as a definitive on-campus signal regardless of
    // which ISP backhauls it.
    'campus_ssids' => array_filter(array_map('trim', explode(',', env('ICT_AGENT_CAMPUS_SSIDS', '')))),

    // Known static public IPs for the campus's ISPs. Not all campus ISPs have
    // a static IP (some are CGNAT'd), so a non-match here is inconclusive,
    // never treated as proof of being off-campus.
    'campus_ips' => array_filter(array_map('trim', explode(',', env('ICT_AGENT_CAMPUS_IPS', '')))),
];
