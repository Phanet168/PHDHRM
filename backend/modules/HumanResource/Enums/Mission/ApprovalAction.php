<?php

namespace Modules\HumanResource\Enums\Mission;

enum ApprovalAction: string
{
    case Endorse = 'endorse';
    case Approve = 'approve';
    case Reject = 'reject';
    case ReturnForCorrection = 'return_for_correction';
}

