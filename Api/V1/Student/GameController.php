<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Controller;
use App\Repositories\GameRepository;
use App\Transformers\V1\Admin\GameTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameController extends ApiController
{
    protected $gameRepository;
    protected $gameTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->gameRepository = new GameRepository();
        $this->gameTransformer = new GameTransformer();
    }

    public function load(){
        $keywordSearch = isset($this->data['keyword_search']) ? $this->data['keyword_search'] : null;
        $gradeId = isset($this->data['grade_id']) ? $this->data['grade_id'] : null;
        $subjectId = isset($this->data['subject_id']) ? $this->data['subject_id'] : null;
        $listGames = $this->gameRepository->getListGamesForStudent(Auth::user(), $subjectId, $gradeId, $keywordSearch);
        $listGames = $this->gameTransformer->transform_collection($listGames->all());
        return $this->responseSuccess($listGames, trans('api.admin.success'));

    }
}
