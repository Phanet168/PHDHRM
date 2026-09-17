<?php

namespace Modules\HumanResource\Enums\Mission;

enum MissionCreationSource: string
{
    case EmployeeRequest = 'employee_request';
    case InvitationLetter = 'invitation_letter';
    case DirectorInstruction = 'director_instruction';
    case AdministrationDirect = 'administration_direct';
    case Other = 'other';
}

