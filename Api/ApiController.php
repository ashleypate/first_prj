<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    protected $data;
    protected $user;

    public function __construct()
    {
        $request = new Request();
        $this->data = $request->json()->all();
    }

    public function respond($status_code, $_data = "", $message = "")
    {
        return response()->json([
            'status' => $status_code,
            'message' => $message,
            'data' => $_data,
        ]);
    }

    public function responseSuccess($data, $message = "")
    {
        return $this->respond(SUCCESS, $data, $message);
    }

    public function responseError($data = "", $message = "")
    {
        return $this->respond(FAILED, $data, $message);
    }

    public function getCurrentMoetUnitId(Request $request)
    {
        return $request->header('moetId', 0);
    }

    public function getCurrentRoleId(Request $request)
    {
        return $request->header('roleId', 0);
    }

    public function getCurrentSchoolYearId(Request $request)
    {
        return $request->header('schoolYearId', 0);
    }
}
