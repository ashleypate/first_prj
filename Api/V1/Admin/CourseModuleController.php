<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\CourseModule;
use App\Models\Game;
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

class CourseModuleController extends ApiController
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


    public function getListModuleLabel()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $condition = [];
        $condition['course_id'] = $data['course_id'];
        $condition['object_type'] = 'course_module_label';
        $list_module = $this->courseModuleRepository->getData($condition, null, ['sort_index' => 'asc']);
        $data_result = [];
        if (count($list_module) > 0) {
            $data_result = $this->courseModuleTransformer->transform_collection($list_module->all());
        }
        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

    public function addModuleLabel()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_id', 'name']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $course_info = $this->courseRepository->getCourseById($data['course_id']);
        if (empty($course_info)) {
            return $this->responseError([""], trans('course.not_found_course'));
        }
        $last_module_by_index = $this->courseModuleRepository->getData(['course_id' => $data['course_id'], 'object_type' => 'course_module_label'], null, ['sort_index' => 'desc'], 0, 0, null, true);
        $sort_index = 0;
        if (!empty($last_module_by_index)) {
            $sort_index = $last_module_by_index->sort_index + 1;
        }
        $data_insert = [
            "sis_id" => "",
            "course_id" => $data['course_id'],
            "object_type" => "course_module_label",
            "object_id" => "",
            "name" => $data['name'],
            "access_code" => $this->generateCourseModuleAccessCode(),
            "description" => "",
            "max_grade" => 0,
            "settings" => "",
            "course_module_parent_id" => "",
            "course_module_path" => "",
            'open_date' => empty($data['open_date']) ? 0 : convertTimestampFromFormatToUTC($data['open_date']),
            'close_date' => empty($data['close_date']) ? 0 : convertTimestampFromFormatToUTC($data['close_date']),
            "sort_index" => $sort_index,
            "status" => 2
        ];
        $this->courseModuleRepository->create($data_insert);
        return $this->responseSuccess([], trans('api.teacher.message.create_success'));
    }

    public function updateModuleLabel()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_module_label_id', 'name']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $course_module_info = $this->courseModuleRepository->getData(['_id' => $data['course_module_label_id']], null, [], 0, 0, [], true);

        if (empty($course_module_info)) {
            return $this->responseError([""], trans('course.not_found_course_module'));
        }
