<?php

namespace App\Services\FacultyLoading;

/**
 * SchedulingConstants
 *
 * Single source of truth for all PSHS-CRC scheduling rules.
 * Every time window, timetable structure, and grade-group mapping
 * used by the scheduling engine lives here as named constants.
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
 *   CLASS      — Schedulable class period
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
            ['start' => '15:10', 'end' => '15:30'],
        ],
        'G11_Monday' => [['start' => '10:30', 'end' => '10:50']],
        'G12_Monday' => [['start' => '10:30', 'end' => '10:50']],
        // Tuesday–Friday
        'G7_TueFri'  => [
            ['start' => '08:20', 'end' => '08:40'],
            ['start' => '13:00', 'end' => '13:20'],
        ],
        'G8_TueFri'  => [
            ['start' => '08:20', 'end' => '08:40'],
            ['start' => '13:00', 'end' => '13:20'],
        ],
        'G9_TueFri'  => [
            ['start' => '09:10', 'end' => '09:30'],
            ['start' => '13:50', 'end' => '14:10'],
        ],
        'G10_TueFri' => [
            ['start' => '09:10', 'end' => '09:30'],
            ['start' => '13:50', 'end' => '14:10'],
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
        // G10 has a second recess 15:10–15:30 and Period 7 (handled separately for G10 only)
        ['start' => '15:10', 'end' => '15:30', 'type' => 'RECESS',   'label' => 'Recess'],      // G10 only
        ['start' => '15:30', 'end' => '16:20', 'type' => 'CLASS',    'label' => 'Period 7'],    // G10 only
        ['start' => '16:20', 'end' => '17:00', 'type' => 'CONSULT',  'label' => 'Consultation / Home Bound'],
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
        ['start' => '13:00', 'end' => '13:20', 'type' => 'RECESS',  'label' => 'Recess'],
        ['start' => '13:20', 'end' => '14:10', 'type' => 'CLASS',   'label' => 'Period 6'],
        ['start' => '14:10', 'end' => '15:00', 'type' => 'CLASS',   'label' => 'Period 7'],
        ['start' => '15:00', 'end' => '15:50', 'type' => 'CLASS',   'label' => 'Period 8'],
        ['start' => '15:50', 'end' => '16:30', 'type' => 'CONSULT', 'label' => 'Consultation / Home Bound'],
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
        ['start' => '13:50', 'end' => '14:10', 'type' => 'RECESS',  'label' => 'Recess'],
        ['start' => '14:10', 'end' => '15:00', 'type' => 'CLASS',   'label' => 'Period 7'],
        ['start' => '15:00', 'end' => '15:50', 'type' => 'CLASS',   'label' => 'Period 8'],
        ['start' => '15:50', 'end' => '17:00', 'type' => 'CONSULT', 'label' => 'Consultation / Home Bound'],
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
     * Default fixed Activity Learning Program (ALP) block. Grade 8 starts at
     * 15:50 so its 39-session curriculum retains the final Wednesday period.
     */
    public const WEDNESDAY_ALP = ['start' => '15:10', 'end' => '17:00'];

    public const WEDNESDAY_ALP_BY_GRADE = [
        8 => ['start' => '15:50', 'end' => '17:00'],
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
     * timetable currently runs a CLASS period past 15:50, so this is purely
     * defensive/explicit — it doesn't remove any existing capacity today.
     */
    public const FRIDAY_FLAG_RETREAT = ['start' => '16:00', 'end' => '17:00'];

    // ── Over-subscribed-grade exceptions ───────────────────────────────────────
    // G8 carries the heaviest weekly load (39h). Its load only fits when the
    // full Wednesday is opened for teaching.

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
        'G7G8_TueFri'   => '15:50',
        'G9G10_Monday'  => '16:20',
        'G9G10_TueFri'  => '15:50',
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

    // ── Science Core (G11/G12 parallel blocks) ────────────────────────────────

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

    // ── Elective Windows ──────────────────────────────────────────────────────

    public const ELECTIVE_WINDOWS_G10 = [
        ['day' => 'Wednesday', 'start' => '14:20', 'end' => '15:10'],
        ['day' => 'Thursday',  'start' => '14:10', 'end' => '15:00'],
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
        return self::WEDNESDAY_ALP_BY_GRADE[$grade] ?? self::WEDNESDAY_ALP;
    }

    /**
     * Get the canonical Monday timetable for a grade.
     * For G10 the G9G10 table is used (contains the extra recess + Period 7
     * entries; callers should filter by grade when needed).
     */
    public static function getMondayTimetable(int $grade): array
    {
        if ($grade === 7)  return self::MONDAY_G7G8;
        if ($grade === 8)  return self::MONDAY_G8;
        if ($grade === 9)  return self::MONDAY_G9;
        if ($grade === 10) return self::MONDAY_G9G10;
        return self::MONDAY_G11G12;
    }

    /**
     * Get the canonical Tue–Fri timetable for a grade (early 7:30 shift).
     */
    public static function getTueFriTimetable(int $grade): array
    {
        if ($grade <= 8)  return self::TUEFRI_730_G7G8;
        if ($grade <= 10) return self::TUEFRI_730_G9G10;
        return self::TUEFRI_730_G11G12;
    }

    /**
     * Return only CLASS-type slots from a timetable for the given grade + day.
     */
    public static function getClassSlots(int $grade, string $day): array
    {
        $timetable = ($day === 'Monday')
            ? self::getMondayTimetable($grade)
            : self::getTueFriTimetable($grade);

        return array_values(
            array_filter($timetable, static fn ($s) => $s['type'] === 'CLASS')
        );
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
     */
    public static function getBlockedSlots(int $grade, string $day): array
    {
        $timetable = ($day === 'Monday')
            ? self::getMondayTimetable($grade)
            : self::getTueFriTimetable($grade);

        $blocked = array_values(
            array_filter($timetable, static fn ($s) => $s['type'] !== 'CLASS')
        );

        if ($day === 'Wednesday') {
            $fullWed = in_array($grade, self::WEDNESDAY_FULL_GRADES, true);
            $alp     = self::getWednesdayAlp($grade);

            // Full-Wednesday grades are not excluded from the Wellness window
            // during placement, so it isn't shown as blocked for them either.
            if (! $fullWed) {
                $blocked[] = array_merge(self::WEDNESDAY_WELLNESS, [
                    'type'  => 'WELLNESS',
                    'label' => 'Wellness Break',
                ]);
            }

            $group        = self::getGradeGroup($grade);
            $cutoffStart  = $fullWed
                ? $alp['start']
                : (self::WEDNESDAY_ACTIVITY_START[$group] ?? $alp['start']);
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
            $flagBlock = array_merge(self::FRIDAY_FLAG_RETREAT, [
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
     * The actual first-class-start / last-class-end for a grade+day, after
     * applying Wednesday's cutoff/wellness/ALP exclusions and Friday's Flag
     * Retreat exclusion (via getBlockedSlots), and the Friday ILA day-skip.
     * Null if the grade has no classes that day.
     *
     * @return array{start:string,end:string}|null
     */
    public static function getEffectiveClassWindow(int $grade, string $day): ?array
    {
        $classSlots = self::getSchedulableClassSlots($grade, $day);

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
        if ($day === 'Friday' && in_array($grade, self::FRIDAY_ILA_GRADES, true)) {
            return [];
        }

        return array_values(array_filter(
            self::getClassSlots($grade, $day),
            fn ($slot) => ! self::overlapsBlocked($grade, $day, $slot['start'], $slot['end']),
        ));
    }

    /**
     * True if a proposed time window overlaps any blocked slot for the grade/day.
     */
    public static function overlapsBlocked(int $grade, string $day, string $start, string $end): bool
    {
        foreach (self::getBlockedSlots($grade, $day) as $blocked) {
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
