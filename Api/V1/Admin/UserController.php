<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\CourseRepository;
use App\Repositories\MoetUnitRepository;
use App\Repositories\RoleRepository;
use App\Repositories\RoleUserRepository;
use App\Repositories\UserRepository;
use App\Transformers\V1\Admin\MoetUnitTransformer;
use App\Transformers\V1\Admin\UserTransformer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends ApiController
{
    protected $userRepository;
    protected $roleRepository;
    protected $roleUserRepository;
    protected $moetUnitRepository;
    protected $courseRepository;
    protected $userTransformer;
    protected $moetUnitTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->userRepository = new UserRepository();
        $this->roleRepository = new RoleRepository();
        $this->roleUserRepository = new RoleUserRepository();
        $this->moetUnitRepository = new MoetUnitRepository();
        $this->courseRepository = new CourseRepository();
        $this->userTransformer = new UserTransformer();
        $this->moetUnitTransformer = new MoetUnitTransformer();
        //Moet Unit Current
        $this->moetUnitDefault = $this->moetUnitRepository->getFirst();

    }

    public function index()
    {
        $data = $this->data;
        $keyword_search = @$data['keyword_search'];
        $role_code = @$data['role_code'];
        $page = @$data['page'];
        $status = @$data['status'];
        $moet_unit_id = empty($data['moet_unit_id']) ? null : $data['moet_unit_id'];
        if (empty($page)) {
            $page = 1;
        }
        $take = isset($this->data['page_size']) ? $this->data['page_size'] : NUMBER_OF_RECORD;
        if ($page == 1) {
            $skip = 0;
        } else {
            $skip = $take * ($page-1);
        }
        if (empty($role_code)) {
            return $this->responseError([''], trans('api.admin.user.role_not_found'));
        }
        $role_info = $this->roleRepository->getRoleByCode($role_code);
        if (empty($role_info)) {
            return $this->responseError([''], trans('api.admin.user.role_not_found'));
        }
        $users = $this->userRepository->getUserByRole($role_info->_id, $skip, $take, $keyword_search, $status, false, $moet_unit_id);
        $total_user = $this->userRepository->getUserByRole($role_info->_id, $skip, $take, $keyword_search, $status, true, $moet_unit_id);
        $data_result = [];
        $a = 0;
        foreach ($users as $user) {
            $data_result[$a] = $this->userTransformer->transform($user);
            $moet_unit = [];
            foreach ($user->roleUser as $role_user) {
                if (!empty($role_user->moetUnit)) {
                    $moet_unit[] = $this->moetUnitTransformer->transform($role_user->moetUnit);
                }
            }
            $data_result[$a]['moet_unit'] = $moet_unit;
            $a++;
        }
        return response()->json([
            'status' => SUCCESS,
            'message' => trans('api.admin.success'),
            'total_record' => $total_user,
            'first_page' => 1,
            'end_page' => ceil($total_user / NUMBER_OF_RECORD),
            'page_size' => $take,
            'data' => $data_result,
        ]);
    }

    public function detail()
    {
        $data = $this->data;
        $user_id = $data['user_id'];
        $validate = validateEmptyData($data, ['user_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $user_info = $this->userRepository->getUserByParam(['_id' => $user_id], null, true);
        if (empty($user_info)) {
            return $this->responseError(['api.admin.user.no_user'], trans('api.param_error'));
        }
        $data_result = $this->userTransformer->transform($user_info);
        $moet_unit = [];
        foreach ($user_info->roleUser as $role_user) {
            if (!empty($role_user->moetUnit)) {
                $moet_unit[] = $this->moetUnitTransformer->transform($role_user->moetUnit);
            }
        }
        $data_result['moet_unit'] = $moet_unit;
        return $this->responseSuccess($data_result, trans('api.admin.game.update_success'));
    }

    public function changePassword()
    {
        $data = $this->data;
        $password = $data['password'];
        $user_id = $data['user_id'];
        $re_password = $data['re_password'];
        $validate = validateEmptyData($data, ['password', 'user_id', 're_password']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        if (strcmp($password, $re_password) != 0) {
            return $this->responseError(['api.admin.user.password_not_matching'], trans('api.param_error'));
        }
        $user_info = $this->userRepository->getUserByParam(['_id' => $user_id]);
        if (empty($user_info)) {
            return $this->responseError(['api.admin.user.no_user'], trans('api.param_error'));
        }
        $data_update = ["password" => Hash::make($data['password']), 'updated_at' => dateNow()];
        $this->userRepository->update($data['user_id'], $data_update);
        return $this->responseSuccess([], trans('api.student.change_pass_success'));
    }

    public function changeStatus()
    {
        $data = $this->data;
        $user_id = $data['user_id'];
        $validate = validateEmptyData($data, ['user_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $user_info = $this->userRepository->getUserByParam(['_id' => $user_id]);
        if (empty($user_info)) {
            return $this->responseError(['api.admin.user.no_user'], trans('api.param_error'));
        }
        $data_update = ["status" => $data['status'], 'updated_at' => dateNow()];
        $this->userRepository->update($data['user_id'], $data_update);
        return $this->responseSuccess([], trans('api.admin.game.update_success'));
    }

    public function create()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['username', 'password', 'code', 'role_code']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $checkdouble = $this->checkDoubleData($data['code'], $data['username'], $data['email'], $data['phone']);
        if (!empty($checkdouble)) {
            return $this->responseError($checkdouble, trans('api.param_error'));
        }
        $moet_unit_id = empty($data['moet_unit_id']) ? null : $data['moet_unit_id'];
        $role_info = $this->roleRepository->getRoleByCode($data['role_code']);
        if (empty($role_info)) {
            return $this->responseError([''], trans('api.admin.user.role_not_found'));
        }
        $image['image'] = $data['avatar'];
        $data_user = [
            'tenant_id' => TENANT_ID_DEFAULT,
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'code' => $data['code'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'avatar' => $this->courseRepository->makeImage($image, 'avatar-user'),
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'fullname' => $data['fullname'],
            'sis_id' => $data['sis_id'],
            'status' => $data['status'],
            'account_group' => 1,
            'auth_provider' => "",
            'created_at' => dateNow(),
            'updated_at' => dateNow()
        ];
        try {
            $user_info = $this->userRepository->create($data_user);

            $data_role_user = [
                'role_id' => $role_info->_id,
                'user_id' => $user_info->_id,
                'moet_unit_id' => empty($moet_unit_id) ? "" : $moet_unit_id,
            ];
            $this->roleUserRepository->create($data_role_user);
        } catch (\Exception $exception) {
            return $this->responseError([''], trans('api.admin.user.error_server'));
        }

        return $this->responseSuccess([], trans('api.admin.game.create_success'));
    }

    public function update()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['user_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $checkdouble = $this->checkDoubleUpdateData($data['user_id'], $data['email'], $data['phone']);
        if (!empty($checkdouble)) {
            return $this->responseError($checkdouble, trans('api.param_error'));
        }
        $username = trim(@$data['username']);

        $user_info = $this->userRepository->getUserByParam(['_id' => $data['user_id']]);
        if (empty($user_info)) {
            return $this->responseError([''], trans('api.admin.user.no_user'));
        }
        $image['image'] = $data['avatar'];
        $data_user = [
            'email' => $data['email'],
            'phone' => $data['phone'],
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'fullname' => $data['fullname'],
            'sis_id' => $data['sis_id'],
            'status' => $data['status'],
            'account_group' => 1,
            'auth_provider' => "",
            'updated_at' => dateNow()
        ];
        if(!empty($username))
            $data_user['username'] = $username;

        if (!empty($data['avatar'])) {
            $data_user['avatar'] = $this->courseRepository->makeImage($image, 'avatar-user');
        }
        $this->userRepository->update($data['user_id'], $data_user);

        return $this->responseSuccess([], trans('api.admin.user.update_success'));
    }

    public function delete()
    {
        $data = $this->data;
        $validate = validateEmptyData($data, ['user_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $user_info = $this->userRepository->getUserByParam(['_id' => $data['user_id']]);
        if (empty($user_info)) {
            return $this->responseError([''], trans('api.admin.user.no_user'));
        }
        $this->userRepository->delete($data['user_id']);
        $this->roleUserRepository->deleteByUser($data['user_id']);
        return $this->responseSuccess([], trans('api.admin.user.delete_success'));
    }

    private function checkDoubleData($code, $username, $email = null, $phone = null)
    {
        //Check double code
        $data_double_error = [];
        $user_by_code = $this->userRepository->getUserByParam(['code' => $code]);
        if (!empty($user_by_code)) {
            $data_double_error[] = trans('api.message.double_code');
        }
        $user_by_username = $this->userRepository->getUserByParam(['username' => $username]);
        if (!empty($user_by_username)) {
            $data_double_error[] = trans('api.message.double_username');
        }
        if (!empty($phone)) {
            $user_by_phone = $this->userRepository->getUserByParam(['phone' => $phone]);
            if (!empty($user_by_phone)) {
                $data_double_error[] = trans('api.message.double_phone');
            }
        }
        if (!empty($email)) {
            $user_by_email = $this->userRepository->getUserByParam(['email' => $email]);
            if (!empty($user_by_email)) {
                $data_double_error[] = trans('api.message.double_email');
            }
        }
        return $data_double_error;
    }

    private function checkDoubleUpdateData($id, $email = null, $phone = null)
    {
        $data_double_error = [];
        if (!empty($phone)) {
            $user_by_phone = $this->userRepository->getUserByParam(['phone' => $phone], $id);
            if (!empty($user_by_phone)) {
                $data_double_error[] = trans('api.message.double_phone');
            }
        }
        if (!empty($email)) {
            $user_by_email = $this->userRepository->getUserByParam(['email' => $email], $id);
            if (!empty($user_by_email)) {
                $data_double_error[] = trans('api.message.double_email');
            }
        }
        return $data_double_error;
    }
}
