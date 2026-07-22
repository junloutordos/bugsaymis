<?php

namespace App\Services\FacultyLoading;

use Illuminate\Support\Facades\DB;

/**
 * SchedulingConstants
 *
 * Single source of truth for all PSHS-CRC scheduling rules.
 * Every time window, timetable structure, and grade-group mapping
 * used by the scheduling engine lives here as named constants.
 *
 * The Monday / Tue–Fri timetables are additionally overridable at runtime by
 * the Bell Schedule editor (bell_schedule_overrides table) — CID/admin can
 * shift flag ceremony, recess, lunch, consultation, etc. When no override
 * row exists for a timetable, the hardcoded default below is used unchanged,
 * so an empty override table reproduces today's behavior exactly.
 *
 * Time strings use 'HH:MM' (24-hour). Slot arrays use:
 *   ['start' => 'HH:MM', 'end' => 'HH:MM', 'type' => '...', 'label' => '...']
 *
 * Slot types:
 *   FLAG       — Flag Ceremony (Monday 7:30–8:00, all)
 *   HOMEROOM   — Homeroom Class. G7, G8, G10: Monday 8:00–8:50. G9: Monday
 *                8:00–9:40 (extended — absorbs the first teaching period).
 *   ADVISING   — Homeroom Class for G11–G12 (Monday 8:00–8:50) — kept as its
 *                own type for historical reasons, functionally identical to HOMEROOM.
 *   DEAD       — Dead zone / no classes (G7 Monday 8:50–9:40, labeled "Free Time")
 *   RECESS     — Recess break
 *   LUNCH      — Lunch break
 *   CLASS      — Schedulable regular class period
 *   ILP_ONLY   — Overflow period reserved for an Independent Learning Period
 *   CONSULT    — Consultation / Home Bound (end of day)
 *   WELLNESS   — Wellness block (Wednesday ~9:50–10:20, all grades except
 *                full-Wednesday grades)
 *   ACTIVITY   — Activity Proper / ALP. Fixed 15:10–17:00 window on Wednesday,
 *                applied to every grade (see WEDNESDAY_ALP); grades with an
 *                earlier activity cutoff (WEDNESDAY_ACTIVITY_START) are
 *                blocked from that cutoff onward instead.
 *   ILA        — Independent Learning Activities (Friday) — currently unused;
 *                see FRIDAY_ILA_GRADES
 *   SCALE      — SCALE Advising block (Tuesday G11–G12)
 *   ELECTIVE   — Elective block window
 */
class SchedulingConstants
{
    // ── Days ─────────────────────────────────────────────────────────────────

    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    // ── Canonical session lengths ────────────────────────────────────────────
    // Every CLASS period in the timetables below is 50 minutes; a has_ilp
    // subject's Independent Learning Period session is always 30 minutes.
    public const REGULAR_SESSION_MINUTES = 50;
    public const ILP_SESSION_MINUTES = 30;

    // ── Grade Sections ────────────────────────────────────────────────────────

    public const GRADE_SECTIONS = [
        7  => ['Aquamarine', 'Opal', 'Turquoise', 'Sapphire'],
        8  => ['Anthurium', 'Carnation', 'Daffodil', 'Sunflower'],
        9  => ['Calcium', 'Lithium', 'Sodium', 'Barium'],
        10 => ['Electron', 'Graviton', 'Proton', 'Neutron'],
        11 => ['Venus', 'Mars', 'Mercury'],
        12 => ['Del Mundo', 'Orosa', 'Zara'],
    ];

    // ── Grade Groups ──────────────────────────────────────────────────────────

    public const GRADE_GROUPS = [
        'G7G8'   => [7, 8],
        'G9G10'  => [9, 10],
        'G11G12' => [11, 12],
    ];

    // ── Lunch Windows ─────────────────────────────────────────────────────────
    // Key format:  "G{grade}_Monday" | "G{grade}_TueFri"

    public const SECTION_LUNCH = [
        // Monday
        'G7_Monday'  => ['start' => '11:40', 'end' => '12:40'],
        'G8_Monday'  => ['start' => '11:40', 'end' => '12:40'],
        'G9_Monday'  => ['start' => '10:50', 'end' => '11:50'],
        'G10_Monday' => ['start' => '10:50', 'end' => '11:50'],
        'G11_Monday' => ['start' => '12:30', 'end' => '13:30'],
        'G12_Monday' => ['start' => '12:30', 'end' => '13:30'],
        // Tuesday–Friday
        'G7_TueFri'  => ['start' => '10:20', 'end' => '11:20'],
        'G8_TueFri'  => ['start' => '10:20', 'end' => '11:20'],
        'G9_TueFri'  => ['start' => '11:10', 'end' => '12:10'],
        'G10_TueFri' => ['start' => '11:10', 'end' => '12:10'],
        'G11_TueFri' => ['start' => '12:00', 'end' => '13:00'],
        'G12_TueFri' => ['start' => '12:00', 'end' => '13:00'],
    ];

    // ── Recess Windows ────────────────────────────────────────────────────────
    // Each grade/day can have multiple recess slots.

    public const SECTION_RECESS = [
        // Monday
        'G7_Monday'  => [['start' => '09:40', 'end' => '10:00']],
        'G8_Monday'  => [['start' => '09:40', 'end' => '10:00']],
        'G9_Monday'  => [['start' => '09:40', 'end' => '10:00']],
        'G10_Monday' => [
            ['start' => '09:40', 'end' => '10:00'],
        ],
        'G11_Monday' => [['start' => '10:30', 'end' => '10:50']],
        'G12_Monday' => [['start' => '10:30', 'end' => '10:50']],
        // Tuesday–Friday
        'G7_TueFri'  => [
            ['start' => '08:20', 'end' => '08:40'],
        ],
        'G8_TueFri'  => [
            ['start' => '08:20', 'end' => '08:40'],
        ],
        'G9_TueFri'  => [
            ['start' => '09:10', 'end' => '09:30'],
        ],
        'G10_TueFri' => [
            ['start' => '09:10', 'end' => '09:30'],
        ],
        'G11_TueFri' => [['start' => '10:00', 'end' => '10:20']],
        'G12_TueFri' => [['start' => '10:00', 'end' => '10:20']],
    ];

    // ── Monday Timetables ─────────────────────────────────────────────────────

