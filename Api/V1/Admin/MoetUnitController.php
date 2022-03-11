<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\RoleUser;
use App\Repositories\MoetUnitRepository;
use App\Repositories\RoleRepository;
use Illuminate\Http\Request;

class MoetUnitController extends ApiController
{
    protected $moet_unit_repository;

    public function __construct()
    {
        $this->moet_unit_repository = new MoetUnitRepository();
        parent::__construct();
    }

    public function index(Request $request)
    {
        $data = $request->json()->all();
        $parent_id = isset($data['parentId']) ? $data['parentId'] : 0;
        $keyword = @trim($data['keyword']);

        $conditions = array();
        $conditions['parent_id'] = $parent_id;
        if(isset($data['gradeLevel']))
            $conditions['grade_level_id'] = $data['gradeLevel'];

        if(isset($data['status']))
            $conditions['status'] = $data['status'];

        if (!empty($keyword))
            $conditions['keyword'] = $keyword;

        $data = $this->moet_unit_repository->getData($conditions, ['grade_level']);
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function detail($id)
    {
        $data = $this->moet_unit_repository->find($id);
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function update(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data['id']))
            return $this->responseError([], trans('api.admin.not_found'));

        $data_insert = $this->transformData($data);
        $data_result = $this->moet_unit_repository->update($data_insert, $data['id']);
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

        $check_exist = $this->moet_unit_repository->getData(['code' => $data['code']]);
        if(!empty($check_exist) && count($check_exist) > 0)
            return $this->responseError([], trans('api.admin.exist'));

        $data_result = $this->moet_unit_repository->create($data_insert);
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
            $delete = $this->moet_unit_repository->delete($data['id']);
            if ($delete)
                return $this->responseSuccess([], trans('api.admin.success'));
            else
                return $this->responseError([], trans('api.admin.fail'));
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
        $data_insert['sis_id'] = $data['sisId'];
        $data_insert['parent_id'] = isset($data['parentId']) ? $data['parentId'] : 0;
        $data_insert['path'] = @$data['path'];
        $data_insert['moet_level'] = @$data['moetLevel'];
        $data_insert['grade_level_id'] = @$data['gradeLevelId'];
        $data_insert['settings'] = @$data['settings'];
        $data_insert['status'] = @$data['status'];
        return $data_insert;
    }

    public function enrollStudents(){
        if (empty($this->data['moet_unit_id'])){
            return $this->responseError([], trans('api.admin.not_found'));
        }

        $studentRole = (new RoleRepository())->getRoleByCode('student');
        if (empty($studentRole)) {
            return $this->responseError([''], trans('api.admin.user.role_not_found'));
        }


        $studentsInSchoolIds = RoleUser::where('role_id',$studentRole->_id)
            ->whereIn('user_id',$this->data['user_ids'])
            ->where('moet_unit_id',$this->data['moet_unit_id'])
            ->get()->pluck('user_id')->toArray();

        $studentIdsCanConnect = array_diff($this->data['user_ids'],$studentsInSchoolIds);
        foreach($studentIdsCanConnect as $studentId){
            RoleUser::create([
                'role_id'       => $studentRole->_id,
                'moet_unit_id'  => $this->data['moet_unit_id'],
                'user_id'       => $studentId
            ]);
        }

        return $this->responseSuccess([], trans('api.admin.success'));
    }
}
