<?php

namespace Modules\HumanResource\Enums\Mission;

enum DestinationScope: string
{
    case WithinProvince = 'within_province';
    case OutsideProvince = 'outside_province';
}

