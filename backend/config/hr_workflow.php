<?php

use Modules\HumanResource\Entities\UserOrgRole;

return [
    /*
    |--------------------------------------------------------------------------
    | HR Workflow Matrix
    |--------------------------------------------------------------------------
    |
    | Central workflow-role mapping for "Grade/Rank Promotion" approvals.
    | Keys under `promotion` are normalized promotion types used by
    | EmployeePayPromotionController:
    | - annual_grade
    | - annual_rank
    | - degree_based
    | - honorary_pre_retirement
    |
    | Each action can define org roles that are allowed at that stage:
    | - recommend
    | - approve
    | - reject
    |
    */
    'promotion' => [
        'annual_grade' => [
            'recommend' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            'approve' => [
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            'reject' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
        ],
        'annual_rank' => [
            'recommend' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            'approve' => [
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            'reject' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
        ],
        'degree_based' => [
            'recommend' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            'approve' => [
                UserOrgRole::ROLE_HEAD,
            ],
            'reject' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
        ],
        'honorary_pre_retirement' => [
            'recommend' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            'approve' => [
                UserOrgRole::ROLE_HEAD,
            ],
            'reject' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
        ],

        '_default' => [
            'recommend' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            'approve' => [
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            'reject' => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
        ],
    ],
];

