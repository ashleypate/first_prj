<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\CourseRepository;
use App\Repositories\QuestionAnswerRepository;
use App\Repositories\QuestionRepository;
use App\Repositories\QuizQuestionAnswerRepository;
use App\Repositories\QuizQuestionRepository;
use App\Repositories\QuizRepository;
use App\Transformers\V1\Admin\QuestionTransformer;
use Illuminate\Support\Facades\Auth;

class QuestionController extends ApiController
{
    protected $courseRepository;
    protected $quizRepository;
    protected $questionRepository;
    protected $quizQuestionRepository;
    protected $quizQuestionAnswerRepository;
    protected $questionAnswerRepository;
    protected $questionTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->courseRepository = new CourseRepository();
        $this->quizRepository = new QuizRepository();
        $this->quizQuestionRepository = new QuizQuestionRepository();
        $this->quizQuestionAnswerRepository = new QuizQuestionAnswerRepository();
        $this->questionRepository = new QuestionRepository();
        $this->questionAnswerRepository = new QuestionAnswerRepository();
        $this->questionTransformer = new QuestionTransformer();
    }

    public function getListData()
    {
        $data_input = $this->data;
        $validate = validateEmptyData($data_input, ['question_category_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }

        $keyword_search = @$data_input['keyword_search'];
        $page = @$data_input['page'];
        if (empty($page)) {
            $page = 1;
        }
        $take = 100;
        if ($page == 1) {
            $skip = 0;
        } else {
            $skip = 100 * ($page - 1);
        }
        $data_condition = [];
        if (!empty($data_input['question_category_id'])) {
            $data_condition['question_category_id'] = $data_input['question_category_id'];
        }
//        $data_condition['created_user_id'] = empty(Auth::user()->_id) ? "" : Auth::user()->_id;

        if (!empty($keyword_search)) {
            $data_condition['keyword'] = $keyword_search;
        }
        $data_category = $this->questionRepository->getData($data_condition, ['answer'], [], $skip, $take);
        if (count($data_category) <= 0) {
            $data_result = [];
        } else {
            $data_result = $this->questionTransformer->transform_collection($data_category->all());
        }
        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

    public function getById()
    {
        $data_input = $this->data;
        $validate = validateEmptyData($data_input, ['question_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $question_info = $this->questionRepository->getData(['_id' => $data_input['question_id']], ['answer'], [], 0, 0, '*', true);
        if (empty($question_info))
            return $this->responseSuccess([], trans('api.admin.success'));
        $data_result = $this->questionTransformer->transform($question_info);
        return $this->responseSuccess($data_result, trans('api.admin.success'));

    }

    public function createQuestion()
    {
        $data_input = $this->data;
        $validate = validateEmptyData($data_input, ['question_category_id', 'grade_id', 'subject_id', 'name', 'content']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        if (in_array($data_input['type'], [ONE_CHOICE, MULTIPLE_CHOICE])) {
            if (empty($data_input['answer'])) {
                return $this->responseError(trans('api.message.param_requered', ['param' => "answer"]), trans('api.param_error'));
            }
        }
        $data_input['code'] = $this->questionRepository->genCodeQuestion();
        $data_input['created_user_id'] = empty(Auth::user()->_id) ? "" : Auth::user()->_id;
        $answer = $data_input['answer'];
        unset($data_input['answer']);
        $question_info = $this->questionRepository->create($data_input);
        //Chỉ tạo câu trả lời cho câu hỏi chọn đáp án đúng
        if (!empty($answer)) {
            $this->createQuestionAnswer($question_info, $answer);
        }
        return $this->responseSuccess([], trans('api.teacher.message.create_success'));
    }

    public function createQuestionAnswer($question_info, $answers)
    {
        $data_insert_answer = [];
        foreach ($answers as $answer) {
            $answer['question_id'] = $question_info->_id;
            $answer['created_user_id'] = empty(Auth::user()->_id) ? "" : Auth::user()->_id;
            $answer['content'] = createFileFromContentBase64($answer['content']);
            $data_insert_answer[] = $answer;
        }
        $this->questionAnswerRepository->bulkInsert($data_insert_answer);
        return true;
    }

    public function update()
    {
        $data_input = $this->data;
        $validate = validateEmptyData($data_input, ['question_id', 'name', 'content']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $data_update = [
            'name' => $data_input['name'],
            'content' => createFileFromContentBase64($data_input['content']),
            'solution_guide' => $data_input['solution_guide'],
            'status' => $data_input['status'],
        ];
        $this->questionRepository->update($data_update, $data_input['question_id']);

        $question_info = $this->questionRepository->find($data_input['question_id']);
        if (in_array($question_info->type, [ONE_CHOICE, MULTIPLE_CHOICE])) {
            $answer = $data_input['answer'];
            //Xóa đáp án câu hỏi hiện tại
            $this->questionAnswerRepository->deleteByParam(['question_id' => $data_input['question_id']]);
            //Thêm câu trả lời cho câu hỏi
            $this->createQuestionAnswer($question_info, $answer);
        }
        return $this->responseSuccess([], trans('api.admin.success'));
    }

    public function delete()
    {
        $data_input = $this->data;
        $validate = validateEmptyData($data_input, ['question_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $this->questionRepository->delete($this->data['question_id']);
        $this->questionAnswerRepository->deleteByParam(['question_id' => $this->data['question_id']]);

        return $this->responseSuccess([], trans('question.delete_successfully'));
    }
}
