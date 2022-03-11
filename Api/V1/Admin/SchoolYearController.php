<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\SchoolYearRepository;
use Illuminate\Http\Request;

class SchoolYearController extends ApiController
{
    protected $school_year_repository;

    public function __construct()
    {
        $this->school_year_repository = new SchoolYearRepository();
        parent::__construct();
    }

    public function index(Request $request)
    {
        $data = $request->json()->all();
        $keyword = @trim($data['keyword']);

        $conditions = array();
        if (!empty($keyword))
            $conditions['keyword'] = $keyword;

        $data = $this->school_year_repository->getData($conditions);
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function detail($id)
    {
        $data = $this->school_year_repository->find($id);
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function update(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data['id']))
            return $this->responseError([], trans('api.admin.not_found'));

        $data_insert = $this->transformData($data);
        $data_result = $this->school_year_repository->update($data_insert, $data['id']);
        if (!empty($data_result))
            return $this->responseSuccess([], trans('api.admin.success'));
        else
            return $this->responseError([], trans('api.admin.fail'));
    }

    public function create(Request $request)
    {
        $data = $request->json()->all();
        $data_insert = $this->transformData($data);
        $data_insert['code'] = $data['code'];

        $check_exist = $this->school_year_repository->getData(['code' => $data['code']]);
        if(!empty($check_exist) && count($check_exist) > 0)
            return $this->responseError([], trans('api.admin.exist'));

        $data_result = $this->school_year_repository->create($data_insert);
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
            $this->school_year_repository->delete($data['id']);
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
        $data_insert['name'] = $data['name'];
        $data_insert['start_date'] = $data['startDate'];
        $data_insert['end_date'] = $data['endDate'];
        $data_insert['status'] = @$data['status'];
        return $data_insert;
    }
}
