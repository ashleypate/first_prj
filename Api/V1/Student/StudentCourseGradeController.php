<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\CourseEnrollRepository;
use App\Repositories\CourseGradeRepository;
use App\Repositories\CourseModuleRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GameRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Transformers\V1\Admin\CourseEnrollTransformer;
use App\Transformers\V1\Admin\CourseGradeTransformer;
use App\Transformers\V1\Admin\CourseModuleTransformer;
use App\Transformers\V1\Admin\CourseTransformer;
use App\Transformers\V1\Admin\GameTransformer;
use App\Transformers\V1\Admin\UserTransformer;
use Illuminate\Support\Facades\Auth;

class StudentCourseGradeController extends ApiController
{
    protected $courseRepository;
    protected $courseEnrollRepository;
    protected $courseGradeRepository;
    protected $courseModuleRepository;
    protected $userRepository;
    protected $roleRepository;
    protected $gameRepository;
    protected $courseTransformer;
    protected $courseEnrollTransformer;
    protected $courseGradeTransformer;
    protected $courseModuleTransformer;
    protected $userTransformer;
    protected $gameTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->courseRepository = new CourseRepository();
        $this->courseEnrollRepository = new CourseEnrollRepository();
        $this->courseModuleRepository = new CourseModuleRepository();
        $this->courseGradeRepository = new CourseGradeRepository();
        $this->roleRepository = new RoleRepository();
        $this->userRepository = new UserRepository();
        $this->gameRepository = new GameRepository();
        $this->courseTransformer = new CourseTransformer();
        $this->courseGradeTransformer = new CourseGradeTransformer();
        $this->courseEnrollTransformer = new CourseEnrollTransformer();
        $this->courseModuleTransformer = new CourseModuleTransformer();
        $this->userTransformer = new UserTransformer();
        $this->gameTransformer = new GameTransformer();
    }


    public function getGradeOfStudentInCourse()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $user_info = Auth::user();
        $user_id = $user_info->id;
        $course_id = $data['course_id'];

        $course_grade = $this->courseGradeRepository->getData(['user_id' => $user_id, 'course_id' => $course_id]);
        $course_grade_by_modules = $course_grade->groupBy('course_module_id');
        $data_result = [];
        $i = 0;
        foreach ($course_grade_by_modules as $course_module_id => $course_grade_by_module) {
            $course_module = $this->courseModuleRepository->getData(['_id' => $course_module_id], ['game'], [], 0, 0, [], true);
            if (empty($course_module)) {
                continue;
            }
            $data_result[$i]['name_game'] = empty($course_module->game->name) ? "" : $course_module->game->name;
            $data_result[$i]['total_game_play'] = count($course_grade_by_module);
            $data_result[$i]['total_point'] = $course_grade_by_module->sum('final_grade');
            $i++;
        }

        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

}
