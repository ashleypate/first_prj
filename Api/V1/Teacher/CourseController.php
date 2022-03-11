<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Api\ApiController;
use App\Models\Grade;
use App\Models\Subject;
use App\Repositories\CourseRepository;
use App\Repositories\RoleUserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends ApiController
{
    protected $courseRepository;
    protected $roleType;

    public function __construct()
    {
        parent::__construct();
        $this->courseRepository = new CourseRepository();
        $this->roleType = 'teacher';
    }

    public function load(Request $request)
    {
//
//        if(!isset($this->data['grade_id'])){
//            $grades = Grade::all()->map(function ($grade) {
//                return array(
//                    'id' => $grade->_id."",
//                    'name' => $grade->name.""
//                );
//            });
//        }
//
//        if(!isset($this->data['subject_id'])) {
//            $subjects = Subject::all()->map(function ($grade) {
//                return array(
//                    'id' => $grade->_id."",
//                    'name' => $grade->name.""
//                );
//            });
//        }

        $this->data['school_year_id'] = $this->getCurrentSchoolYearId($request);
        $this->data['moet_unit_id'] = $this->getCurrentMoetUnitId($request);
        $courses = $this->courseRepository->getListCourses(Auth::user(), $this->data, $this->roleType);

        $roleUserRepository = new RoleUserRepository();
        $school = $roleUserRepository->getMoetUnitOfUser(Auth::user()->id);
        if (!is_null($school)) {
            $school = [
                'id' => $school->_id . "",
                'name' => $school->name . ""
            ];
        } else {
            $school = [
                'id' => 0,
                'name' => trans('course.no_school')
            ];
        }

        $data = compact('courses', 'school');
        if (isset($grades)) {
            $data['grades'] = $grades;
        }
        if (isset($subjects)) {
            $data['subjects'] = $subjects;
        }
        return $this->responseSuccess($data);

    }

    public function create()
    {

        $dataCreate = $this->courseRepository->validateData($this->data);
        if (!$dataCreate) {
            return $this->responseError([""], trans('api.message.mising_params'));
        }

        $course = $this->courseRepository->create($dataCreate);
        $this->courseRepository->enrollTeacher($course, Auth::user());
        $course = $this->courseRepository->transformCourse($course);

        return $this->responseSuccess(['course' => $course], trans('course.create_successfully'));
    }

    public function update()
    {

        $dataUpdate = $this->courseRepository->makeDataUpdate($this->data);
        $course = $this->courseRepository->update($dataUpdate, $this->data['course_id']);

        if (!$course) {
            return $this->responseError([""], trans('course.update_failed'));
        }

        $course = $this->courseRepository->find($this->data['course_id']);
        if (is_null($course)) {
            return $this->responseError([""], trans('course.not_found_course'));
        }

        $course = $this->courseRepository->transformCourse($course);

        return $this->responseSuccess(['course' => $course], trans('course.update_successfully'));
    }

    public function detail()
    {
        $course = $this->courseRepository->find($this->data['course_id']);
        if (is_null($course)) {
            return $this->responseError([""], trans('course.not_found_course'));
        }

        $course = $this->courseRepository->transformCourse($course);

        return $this->responseSuccess(['course' => $course], trans('course.success'));
    }

    public function delete()
    {
        $delete = $this->courseRepository->delete($this->data['course_id']);
        if (!$delete) {
            return $this->responseError([""], trans('course.delete_failed'));
        }
        return $this->responseSuccess([], trans('course.delete_successfully'));
    }

}
