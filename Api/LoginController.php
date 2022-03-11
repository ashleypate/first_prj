<?php

namespace App\Http\Controllers\Api;

use App\Helpers\CommonLib;
use App\Repositories\RoleRepository;
use App\Repositories\RoleUserRepository;
use App\Repositories\SchoolYearRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LoginController extends ApiController
{
    protected $school_year_repository;
    protected $role_user_repository;
    protected $role_repository;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware('auth:api', ['except' => ['login']]);
        $this->school_year_repository = new SchoolYearRepository();
        $this->role_user_repository = new RoleUserRepository();
        $this->role_repository = new RoleRepository();
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $data = $request->json()->all();
        $credentials = array(
            'username' => $this->data['username'],
            'password' => $this->data['password'],
        );
        $token = auth()->attempt($credentials);
        if (empty($token)) {
            return $this->responseError([], trans('api.auth.auth_failed'));
        }

        //0: la hoc sinh, 1: la giao vien, null: la admin
        $state = @$data['state'];
        //Init data login
        $data = CommonLib::initDataLogin($state, Auth::user());

        if (empty($data))
            return $this->responseError([], trans('api.auth.role_not_found'));

        return $this->responseSuccess(['token' => $token, 'state' => $state, 'user_info' => Auth::user(), 'tenant_id' => Auth::user()->tenant_id, 'current_school_year_id' => $data['school_year_id'], 'current_role_id' => $data['role_id'], 'current_moet_unit_id' => $data['moet_unit_id'], 'roles' => @$data['roles'], 'moet_units' => @$data['moet_units'], 'school_years' => @$data['school_years']]);
    }

    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout()
    {
        auth()->logout();
        Session::flush();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh()
    {
        return $this->responseSuccess(['token' => auth()->refresh()]);
    }
}
