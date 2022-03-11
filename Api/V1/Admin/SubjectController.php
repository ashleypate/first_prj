<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Helpers\CommonLib;
use App\Http\Controllers\Api\ApiController;
use App\Repositories\SubjectGradeRepository;
use App\Repositories\SubjectRepository;
use Illuminate\Http\Request;

class SubjectController extends ApiController
{
    protected $subject_repository;
    protected $subject_grade_repository;

    public function __construct()
    {
        $this->subject_repository = new SubjectRepository();
        $this->subject_grade_repository = new SubjectGradeRepository();
        parent::__construct();
    }

    public function index(Request $request)
    {
        $data = $request->json()->all();
        $keyword = @trim($data['keyword']);

        $conditions = array();
        if(isset($data['gradeLevel']))
            $conditions['grade_level_id'] = $data['gradeLevel'];

        if(isset($data['status']))
            $conditions['status'] = $data['status'];

        if (!empty($keyword))
            $conditions['keyword'] = $keyword;

        $data = $this->subject_repository->getData($conditions, ['grade_level']);
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function detail($id)
    {
        $data = $this->subject_repository->find($id);
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function grade($subject_id)
    {
        $data = $this->subject_grade_repository->getData(['subject_id' => $subject_id], ['grade']);
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function addGrade(Request $request, $subject_id)
    {
        $data = $request->json()->all();
        $grade_ids = $data['grade_ids'];
        $subject_info = $this->subject_repository->find($subject_id);
        if (empty($subject_info))
            return $this->responseError([], trans('api.admin.not_found'));

        foreach ($grade_ids as $grade_id) {
            $this->subject_grade_repository->create(['subject_id' => $subject_id, 'grade_id' => $grade_id]);
        }
        return $this->responseSuccess([], trans('api.admin.success'));
    }

    public function update(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data['id']))
            return $this->responseError([], trans('api.admin.not_found'));

        $data_insert = $this->transformData($data);
        $data_result = $this->subject_repository->update($data_insert, $data['id']);
        if (!empty($data_result))
            return $this->responseSuccess([], trans('api.admin.success'));
        else
            return $this->responseError([], trans('api.admin.fail'));
    }

    public function create(Request $request)
    {
        $data = $request->json()->all();
        $data_insert = $this->transformData($data);
        $data_insert['code'] = @$data['code'];
        if(empty($data['code']))
            return $this->responseError([], trans('api.admin.code_invalid'));

        $check_exist = $this->subject_repository->getData(['code' => $data_insert['code']]);
        if (!empty($check_exist) && count($check_exist) > 0)
            return $this->responseError([], trans('api.admin.exist'));

        $data_result = $this->subject_repository->create($data_insert);
        if (!empty($data_result))
            return $this->responseSuccess($data_result, trans('api.admin.success'));
        else
            return $this->responseError([], trans('api.admin.success'));
    }

    public function delete(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data['id']))
            return $this->responseError([], trans('api.admin.not_found'));
        else {
            $this->subject_repository->delete($data['id']);
            return $this->responseSuccess([], trans('api.admin.success'));
        }
    }

    /**
     * @param array $data
     * @return array
     */
    public function transformData(array $data)
    {
        $data_insert = array();
        $data_insert['sis_id'] = @$data['sisId'];
        $data_insert['name'] = @$data['name'];
        $data_insert['grade_level_id'] = @$data['gradeLevelId'];
        $data_insert['sort_index'] = @$data['sortIndex'];
        $data_insert['status'] = @$data['status'];
        return $data_insert;
    }
}
