<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\CourseGradeRepository;
use App\Repositories\CourseModuleRepository;
use App\Repositories\GameRepository;
use App\Repositories\GradeRepository;
use App\Repositories\SubjectRepository;
use App\Transformers\V1\Admin\CourseGradeTransformer;
use App\Transformers\V1\Admin\GameTransformer;
use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;

class GameController extends ApiController
{
    protected $gameRepository;
    protected $gameTransformer;
    protected $courseModuleRepository;
    protected $courseGradeRepository;
    protected $gradeRepository;
    protected $subjectRepository;
    protected $courseGradeTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->gameRepository = new GameRepository();
        $this->courseModuleRepository = new CourseModuleRepository();
        $this->courseGradeRepository = new CourseGradeRepository();
        $this->gradeRepository = new GradeRepository();
        $this->subjectRepository = new SubjectRepository();
        $this->courseGradeRepository = new CourseGradeRepository();
        $this->gameTransformer = new GameTransformer();
        $this->courseGradeTransformer = new CourseGradeTransformer();
    }

    public function index()
    {
        $data = $this->data;
        $keyword_search = @$data['keyword_search'];
        $grade_id = @$data['grade_id'];
        $subject_id = @$data['subject_id'];
        $list_games = $this->gameRepository->getListGame($subject_id, $grade_id, $keyword_search);
        $data_result = [];
        $a = 0;

        $subject_arr = [];
        $grade_arr = [];
        $subjects = $this->subjectRepository->getData();
        if (count($subjects) > 0) {
            $subject_arr = $subjects->pluck('name', '_id');
        }
        $grades = $this->gradeRepository->getData();
        if (count($grades) > 0) {
            $grade_arr = $grades->pluck('name', '_id');
        }
        foreach ($list_games as $game_info) {
            $data_result[$a] = $this->gameTransformer->transform($game_info);
            $grade_ids = $game_info->grade_ids;
            $grade_info = [];
            $subject_info = [];
            if (!empty($grade_ids)) {
                foreach ($grade_ids as $grade_id) {
                    $grade_info[] = [
                        'grade_id' => $grade_id,
                        'grade_name' => @$grade_arr[$grade_id],
                    ];
                }
            }

            $subject_ids = $game_info->subject_ids;
            if (!empty($subject_ids)) {
                foreach ($subject_ids as $subject_id) {
                    $subject_info[] = [
                        'subject_id' => $subject_id,
                        'subject_name' => @$subject_arr[$subject_id],
                    ];
                }
            }
            $data_result[$a]['grade'] = $grade_info;
            $data_result[$a]['subject'] = $subject_info;
            $a++;
        }
        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

    public function create()
    {

    }

    public function update()
    {
    }

    public function delete()
    {
    }

    public function plays()
    {
        $data = $this->data;
        //Nếu lấy danh sách người chơi không theo game
        if (empty($data['game_id'])) {
            $course_module_id = $this->courseModuleRepository->getCourseModuleByGame(null, true);
        } else {
            $game_id = $data['game_id'];
            $course_module_id = $this->courseModuleRepository->getCourseModuleByGame($game_id, true);
        }
        $course_grade = $this->courseGradeRepository->getCourseGradeByCourseModule($course_module_id);
        $data_student = $this->courseGradeTransformer->transform_collection($course_grade->all());
        return $this->responseSuccess($data_student, trans('api.admin.success'));
    }

    public function course()
    {
    }
}
