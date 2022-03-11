<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\AssignmentRepository;
use App\Repositories\CourseEnrollRepository;
use App\Repositories\CourseRepository;
use App\Repositories\QuestionRepository;
use App\Repositories\QuizQuestionAnswerRepository;
use App\Repositories\QuizQuestionCategoryRepository;
use App\Repositories\QuizQuestionRepository;
use App\Repositories\QuizRepository;
use App\Repositories\RoleRepository;
use App\Transformers\V1\Admin\CourseEnrollTransformer;
use App\Transformers\V1\Admin\QuizQuestionAnswerTransformer;
use App\Transformers\V1\Admin\QuizQuestionCategoryTransformer;
use App\Transformers\V1\Admin\QuizQuestionTransformer;
use App\Transformers\V1\Admin\QuizTransformer;
use Illuminate\Support\Facades\Auth;

class QuizController extends ApiController
{
    protected $courseRepository;
    protected $quizRepository;
    protected $questionRepository;
    protected $quizQuestionRepository;
    protected $quizQuestionAnswerRepository;
    protected $quizTransfomer;
    protected $quizQuestionTransfomer;
    protected $quizQuestionAnswerTransfomer;
    protected $quizQuestionCategoryTransfomer;
    protected $quizQuestionCategoryReposiotory;
    protected $assignmentRepository;
    protected $roleRepository;
    protected $courseEnrollTransformer;
    protected $courseEnrollRepository;

    public function __construct()
    {
        parent::__construct();
        $this->courseRepository = new CourseRepository();
        $this->quizRepository = new QuizRepository();
        $this->quizQuestionRepository = new QuizQuestionRepository();
        $this->quizQuestionAnswerRepository = new QuizQuestionAnswerRepository();
        $this->questionRepository = new QuestionRepository();
        $this->quizTransfomer = new QuizTransformer();
        $this->quizQuestionTransfomer = new QuizQuestionTransformer();
        $this->quizQuestionAnswerTransfomer = new QuizQuestionAnswerTransformer();
        $this->quizQuestionCategoryReposiotory = new QuizQuestionCategoryRepository();
        $this->quizQuestionCategoryTransfomer = new QuizQuestionCategoryTransformer();
        $this->assignmentRepository = new AssignmentRepository();
        $this->roleRepository = new RoleRepository();
        $this->roleRepository = new RoleRepository();
        $this->courseEnrollTransformer = new CourseEnrollTransformer();
        $this->courseEnrollRepository = new CourseEnrollRepository();
    }

    public function getListData()
    {
        $data = $this->data;
        $keyword = @trim($data['keyword']);
        $data_condition = [];
        if (!empty($data['course_id']))
            $data_condition['course_id'] = $data['course_id'];

        if (!empty($keyword))
            $data_condition['keyword'] = $keyword;

        $list_quiz = $this->quizRepository->getData($data_condition, ['course.grade']);
        if (count($list_quiz) <= 0)
            return $this->responseSuccess([], trans('quiz.successfully'));
        $data_result = [];
        foreach ($list_quiz as $quiz) {
            $item = $this->quizTransfomer->transform($quiz);
            $item['course_name'] = empty($quiz->course->name) ? "" : $quiz->course->name;
            $item['grade_name'] = empty($quiz->course->grade->name) ? "" : $quiz->course->grade->name;
            $data_result[] = $item;
        }
        return $this->responseSuccess($data_result, trans('quiz.successfully'));
    }

    public function detail()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['quiz_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $quiz = $this->quizRepository->getData(['_id' => $data['quiz_id']], ['quiz_question.answer', 'quiz_question_category'], [], 0, 0, ['*'], true);
        if (empty($quiz)) {
            return $this->responseError([""], trans('quiz.not_found_course'));
        }

        $quiz = $this->quizRepository->transformer($quiz);

        return $this->responseSuccess([$quiz], trans('quiz.successfully'));
    }

    public function genCode()
    {
        $code = $this->quizRepository->makeCodeForQuiz();
        return $code;
    }