//        $sort_current = $course_module_info->sort_index;
//        if ($data['move_to'] == "top") {
//            $param = ['course_id' => $course_module_info->course_id, 'object_type' => 'course_module_label', 'sort_index' => $sort_current + 1];
//            $course_module_top = $this->courseModuleRepository->getData($param, null, [], 0, 0, [], true);
//            if (!empty($course_module_top)) {
//                $this->courseModuleRepository->update(['sort_index' => $sort_current], $course_module_top->_id);
//                $this->courseModuleRepository->update(['name' => $data['name'], 'sort_index' => $sort_current + 1], $course_module_info->_id);
//            }
//            return $this->responseSuccess([], trans('api.teacher.message.update_success'));
//        }
//        if ($data['move_to'] == "bottom") {
//            $param = ['course_id' => $course_module_info->course_id, 'object_type' => 'course_module_label', 'sort_index' => $sort_current - 1];
//            $course_module_top = $this->courseModuleRepository->getData($param, null, [], 0, 0, [], true);
//            if (!empty($course_module_top)) {
//                $this->courseModuleRepository->update(['sort_index' => $sort_current], $course_module_top->_id);
//                $this->courseModuleRepository->update(['name' => $data['name'], 'sort_index' => $sort_current - 1], $course_module_info->_id);
//            }
//            return $this->responseSuccess([], trans('api.teacher.message.update_success'));
//        }
        $data_update = [
            'name' => $data['name'],
            'open_date' => empty($data['open_date']) ? 0 : convertTimestampFromFormatToUTC($data['open_date']),
            'close_date' => empty($data['close_date']) ? 0 : convertTimestampFromFormatToUTC($data['close_date']),
        ];
        $this->courseModuleRepository->update($data_update, $course_module_info->_id);
        return $this->responseSuccess([], trans('api.teacher.message.update_success'));
    }

    public function sortModule()
    {
        $datas = $this->data;
        $validate = validateEmptyData($datas, ['data']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        foreach ($datas['data'] as $data) {
            $course_module_label_id = @$data['course_module_label_id'];
            $sort_index = @$data['sort_index'];
            if (empty($course_module_label_id)) {
                return $this->responseError([], trans('api.param_error'));
            }
            $this->courseModuleRepository->update(['sort_index' => $sort_index], $course_module_label_id);
        }
        return $this->responseSuccess([], trans('api.teacher.message.update_success'));
    }


    public function listGameOfModule()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_module_label_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $keyword_search = @$data['keyword_search'];
        $condition = [];
        $condition['course_module_parent_id'] = $data['course_module_label_id'];
        $condition['object_type'] = 'game';
        $list_module = $this->courseModuleRepository->getData($condition);
        $list_game_module = $list_module->pluck('_id', 'object_id')->toArray();
        $list_game_ids = array_keys($list_game_module);
        $sort_arr = $list_module->pluck('sort_index', 'object_id')->toArray();
        $setting_arr = $list_module->pluck('settings', 'object_id')->toArray();
        $condition_game = [];
        if (!empty($keyword_search)) {
            $condition_game['keyword'] = $keyword_search;
        }
        $condition_game['_id'] = $list_game_ids;
        $list_game = $this->gameRepository->getData($condition_game);
        $data_result = [];
        if (count($list_game) > 0) {
            foreach ($list_game as $game_info) {
                $game_arr = $this->gameTransformer->transform($game_info);
                $game_arr['course_module_id'] = $list_game_module[$game_info->_id];
                $game_arr['sort_index'] = $sort_arr[$game_info->_id];
                $game_arr['settings'] = json_decode($setting_arr[$game_info->_id], true);
                $data_result[] = $game_arr;
            }
        }
        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

    public function addGameToCourse()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_id', 'game_id', 'course_module_label_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $game_info = $this->gameRepository->find($data['game_id']);
        if (empty($game_info)) {
            return $this->responseError($validate, trans('api.teacher.message.game_not_found'));
        }
        $check_exist = $this->courseModuleRepository->getData(['course_id' => $data['course_id'], 'object_type' => 'game', 'object_id' => $data['game_id']]);
        if (count($check_exist) > 0) {
            return $this->responseError($validate, trans('api.teacher.message.game_exist_in_course'));
        }
        $last_module_by_index = $this->courseModuleRepository->getData(['course_module_parent_id' => $data['course_module_label_id'], 'object_type' => 'game'], null, ['sort_index' => 'desc'], 0, 0, null, true);
        $sort_index = 0;
        if (!empty($last_module_by_index)) {
            $sort_index = $last_module_by_index->sort_index + 1;
        }
        $data_insert = [
            "sis_id" => "",
            "course_id" => $data['course_id'],
            "object_type" => "game",
            "object_id" => $data['game_id'],
            "name" => $game_info->name,
            "access_code" => $this->generateCourseModuleAccessCode(),
            "description" => $game_info->description,
            "max_grade" => 0,
            "settings" => "",
            "course_module_parent_id" => $data['course_module_label_id'],
            "course_module_path" => "",
            "open_date" => 0,
            "close_date" => 0,
            "sort_index" => $sort_index,
            "status" => 2
        ];
        $this->courseModuleRepository->create($data_insert);
        return $this->responseSuccess([], trans('api.teacher.message.create_success'));
    }

    public function getListGame()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_id']);

        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $course_info = $this->courseRepository->getCourseById($data['course_id']);
        if (empty($course_info)) {
            return $this->responseError([""], trans('course.not_found_course'));
        }
        $keyword_search = @$data['keyword_search'];

        $grade_id = $course_info->grade_id;
        $subject_id = $course_info->subject_id;
        //Lấy game đã có trong lớp
        $condition = [];
        $condition['course_id'] = $data['course_id'];
        $condition['object_type'] = 'game';
        $list_module = $this->courseModuleRepository->getData($condition);
        $list_game_ids = $list_module->pluck('object_id')->toArray();

        $list_games = $this->gameRepository->getListGame([$subject_id], [$grade_id], $keyword_search, $list_game_ids);
        $data_game = [];
        if (count($list_games) > 0) {
            $i = 0;
            foreach ($list_games as $list_game) {
                $data_game[$i] = $this->gameTransformer->transform($list_game);
                $data_game[$i]['grade'] = empty($course_info->grade->name) ? "" : $course_info->grade->name;
                $data_game[$i]['subject'] = empty($course_info->subject->name) ? "" : $course_info->subject->name;
                $i++;
            }
        }
        return $this->responseSuccess($data_game, trans('api.teacher.message.create_success'));
    }

    public function update()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['course_module_id']);

        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $course_module_info = $this->courseModuleRepository->getData(['_id' => $data['course_module_id']]);
        if (empty($course_module_info)) {
            return $this->responseError([""], trans('api.teacher.message.game_not_found'));
        }
        $data_update = [
//            'open_date' => empty($data['open_date']) ? 0 : convertDateFromFormatToUTC($data['open_date']),
//            'close_date' => empty($data['close_date']) ? 0 : convertDateFromFormatToUTC($data['close_date']),
            'repeat_number' => empty($data['repeat_number']) ? 0 : $data['repeat_number'],
            'time' => empty($data['time']) ? 0 : $data['time'],
            'game_radius' => empty($data['game_radius']) ? 0 : $data['game_radius'],
        ];
        $this->courseModuleRepository->update(['settings' => json_encode($data_update)], $data['course_module_id']);
        return $this->responseSuccess([], trans('api.teacher.message.update_success'));
    }

    public function sortGameOfModule()
    {
        $datas = $this->data;
        $validate = validateEmptyData($datas, ['data']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        foreach ($datas['data'] as $data) {
            $course_module_id = @$data['course_module_id'];
            $sort_index = @$data['sort_index'];
            if (empty($course_module_id)) {
                return $this->responseError([], trans('api.param_error'));
            }
            $this->courseModuleRepository->update(['sort_index' => $sort_index], $course_module_id);
        }
        return $this->responseSuccess([], trans('api.teacher.message.update_success'));
    }

    public function delete()
    {
        return $this->responseSuccess([], trans('api.teacher.message.delete_success'));
    }

    private function generateGameAccessCode()
    {
        do {
            $code = rand(100000, 999999);
            $game = Game::where('access_code', $code)->first();
        } while (!is_null($game));

        return $code;
    }

    private function generateCourseModuleAccessCode()
    {
        do {
            $code = rand(100000, 999999);
            $cmd = CourseModule::where('access_code', $code)->first();
        } while (!is_null($cmd));

        return $code;
    }
}
