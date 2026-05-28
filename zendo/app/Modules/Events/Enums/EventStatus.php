<?php

namespace App\Modules\Events\Enums;

enum EventStatus: string
{
    case Published = 'PUBLISHED';
    case Draft = 'DRAFT';
}
