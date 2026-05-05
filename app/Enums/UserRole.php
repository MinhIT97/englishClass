<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';

    public function isStaff(): bool
    {
        return in_array($this, [self::Admin, self::Teacher], true);
    }
}