    // Grade 7 only (Grade 8 has its own table with a reclaimed 08:50 period).
    public const MONDAY_G7G8 = [
        ['start' => '07:30', 'end' => '08:00', 'type' => 'FLAG',     'label' => 'Flag Ceremony'],
        ['start' => '08:00', 'end' => '08:50', 'type' => 'HOMEROOM', 'label' => 'Homeroom Class'],
        ['start' => '08:50', 'end' => '09:40', 'type' => 'DEAD',     'label' => 'Free Time'],
        ['start' => '09:40', 'end' => '10:00', 'type' => 'RECESS',   'label' => 'Recess'],
        ['start' => '10:00', 'end' => '10:50', 'type' => 'CLASS',    'label' => 'Period 1'],
        ['start' => '10:50', 'end' => '11:40', 'type' => 'CLASS',    'label' => 'Period 2'],
        ['start' => '11:40', 'end' => '12:40', 'type' => 'LUNCH',    'label' => 'Lunch Break'],
        ['start' => '12:40', 'end' => '13:30', 'type' => 'CLASS',    'label' => 'Period 3'],
        ['start' => '13:30', 'end' => '14:20', 'type' => 'CLASS',    'label' => 'Period 4'],
        ['start' => '14:20', 'end' => '15:10', 'type' => 'CLASS',    'label' => 'Period 5'],
        ['start' => '15:10', 'end' => '16:00', 'type' => 'CLASS',    'label' => 'Period 6'],
        ['start' => '16:00', 'end' => '16:30', 'type' => 'CONSULT',  'label' => 'Consultation / Home Bound'],
    ];

    /**
     * Grade 8 carries 39 weekly teaching sessions. Its Monday 08:50–09:40
     * period is required to keep bell-schedule capacity aligned with that load.
     */
    public const MONDAY_G8 = [
        ['start' => '07:30', 'end' => '08:00', 'type' => 'FLAG',     'label' => 'Flag Ceremony'],
        ['start' => '08:00', 'end' => '08:50', 'type' => 'HOMEROOM', 'label' => 'Homeroom Class'],
        ['start' => '08:50', 'end' => '09:40', 'type' => 'CLASS',    'label' => 'Period 1'],
        ['start' => '09:40', 'end' => '10:00', 'type' => 'RECESS',   'label' => 'Recess'],
        ['start' => '10:00', 'end' => '10:50', 'type' => 'CLASS',    'label' => 'Period 2'],
        ['start' => '10:50', 'end' => '11:40', 'type' => 'CLASS',    'label' => 'Period 3'],
        ['start' => '11:40', 'end' => '12:40', 'type' => 'LUNCH',    'label' => 'Lunch Break'],
        ['start' => '12:40', 'end' => '13:30', 'type' => 'CLASS',    'label' => 'Period 4'],
        ['start' => '13:30', 'end' => '14:20', 'type' => 'CLASS',    'label' => 'Period 5'],
        ['start' => '14:20', 'end' => '15:10', 'type' => 'CLASS',    'label' => 'Period 6'],
        ['start' => '15:10', 'end' => '16:00', 'type' => 'CLASS',    'label' => 'Period 7'],
        ['start' => '16:00', 'end' => '16:30', 'type' => 'CONSULT',  'label' => 'Consultation / Home Bound'],
    ];

    public const MONDAY_G9G10 = [
        ['start' => '07:30', 'end' => '08:00', 'type' => 'FLAG',     'label' => 'Flag Ceremony'],
        ['start' => '08:00', 'end' => '08:50', 'type' => 'HOMEROOM', 'label' => 'Homeroom Class'],
        ['start' => '08:50', 'end' => '09:40', 'type' => 'CLASS',    'label' => 'Period 1'],
        ['start' => '09:40', 'end' => '10:00', 'type' => 'RECESS',   'label' => 'Recess'],
        ['start' => '10:00', 'end' => '10:50', 'type' => 'CLASS',    'label' => 'Period 2'],
        // G9 lunch 10:50–11:50 | G10 lunch 10:50–11:50 (same)
        ['start' => '10:50', 'end' => '11:50', 'type' => 'LUNCH',    'label' => 'Lunch Break'],
        ['start' => '11:50', 'end' => '12:40', 'type' => 'CLASS',    'label' => 'Period 3'],
        ['start' => '12:40', 'end' => '13:30', 'type' => 'CLASS',    'label' => 'Period 4'],
        ['start' => '13:30', 'end' => '14:20', 'type' => 'CLASS',    'label' => 'Period 5'],
        ['start' => '14:20', 'end' => '15:10', 'type' => 'CLASS',    'label' => 'Period 6'],
        ['start' => '15:10', 'end' => '16:00', 'type' => 'CLASS',    'label' => 'Period 7'],
        ['start' => '16:00', 'end' => '17:00', 'type' => 'CONSULT',  'label' => 'Consultation / Home Bound'],
    ];

    /**
     * G9 variant — Homeroom extended to 8:00–9:40 (was Homeroom 8:00–8:50 +
     * Period 1 8:50–9:40 as a real class). This removes that Monday period;
     * remaining periods are renumbered starting from 1.
     */
    public const MONDAY_G9 = [
        ['start' => '07:30', 'end' => '08:00', 'type' => 'FLAG',     'label' => 'Flag Ceremony'],
        ['start' => '08:00', 'end' => '09:40', 'type' => 'HOMEROOM', 'label' => 'Homeroom Class'],
        ['start' => '09:40', 'end' => '10:00', 'type' => 'RECESS',   'label' => 'Recess'],
        ['start' => '10:00', 'end' => '10:50', 'type' => 'CLASS',    'label' => 'Period 1'],
        ['start' => '10:50', 'end' => '11:50', 'type' => 'LUNCH',    'label' => 'Lunch Break'],
        ['start' => '11:50', 'end' => '12:40', 'type' => 'CLASS',    'label' => 'Period 2'],
        ['start' => '12:40', 'end' => '13:30', 'type' => 'CLASS',    'label' => 'Period 3'],
        ['start' => '13:30', 'end' => '14:20', 'type' => 'CLASS',    'label' => 'Period 4'],
        ['start' => '14:20', 'end' => '15:10', 'type' => 'CLASS',    'label' => 'Period 5'],
        ['start' => '15:10', 'end' => '16:20', 'type' => 'CONSULT',  'label' => 'Consultation / Home Bound'],
    ];

    public const MONDAY_G11G12 = [
        ['start' => '07:30', 'end' => '08:00', 'type' => 'FLAG',     'label' => 'Flag Ceremony'],
        ['start' => '08:00', 'end' => '08:50', 'type' => 'ADVISING', 'label' => 'Homeroom Class'],
        ['start' => '08:50', 'end' => '09:40', 'type' => 'CLASS',    'label' => 'Period 1'],
        ['start' => '09:40', 'end' => '10:30', 'type' => 'CLASS',    'label' => 'Period 2'],
        ['start' => '10:30', 'end' => '10:50', 'type' => 'RECESS',   'label' => 'Recess'],
        ['start' => '10:50', 'end' => '11:40', 'type' => 'CLASS',    'label' => 'Period 3'],
        ['start' => '11:40', 'end' => '12:30', 'type' => 'CLASS',    'label' => 'Period 4'],
        ['start' => '12:30', 'end' => '13:30', 'type' => 'LUNCH',    'label' => 'Lunch Break'],
        ['start' => '13:30', 'end' => '14:20', 'type' => 'CLASS',    'label' => 'Period 5 (Elective)'],
        ['start' => '14:20', 'end' => '15:10', 'type' => 'CLASS',    'label' => 'Period 6 (Elective)'],
        ['start' => '15:10', 'end' => '16:00', 'type' => 'CONSULT',  'label' => 'Subject Consultation / Home Bound'],
    ];

