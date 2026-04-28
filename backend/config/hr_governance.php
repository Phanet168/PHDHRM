<?php

use Modules\HumanResource\Entities\UserAssignment;

return [
    /*
    |--------------------------------------------------------------------------
    | Module-owned governance policy catalog
    |--------------------------------------------------------------------------
    |
    | The central HR governance core keeps users, employees, departments,
    | positions, user assignments, scope, and the workflow engine. Module
    | business rights live here as module policy definitions and are bound to
    | users through responsibility templates on user_assignments.
    |
    | Positions are intentionally not listed here. They can describe placement
    | or route workflow actors, but they must not grant module permissions.
    */
    'modules' => [
        'correspondence' => [
            'label' => 'Correspondence Management',
            'actions' => [
                'create_incoming' => 'Create incoming letter',
                'create_outgoing' => 'Create outgoing letter',
                'delegate' => 'Delegate',
                'office_comment' => 'Office comment',
                'deputy_review' => 'Deputy review',
                'director_decision' => 'Director decision',
                'distribute' => 'Distribute',
                'acknowledge' => 'Acknowledge',
                'feedback' => 'Feedback',
                'close' => 'Close case',
                'print' => 'Print',
            ],
            'request_types' => [
                'incoming_letter' => 'Incoming letters',
                'outgoing_letter' => 'Outgoing letters',
            ],
            'templates' => [
                [
                    'template_key' => 'corr_office_manager',
                    'name' => 'Correspondence Office Manager',
                    'name_km' => 'អ្នកគ្រប់គ្រងលិខិត (ការិយាល័យ)',
                    'responsibility_code' => 'manager',
                    'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                    'sort_order' => 10,
                    'actions' => [
                        'create_incoming',
                        'create_outgoing',
                        'office_comment',
                        'distribute',
                        'acknowledge',
                        'feedback',
                        'print',
                    ],
                ],
                [
                    'template_key' => 'corr_deputy_reviewer',
                    'name' => 'Correspondence Deputy Reviewer',
                    'name_km' => 'អ្នកពិនិត្យអនុប្រធាន (លិខិត)',
                    'responsibility_code' => 'deputy_head',
                    'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                    'sort_order' => 20,
                    'actions' => [
                        'delegate',
                        'deputy_review',
                        'distribute',
                        'close',
                        'print',
                    ],
                ],
                [
                    'template_key' => 'corr_director_final',
                    'name' => 'Correspondence Director Final',
                    'name_km' => 'ប្រធានអនុម័តចុងក្រោយ (លិខិត)',
                    'responsibility_code' => 'head',
                    'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                    'sort_order' => 30,
                    'actions' => [
                        'director_decision',
                        'delegate',
                        'distribute',
                        'close',
                        'print',
                    ],
                ],
            ],
        ],

        'attendance' => [
            'label' => 'Attendance',
            'actions' => [
                'create_adjustment' => 'Create attendance adjustment',
                'review_adjustment' => 'Review attendance adjustment',
                'approve_adjustment' => 'Approve attendance adjustment',
                'finalize_adjustment' => 'Finalize attendance adjustment',
                'manage_exceptions' => 'Manage attendance exceptions',
            ],
            'request_types' => [
                'attendance_adjustment' => 'Attendance adjustment',
                'attendance_exception' => 'Attendance exception',
            ],
            'templates' => [
                [
                    'template_key' => 'att_requester',
                    'name' => 'Attendance Requester',
                    'name_km' => 'អ្នកស្នើកែតម្រូវវត្តមាន',
                    'responsibility_code' => 'staff',
                    'default_scope_type' => UserAssignment::SCOPE_SELF_ONLY,
                    'sort_order' => 10,
                    'actions' => ['create_adjustment'],
                ],
                [
                    'template_key' => 'att_manager_reviewer',
                    'name' => 'Attendance Manager Reviewer',
                    'name_km' => 'អ្នកពិនិត្យវត្តមាន (អ្នកគ្រប់គ្រង)',
                    'responsibility_code' => 'manager',
                    'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                    'sort_order' => 20,
                    'actions' => [
                        'review_adjustment',
                        'manage_exceptions',
                    ],
                ],
                [
                    'template_key' => 'att_head_approver',
                    'name' => 'Attendance Final Approver',
                    'name_km' => 'អ្នកអនុម័តចុងក្រោយវត្តមាន',
                    'responsibility_code' => 'head',
                    'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                    'sort_order' => 30,
                    'actions' => [
                        'approve_adjustment',
                        'finalize_adjustment',
                        'manage_exceptions',
                    ],
                ],
            ],
        ],
    ],

    'ui' => [
        'show_advanced_central_governance' => env('HR_SHOW_ADVANCED_CENTRAL_GOVERNANCE', false),
    ],
];
