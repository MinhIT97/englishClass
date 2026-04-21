<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('classroom.{id}', function ($user, $id) {
    $classroom = \Modules\Classroom\Models\Classroom::find($id);
    if (!$classroom) return false;

    // Admin can access everything
    if ($user->role === 'admin') return true;

    // Check if user is the teacher
    if ($classroom->teacher_id == $user->id) return true;

    // Check if user is a student in the classroom
    return $user->classrooms()->where('classroom_id', $id)->exists();
});
