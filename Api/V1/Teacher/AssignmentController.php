<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\AssignmentDetailRepository;
use App\Repositories\AssignmentRepository;
use App\Repositories\CourseRepository;
use App\Repositories\QuestionRepository;
use App\Repositories\QuizQuestionAnswerRepository;
use App\Repositories\QuizQuestionRepository;
use App\Repositories\QuizRepository;
use App\Transformers\V1\Admin\AssignmentTransformer;

class AssignmentController extends ApiController
{
    protected $courseRepository;
    protected $quizRepository;
    protected $questionRepository;
    protected $quizQuestionRepository;
    protected $quizQuestionAnswerRepository;
    protected $assignmentRepository;
    protected $assignmentDetailRepository;
    protected $assignmentTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->courseRepository = new CourseRepository();
        $this->quizRepository = new QuizRepository();
        $this->quizQuestionRepository = new QuizQuestionRepository();
        $this->quizQuestionAnswerRepository = new QuizQuestionAnswerRepository();
        $this->questionRepository = new QuestionRepository();
        $this->assignmentRepository = new AssignmentRepository();
        $this->assignmentDetailRepository = new AssignmentDetailRepository();
        $this->assignmentTransformer = new AssignmentTransformer();
    }

    public function publishAssignment()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['assignment_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $publish = $data['open_view_score'];

        $this->assignmentRepository->update(['open_view_score' => $publish], $data['assignment_id']);
        return $this->responseSuccess([], trans('quiz.successfully'));
    }

    public function gradeAssignment()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['assignment_id', 'quiz_question_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $score = $data['score'];
        $assignment_id = $data['assignment_id'];
        $quiz_question_id = $data['quiz_question_id'];

        $assignment_info = $this->assignmentRepository->find($assignment_id);
        if (empty($assignment_info))
            return $this->responseError([], trans('api.assignment_not_found'));

        $assignment_detail_info = $this->assignmentDetailRepository->getData(['assignment_id' => $assignment_id, 'quiz_question_id' => $quiz_question_id, 'quiz_question_type' => 2])->first();

        if (empty($assignment_detail_info))
            return $this->responseError([], trans('api.assignment_detail_not_found'));

        $score_old = $assignment_detail_info->score;
        $status_old = $assignment_detail_info->status;

        $this->assignmentDetailRepository->update(['score' => $score, 'status' => 2], $assignment_detail_info->id);

        if ($status_old == 1) {
            //Chua cham diem
            $this->assignmentRepository->update(['score' => $score + $assignment_info->score], $assignment_info->id);
        } elseif ($status_old == 2) {
            //Da tung cham diem
            $score_new = $assignment_info->score - $score_old + $score;
            $this->assignmentRepository->update(['score' => $score_new], $assignment_info->id);
        }

        //Neu cac cau hoi da cham xong het, thi update trang thai bai thi ve Da cham xong
        $all_assignment_detail = $this->assignmentDetailRepository->getData(['assignment_id' => $assignment_id]);
        $assignment_detail_done = $all_assignment_detail->where('status', 2);
        if ($all_assignment_detail->count() == $assignment_detail_done->count())
            $this->assignmentRepository->update(['status' => 2], $assignment_id);

        return $this->responseSuccess([], trans('quiz.successfully'));
    }

    public function historyAssignment()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['quiz_id', 'student_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $list_assignment = $this->assignmentRepository->getData(['quiz_id' => $data['quiz_id'], 'user_id' => $data['student_id']]);
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
