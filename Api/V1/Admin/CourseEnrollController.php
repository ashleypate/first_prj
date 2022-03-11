<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\CourseEnroll;
use App\Models\Role;
use App\Models\RoleUser;
use App\Repositories\CourseEnrollRepository;
use App\Repositories\CourseGradeRepository;
use App\Repositories\CourseModuleRepository;
use App\Repositories\CourseRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Transformers\V1\Admin\CourseEnrollTransformer;
use App\Transformers\V1\Admin\CourseGradeTransformer;
use App\Transformers\V1\Admin\CourseModuleTransformer;
use App\Transformers\V1\Admin\CourseTransformer;
use App\Transformers\V1\Admin\UserTransformer;
use Illuminate\Support\Facades\Auth;

class CourseEnrollController extends ApiController
{
    protected $courseRepository;
    protected $courseEnrollRepository;
    protected $courseGradeRepository;
    protected $courseModuleRepository;
    protected $userRepository;
    protected $roleRepository;
    protected $courseTransformer;
    protected $courseEnrollTransformer;
    protected $courseGradeTransformer;
    protected $courseModuleTransformer;
    protected $userTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->courseRepository = new CourseRepository();
        $this->courseEnrollRepository = new CourseEnrollRepository();
        $this->courseModuleRepository = new CourseModuleRepository();
        $this->courseGradeRepository = new CourseGradeRepository();
        $this->roleRepository = new RoleRepository();
        $this->userRepository = new UserRepository();
        $this->courseTransformer = new CourseTransformer();
        $this->courseGradeTransformer = new CourseGradeTransformer();
        $this->courseEnrollTransformer = new CourseEnrollTransformer();
        $this->courseModuleTransformer = new CourseModuleTransformer();
        $this->userTransformer = new UserTransformer();
    }

    public function index()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $role_student = $this->roleRepository->getRoleByCode('student');
        $keyword_search = @$data['keyword_search'];
        $condition = [];
        if (!empty($keyword_search)) {
            $condition['keyword'] = $keyword_search;
        }
        $condition['course_id'] = $data['course_id'];
        $condition['role_id'] = $role_student->_id;
        $course_enrolls = $this->courseEnrollRepository->getData($condition, ['user']);
        $data_result = [];
        if (count($course_enrolls) > 0) {
            $a = 0;
            foreach ($course_enrolls as $course_enroll) {
                $data_result[$a] = $this->courseEnrollTransformer->transform($course_enroll);
                $data_result[$a]['linked'] = UN_LINKED;
                if (!empty($course_enroll->user)) {
                    $data_result[$a]['linked'] = LINKED;
                }
                $a++;
            }
        }
        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

    public function addSingle()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['fullname', 'course_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        if (!empty($data['email'])) {
            $enroll_by_email = $this->courseEnrollRepository->getData(['email' => $data['email']]);
            if (count($enroll_by_email) > 0) {
                return $this->responseError([], trans('api.teacher.validate.double_email'));
            }
        }
        $role_student = $this->roleRepository->getRoleByCode('student');
        $data_enroll = [
            'course_id' => $data['course_id'],
            'fullname' => $data['fullname'],
            'email' => empty($data['email']) ? "" : $data['email'],
            'phone' => empty($data['phone']) ? "" : $data['phone'],
            'user_id' => "",
            'role_id' => $role_student->_id,
            'course_team_id' => "",
            'enroll_code' => $this->generateEnrollCode(),
        ];
        $this->courseEnrollRepository->create($data_enroll);
        return $this->responseSuccess([], trans('api.teacher.message.create_success'));
    }

    public function addMultiple()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['user_ids', 'course_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        //Kiểm tra xem học viên có trong lớp hay chưa
        $list_user_in_course = $this->courseEnrollRepository->getData(['course_id' => $data['course_id'], 'user_id' => $data['user_ids']]);
        if (count($list_user_in_course) > 0) {
            $data_error = [];
            foreach ($list_user_in_course as $user_in_course) {
                $data_error[] = $user_in_course->fullname;
            }
            return $this->responseError($data_error, trans('api.teacher.message.user_exist'));
        }
        $role_student = $this->roleRepository->getRoleByCode('student');

        $list_users = $this->userRepository->getUserByParam(['_id' => $data['user_ids']], null, false, true);
        foreach ($list_users as $user) {
            $data_enroll = [
                'course_id' => $data['course_id'],
                'fullname' => $user->fullname,
                'email' => $user->email,
                'phone' => $user->phone,
                'user_id' => $user->_id,
                'role_id' => $role_student->_id,
                'course_team_id' => "",
                'enroll_code' => $this->generateEnrollCode(),
            ];
            $this->courseEnrollRepository->create($data_enroll);
        }
        return $this->responseSuccess([], trans('api.teacher.message.create_success'));
    }

    public function delete()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_enroll_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $course_enroll = $this->courseEnrollRepository->getData(['_id' => $data['course_enroll_id']]);
        if (count($course_enroll) <= 0) {
            return $this->responseError([], trans('api.teacher.message.enroll_not_found'));
        }
        $this->courseEnrollRepository->delete($data['course_enroll_id']);
        return $this->responseSuccess([], trans('api.teacher.message.delete_success'));
    }

    public function searchStudents()
    {
        $keyword_search = isset($this->data['keyword_search']) ? $this->data['keyword_search'] : null;

        $studentRole = $this->roleRepository->getRoleByCode('student');
        if (empty($studentRole)) {
            return $this->responseError([''], trans('api.admin.user.role_not_found'));
        }

        $pages = isset($this->data['page']) ? (integer)$this->data['page'] : 1;
        $size = isset($this->data['size']) ? (integer)$this->data['size'] : 100;
        $user_ignore = [];
        if (!empty($this->data['course_id'])) {
            $role_student = $this->roleRepository->getRoleByCode('student');
            $condition = [];
            $condition['course_id'] = $this->data['course_id'];
            $condition['role_id'] = $role_student->_id;
            $course_enrolls = $this->courseEnrollRepository->getData($condition);
            if (count($course_enrolls) > 0) {
                $user_ignore = $course_enrolls->pluck('user_id')->toArray();
            }
        }
        $students = $this->userRepository->getUserByRole($studentRole->_id, ($pages - 1) * $size, $size, $keyword_search, null, false, null, $user_ignore);

        if(isset($this->data['moet_unit_id'])){
            $students = $this->filterStudentsNotInMoetUnit($students, $this->data['moet_unit_id'],$studentRole->_id);
        }

        return $this->responseSuccess([
            'students' => $students->map(function ($student) {
                return array(
                    'id' => $student->_id . "",
                    'username' => $student->username . "",
                    'fullname' => $student->fullname . "",
                    'gender' => (integer)$student->gender,
                    'date_of_birth' => (double)$student->date_of_birth,
                    'email' => $student->email . "",
                    'phone' => $student->phone . ""
                );
            })
        ], '');
    }

    private function filterStudentsNotInMoetUnit($students, $unitId, $studentRoleId){
        $studentIds = RoleUser::where('moet_unit_id',$unitId)->where('role_id',$studentRoleId)
            ->get()->pluck('user_id')->toArray();

        return $students->filter(function($student) use ($studentIds){
            return !in_array($student->_id, $studentIds);
        })->values();
    }

    private function generateEnrollCode()
    {
        do {
            $code = rand(100000, 999999);
            $course = CourseEnroll::where('enroll_code', $code)->first();
        } while (!is_null($course));

        return $code;
    }
}
