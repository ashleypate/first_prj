<?php

namespace App\Http\Controllers\Api\V1\Teacher;

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

class CourseGradeController extends ApiController
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


    public function index()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $keyword_search = @$data['keyword_search'];

        $course_modules = $this->courseModuleRepository->getData(['course_id' => $data['course_id'], 'object_type' => 'game']);
        if (count($course_modules) <= 0) {
            return $this->responseSuccess([], trans('api.admin.success'));
        }
        $course_module_id = $course_modules->pluck('_id')->toArray();

        $list_course_module_label = $this->courseModuleRepository->getData(['course_id' => $data['course_id'], 'object_type' => 'course_module_label'])->pluck('name', '_id')->toArray();
        $course_grade = $this->courseGradeRepository->getCourseGradeByCourseModule($course_module_id, true, $keyword_search);

        $data_result = array();
        foreach ($course_grade as $course_grade_item) {
            $item = array();
            $item['total_game_play'] = 1;
            $item['total_point'] = $course_grade_item->final_grade;
            $item['avg_point'] = $course_grade_item->final_grade;
            $item['code_game'] = @$course_grade_item->course_module->game->code;
            $item['name_game'] = @$course_grade_item->course_module->game->name;
            $item['time_play'] = $course_grade_item->time_play;
            $item['health'] = $course_grade_item->health;
            $item['course_module_label'] = isset($list_course_module_label[$course_grade_item->course_module->course_module_parent_id]) ? @$list_course_module_label[$course_grade_item->course_module->course_module_parent_id] : null;

            $user_info = [
                "user_id" => @$course_grade_item->user->_id,
                "avatar" => @$course_grade_item->user->avatar,
                "fullname" => @$course_grade_item->user->fullname,
                "email" => @$course_grade_item->user->email,
                "phone" => @$course_grade_item->user->phone,
                "content" => @$course_grade_item->content,
            ];
            $item['user_info'] = $user_info;
            $data_result[] = $item;
        }


//        $course_grade_by_user = $course_grade->groupBy('user_id');
//        $data_result = [];
//        $i = 0;
//        foreach ($course_grade_by_user as $user_id => $course_grade_list) {
//            if (!empty($user_id)) {
//                $user_info = [];
//                $data_result[$i]['total_game_play'] = count($course_grade_list);
//                $data_result[$i]['total_point'] = $course_grade_list->sum('final_grade');
//                $data_result[$i]['avg_point'] = round($course_grade_list->sum('final_grade') / count($course_grade_list), 2);
//                $data_result[$i]['code_game'] = round($course_grade_list->sum('final_grade') / count($course_grade_list), 2);
//                foreach ($course_grade_list as $course_grade) {
//                    if (empty($course_grade->user)) {
//                        continue;
//                    }
//                    $user_info = [
//                        "user_id" => $course_grade->user->_id,
//                        "avatar" => $course_grade->user->avatar,
//                        "fullname" => $course_grade->user->fullname,
//                        "email" => $course_grade->user->email,
//                        "phone" => $course_grade->user->phone,
//                        "content" => $course_grade->content,
//                    ];
//                }
//                $data_result[$i]['user_info'] = $user_info;
//            } else {
//                foreach ($course_grade_list as $course_grade) {
////                    if (empty($course_grade->user)) {
////                        continue;
////                    }
//                    $item = array();
//                    $item['total_game_play'] = 1;
//                    $item['total_point'] = $course_grade->final_grade;
//                    $item['avg_point'] = $course_grade->final_grade;
//                    $user_info = [
//                        "user_id" => @$course_grade->user->_id,
//                        "avatar" => @$course_grade->user->avatar,
//                        "fullname" => $course_grade->user_fullname,
//                        "email" => @$course_grade->user->email,
//                        "phone" => @$course_grade->user->phone,
//                        "content" => $course_grade->content,
//                    ];
//                    $item['user_info'] = $user_info;
//                    $data_result[] = $item;
//                }
//            }
//            $i++;
//        }

        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

}