    // ── Tue–Fri Timetables (7:30 early shift) ────────────────────────────────

    public const TUEFRI_730_G7G8 = [
        ['start' => '07:30', 'end' => '08:20', 'type' => 'CLASS',   'label' => 'Period 1'],
        ['start' => '08:20', 'end' => '08:40', 'type' => 'RECESS',  'label' => 'Recess'],
        ['start' => '08:40', 'end' => '09:30', 'type' => 'CLASS',   'label' => 'Period 2'],
        ['start' => '09:30', 'end' => '10:20', 'type' => 'CLASS',   'label' => 'Period 3'],
        ['start' => '10:20', 'end' => '11:20', 'type' => 'LUNCH',   'label' => 'Lunch Break'],
        ['start' => '11:20', 'end' => '12:10', 'type' => 'CLASS',   'label' => 'Period 4'],
        ['start' => '12:10', 'end' => '13:00', 'type' => 'CLASS',   'label' => 'Period 5'],
        ['start' => '13:00', 'end' => '13:50', 'type' => 'CLASS',   'label' => 'Period 6'],
        ['start' => '13:50', 'end' => '14:40', 'type' => 'CLASS',   'label' => 'Period 7'],
        ['start' => '14:40', 'end' => '15:30', 'type' => 'CLASS',   'label' => 'Period 8'],
        ['start' => '15:30', 'end' => '16:30', 'type' => 'CONSULT', 'label' => 'Consultation / Home Bound'],
    ];

    public const TUEFRI_730_G9G10 = [
        ['start' => '07:30', 'end' => '08:20', 'type' => 'CLASS',   'label' => 'Period 1'],
        ['start' => '08:20', 'end' => '09:10', 'type' => 'CLASS',   'label' => 'Period 2'],
        ['start' => '09:10', 'end' => '09:30', 'type' => 'RECESS',  'label' => 'Recess'],
        ['start' => '09:30', 'end' => '10:20', 'type' => 'CLASS',   'label' => 'Period 3'],
        ['start' => '10:20', 'end' => '11:10', 'type' => 'CLASS',   'label' => 'Period 4'],
        ['start' => '11:10', 'end' => '12:10', 'type' => 'LUNCH',   'label' => 'Lunch Break'],
        ['start' => '12:10', 'end' => '13:00', 'type' => 'CLASS',   'label' => 'Period 5'],
        ['start' => '13:00', 'end' => '13:50', 'type' => 'CLASS',   'label' => 'Period 6'],
        ['start' => '13:50', 'end' => '14:40', 'type' => 'CLASS',   'label' => 'Period 7'],
        ['start' => '14:40', 'end' => '15:30', 'type' => 'CLASS',   'label' => 'Period 8'],
        ['start' => '15:30', 'end' => '17:00', 'type' => 'CONSULT', 'label' => 'Consultation / Home Bound'],
    ];

    public const TUEFRI_730_G11G12 = [
        ['start' => '07:30', 'end' => '08:20', 'type' => 'CLASS',   'label' => 'Period 1'],
        ['start' => '08:20', 'end' => '09:10', 'type' => 'CLASS',   'label' => 'Period 2'],
        ['start' => '09:10', 'end' => '10:00', 'type' => 'CLASS',   'label' => 'Period 3 (Science Core)'],
        ['start' => '10:00', 'end' => '10:20', 'type' => 'RECESS',  'label' => 'Recess'],
        ['start' => '10:20', 'end' => '11:10', 'type' => 'CLASS',   'label' => 'Period 4 (Elective)'],
        ['start' => '11:10', 'end' => '12:00', 'type' => 'CLASS',   'label' => 'Period 5'],
        ['start' => '12:00', 'end' => '13:00', 'type' => 'LUNCH',   'label' => 'Lunch Break'],
        ['start' => '13:00', 'end' => '13:50', 'type' => 'CLASS',   'label' => 'Period 6'],
        ['start' => '13:50', 'end' => '14:40', 'type' => 'CLASS',   'label' => 'Period 7 (Elective)'],
        ['start' => '14:40', 'end' => '15:30', 'type' => 'CLASS',   'label' => 'Period 8 (Elective)'],
        ['start' => '15:30', 'end' => '17:00', 'type' => 'CONSULT', 'label' => 'Subject Consultation / Home Bound'],
    ];

    // ── Tue–Fri Timetables (8:00 late / compressed shift) ────────────────────

    public const TUEFRI_800_G7G8 = [
        ['start' => '08:00', 'end' => '08:50', 'type' => 'CLASS',   'label' => 'Period 1'],
        ['start' => '08:50', 'end' => '09:40', 'type' => 'CLASS',   'label' => 'Period 2'],
        ['start' => '09:40', 'end' => '10:00', 'type' => 'RECESS',  'label' => 'Recess'],
        ['start' => '10:00', 'end' => '10:50', 'type' => 'CLASS',   'label' => 'Period 3'],
        ['start' => '10:50', 'end' => '11:50', 'type' => 'LUNCH',   'label' => 'Lunch Break'],
        ['start' => '11:50', 'end' => '12:40', 'type' => 'CLASS',   'label' => 'Period 4'],
        ['start' => '12:40', 'end' => '13:30', 'type' => 'CLASS',   'label' => 'Period 5'],
        ['start' => '13:30', 'end' => '14:20', 'type' => 'CLASS',   'label' => 'Period 6'],
        ['start' => '14:20', 'end' => '17:00', 'type' => 'CONSULT', 'label' => 'Consultation / Home Bound'],
    ];

    public const TUEFRI_800_G9G10 = [
        ['start' => '08:00', 'end' => '08:50', 'type' => 'CLASS',   'label' => 'Period 1'],
        ['start' => '08:50', 'end' => '09:40', 'type' => 'CLASS',   'label' => 'Period 2'],
        ['start' => '09:40', 'end' => '10:00', 'type' => 'RECESS',  'label' => 'Recess'],
        ['start' => '10:00', 'end' => '10:50', 'type' => 'CLASS',   'label' => 'Period 3'],
        ['start' => '10:50', 'end' => '11:50', 'type' => 'LUNCH',   'label' => 'Lunch Break'],
        ['start' => '11:50', 'end' => '12:40', 'type' => 'CLASS',   'label' => 'Period 4'],
        ['start' => '12:40', 'end' => '13:30', 'type' => 'CLASS',   'label' => 'Period 5'],
        ['start' => '13:30', 'end' => '17:00', 'type' => 'CONSULT', 'label' => 'Consultation / Home Bound'],
    ];

    // ── Wednesday Special ────────────────────────────────────────────────────

    /** Wellness block (all grades except full-Wednesday grades, Wednesday only) */
    public const WEDNESDAY_WELLNESS = ['start' => '09:50', 'end' => '10:20'];

