<?php

namespace Modules\Classroom\Enums;

enum ClassroomPostType: string
{
    case Announcement = 'announcement';
    case Schedule = 'schedule';
    case Meeting = 'meeting';
    case Material = 'material';
    case Video = 'video';
    case Pronunciation = 'pronunciation';
}
