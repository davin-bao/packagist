<?php

namespace App\Http\Controllers;

use App\Http\Models\Package;
use GitLab;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

/**
 * Class IndexController
 * @package App\Http\Controllers
 */
class IndexController extends BaseController
{
    private $package = null;

    private function getPackage(){
        if(is_null($this->package)){
            $this->package = new Package();
        }
        return $this->package;
    }

    public function getIndex(){
        return view('index');
    }

    public function getPackages(){
        return new JsonResponse(json_decode($this->getPackage()->get()), 200, $headers = ['Content-Type' => 'application/json;charset=utf-8'], 0);
    }

    public function getRepoList(){

        $repoList = config('gitlab.connections');
        $currentRepo = config('gitlab.default');

        return new JsonResponse(array_merge(['rows'=>$repoList, 'current'=> $currentRepo], ['msg'=>'', 'code'=>200]), 200, $headers = [], 0);
    }

    public function getPackageList(){
        return new JsonResponse(array_merge(['rows'=>$this->getPackage()->getMyPackages()], ['msg'=>'', 'code'=>200]), 200, $headers = [], 0);
    }

    public function postSync(){
        $this->getPackage()->create();
        return new JsonResponse(['msg'=>'sync success', 'code'=>200], 200, $headers = [], 0);
    }
}