    /**
     * After this time on Wednesday, no regular teaching classes may be scheduled.
     * Key = grade group string.
     */
    public const WEDNESDAY_ACTIVITY_START = [
        'G7G8'   => '15:00',
        'G9G10'  => '15:00',
        'G11G12' => '12:20',
    ];

    /**
     * Default fixed Activity Learning Program (ALP) block.
     */
    public const WEDNESDAY_ALP = ['start' => '15:10', 'end' => '17:00'];

    public const WEDNESDAY_ALP_BY_GRADE = [
        8 => ['start' => '15:00', 'end' => '17:00'],
    ];

    // ── Friday Special ────────────────────────────────────────────────────────

    /**
     * Grades locked for ILA (no in-person teaching on Fridays). Empty — every
     * grade now has in-person Friday classes.
     */
    public const FRIDAY_ILA_GRADES = [];

    /**
     * Fixed Flag Retreat Ceremony block — every Friday, 16:00 onward, for ALL
     * grades and sections. No class may be scheduled here. No grade's Friday
     * timetable currently runs a CLASS period past 15:30, so this is
     * defensive/explicit — it doesn't remove any existing capacity today.
     */
    public const FRIDAY_FLAG_RETREAT = ['start' => '16:00', 'end' => '17:00'];

    // ── Over-subscribed-grade exceptions ───────────────────────────────────────
    // G8 carries the heaviest weekly load. These last-resort periods preserve
    // capacity while keeping Wednesday ALP at its official 15:00 start.

    public const GRADE8_OVERFLOW_SLOTS = [
        'Monday' => [
            ['start' => '16:00', 'end' => '16:30', 'type' => 'ILP_ONLY', 'label' => 'ILP Overflow'],
        ],
        'Tuesday' => [
            ['start' => '15:30', 'end' => '16:20', 'type' => 'CLASS', 'label' => 'Period 9 (Overflow)'],
            ['start' => '16:20', 'end' => '16:50', 'type' => 'ILP_ONLY', 'label' => 'ILP Overflow'],
        ],
        'Thursday' => [
            ['start' => '15:30', 'end' => '16:20', 'type' => 'CLASS', 'label' => 'Period 9 (Overflow)'],
        ],
    ];

    /**
     * Grades that hold regular classes through the FULL Wednesday — the activity
     * cutoff and the Wednesday wellness block do not apply to these grades (the
     * fixed Wednesday ALP block still does — see WEDNESDAY_ALP).
     */
    public const WEDNESDAY_FULL_GRADES = [8];

    // ── Official Time Shifts ──────────────────────────────────────────────────

    public const SHIFT_EARLY = ['start' => '07:30', 'end' => '16:30'];
    public const SHIFT_LATE  = ['start' => '08:00', 'end' => '17:00'];

    // ── Consultation / Homebound Start Times ─────────────────────────────────

    public const CONSULTATION_START = [
        'G7G8_Monday'   => '16:00',
        'G7G8_TueFri'   => '15:30',
        'G9G10_Monday'  => '16:00',
        'G9G10_TueFri'  => '15:30',
        'G11G12_Monday' => '15:10',
        'G11G12_TueFri' => '15:30',
    ];

    // ── Homeroom Advisers (reference — actual FK is sections.adviser) ─────────

    public const HOMEROOM_ADVISERS = [
        'Aquamarine' => 'Fulay',
        'Opal'       => 'Payao',
        'Turquoise'  => 'Nuñez',
        'Sapphire'   => 'Empuesto',
        'Anthurium'  => 'Salvan',
        'Carnation'  => 'Llido',
        'Daffodil'   => 'Altar',
        'Sunflower'  => 'Francisco',
        'Calcium'    => 'Mahinay',
        'Lithium'    => 'Fernando',
        'Sodium'     => 'Morales',
        'Barium'     => 'Salang',
    ];

    // ── Science Core — VESTIGIAL, belongs to the never-applied "Slot Plan" ────
    // prototype (ScheduleGeneratorService/ScienceCorePlacementService, driven
    // by SlotPlanController — preview() only returns JSON, no apply route
    // exists, confirmed nothing here ever reaches class_schedules). Also
    // encodes a DIFFERENT, simpler model than what's actually needed (one
    // Science Core subject per homeroom section, all sharing one slot) —
    // doesn't match production data, where e.g. Biology 3 Level 2 alone needs
    // 3 parallel classes/faculty cutting across homerooms, not one.
    //
    // The real, live Science Core feature (2026-07-20) is driven by
    // subjects.subject_type='science_core' + ScienceCoreService, placed by
    // DeterministicSchedulingService::placeScienceCoreGroups() — the engine
    // AutoScheduleController actually persists schedules through. Do not
    // extend these constants; they're kept only because SlotPlanController's
    // preview-only route still references them.

    public const SCIENCE_CORE_G11 = [
        'Venus'   => 'Physics 3',
        'Mars'    => 'Biology 3',
        'Mercury' => 'Chemistry 3',
    ];

    public const SCIENCE_CORE_G12 = [
        'Del Mundo' => 'Physics 4',
        'Orosa'     => 'Biology 4',
        'Zara'      => 'Chemistry 4',
    ];

    /** Days on which Science Core blocks may be placed */
    public const SCIENCE_CORE_DAYS = ['Monday', 'Wednesday', 'Thursday', 'Friday'];

    // ── Cognitive-load placement ─────────────────────────────────────────────
    // Shared by ScheduleAnalyticsService (measures PM-heavy placement) and
    // DeterministicSchedulingService (scores against it during placement) so
    // the two never drift apart on what counts as "heavy" or "PM".

    /**
     * Subjects treated as cognitively heavy, matched by uppercase code prefix
     * (MATH1, BIO3L2, CHEM4L2, PHY3, CS1, STEMR2, SCI…). Heuristic — subjects
     * carry no explicit difficulty flag.
     */
    public const HEAVY_SUBJECT_PREFIXES = ['MATH', 'BIO', 'CHEM', 'PHY', 'SCI', 'CS', 'STEM'];

    /** Afternoon boundary (minutes from midnight) for the PM-placement metric. */
    public const PM_START_MIN = 780; // 13:00

    /** Two class intervals closer than this (minutes) are one continuous teaching streak. */
    public const STREAK_JOIN_GAP_MIN = 15;

    /** Streaks beyond this many contiguous minutes are flagged (fatigue guideline). */
    public const MAX_STREAK_MIN = 180;

    // ── Elective Windows ──────────────────────────────────────────────────────

    public const ELECTIVE_WINDOWS_G10 = [
        ['day' => 'Wednesday', 'start' => '13:50', 'end' => '14:40'],
        ['day' => 'Thursday',  'start' => '13:50', 'end' => '14:40'],
    ];

    public const ELECTIVE_WINDOWS_G11G12 = [
        ['day' => 'Tuesday',   'start' => '13:00', 'end' => '14:40'],
        ['day' => 'Wednesday', 'start' => '10:20', 'end' => '11:10'],
        ['day' => 'Thursday',  'start' => '14:10', 'end' => '15:50'],
    ];

