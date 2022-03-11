<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\Teacher\CourseController;
use App\Repositories\CourseEnrollRepository;
use App\Repositories\CourseRepository;
use Illuminate\Support\Facades\Auth;


class StudentCourseController extends CourseController
{
    protected $courseRepository;

    protected $roleType;

    public function __construct()
    {
        parent::__construct();
        $this->courseRepository = new CourseRepository();
        $this->roleType = 'student';
    }

    public function enrollCourse(){
        $enrollRepository = new CourseEnrollRepository();
        $enroll = $enrollRepository->studentEnrollCourse(Auth::user()->id, $this->data['enroll_code']);
        if(!$enroll){
            return $this->responseError([], trans('api.student.enroll_code_not_found'));
        }
        return $this->responseSuccess([], trans('api.student.enroll_successfully'));
    }

}
