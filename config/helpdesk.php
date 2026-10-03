<?php

return [

    // Prefix of the human-readable ticket reference (e.g. TKT-000123).
    'reference_prefix' => env('HELPDESK_REFERENCE_PREFIX', 'TKT-'),

    // Automatically assign new unassigned tickets to the least busy agent of
    // the ticket's department.
    'auto_assign' => env('HELPDESK_AUTO_ASSIGN', true),

    // Minutes after the level 1 escalation of a breached ticket before it is
    // escalated again to the administrators (level 2).
    'escalation_level2_after_minutes' => env('HELPDESK_ESCALATION_L2_MINUTES', 60),

    // Untriaged tickets older than this escalate (once) to the triagers.
    'triage_minutes' => env('HELPDESK_TRIAGE_MINUTES', 60),

    // A ticket is "at risk" when its resolution due date is this close.
    'at_risk_minutes' => env('HELPDESK_AT_RISK_MINUTES', 60),

    'attachments' => [
        'max_kb' => env('HELPDESK_ATTACHMENT_MAX_KB', 10240),
        'max_files' => 5,
        'mimes' => 'jpg,jpeg,png,gif,webp,pdf,txt,log,csv,doc,docx,xls,xlsx,ppt,pptx,zip',
    ],

];