    // ── SCALE Advising ────────────────────────────────────────────────────────

    public const SCALE_ADVISING = [
        'day'   => 'Tuesday',
        'start' => '11:10',
        'end'   => '12:00',
    ];

    // ── Homeroom / Advising Block ─────────────────────────────────────────────

    public const HOMEROOM_BLOCK = ['start' => '08:00', 'end' => '08:50'];

    // ── Flag Ceremony ─────────────────────────────────────────────────────────

    public const FLAG_CEREMONY = ['start' => '07:30', 'end' => '08:00'];

    // =========================================================================
    // Static helper methods
    // =========================================================================

    // ── Editable timetable override layer ────────────────────────────────────

    /**
     * Timetables the Bell Schedule editor can override, in display order.
     * Key = the constant name; the label is what the editor shows.
     */
    public const EDITABLE_TIMETABLES = [
        'MONDAY_G7G8'       => 'Grade 7 — Monday',
        'MONDAY_G8'         => 'Grade 8 — Monday',
        'MONDAY_G9'         => 'Grade 9 — Monday',
        'MONDAY_G9G10'      => 'Grade 10 — Monday',
        'MONDAY_G11G12'     => 'Grades 11–12 — Monday',
        'TUEFRI_730_G7G8'   => 'Grades 7–8 — Tue to Fri',
        'TUEFRI_730_G9G10'  => 'Grades 9–10 — Tue to Fri',
        'TUEFRI_730_G11G12' => 'Grades 11–12 — Tue to Fri',
    ];

    /** @var array<string,array<int,array<string,mixed>>>|null request-scoped override cache */
    private static ?array $overrideCache = null;

    /** The hardcoded default rows for an editable timetable key. */
    public static function defaultTimetableRows(string $key): array
    {
        return match ($key) {
            'MONDAY_G7G8'       => self::MONDAY_G7G8,
            'MONDAY_G8'         => self::MONDAY_G8,
            'MONDAY_G9'         => self::MONDAY_G9,
            'MONDAY_G9G10'      => self::MONDAY_G9G10,
            'MONDAY_G11G12'     => self::MONDAY_G11G12,
            'TUEFRI_730_G7G8'   => self::TUEFRI_730_G7G8,
            'TUEFRI_730_G9G10'  => self::TUEFRI_730_G9G10,
            'TUEFRI_730_G11G12' => self::TUEFRI_730_G11G12,
            default             => [],
        };
    }

    /**
     * The effective rows for a timetable key — the saved override if present
     * and non-empty, otherwise the hardcoded default. DB failures fall back to
     * the default (this runs in artisan commands too, never let it throw).
     */
    private static function timetable(string $key): array
    {
        if (self::$overrideCache === null) {
            try {
                self::$overrideCache = DB::table('bell_schedule_overrides')
                    ->pluck('rows', 'timetable_key')
                    ->map(fn ($json) => json_decode((string) $json, true))
                    ->all();
            } catch (\Throwable $e) {
                self::$overrideCache = [];
            }
        }

        $override = self::$overrideCache[$key] ?? null;

        return is_array($override) && $override !== []
            ? $override
            : self::defaultTimetableRows($key);
    }

    /** Public accessor: the effective rows for an editable timetable key. */
    public static function effectiveTimetableRows(string $key): array
    {
        return self::timetable($key);
    }

    // ── Special-window override layer (bell_schedule_settings) ────────────────

    /**
     * Editable special windows/lists that aren't full timetables. Each key maps
     * to its hardcoded default; an admin edit is stored in bell_schedule_settings
     * and read back here with the default as fallback.
     */
    public const SETTING_KEYS = [
        'WEDNESDAY_WELLNESS',
        'WEDNESDAY_ALP',
        'WEDNESDAY_ALP_BY_GRADE',
        'WEDNESDAY_ACTIVITY_START',
        'WEDNESDAY_FULL_GRADES',
        'FRIDAY_FLAG_RETREAT',
        'FRIDAY_ILA_GRADES',
        'GRADE8_OVERFLOW_SLOTS',
    ];

    /** @var array<string,mixed>|null request-scoped settings cache */
    private static ?array $settingCache = null;

    /** Hardcoded default value for a special-window setting key. */
    public static function defaultSetting(string $key): mixed
    {
        return match ($key) {
            'WEDNESDAY_WELLNESS'       => self::WEDNESDAY_WELLNESS,
            'WEDNESDAY_ALP'            => self::WEDNESDAY_ALP,
            'WEDNESDAY_ALP_BY_GRADE'   => self::WEDNESDAY_ALP_BY_GRADE,
            'WEDNESDAY_ACTIVITY_START' => self::WEDNESDAY_ACTIVITY_START,
            'WEDNESDAY_FULL_GRADES'    => self::WEDNESDAY_FULL_GRADES,
            'FRIDAY_FLAG_RETREAT'      => self::FRIDAY_FLAG_RETREAT,
            'FRIDAY_ILA_GRADES'        => self::FRIDAY_ILA_GRADES,
            'GRADE8_OVERFLOW_SLOTS'    => self::GRADE8_OVERFLOW_SLOTS,
            default                    => null,
        };
    }

    /** Effective value for a setting key — saved override or hardcoded default. */
    public static function setting(string $key): mixed
    {
        if (self::$settingCache === null) {
            try {
                self::$settingCache = DB::table('bell_schedule_settings')
                    ->pluck('value', 'setting_key')
                    ->map(fn ($json) => json_decode((string) $json, true))
                    ->all();
            } catch (\Throwable $e) {
                self::$settingCache = [];
            }
        }

        return array_key_exists($key, self::$settingCache) && self::$settingCache[$key] !== null
            ? self::$settingCache[$key]
            : self::defaultSetting($key);
    }

    // Typed accessors — every consumer reads through these so overrides apply
    // uniformly (never read the raw WEDNESDAY_*/FRIDAY_*/GRADE8_* consts directly).

    /** Normalise a stored window to a clean start→end shape (JSON reorders keys). */
    private static function window(mixed $w): array
    {
        return ['start' => $w['start'] ?? null, 'end' => $w['end'] ?? null];
    }

    public static function wednesdayWellness(): array
    {
        return self::window(self::setting('WEDNESDAY_WELLNESS'));
    }

    public static function wednesdayActivityStart(string $group): ?string
    {
        return self::setting('WEDNESDAY_ACTIVITY_START')[$group] ?? null;
    }

    /** @return array<int,int> */
    public static function wednesdayFullGrades(): array
    {
        return array_map('intval', self::setting('WEDNESDAY_FULL_GRADES'));
    }

    public static function fridayFlagRetreat(): array
    {
        return self::window(self::setting('FRIDAY_FLAG_RETREAT'));
    }

    /** @return array<int,int> */
    public static function fridayIlaGrades(): array
    {
        return array_map('intval', self::setting('FRIDAY_ILA_GRADES'));
    }

