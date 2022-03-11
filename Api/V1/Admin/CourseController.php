<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\CourseEnrollRepository;
use App\Repositories\CourseGradeRepository;
use App\Repositories\CourseModuleRepository;
use App\Repositories\CourseRepository;
use App\Transformers\V1\Admin\CourseEnrollTransformer;
use App\Transformers\V1\Admin\CourseGradeTransformer;
use App\Transformers\V1\Admin\CourseModuleTransformer;
use App\Transformers\V1\Admin\CourseTransformer;

class CourseController extends ApiController
{

    protected $courseRepository;
    protected $courseEnrollRepository;
    protected $courseGradeRepository;
    protected $courseModuleRepository;
    protected $userRepository;
    protected $courseTransformer;
    protected $courseEnrollTransformer;
    protected $courseGradeTransformer;
    protected $courseModuleTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->courseRepository = new CourseRepository();
        $this->courseEnrollRepository = new CourseEnrollRepository();
        $this->courseModuleRepository = new CourseModuleRepository();
        $this->courseGradeRepository = new CourseGradeRepository();
        $this->courseTransformer = new CourseTransformer();
        $this->courseGradeTransformer = new CourseGradeTransformer();
        $this->courseEnrollTransformer = new CourseEnrollTransformer();
        $this->courseModuleTransformer = new CourseModuleTransformer();
    }

    public function index()
    {
        $data = $this->data;
        $keyword_search = @$data['keyword_search'];
        $page = @$data['page'];
        if (empty($page)) {
            $page = 1;
        }
        $take = NUMBER_OF_RECORD;
        if ($page == 1) {
            $skip = 0;
        } else {
            $skip = NUMBER_OF_RECORD * $page;
        }
        $condition = [];
        if (!empty($keyword_search)) {
            $condition['keyword'] = $keyword_search;
        }
        if (!empty($data['grade_id'])) {
            $condition['grade_id'] = $data['grade_id'];
        }
        if (!empty($data['subject_id'])) {
            $condition['subject_id'] = $data['subject_id'];
        }
        $condition['moet_unit_id'] = $data['moet_unit_id'];
        $list_course = $this->courseRepository->getData($condition, ['subject', 'grade', 'schoolYear', 'moetUnit'], [], $skip, $take);
        $data_result = $this->courseTransformer->transform_collection($list_course->all());
        return $this->responseSuccess($data_result, trans('api.admin.success'));
    }

    public function create()
    {
        $dataCreate = $this->courseRepository->validateData($this->data);
        if (!$dataCreate) {
            return $this->responseError([""], trans('api.message.mising_params'));
        }

        $course = $this->courseRepository->create($dataCreate);
        $course = $this->courseRepository->transformCourse($course);

        return $this->responseSuccess(['course' => $course], trans('course.create_successfully'));
    }

    public function update()
    {
        $dataUpdate = $this->courseRepository->makeDataUpdate($this->data);
        $course = $this->courseRepository->update($dataUpdate, $this->data['course_id']);

        if (!$course) {
            return $this->responseError([""], trans('course.update_failed'));
        }

        $course = $this->courseRepository->find($this->data['course_id']);
        if (is_null($course)) {
            return $this->responseError([""], trans('course.not_found_course'));
        }

        $course = $this->courseRepository->transformCourse($course);

        return $this->responseSuccess(['course' => $course], trans('course.update_successfully'));
    }

    public function detail()
    {
        $course = $this->courseRepository->find($this->data['course_id']);
        if (is_null($course)) {
            return $this->responseError([""], trans('course.not_found_course'));
        }

        $course = $this->courseRepository->transformCourse($course);

        return $this->responseSuccess(['course' => $course], trans('course.success'));
    }

    public function delete()
    {
        $delete = $this->courseRepository->delete($this->data['course_id']);
        if (!$delete) {
            return $this->responseError([""], trans('course.delete_failed'));
        }
        return $this->responseSuccess([], trans('course.delete_successfully'));
    }

}
