<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\CourseRepository;
use App\Repositories\QuestionCategoryRepository;
use App\Repositories\QuestionRepository;
use App\Repositories\QuizQuestionAnswerRepository;
use App\Repositories\QuizQuestionRepository;
use App\Repositories\QuizRepository;
use App\Transformers\V1\Admin\QuestionCategoryTransformer;
use Illuminate\Support\Facades\Auth;

class QuestionCategoryController extends ApiController
{
    protected $courseRepository;
    protected $quizRepository;
    protected $questionRepository;
    protected $quizQuestionRepository;
    protected $quizQuestionAnswerRepository;
    protected $questionCategoryRepository;
    protected $questionCategoryTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->courseRepository = new CourseRepository();
        $this->quizRepository = new QuizRepository();
        $this->quizQuestionRepository = new QuizQuestionRepository();
        $this->quizQuestionAnswerRepository = new QuizQuestionAnswerRepository();
        $this->questionRepository = new QuestionRepository();
        $this->questionCategoryRepository = new QuestionCategoryRepository();
        $this->questionCategoryTransformer = new QuestionCategoryTransformer();
        $this->tenant_id = TENANT_ID_DEFAULT;
    }

    public function getListData()
    {
        $data_input = $this->data;
        $data_condition = [];
        if (!empty($data_input['subject_id'])) {
            $data_condition['subject_id'] = $data_input['subject_id'];
        }
        if (!empty($data_input['grade_id'])) {
            $data_condition['grade_id'] = $data_input['grade_id'];
        }
        if (!empty($data_input['parent_id'])) {
            $data_condition['parent_id'] = $data_input['parent_id'];
        }
        $data_question = $this->questionCategoryRepository->getData($data_condition);
        if (count($data_question) <= 0) {
            $data_result = [];
        } else {
            $data_result = $this->questionCategoryTransformer->transform_collection($data_question->all());
        }
        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

    public function getById()
    {
        $data_input = $this->data;
        $validate = validateEmptyData($data_input, ['category_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $category_info = $this->questionCategoryRepository->find($data_input['category_id']);
        $data_result = [];
        if (!empty($category_info)) {
            $data_result = $this->questionCategoryTransformer->transform($category_info);
        }

        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

    public function create()
    {
        $data_input = $this->data;
        $validate = validateEmptyData($data_input, ['name', 'grade_id', 'subject_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $data_input['code'] = $this->questionCategoryRepository->genCodeCategory();
        $data_input['created_user_id'] = Auth::check() ? Auth::user()->_id : "";
        $this->questionCategoryRepository->create($data_input);
        return $this->responseSuccess([], trans('api.teacher.message.create_success'));
    }

    public function update()
    {
        $data_input = $this->data;
        $validate = validateEmptyData($data_input, ['name', 'category_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }

        $data_result = $this->questionCategoryRepository->update($data_input, $data_input['category_id']);
        if (!empty($data_result))
            return $this->responseSuccess([], trans('api.admin.success'));
        else
            return $this->responseError([], trans('api.admin.fail'));
    }

    public function delete()
    {
        $data_input = $this->data;
        $validate = validateEmptyData($data_input, ['category_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $this->questionCategoryRepository->delete($data_input['category_id']);
        return $this->responseSuccess([], trans('api.admin.success'));
    }

}