    /** @return array<int,array<string,mixed>> */
    public static function grade8Overflow(string $day): array
    {
        return array_map(
            fn ($r) => ['start' => $r['start'], 'end' => $r['end'], 'type' => $r['type'], 'label' => $r['label']],
            self::setting('GRADE8_OVERFLOW_SLOTS')[$day] ?? []
        );
    }

    /** Drop the request-scoped override caches (call after saving an edit). */
    public static function flushOverrideCache(): void
    {
        self::$overrideCache = null;
        self::$settingCache  = null;
    }

    /** Map a grade integer to its grade group string. */
    public static function getGradeGroup(int $grade): string
    {
        if ($grade <= 8)  return 'G7G8';
        if ($grade <= 10) return 'G9G10';
        return 'G11G12';
    }

    /** Get the lunch window for a given grade and day. */
    public static function getLunch(int $grade, string $day): array
    {
        $suffix = ($day === 'Monday') ? 'Monday' : 'TueFri';
        return self::SECTION_LUNCH["G{$grade}_{$suffix}"]
            ?? ['start' => '12:00', 'end' => '13:00'];
    }

    /** Get all recess windows for a given grade and day. */
    public static function getRecess(int $grade, string $day): array
    {
        $suffix = ($day === 'Monday') ? 'Monday' : 'TueFri';
        return self::SECTION_RECESS["G{$grade}_{$suffix}"] ?? [];
    }

    /** Grade-specific Wednesday ALP window, falling back to the campus default. */
    public static function getWednesdayAlp(int $grade): array
    {
        return self::window(self::setting('WEDNESDAY_ALP_BY_GRADE')[$grade] ?? self::setting('WEDNESDAY_ALP'));
    }

    /**
     * Get the canonical Monday timetable for a grade.
     * For G10 the G9G10 table is used (contains the extra recess + Period 7
     * entries; callers should filter by grade when needed).
     */
    public static function getMondayTimetable(int $grade): array
    {
        if ($grade === 7)  return self::timetable('MONDAY_G7G8');
        if ($grade === 8)  return self::timetable('MONDAY_G8');
        if ($grade === 9)  return self::timetable('MONDAY_G9');
        if ($grade === 10) return self::timetable('MONDAY_G9G10');
        return self::timetable('MONDAY_G11G12');
    }

    /**
     * Get the canonical Tue–Fri timetable for a grade (early 7:30 shift).
     *
     * Grade 10 shares the G9G10 table with Grade 9, but reserves a fixed
     * elective window on Wednesday/Thursday/Friday only (Tuesday stays a
     * plain, non-elective day, identical to Grade 9) — see
     * applyG10ElectiveWindow(). Every downstream consumer — the generator's
     * slot grid, class/blocked-slot helpers, and the calendar elective band —
     * goes through this method, so they all treat it uniformly.
     */
    public static function getTueFriTimetable(int $grade, string $day): array
    {
        if ($grade <= 8)  return self::timetable('TUEFRI_730_G7G8');
        if ($grade === 9) return self::timetable('TUEFRI_730_G9G10');
        if ($grade === 10) return self::applyG10ElectiveWindow(self::timetable('TUEFRI_730_G9G10'), $day);
        return self::timetable('TUEFRI_730_G11G12');
    }

    /**
     * The EDITABLE_TIMETABLES key backing a grade's literal rows for a day —
     * same grade→key mapping getMondayTimetable()/getTueFriTimetable() use
     * internally, surfaced so callers (the schedule calendar's inline bell-
     * schedule editing) can know which key to PATCH without duplicating the
     * branching logic.
     */
    public static function timetableKeyFor(int $grade, string $day): string
    {
        if ($day === 'Monday') {
            if ($grade === 7)  return 'MONDAY_G7G8';
            if ($grade === 8)  return 'MONDAY_G8';
            if ($grade === 9)  return 'MONDAY_G9';
            if ($grade === 10) return 'MONDAY_G9G10';
            return 'MONDAY_G11G12';
        }

        if ($grade <= 8)  return 'TUEFRI_730_G7G8';
        if ($grade <= 10) return 'TUEFRI_730_G9G10';
        return 'TUEFRI_730_G11G12';
    }

    /**
     * How a blocked band of the given type should be persisted if the calendar
     * lets an admin/CID Chief drag it directly. Types that are literal rows in
     * an EDITABLE_TIMETABLES timetable (FLAG/HOMEROOM/ADVISING/RECESS/LUNCH/
     * CONSULT/DEAD) resolve to that timetable key. WELLNESS and FLAG_RETREAT
     * are synthetic overlays backed by a single window setting. ACTIVITY is a
     * merged display of TWO settings (the Wednesday activity cutoff and the
     * ALP window, see getDisplayBlockedSlots()) that can legitimately differ,
     * so it's flagged 'activity' for the frontend to resolve via an explicit
     * two-field editor rather than a single ambiguous drag.
     *
     * @return array{kind:string,key?:string,shape?:string,grade?:int,group?:string,full_wed?:bool}|null
     */
    public static function bandWriteDescriptor(string $type, int $grade, string $day): ?array
    {
        return match ($type) {
            'WELLNESS'     => ['kind' => 'setting', 'key' => 'WEDNESDAY_WELLNESS', 'shape' => 'window'],
            'FLAG_RETREAT' => ['kind' => 'setting', 'key' => 'FRIDAY_FLAG_RETREAT', 'shape' => 'window'],
            'ACTIVITY'     => [
                'kind'     => 'activity',
                'grade'    => $grade,
                'group'    => self::getGradeGroup($grade),
                'full_wed' => in_array($grade, self::wednesdayFullGrades(), true),
            ],
            'FLAG', 'HOMEROOM', 'ADVISING', 'RECESS', 'LUNCH', 'CONSULT', 'DEAD' =>
                ['kind' => 'timetable', 'key' => self::timetableKeyFor($grade, $day)],
            default => null,
        };
    }

