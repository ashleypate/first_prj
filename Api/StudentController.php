<?php

namespace App\Http\Controllers\Api;

use App\Repositories\CourseGradeRepository;
use App\Repositories\CourseModuleRepository;
use App\Repositories\UserRepository;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudentController extends ApiController
{
    protected $course_module_repository;
    protected $course_grade_repository;
    protected $user_repository;
    protected $key;

    public function __construct()
    {
        parent::__construct();
        $this->course_module_repository = new CourseModuleRepository();
        $this->course_grade_repository = new CourseGradeRepository();
        $this->user_repository = new UserRepository();
        $this->key = env("JWT_SECRET", 'OMT1234@1234');
    }

    public function join(Request $request)
    {
        $data = $request->json()->all();
        $access_code = @$data['access_code'];
        if (empty($data['fullname']))
            return $this->responseError([], trans('api.student.fullname_invalid'));

        $user_info = $this->user_repository->getData(['username' => $data['fullname']])->first();

        $course_module_parent = $this->course_module_repository->getData(['access_code' => (int)$access_code, 'status' => 2], ['course'])->first();
        if (empty($course_module_parent))
            return $this->responseError([], trans('api.student.code_invalid'));
        $jwt_token = $this->generaToken($course_module_parent);

        $course_module_items = $this->course_module_repository->getData(['course_module_parent_id' => $course_module_parent->id], ['object']);

        $games = array();
        foreach ($course_module_items as $course_module_item) {
            if ($course_module_item->object_type != 'game')
                continue;

            if (empty($course_module_item->object))
                continue;

            $item = $this->transformResult($course_module_item);

            //Them hoc sinh vao course_grade
            $data_insert = array();
            $data_insert['course_id'] = $course_module_item->course_id;
            $data_insert['course_module_id'] = $course_module_item->id;
            $data_insert['user_fullname'] = $data['fullname'];
            $data_insert['final_grade'] = null;
            $data_insert['user_id'] = @$user_info->id;
            $data_insert['content'] = @$data['content'];
            $data_insert['play_status'] = 0;
            $data_insert['status'] = 1;//1 la khoi tao, khong hien thi ra danh sach diem
            $data_insert['time_play'] = 0;//Thoi gian choi
            $data_insert['health'] = 0;// So mang con lai. Tuy game co hay khong
            $result = $this->course_grade_repository->create($data_insert);
            $item['course_grade_id'] = $result->id;

            $games[] = $item;
        }
        return $this->responseSuccess(['access_token' => $jwt_token, 'class' => @$course_module_parent->course->code, 'total_time' => 1800, 'games' => $games], trans('api.student.join_success'));
    }

    public function result(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data['access_token']))
            return $this->responseError([], trans('api.student.token_not_found'));

        if (empty($data['data']))
            return $this->responseError([], trans('api.student.game_not_found'));

        try {
            JWT::decode($data['access_token'], $this->key, array('HS256'));
        } catch (\Exepciton $e) {
            return $this->responseError([], $e->getMessage());
        }
        Log::info(json_encode($data['data']));

        foreach ($data['data'] as $game_result) {
            $course_grade_id = $game_result['course_grade_id'];
            $course_grade_info = $this->course_grade_repository->find($course_grade_id);

            if (empty($course_grade_info)) continue;

            $this->course_grade_repository->update(['final_grade' => @$game_result['correct_answer'], 'play_status' => 3, 'time_play' => @$game_result['timePlay'], 'health' => @$game_result['health'], 'status' => 0], $course_grade_id);
        }

        return $this->responseSuccess([], trans("api.student.result"));
    }

    private function transformResult($data)
    {
        return [
            'id' => $data->object->code,
            'time' => isset(json_decode($data->settings)->time) ? json_decode($data->settings)->time : 120,
            'repeat_number' => isset(json_decode($data->settings)->repeat_number) ? json_decode($data->settings)->repeat_number : 1,
            'game_radius' => isset(json_decode($data->settings)->game_radius) ? json_decode($data->settings)->game_radius : 1,
            'correct_answer' => 6,
            'total_answer' => 15,
            'slot' => $data->sort_index
        ];
    }

    private function generaToken($course_module)
    {

        $payload = array(
            "course_module_id" => $course_module->id,
            "iss" => "Learning game",
            "aud" => "OMT",
            "iat" => time(),
            "exp" => time() + 7200
        );
        return JWT::encode($payload, $this->key, "HS256");
    }
}
