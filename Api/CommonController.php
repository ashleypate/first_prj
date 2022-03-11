<?php

namespace App\Http\Controllers\Api;

use App\Entities\FileEntity;
use App\Helpers\CommonLib;
use App\Repositories\RoleRepository;
use App\Repositories\RoleUserRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommonController extends ApiController
{
    protected $role_repository;
    protected $role_user_repository;

    public function __construct()
    {
        parent::__construct();
        $this->role_repository = new RoleRepository();
        $this->role_user_repository = new RoleUserRepository();
    }

    public function generateCode(Request $request)
    {
        $data = $request->json()->all();
        $length = $data['length'];
        $prefix = $data['prefix'];
        return $this->responseSuccess(CommonLib::generateRandomString($length, $prefix), trans('api.admin.success'));
    }

    public function switchRole(Request $request)
    {
        $data = $request->json()->all();
        $role_id = $data['role_id'];
        $moet_unit_id = $data['moet_unit_id'];
        $school_year_id = $data['school_year_id'];
        $data = CommonLib::initSessionData($role_id, $moet_unit_id, $school_year_id);
        return $this->responseSuccess(['tenant_id' => Auth::user()->tenant_id, 'current_school_year_id' => $data['school_year_id'], 'current_role_id' => $data['role_id'], 'current_moet_id' => $data['moet_unit_id'], 'roles' => @$data['roles'], 'moet_units' => @$data['moet_units'], 'school_years' => @$data['school_years']]);
    }

    public function uploadImage(Request $request)
    {
        $data = $request->json()->all();
        return $data;
        $imageDefault = '/import/course-default.jpg';

        if (!isset($data['upload'])) {
            return $imageDefault;
        }
        $code = 'IMG';
        $filename = $code . '-' . Carbon::now()->timestamp . '-' . randomString(6);
        if (is_string($data['upload'])) {
            $path = (new FileEntity())->saveFileAmazonBase64($data['upload'], $filename, 'learning-games/gen-image');
        } else {
            $path = (new FileEntity())->saveFileAmazon($data['upload'], $filename, 'learning-games/gen-image');
        }

        return $path ? $path : $imageDefault;
    }

}
