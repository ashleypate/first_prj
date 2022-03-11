<?php

namespace App\Http\Controllers\Api;

use App\Helpers\CommonLib;
use App\Repositories\RoleRepository;
use App\Repositories\RoleUserRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Zalo\Zalo;
use Zalo\ZaloEndPoint;

class LoginWithZaloController extends ApiController
{
    protected $zalo;
    protected $user_repository;
    protected $role_user_repository;
    protected $role_repository;

    public function __construct()
    {
        parent::__construct();
        $this->zalo = new Zalo(CommonLib::getConfigZalo());
        $this->user_repository = new UserRepository();
        $this->role_user_repository = new RoleUserRepository();
        $this->role_repository = new RoleRepository();
    }

    public function loginZalo()
    {
        $helper = $this->zalo->getRedirectLoginHelper();
        $callBackUrl = env('ZALO_CALLBACK_URL', 'https://www.callback.com');
        $accessToken = $helper->getAccessToken($callBackUrl); // get access token
        $state = @$_GET['state'];
        if (empty($accessToken))
            return response()->json(['status' => 0, 'message' => 'No authorization', 'data' => []]);

        $params = ['fields' => 'id,name,birthday,gender,picture'];
        $response = $this->zalo->get(ZaloEndPoint::API_GRAPH_ME, $accessToken, $params);
        $result = $response->getDecodedBody(); // result

        if (empty($result))
            return response()->json(['status' => 0, 'message' => 'No authorization', 'data' => []]);

        $user_info = $this->user_repository->getUserByParam(['username' => $result['id']]);
        if (!empty($user_info)) {
            auth()->login($user_info);
            $token = auth()->tokenById($user_info->id);
        } else {
            $data_user = [
                'tenant_id' => TENANT_ID_DEFAULT,
                'username' => $result['id'],
                'password' => Hash::make($result['id']),
                'code' => $result['id'],
                'email' => $result['id'],
                'phone' => $result['id'],
                'avatar' => @$result['picture']['data']['url'],
                'firstname' => null,
                'lastname' => null,
                'fullname' => $result['name'],
                'sis_id' => null,
                'status' => 1,
                'account_group' => 1,
                'auth_provider' => "",
                'sso_type' => 'zalo',
                'created_at' => dateNow(),
                'updated_at' => dateNow()
            ];
            $user_info = $this->user_repository->create($data_user);
            if (empty($user_info)) {
                abort(500);
            }
            auth()->login($user_info);
            $token = auth()->tokenById($user_info->id);

            if ($state == 1)
                //Giao vien
                $role = $this->role_repository->getData(['code' => ROLE_TEACHER])->first();
            else
                //Hoc vien
                $role = $this->role_repository->getData(['code' => ROLE_STUDENT])->first();

            if (!empty($role)) {
                $this->role_user_repository->create(['role_id' => $role->id, 'user_id' => $user_info->id, 'moet_unit_id' => null]);
            }
        }

        $data_login = CommonLib::initDataLogin($state, $user_info);
        $data = ['token' => $token, 'state' => $state, 'user_info' => Auth::user(), 'tenant_id' => Auth::user()->tenant_id, 'current_school_year_id' => $data_login['school_year_id'], 'current_role_id' => $data_login['role_id'], 'current_moet_unit_id' => $data_login['moet_unit_id'], 'roles' => @$data_login['roles'], 'moet_units' => @$data_login['moet_units'], 'school_years' => @$data_login['school_years']];
        return Redirect::to(env('GAME_CALLBACK_URL') . "?loginzalo=1&data_result=" . json_encode($data));
    }

    public function loginZaloApp(Request $request)
    {
        $data = $request->json()->all();
        $data_user = [
            'tenant_id' => TENANT_ID_DEFAULT,
            'username' => $data['user_id'],
            'password' => Hash::make($data['user_id']),
            'code' => $data['user_id'],
            'email' => $data['user_id'],
            'phone' => @$data['phone_number'],
            'avatar' => @$data['picture']['data']['url'],
            'firstname' => null,
            'lastname' => null,
            'fullname' => @$data['display_name'],
            'sis_id' => null,
            'status' => 1,
            'account_group' => 1,
            'auth_provider' => "",
            'sso_type' => 'zalo',
            'created_at' => dateNow(),
            'updated_at' => dateNow()
        ];
        $check_exist = $this->user_repository->getUserByParam(['username' => $data['user_id']]);
        if (!empty($check_exist)) {
            return $this->responseSuccess(['username' => $data['user_id'], 'password' => $data['user_id']], 'Success');
        }

        $user_info = $this->user_repository->create($data_user);
        //Giao vien
        $role = $this->role_repository->getData(['code' => ROLE_TEACHER])->first();
        if (!empty($role)) {
            $this->role_user_repository->create(['role_id' => $role->id, 'user_id' => $user_info->id, 'moet_unit_id' => null]);
        }
        if (empty($user_info)) {
            return $this->responseError([], trans('api.auth.auth_failed'));
        }

        return $this->responseSuccess(['username' => $data['user_id'], 'password' => $data['user_id']], 'Success');
    }
}