    /**
     * Grade 10's elective window: fixed at 12:10–13:10 on Wednesday, Thursday,
     * and Friday only — Tuesday is left untouched (a plain, non-elective day,
     * identical to Grade 9). This mirrors where G10 elective classes are
     * actually taught in production (confirmed 2026-07-22) — previously this
     * tagged "Period 7" as elective on all four Tue–Fri days, but no G10
     * elective class has ever run there; the real classes sit at this
     * Period-5-area slot on three of the four days.
     *
     * Matches the "Period 5" row by label rather than by its current time —
     * consistent with the rest of this class's resilience against bell-
     * schedule edits — but then overwrites its start/end with the fixed
     * elective window regardless of what that edit set, since (unlike a
     * normal class period) this window is meant to be fixed, the same way
     * G11/G12's elective periods are literal fixed rows rather than tracking
     * another period's time.
     */
    private static function applyG10ElectiveWindow(array $rows, string $day): array
    {
        if (! in_array($day, ['Wednesday', 'Thursday', 'Friday'], true)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            if (($row['type'] ?? '') === 'CLASS' && trim((string) ($row['label'] ?? '')) === 'Period 5') {
                $row['start'] = '12:10';
                $row['end']   = '13:10';
                $row['label'] = 'Period 5 (Elective)';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * The elective window(s) reserved for a grade on a day, as display bands —
     * contiguous elective-labelled periods are merged into a single "Electives"
     * block. Empty for grades whose bell schedule has no elective period.
     *
     * @return array<int,array{start:string,end:string,label:string}>
     */
    public static function getElectiveWindows(int $grade, string $day): array
    {
        $timetable = ($day === 'Monday')
            ? self::getMondayTimetable($grade)
            : self::getTueFriTimetable($grade, $day);

        $periods = array_values(array_filter(
            $timetable,
            static fn ($s) => ($s['type'] ?? '') === 'CLASS'
                && str_contains((string) ($s['label'] ?? ''), 'Elective')
        ));

        if ($periods === []) {
            return [];
        }

        usort($periods, static fn ($a, $b) => $a['start'] <=> $b['start']);

        $windows = [];
        foreach ($periods as $period) {
            $lastIdx = count($windows) - 1;
            if ($lastIdx >= 0 && $windows[$lastIdx]['end'] === $period['start']) {
                $windows[$lastIdx]['end'] = $period['end'];
            } else {
                $windows[] = ['start' => $period['start'], 'end' => $period['end'], 'label' => 'Electives'];
            }
        }

        return $windows;
    }

    /**
     * Return only CLASS-type slots from a timetable for the given grade + day.
     */
    public static function getClassSlots(int $grade, string $day): array
    {
        $timetable = ($day === 'Monday')
            ? self::getMondayTimetable($grade)
            : self::getTueFriTimetable($grade, $day);

        $slots = array_values(
            array_filter($timetable, static fn ($s) => $s['type'] === 'CLASS')
        );

        if ($grade === 8) {
            $slots = array_merge($slots, array_values(array_filter(
                self::grade8Overflow($day),
                static fn ($s) => $s['type'] === 'CLASS',
            )));
        }

        return $slots;
    }

    /** Return regular and ILP-only teaching slots for generator placement. */
    public static function getTeachingSlots(int $grade, string $day): array
    {
        $slots = self::getClassSlots($grade, $day);

        if ($grade === 8) {
            $slots = array_merge($slots, array_values(array_filter(
                self::grade8Overflow($day),
                static fn ($s) => $s['type'] === 'ILP_ONLY',
            )));
        }

        usort($slots, static fn ($a, $b) => $a['start'] <=> $b['start']);

        return array_values($slots);
    }

    /**
     * Return all non-CLASS (blocked) slots for a grade + day, for calendar
     * rendering. These are time windows where no teaching class may be placed.
     *
     * Wednesday additionally gets synthetic Wellness / Activity-Proper-ALP
     * entries injected, and Friday gets a synthetic Flag Retreat entry —
     * those aren't literal timetable rows, they're overlay rules applied
     * during placement (see DeterministicSchedulingService::buildSlotGrid()).
     * Injecting them here keeps the calendar's rendering in lockstep with
     * what the generator actually treats as blocked, instead of drifting out
     * of sync with a separately-maintained schedule.
     *
     * $lunchOverride: optional ['start'=>'HH:MM','end'=>'HH:MM'] that
     * replaces the LUNCH row's time for this call only — used to give one
     * section a different lunch time than the rest of its grade on this one
     * day (Section::LUNCH_OVERRIDE_COLUMNS). The caller is responsible for
     * only passing this when the override actually applies to $day.
     *
     * $recessOverride: same idea, for the RECESS row (Section::RECESS_OVERRIDE_COLUMNS).
     * Every grade+day timetable literal carries exactly one RECESS row, so
     * this replaces it the same way $lunchOverride replaces LUNCH.
     */
    public static function getBlockedSlots(int $grade, string $day, ?array $lunchOverride = null, ?array $recessOverride = null): array
    {
        $timetable = ($day === 'Monday')
            ? self::getMondayTimetable($grade)
            : self::getTueFriTimetable($grade, $day);

        if ($lunchOverride !== null) {
            $timetable = array_map(static function ($row) use ($lunchOverride) {
                if ($row['type'] === 'LUNCH') {
                    $row['start'] = $lunchOverride['start'];
                    $row['end']   = $lunchOverride['end'];
                }
                return $row;
            }, $timetable);
        }

        if ($recessOverride !== null) {
            $timetable = array_map(static function ($row) use ($recessOverride) {
                if ($row['type'] === 'RECESS') {
                    $row['start'] = $recessOverride['start'];
                    $row['end']   = $recessOverride['end'];
                }
                return $row;
            }, $timetable);
        }

        $blocked = array_values(
            array_filter($timetable, static fn ($s) => $s['type'] !== 'CLASS')
        );

        // Grade 8 overflow teaching replaces the overlapping consultation band.
        foreach (self::grade8Overflow($day) as $overflow) {
            if ($grade !== 8) {
                break;
            }
            $blocked = array_values(array_filter($blocked, static fn ($b) =>
                ! ($b['type'] === 'CONSULT' && self::timesOverlap($b['start'], $b['end'], $overflow['start'], $overflow['end']))
            ));
        }

        if ($day === 'Wednesday') {
            $fullWed = in_array($grade, self::wednesdayFullGrades(), true);
            $alp     = self::getWednesdayAlp($grade);

            // Full-Wednesday grades are not excluded from the Wellness window
            // during placement, so it isn't shown as blocked for them either.
            if (! $fullWed) {
                $blocked[] = array_merge(self::wednesdayWellness(), [
                    'type'  => 'WELLNESS',
                    'label' => 'Wellness Break',
                ]);
            }

            $group        = self::getGradeGroup($grade);
            $cutoffStart  = $fullWed
                ? $alp['start']
                : (self::wednesdayActivityStart($group) ?? $alp['start']);
            $activityStart = min($cutoffStart, $alp['start']);

            $activityBlock = [
                'start' => $activityStart,
                'end'   => $alp['end'],
                'type'  => 'ACTIVITY',
                'label' => 'Activity Proper / ALP',
            ];

            // The timetable's own Consultation/Home Bound slot sits inside this
            // window for every grade group — drop it so the calendar doesn't
            // render two overlapping bands for the same time range.
            $blocked = array_values(array_filter($blocked, static fn ($b) =>
                ! ($b['type'] === 'CONSULT' && self::timesOverlap($b['start'], $b['end'], $activityBlock['start'], $activityBlock['end']))
            ));
            $blocked[] = $activityBlock;
        }

        if ($day === 'Friday') {
            $flagBlock = array_merge(self::fridayFlagRetreat(), [
                'type'  => 'FLAG_RETREAT',
                'label' => 'Flag Retreat Ceremony',
            ]);

            // Same overlap as Wednesday's ALP — Flag Retreat supersedes the
            // Consultation/Home Bound slot instead of stacking on top of it.
            $blocked = array_values(array_filter($blocked, static fn ($b) =>
                ! ($b['type'] === 'CONSULT' && self::timesOverlap($b['start'], $b['end'], $flagBlock['start'], $flagBlock['end']))
            ));
            $blocked[] = $flagBlock;
        }

        usort($blocked, static fn ($a, $b) => $a['start'] <=> $b['start']);

        return array_values($blocked);
    }

    /**
     * Blocked slots for CALENDAR/PRINT display only. Unlike getBlockedSlots()
     * (the placement/capacity source consumed by overlapsBlocked()), the
     * Consultation/Home Bound band is TRIMMED against the Wednesday ALP and
     * Friday Flag Retreat windows instead of dropped wholesale, and Grade 8's
     * overflow teaching windows do not suppress it — a scheduled overflow
     * class simply renders on top of the band, and when the overflow period
     * is unused that time genuinely is consultation. Never feed this into
     * capacity/placement math: restoring the G8 consult band there would
     * filter the overflow periods out of the schedulable grid.
     */
    public static function getDisplayBlockedSlots(int $grade, string $day, ?array $lunchOverride = null, ?array $recessOverride = null): array
    {
        $timetable = ($day === 'Monday')
            ? self::getMondayTimetable($grade)
            : self::getTueFriTimetable($grade, $day);

        if ($lunchOverride !== null) {
            $timetable = array_map(static function ($row) use ($lunchOverride) {
                if ($row['type'] === 'LUNCH') {
                    $row['start'] = $lunchOverride['start'];
                    $row['end']   = $lunchOverride['end'];
                }
                return $row;
            }, $timetable);
        }

        if ($recessOverride !== null) {
            $timetable = array_map(static function ($row) use ($recessOverride) {
                if ($row['type'] === 'RECESS') {
                    $row['start'] = $recessOverride['start'];
                    $row['end']   = $recessOverride['end'];
                }
                return $row;
            }, $timetable);
        }

        $blocked = array_values(
            array_filter($timetable, static fn ($s) => $s['type'] !== 'CLASS')
        );

        if ($day === 'Wednesday') {
            $fullWed = in_array($grade, self::wednesdayFullGrades(), true);
            $alp     = self::getWednesdayAlp($grade);

            if (! $fullWed) {
                $wellness = self::wednesdayWellness();
                // G11/G12's Wednesday recess sits inside the Wellness window —
                // trim it so the two bands don't render on top of each other.
                $blocked   = self::trimAround($blocked, $wellness['start'], $wellness['end'], ['RECESS']);
                $blocked[] = array_merge($wellness, [
                    'type'  => 'WELLNESS',
                    'label' => 'Wellness Break',
                ]);
            }

            $group       = self::getGradeGroup($grade);
            $cutoffStart = $fullWed
                ? $alp['start']
                : (self::wednesdayActivityStart($group) ?? $alp['start']);

            $activityBlock = [
                'start' => min($cutoffStart, $alp['start']),
                'end'   => $alp['end'],
                'type'  => 'ACTIVITY',
                'label' => 'Activity Proper / ALP',
            ];

            $blocked   = self::trimAround($blocked, $activityBlock['start'], $activityBlock['end'], ['CONSULT']);
            $blocked[] = $activityBlock;
        }

        if ($day === 'Friday') {
            $flagBlock = array_merge(self::fridayFlagRetreat(), [
                'type'  => 'FLAG_RETREAT',
                'label' => 'Flag Retreat Ceremony',
            ]);

            $blocked   = self::trimAround($blocked, $flagBlock['start'], $flagBlock['end'], ['CONSULT']);
            $blocked[] = $flagBlock;
        }

        usort($blocked, static fn ($a, $b) => $a['start'] <=> $b['start']);

        return array_values($blocked);
    }

    /**
     * Trim entries of the given types against a window: the overlapping
     * portion is cut away and any non-empty remainder kept (a fully-covered
     * band disappears entirely).
     */
    private static function trimAround(array $blocked, string $start, string $end, array $types): array
    {
        $result = [];

        foreach ($blocked as $b) {
            if (! in_array($b['type'], $types, true) || ! self::timesOverlap($b['start'], $b['end'], $start, $end)) {
                $result[] = $b;
                continue;
            }
            if ($b['start'] < $start) {
                $result[] = array_merge($b, ['end' => $start]);
            }
            if ($b['end'] > $end) {
                $result[] = array_merge($b, ['start' => $end]);
            }
        }

        return $result;
    }

    /**
     * The actual first-class-start / last-class-end for a grade+day, after
     * applying Wednesday's cutoff/wellness/ALP exclusions and Friday's Flag
     * Retreat exclusion (via getBlockedSlots), and the Friday ILA day-skip.
     * Null if the grade has no classes that day.
     *
     * @return array{start:string,end:string}|null
     */
    public static function getEffectiveClassWindow(int $grade, string $day): ?array
    {
        $classSlots = self::getSchedulableTeachingSlots($grade, $day);

        if (empty($classSlots)) {
            return null;
        }

        return [
            'start' => min(array_column($classSlots, 'start')),
            'end'   => max(array_column($classSlots, 'end')),
        ];
    }

    /**
     * Canonical class periods after applying every grade/day fixed block.
     * This is the shared source for calendar capacity and auto-placement.
     */
    public static function getSchedulableClassSlots(int $grade, string $day): array
    {
        if ($day === 'Friday' && in_array($grade, self::fridayIlaGrades(), true)) {
            return [];
        }

        return array_values(array_filter(
            self::getClassSlots($grade, $day),
            fn ($slot) => ! self::overlapsBlocked($grade, $day, $slot['start'], $slot['end']),
        ));
    }

    /** Canonical regular and ILP-only periods after applying fixed blocks. */
    public static function getSchedulableTeachingSlots(int $grade, string $day, ?array $lunchOverride = null, ?array $recessOverride = null): array
    {
        if ($day === 'Friday' && in_array($grade, self::fridayIlaGrades(), true)) {
            return [];
        }

        return array_values(array_filter(
            self::getTeachingSlots($grade, $day),
            fn ($slot) => ! self::overlapsBlocked($grade, $day, $slot['start'], $slot['end'], $lunchOverride, $recessOverride),
        ));
    }

    /**
     * True if a proposed time window overlaps any blocked slot for the grade/day.
     */
    public static function overlapsBlocked(int $grade, string $day, string $start, string $end, ?array $lunchOverride = null, ?array $recessOverride = null): bool
    {
        foreach (self::getBlockedSlots($grade, $day, $lunchOverride, $recessOverride) as $blocked) {
            if ($start < $blocked['end'] && $end > $blocked['start']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert 'HH:MM' string to total minutes since midnight.
     */
    public static function toMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int) $h * 60 + (int) $m;
    }

    /**
     * Convert minutes since midnight back to 'HH:MM' string.
     */
    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * True if two time windows [aStart, aEnd) and [bStart, bEnd) overlap.
     * All inputs are 'HH:MM' strings.
     */
    public static function timesOverlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        return $aStart < $bEnd && $aEnd > $bStart;
    }
}