    //Tạo bài kiểm tra
    public function create()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['code', 'course_id', 'name', 'start_date', 'end_date', 'time', 'score', 'status']);
        if (!empty($validate)) {
            return $validate;
        }
        $is_exist_code = $this->isExistCode($data['code']);
        if ($is_exist_code) {
            return $this->responseError([""], trans('quiz.code_exist'));
        }
        $dataCreate = $this->quizRepository->validateData($this->data);
        $quiz = $this->quizRepository->create($dataCreate);
        //Create question in quiz
        $this->createQuestionInQuiz($quiz, $this->data['list_question']);
        return $this->responseSuccess([], trans('quiz.create_successfully'));
    }

    //Thêm câu hỏi vào bài kiểm tra
    public function createQuestionInQuiz($quiz, $list_question)
    {
        $data_create_quiz_question_answer = [];
        foreach ($list_question as $arr) {
            $category_name = $arr['category_name'];
            $sort_index = $arr['sort_index'];
            $questions = $arr['question'];

            //Thêm vào trong bảng quiz category question
            $quiz_category_question_id = "";
            if (!empty($category_name)) {
                $data_create_quiz_question_category = [
                    "quiz_id" => $quiz->_id,
                    "name" => $category_name,
                    "parent_id" => "",
                    "sort_index" => $sort_index,
                    "status" => 1,
                    "created_user_id" => Auth::check() ? Auth::user()->_id : ""
                ];
                $quiz_category_question = $this->quizQuestionCategoryReposiotory->create($data_create_quiz_question_category);
                $quiz_category_question_id = $quiz_category_question->id;
            }
            if (!empty($questions)) {
                foreach ($questions as $question) {
                    $question_id = $question['question_id'];
                    $score = $question['score'];
                    $sort_index = $question['sort_index'];
                    $question = $this->questionRepository->getData(['_id' => $question_id], ['answer'], [], 0, 0, ['*'], true);
                    if (!empty($question)) {
                        $answers = $question->answer;
                        $data_create_quiz_question['quiz_id'] = $quiz->id;
                        $data_create_quiz_question['question_id'] = $question->_id;
                        $data_create_quiz_question['question_name'] = $question->name;
                        $data_create_quiz_question['content'] = $question->content;
                        $data_create_quiz_question['solution_guide'] = $question->solution_guide;
                        $data_create_quiz_question['type'] = $question->type;
                        $data_create_quiz_question['quiz_question_category_id'] = $quiz_category_question_id;
                        $data_create_quiz_question['score'] = $score;
                        $data_create_quiz_question['sort_index'] = $sort_index;
                        $data_create_quiz_question['status'] = $question->status;
                        $data_create_quiz_question['created_user_id'] = Auth::check() ? Auth::user()->_id : "";
                        $quiz_question = $this->quizQuestionRepository->create($data_create_quiz_question);
                        if (count($answers) > 0) {
                            foreach ($answers as $answer) {
                                $data_create_quiz_question_answer[] = [
                                    "quiz_id" => $quiz->_id,
                                    "question_id" => $answer->question_id,
                                    "quiz_question_id" => $quiz_question->id,
                                    "content" => $answer->content,
                                    "is_true" => $answer->is_true,
                                    "percent_score" => $answer->percent_score,
                                    "created_user_id" => Auth::check() ? Auth::user()->_id : ""
                                ];
                            }
                        }
                    }
                }
            }
        }
        if (!empty($data_create_quiz_question_answer)) {
            $this->quizQuestionAnswerRepository->bulkInsert($data_create_quiz_question_answer);
        }
    }

    public function update()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['quiz_id', 'course_id', 'name', 'start_date', 'end_date', 'time', 'score', 'status']);
        if (!empty($validate)) {
            return $validate;
        }
        $quiz_id = $data['quiz_id'];
        $assignment_in_quiz = $this->assignmentRepository->getData(['quiz_id' => $quiz_id], null, [], 0, 0, ['*'], true);
        if (!empty($assignment_in_quiz)) {
            return $this->responseError([""], trans('quiz.exist_assignment_in_quiz'));
        }
        //Xóa quiz cũ
        $this->quizRepository->delete($quiz_id);
        //Tạo quiz mới
        $dataCreate = $this->quizRepository->validateData($this->data);
        $quiz = $this->quizRepository->create($dataCreate);
        //Create question in quiz
        $this->createQuestionInQuiz($quiz, $this->data['list_question']);
        return $this->responseSuccess([], trans('quiz.update_successfully'));
    }

    public function delete($quiz_id = null)
    {
        if (empty($quiz_id)) {
            $data = $this->data;
            $check_quiz_used = $this->assignmentRepository->getData(['quiz_id' => $data['quiz_id']]);
            if (count($check_quiz_used) > 0) {
                return $this->responseError([""], trans('quiz.is_used'));
            }
            $quiz_id = $data['quiz_id'];
        }
        $this->quizRepository->delete($quiz_id);
        $this->quizQuestionRepository->deleteByParam(['quiz_id' => $quiz_id]);
        $this->quizQuestionAnswerRepository->deleteByParam(['quiz_id' => $quiz_id]);
        $this->quizQuestionCategoryReposiotory->deleteByParam(['quiz_id' => $quiz_id]);
        return $this->responseSuccess([], trans('quiz.delete_successfully'));
    }

    public function viewAllStudentInQuiz()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['quiz_id']);
        if (!empty($validate)) {
            return $validate;
        }
        //Lấy bài quiz
        $quiz = $this->quizRepository->getData(['_id' => $data['quiz_id']], ['assignment'], [], 0, 0, ['*'], true);
        if (empty($quiz)) {
            return $this->responseError([""], trans('quiz.not_found_course'));
        }
        $has_essay_question = $this->checkExistEssayQuestion($quiz->id);

        //Lấy học viên trong lớp
        $role_student = $this->roleRepository->getRoleByCode('student');
        $keyword_search = @$data['keyword_search'];
        $condition = [];
        if (!empty($keyword_search)) {
            $condition['keyword'] = $keyword_search;
        }
        $keyword_search = @$data['keyword_search'];

        $condition['course_id'] = $quiz->course_id;
        $condition['role_id'] = $role_student->_id;
        if (!empty($keyword_search)) {
            $condition['keyword'] = $keyword_search;
        }
        $course_enrolls = $this->courseEnrollRepository->getData($condition);
        //Lấy bài làm của học viên trong bài quiz
        $data_result = $this->quizTransfomer->transform($quiz);
        $data_result['has_essay_question'] = $has_essay_question;
        $data_result1 = [];
        if (count($course_enrolls) > 0) {
            $assignment = $quiz->assignment;
            $a = 0;
            foreach ($course_enrolls as $course_enroll) {
                $data_result1[$a] = $this->courseEnrollTransformer->transform($course_enroll);
                if (count($assignment) <= 0) {
                    $data_result1[$a]['so_lan_bai_quiz'] = 0;
                    $data_result1[$a]['trang_thai_lam_bai'] = 0;
                } else {
                    $assignment_of_user = $assignment->where('user_id', $course_enroll->user_id);
                    if (count($assignment_of_user) > 0) {
                        if (count($assignment_of_user->where('status', 0)) > 0) {
                            $data_result1[$a]['trang_thai_lam_bai'] = 1;//0: Chưa làm,1: Đang làm, 2: Đã làm chưa chấm điểm, 3:Đã chấm xong, 4: Đang chấm
                        } elseif (count($assignment_of_user->where('status', 1)) > 0) {
                            $data_result1[$a]['trang_thai_lam_bai'] = 2;//0: Chưa làm,1: Đang làm, 2: Đã làm chưa chấm điểm, 3:Đã chấm xong, 4: Đang chấm
                        } elseif (count($assignment_of_user->where('status', 2)) > 0) {
                            $data_result1[$a]['trang_thai_lam_bai'] = 3;
                        } elseif (count($assignment_of_user->where('status', 3)) > 0) {
                            $data_result1[$a]['trang_thai_lam_bai'] = 4;
                        } elseif (count($assignment_of_user->where('status', 4)) > 0) {
                            $data_result1[$a]['trang_thai_lam_bai'] = 2;
                        } else {
                            $data_result1[$a]['trang_thai_lam_bai'] = 3;//0: Chưa làm,1: Đang làm, 2: Đã làm chưa chấm điểm, 3:Đã chấm xong, 4: Đang chấm
                        }
                    } else {
                        $data_result1[$a]['trang_thai_lam_bai'] = 0;
                    }

                    $data_result1[$a]['so_lan_bai_quiz'] = count($assignment_of_user);
                }
                $a++;
            }
        }
        $data_result['student'] = $data_result1;
        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

    public function assignmentDetail()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['assignment_id']);
        if (!empty($validate)) {
            return $validate;
        }

    }

    public function isExistCode($code)
    {
        $quiz = $this->quizRepository->getData(['code' => $code]);
        if (count($quiz) > 0) {
            return true;
        }
        return false;
    }

    public function checkExistEssayQuestion($quiz_id)
    {
        $question_essay = $this->quizQuestionRepository->getData(['quiz_id' => $quiz_id, 'type' => 2]);
        if (count($question_essay) > 0) {
            return true;
        }
        return false;
    }

}
