<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\RoleRepository;
use App\Repositories\RoleUserRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    protected $role_repository;
    protected $role_user_repository;
    protected $user_repository;

    public function __construct()
    {
        parent::__construct();
        $this->role_repository = new RoleRepository();
        $this->role_user_repository = new RoleUserRepository();
        $this->user_repository = new UserRepository();
    }

    public function index(Request $request)
    {
        $data = $request->json()->all();
        $keyword = @trim($data['keyword']);

        $conditions = array();
        if (!empty($keyword))
            $conditions['keyword'] = $keyword;

        $data = $this->role_repository->getData($conditions, ['users']);
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function detail($id)
    {
        $data = $this->role_repository->find($id);
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function update(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data['id']))
            return $this->responseError([], trans('api.admin.not_found'));

        $data_insert = $this->transformData($data);
        $data_result = $this->role_repository->update($data_insert, $data['id']);
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
        if (empty($data['code']))
            return $this->responseError([], trans('api.admin.code_invalid'));

        $check_exist = $this->role_repository->getData(['code' => $data['code']]);
        if (!empty($check_exist) && count($check_exist) > 0)
            return $this->responseError([], trans('api.admin.exist'));

        $data_result = $this->role_repository->create($data_insert);
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
            $this->role_repository->delete($data['id']);
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
        $data_insert['name'] = @$data['name'];
        $data_insert['status'] = @isset($data['status']) ? $data['status'] : 0;
        return $data_insert;
    }

    public function roleByMoetUnit($moet_unit_id)
    {
        $role_users = $this->role_user_repository->getData(['moet_unit_id' => $moet_unit_id], ['user'])->groupBy('role_id')->toArray();
        $role_user_ids = array_keys($role_users);
        $roles = $this->role_repository->getData(['_id' => $role_user_ids]);
        $data = array();
        foreach ($roles as $role_item) {
            $item = array();
            $item['id'] = $role_item->id;
            $item['code'] = $role_item->code;
            $item['name'] = $role_item->name;
            $item['status'] = $role_item->status;
            $item['users'] = isset($role_users[$role_item->id]) ? count($role_users[$role_item->id]) : 0;
            $data[] = $item;
        }
        return $this->responseSuccess($data, trans('api.admin.success'));
    }

    public function getUserByRole($moet_unit_id, $role_id, Request $request)
    {
        $data = $request->json()->all();
        if (empty($moet_unit_id) || empty($role_id))
            return $this->responseError([], trans('api.admin.not_found'));

        $role_users = $this->role_user_repository->getData(['moet_unit_id' => $moet_unit_id, 'role_id' => $role_id], ['user']);
        $user_ids = $role_users->pluck('user_id')->toArray();
        $conditions = array();
        $conditions['_id'] = $user_ids;
        if (isset($data['keyword']))
            $conditions['keyword'] = $data['keyword'];
        $users = $this->user_repository->getData($conditions);
        return $this->responseSuccess($users, trans('api.admin.success'));
    }

    public function addUserToRole($moet_unit_id, Request $request)
    {
        if (empty($moet_unit_id))
            return $this->responseError([], trans('api.admin.not_found'));

        $data = $request->json()->all();

        $multiple_data = array();
        foreach ($data['user_ids'] as $user_id) {
            $data_insert = array();
            $data_insert['role_id'] = $data['role_id'];
            $data_insert['user_id'] = $user_id;
            $data_insert['moet_unit_id'] = $moet_unit_id;
            $multiple_data[] = $data_insert;
        }
        $this->role_user_repository->bulkInsert($multiple_data);
        return $this->responseSuccess([], trans('api.admin.success'));
    }

    public function getUserNotAssignRole($moet_unit_id, $role_id, Request $request)
    {
        $data = $request->json()->all();
        if (empty($moet_unit_id) || empty($role_id))
            return $this->responseError([], trans('api.admin.not_found'));

        $role_users = $this->role_user_repository->getData(['moet_unit_id' => $moet_unit_id, 'role_id' => $role_id], ['user']);
        $user_ids = $role_users->pluck('user_id')->toArray();
        $conditions = array();
        $conditions['status'] = 1;

        $users = $this->user_repository->getUserByParam($conditions, $user_ids, false, true, $data['keyword']);
        return $this->responseSuccess($users, trans('api.admin.success'));
    }

    public function addMoetUnitUser(Request $request)
    {
        $data = $request->json()->all();
        $moet_unit_id = $data['moet_unit_id'];
        $role_id = $data['role_id'];
        $user_id = $data['user_id'];
        $data_item = $this->role_user_repository->getData(['user_id' => $user_id, 'role_id' => $role_id])->first();
        if (!empty($data_item)) {
            $this->role_user_repository->update(['moet_unit_id' => $moet_unit_id], $data_item->id);
            return $this->responseSuccess([], trans('api.admin.success'));
        }
        return $this->responseError([], trans('api.admin.fail'));
    }
}
