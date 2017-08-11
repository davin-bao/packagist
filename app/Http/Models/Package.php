<?php
namespace App\Http\Models;

use Log;
use GitLab;
use RuntimeException;

/**
 * Package Class
 * @package App\Http\Models\Package
 *
 * User: davin.bao
 * Date: 16/8/25
 * Time: 下午8:22
 */
class Package
{
    protected $cacheDir = '.';
    protected $packagesFile = '';
    protected $projects = [];
    protected $repositories = [];

    public function __construct(){
        set_time_limit(60);
        $this->cacheDir = storage_path('framework' . DIRECTORY_SEPARATOR . 'cache');
        $this->packagesFile = storage_path('packages.json');

        Log::debug('init');
    }

    /**
     * get local all packages
     * @return array
     */
    public function getMyPackages(){
        $packageObj = json_decode(file_get_contents($this->packagesFile));
        if(isset($packageObj->packages)){
            return $packageObj->packages;
        }

        return [];
    }

    /**
     * get packages json string
     * @return string
     */
    public function get(){

        $file = $this->packagesFile;
        if(!file_exists($file) && !$this->create($file)){
            Log::debug('file is not exist or create failed');
            return '{"packages": []}';
        }

        $mTime = filemtime($file);

        header('Last-Modified: ' . gmdate('r', $mTime));
        header('Cache-Control: max-age=0');

        if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && ($since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) && $since >= $mTime) {
            Log::debug('packages is not modified');
            header('HTTP/1.0 304 Not Modified');
            app()->abort(304);
        }
        return file_get_contents($file);
    }

    /**
     * create packages.json
     *
     * @return bool
     */
    public function create(){
        $packages = array();

        $this->projects = GitLab::api('projects')->accessible(1, 9999);
        $this->repositories = GitLab::api('repositories');

        foreach($this->projects as $project){
            //if($project['path_with_namespace'] != 'domain/images') continue;
            Log::debug('creating ' . $project['path_with_namespace']);
            if($package = $this->loadData($project)){
                $packages[$project['path_with_namespace']] = $package;
            }
        }

        $data = json_encode(array(
            'packages' => array_filter($packages),
        ));
        file_put_contents($this->packagesFile, $data);

        return false;
    }

    private function loadData($project){
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . $project['path_with_namespace'] . '.json';
        $mTime   = strtotime($project['last_activity_at']);
        if (!is_dir(dirname($file))) {
            Log::debug('creating file ' . $file);
            mkdir(dirname($file), 0777, true);
        }
        if (file_exists($file) && filemtime($file) >= $mTime) {
            if (filesize($file) > 0) {
                Log::debug('this file ' . $file . ' is newest');
                return json_decode(file_get_contents($file));
            } else {
                Log::debug('this file ' . $file . ' is empty');
                return false;
            }
        } elseif ($data = $this->fetchRefs($project)) {
            Log::debug('fetchRefs data ' . json_encode($data));
            file_put_contents($file, json_encode($data));
            touch($file, $mTime);
            return $data;
        } else {
            Log::debug('touch file ' . $file);
            $f = fopen($file, 'w');
            fclose($f);
            touch($file, $mTime);
            return false;
        }
    }

    private function fetchRefs($project){
        $dataList = array();
        try {
            $refList = array_merge($this->repositories->branches($project['id']), $this->repositories->tags($project['id']));
            Log::debug('get ref list ' . json_encode($refList) . ' from project ' . $project['path_with_namespace']);
            foreach ($refList as $ref) {
                foreach ($this->fetchRef($project, $ref) as $version => $data) {
                    Log::debug('get version ' . $version . ' from project ' . $project['path_with_namespace']);
                    $dataList[$version] = $data;
                }
            }
        } catch (RuntimeException $e) {
            Log::debug('The repo has no commits, skipping it');
        }
        return $dataList;
    }

    private function fetchRef($project, $ref){
        if (preg_match('/^v?\d+\.\d+(\.\d+)*(\-(dev|patch|alpha|beta|RC)\d*)?$/', $ref['name'])) {
            $version = $ref['name'];
        } else {
            $version = 'dev-' . $ref['name'];
        }
        Log::debug("get version $version from project " . $project['path_with_namespace']);
        if (($data = $this->fetchComposer($project, $ref['commit']['id'])) !== false) {

            $data['version'] = $version;
            $data['source'] = array(
                'url'       => $project['ssh' . '_url_to_repo'],
                'type'      => 'git',
                'reference' => $ref['commit']['id'],
            );

            Log::debug("get composer source from project " . $project['path_with_namespace']);
            return array($version => $data);
        } else {
            return array();
        }
    }

    private function fetchComposer($project, $ref){
        try {
            $c = $this->repositories->getFile($project['id'], 'composer.json', $ref);

            $composerFile = is_array($c) ? $c : json_decode($c, true);
            if(!isset($composerFile['encoding']) || $composerFile['encoding'] != 'base64'){
                Log::debug('fetch composer encoding "' . $composerFile['encoding'] .'" not exist or not "base64" from project ' . $project['path_with_namespace']);
                return false;
            }

            if(!isset($composerFile['content']) || empty($composerFile['content'])){
                Log::debug('fetch composer content "' . $composerFile['content'] .'" not exist or empty from project ' . $project['path_with_namespace']);
                return false;
            }

            $composerContent = base64_decode($composerFile['content']);

            $composer = is_array($composerContent) ? $composerContent : json_decode($composerContent, true);

            if (empty($composer['name']) || strcasecmp($composer['name'], $project['path_with_namespace']) !== 0) {
                Log::debug('fetch composer name "' . $composer['name'] .'" failed from project ' . $project['path_with_namespace']);
                return false; // packages must have a name and must match
            }

            return $composer;
        } catch (RuntimeException $e) {
            Log::debug('fetch composer has exception ' . $e->getMessage() .' from project ' . $project['path_with_namespace']);
            return false;
        }
    }
}