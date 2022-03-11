<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Repositories\GradeLevelRepository;
use Illuminate\Http\Request;

class GradeLevelController extends ApiController
{
    protected $grade_level_repository;

    public function __construct()
    {
        $this->grade_level_repository = new GradeLevelRepository();
        parent::__construct();
    }

    public function index(Request $request)
    {
        $data = $this->grade_level_repository->getData();
        return $this->responseSuccess($data, trans('api.admin.success'));
    }
}
