<?php
// Geo cutoff windows for thankyou.php. cutoff_hour / sunday_cutoff_hour are in call-center local
// time, Asia/Nicosia (Cyprus). Cyprus observes DST: EEST (GMT+3) summer, EET (GMT+2) winter.
//
// cutoff_hour:        visitors arriving before this hour (Nicosia) on a weekday or Saturday are called
//                     the same day; at/after, next day.
// skip_weekends:      if true, Sat/Sun calls roll forward to Monday. sunday_cutoff_hour is unused in
//                     that mode but kept in the schema so a row can be flipped without re-shaping.
// sunday_cutoff_hour: only consulted when skip_weekends=false. Sunday's own (typically tighter) window.
// visitor_tz:         the country's primary timezone, used to compute the visitor's "today" so the
//                     calendar label reads "Today"/"Tomorrow"/etc. relative to *their* clock — not
//                     the office's. Has no effect on cutoff math.
//
// Edit only this file to change windows. A future admin panel will rewrite this file in place.
return [
    'GB'      => ['cutoff_hour' => 19, 'skip_weekends' => true, 'sunday_cutoff_hour' => 17, 'visitor_tz' => 'Europe/London'],
    'DE'      => ['cutoff_hour' => 19, 'skip_weekends' => true, 'sunday_cutoff_hour' => 17, 'visitor_tz' => 'Europe/Berlin'],
    'FR'      => ['cutoff_hour' => 19, 'skip_weekends' => true, 'sunday_cutoff_hour' => 17, 'visitor_tz' => 'Europe/Paris'],
    'IT'      => ['cutoff_hour' => 19, 'skip_weekends' => true, 'sunday_cutoff_hour' => 17, 'visitor_tz' => 'Europe/Rome'],
    'BR'      => ['cutoff_hour' => 19, 'skip_weekends' => true, 'sunday_cutoff_hour' => 17, 'visitor_tz' => 'America/Sao_Paulo'],
    'ES'      => ['cutoff_hour' => 19, 'skip_weekends' => true, 'sunday_cutoff_hour' => 17, 'visitor_tz' => 'Europe/Madrid'],
    'CH'      => ['cutoff_hour' => 19, 'skip_weekends' => true, 'sunday_cutoff_hour' => 17, 'visitor_tz' => 'Europe/Zurich'],
    'PL'      => ['cutoff_hour' => 19, 'skip_weekends' => true, 'sunday_cutoff_hour' => 17, 'visitor_tz' => 'Europe/Warsaw'],
    'DEFAULT' => ['cutoff_hour' => 19, 'skip_weekends' => true, 'sunday_cutoff_hour' => 17, 'visitor_tz' => 'UTC'],
];
