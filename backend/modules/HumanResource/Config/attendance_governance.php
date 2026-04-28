<?php

return [
    'module_key' => 'attendance',
    'actions' => [
        'create_adjustment' => 'Create attendance adjustment',
        'review_adjustment' => 'Review attendance adjustment',
        'approve_adjustment' => 'Approve attendance adjustment',
        'finalize_adjustment' => 'Finalize attendance adjustment',
        'manage_exceptions' => 'Manage attendance exceptions',
    ],
    'templates' => [
        [
            'template_key' => 'employee_requester',
            'name' => 'Attendance Requester',
            'name_km' => 'អ្នកស្នើកែតម្រូវវត្តមាន',
            'responsibility_key' => 'employee_requester',
            'default_scope_type' => 'self_only',
            'actions' => ['create_adjustment'],
            'conditions' => [],
            'reviewer_rules' => [],
            'approver_rules' => [],
            'commenter_rules' => [],
        ],
        [
            'template_key' => 'manager_reviewer',
            'name' => 'Attendance Manager Reviewer',
            'name_km' => 'អ្នកពិនិត្យវត្តមាន',
            'responsibility_key' => 'manager_reviewer',
            'default_scope_type' => 'self_and_children',
            'actions' => ['review_adjustment', 'manage_exceptions'],
            'conditions' => [],
            'reviewer_rules' => ['review_adjustment'],
            'approver_rules' => [],
            'commenter_rules' => [],
        ],
        [
            'template_key' => 'head_final_approver',
            'name' => 'Attendance Final Approver',
            'name_km' => 'អ្នកអនុម័តចុងក្រោយវត្តមាន',
            'responsibility_key' => 'head_final_approver',
            'default_scope_type' => 'self_and_children',
            'actions' => ['approve_adjustment', 'finalize_adjustment', 'manage_exceptions'],
            'conditions' => [],
            'reviewer_rules' => [],
            'approver_rules' => ['approve_adjustment', 'finalize_adjustment'],
            'commenter_rules' => [],
        ],
    ],
    'workflow_policies' => [
        [
            'policy_key' => 'attendance_adjustment_default',
            'request_type_key' => 'attendance_adjustment',
            'name' => 'Attendance adjustment workflow',
            'conditions' => [],
            'steps' => [
                ['step_key' => 'manager_review', 'actor_template_key' => 'manager_reviewer', 'action_type' => 'review'],
                ['step_key' => 'head_approval', 'actor_template_key' => 'head_final_approver', 'action_type' => 'approve'],
            ],
        ],
    ],
];
