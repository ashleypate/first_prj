<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\AssignmentDetailRepository;
use App\Repositories\AssignmentDrafRepository;
use App\Repositories\AssignmentRepository;
use App\Repositories\CourseEnrollRepository;
use App\Repositories\QuizRepository;
use App\Transformers\V1\Admin\AssignmentTransformer;
use App\Transformers\V1\Admin\QuizTransformer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends ApiController
{
    protected $assignmentRepository;
    protected $assignmentDetailRepository;
    protected $assignmentTransformer;
    protected $quizRepository;
    protected $quizTransformer;
    protected $courseEnrollRepository;
    protected $assignmentDrafRepository;

    public function __construct()
    {
        parent::__construct();
        $this->assignmentRepository = new AssignmentRepository();
        $this->assignmentDetailRepository = new AssignmentDetailRepository();
        $this->courseEnrollRepository = new CourseEnrollRepository();
        $this->quizRepository = new QuizRepository();
        $this->assignmentTransformer = new AssignmentTransformer();
        $this->quizTransformer = new QuizTransformer();
        $this->assignmentDrafRepository = new AssignmentDrafRepository();
    }

    public function getListQuiz()
    {
        $course_id = isset($this->data['course_id']) ? $this->data['course_id'] : null;
//        $keywordSearch = isset($this->data['keyword_search']) ? $this->data['keyword_search'] : null;
        $condition_course_enroll = [];
        $condition_course_enroll['user_id'] = Auth::user()->_id;
        if (!empty($course_id)) {
            $condition_course_enroll['course_id'] = $course_id;
        }
        $course_enroll = $this->courseEnrollRepository->getData($condition_course_enroll);
        if (empty($course_enroll)) {
            return $this->responseSuccess([], trans('quiz.successfully'));
        }
        $course_ids = $course_enroll->pluck('course_id')->toArray();
        $condition_quiz = [];
        $condition_quiz['course_id'] = $course_ids;
        $list_quiz = $this->quizRepository->getData($condition_quiz, ['course']);
        if (count($list_quiz) <= 0) {
            return $this->responseSuccess([], trans('quiz.successfully'));
        }
        $data_result = [];
        $a = 0;
        foreach ($list_quiz as $quiz) {
            $data_result[$a] = $this->quizTransformer->transform($quiz);
            $data_result[$a]['course_name'] = $quiz->course->name;
            $a++;
        }
        return $this->responseSuccess($data_result, trans('quiz.successfully'));
    }

    /*
     * Bắt đầu làm bài quiz
     */
    public function startAssignment()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['quiz_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $quiz_info = $this->quizRepository->getData(['_id' => $data['quiz_id']], [], [], 0, 0, ['*'], true);
        if (empty($quiz_info)) {
            return $this->responseError([], trans('quiz.not_found_course'));
        }
        $assignment_of_student = $this->assignmentRepository->getData(['quiz_id' => $data['quiz_id'], 'user_id' => Auth::user()->_id]);
        if (!empty($quiz_info->number_of_time) && $quiz_info->number_of_time <= count($assignment_of_student)) {
            return $this->responseError([], trans('quiz.max_in_time'));
        }
        //Kiểm tra xem học viên có đang làm bài hay không
        if (count($assignment_of_student) > 0) {
            if (count($assignment_of_student->where('status', 0)) > 0) {
                $assignment_info = $assignment_of_student->where('status', 0)->first();
                //Kiểm tra xem còn bao nhiêu thời gian làm bài
                $current_time = Carbon::now()->timestamp;
                $time_quiz = $quiz_info->time;//Thời gian làm bài quiz
                $end_time = Carbon::createFromTimestamp($assignment_info->start_time)->addMinutes($time_quiz)->timestamp;//Thời gian bắt đầu làm
                if ($current_time >= $end_time) {
                    return $this->responseError([], trans('quiz.expired_time'));
                }
                $time_expired = $end_time - $current_time;

                $assignment_result = $this->assignmentTransformer->transform($assignment_info);
                $draf = $this->assignmentDrafRepository->getData(['assignment_id' => $assignment_info->id], [], ['created_at' => 'desc'], 0, 0, ['*'], true);
                $assignment_result['time_expired'] = $time_expired;
                if (!empty($draf)) {
                    $assignment_result['draf'] = $draf->data;
                } else {
                    $assignment_result['draf'] = null;
                }
                return $this->responseSuccess($assignment_result, trans('quiz.successfully'));
            }
        }
        // Tạo vào bảng assignment
        $data_create = [
            'course_id' => $quiz_info->course_id,
            'user_id' => empty(Auth::user()->_id) ? "" : Auth::user()->_id,
            'start_time' => Carbon::now()->timestamp,
            'end_time' => 0,
            'version' => count($assignment_of_student) + 1,
            'status' => 0,
            'open_view_score' => $quiz_info->open_view_score,
            'score' => 0,
            'quiz_id' => $quiz_info->_id,
            'time' => 0,
        ];
        $assignment_info = $this->assignmentRepository->create($data_create);
        if (empty($assignment_info)) {
            return $this->responseError([], trans('quiz.error'));
        }
        $assignment_result = $this->assignmentTransformer->transform($assignment_info);
        $assignment_result['time_expired'] = $quiz_info->time * 60;
        return $this->responseSuccess($assignment_result, trans('quiz.successfully'));
    }

    public function infoAssignment()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['assignment_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }

        $assignment_info = $this->assignmentRepository->getData(['_id' => $data['assignment_id']], [], [], 0, 0, ['*'], true);
        if (empty($assignment_info)) {
            return $this->responseSuccess([], trans('quiz.successfully'));
        }
        $quiz_info = $this->quizRepository->find($assignment_info->quiz_id);
        if (empty($quiz_info)) {
            return $this->responseSuccess([], trans('quiz.successfully'));
        }
        //Tính toán thời gian còn lại
        $current_time = Carbon::now()->timestamp;
        $time_quiz = $quiz_info->time;//Thời gian làm bài quiz
        $end_time = Carbon::createFromTimestamp($assignment_info->start_time)->addMinutes($time_quiz)->timestamp;//Thời gian bắt đầu làm
        if ($current_time >= $end_time) {
            $time_expired = 0;
        } else {
            $time_expired = $end_time - $current_time;
        }

        $assignment_result = $this->assignmentTransformer->transform($assignment_info);
        $draf = $this->assignmentDrafRepository->getData(['assignment_id' => $assignment_info->id], [], ['created_at' => 'desc'], 0, 0, ['*'], true);
        $assignment_result['time_expired'] = $time_expired;
        if (!empty($draf)) {
            $assignment_result['draf'] = $draf->data;
        } else {
            $assignment_result['draf'] = null;
        }

        return $this->responseSuccess($assignment_result, trans('quiz.successfully'));
    }

    public function submitAssignment()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['assignment_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $answers = $data['answer'];
        if (empty($answers)) {
            return [];
        }
        foreach ($answers as $key => $answer) {
            $answers[$key]['assignment_id'] = $data['assignment_id'];
            $answers[$key]['score'] = 0;
            $answers[$key]['time'] = @$data['time'];
            $answers[$key]['status'] = 1;//1: đã hoàn thành chưa chấm điểm; 2: Đã chấm điểm
        }
        $this->assignmentRepository->update(['status' => 1, 'time' => @$data['time']], $data['assignment_id']);
        $this->assignmentDetailRepository->bulkInsert($answers);
        return $this->responseSuccess([], trans('quiz.successfully'));
    }

    public function detailQuiz()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['quiz_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $quiz = $this->quizRepository->getData(['_id' => $data['quiz_id']], ['quiz_question.answer', 'quiz_question_category'], [], 0, 0, ['*'], true);
        if (empty($quiz)) {
            return $this->responseError([], trans('quiz.not_found_course'));
        }

        $quiz = $this->quizRepository->transformer($quiz);

        return $this->responseSuccess([$quiz], trans('quiz.successfully'));
    }

    public function saveDraf()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['data', 'assignment_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $data_create = [
            'assignment_id' => $data['assignment_id'],
            'data' => $data['data'],
            'created_at' => Carbon::now()->timestamp,
        ];
        $this->assignmentDrafRepository->create($data_create);
        return $this->responseSuccess([], trans('quiz.successfully'));

    }

    public function historyAssignment()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['quiz_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $list_assignment = $this->assignmentRepository->getData(['quiz_id' => $data['quiz_id'], 'user_id' => Auth::user()->_id]);
        $data_result = [];
        if (count($list_assignment) > 0) {
            $data_result = $this->assignmentTransformer->transform_collection($list_assignment->all());
        }
        return $this->responseSuccess($data_result, trans('quiz.successfully'));
    }

    public function detailAssignment()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['assignment_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $assignment_info = $this->assignmentRepository->getData(['_id' => $data['assignment_id']], [], [], 0, 0, ['*'], true);
        if (empty($assignment_info)) {
            return $this->responseSuccess([], trans('quiz.successfully'));
        }
        $quiz_id = $assignment_info->quiz_id;
        $quiz = $this->quizRepository->getData(['_id' => $quiz_id], ['quiz_question.answer', 'quiz_question_category'], [], 0, 0, ['*'], true);
        if (empty($quiz)) {
            return $this->responseError([], trans('quiz.not_found_course'));
        }

        $data_result = $this->quizRepository->transformerWithResult($assignment_info, $quiz);
        return $this->responseSuccess($data_result, trans('quiz.successfully'));
    }
}
