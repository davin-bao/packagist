<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>Composer Repositoires For GitLab</title>

    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/bootstrap-theme.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/site.css') }}" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="{{ asset('js/html5shiv.min.js') }}"></script>
    <script src="{{ asset('js/respond.min.js') }}"></script>
    <![endif]-->
</head>
<body>
<header class="site-header jumbotron">
    <div class="site-nav"></div>
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <p>Composer Repositoires For GitLab</p>
            </div>
        </div>

        <div class="row">
            <nav class="navbar navbar-default">
                <div class="container-fluid">
                    <div class="navbar-header">
                        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
                            <span class="sr-only">Toggle navigation</span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                        </button>
                        <a class="navbar-brand" href="#">GitLab Libraries</a>
                    </div>
                    <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                        <ul class="nav navbar-nav" id="repo-list"></ul>
                        <ul class="nav navbar-nav navbar-right"></ul>
                    </div><!-- /.navbar-collapse -->
                </div><!-- /.container-fluid -->
            </nav>
        </div>
    </div>
</header>
<div class="container">
    <div class="jumbotron package">
        <h3 class="package-amount left" id="">Package List
            <button class="btn btn-warning pull-right" type="button" id="sync-btn">Sync</button>
        </h3>
        <div class="row" id="package-list"></div>
    </div>
    <div class="jumbotron status">

    </div>
</div>

<script type="text/javascript" src="{{ asset('js/jquery.1.11.3.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/bootstrap.min.js') }}"></script>

<script type="text/javascript">

    $.ajaxSetup({
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        }
    });


    $(function(){
        page.init();
    });

    var page = page || {};
    var rootUri = (function(){ return 'http://' + location.host; })();

    page = {
        repoListDom: $('#repo-list'),
        packageListDom: $('#package-list'),
        syncBtnDom: $('#sync-btn'),

        init: function(){
            this.initRepoList(), this.addEvent()
        },
        initRepoList: function(){
            var self = this;
            this.ajaxGet('/repo-list', this.urlParam(), function(data){
                $.each(data.rows, function(key, value) {
                    var style = '';
                    if(value = data.current){
                        style = 'active';
                    }
                    var url = rootUri + '?repo=' + value;
                    self.repoListDom.append('<li class="' + style + '"><a href="' + url + '">' + key + '</a></li>');
                });
                self.refreshPackageList();
            });
        },
        refreshPackageList: function(){
            var self = this;
            this.ajaxGet('/package-list', this.urlParam(), function(data){
                self.packageListDom.html('');

                for(var key in data.rows){
                    var tagList = '';
                    var url = '';
                    var type = 'git';
                    for(var tag in data.rows[key]){
                        if(tag.indexOf('master') >=0){
                            tagList += '<span class="label label-success">' + tag + '</span>';
                        }else {
                            tagList += '<span class="label label-warning">' + tag + '</span>';
                        }

                        type = data.rows[key][tag].source.type;
                        url = data.rows[key][tag].source.url;
                    }
                    self.packageListDom.append(
                            '<div class="panel panel-default pull-left">\
                            <span class="label label-info pull-right">' + type + '</span>\
                            <h4 class="panel-name">' + key + '</h4>\
                            <dl class="dl-horizontal">\
                            <dt>TAGS: </dt><dd class="panel-tags">' + tagList + '</dd>\
                            <dt> URL: </dt><dd class="panel-url">'+ url + '</dd>\
                            </dl>\
                            </div>'
                    );
                }
            });
        },
        sync: function(){
            var self = this;

            this.ajaxPost('/sync', [], function(){
                self.refreshPackageList();
            });
        },
        addEvent: function(){
            var self = this;

            this.syncBtnDom.on('click', function(){
                self.sync();
            });
        },

        ajaxGet: function(url, params, callback){
            var self =this;
            self.syncBtnDom.html('waiting... ');
            self.syncBtnDom.attr('disabled', 'disabled');

            $.ajax({
                type: "GET",
                url: rootUri + url,
                dataType: "json",
                data: params,
                async: (typeof(params.async) == 'undefined') ? true : params.async,
                complete: function(){
                    self.syncBtnDom.html('Sync');
                    self.syncBtnDom.removeAttr('disabled');
                },
                success: function(data, status){
                    if(data.code == 200) {
                        return callback(data);
                    }else{
                        self.alert('CODE: ' + data.code + ':' + data.msg, 'danger');
                        return false;
                    }
                },
                error: function(err){
                    self.syncBtnDom.html('Sync');
                    self.syncBtnDom.removeAttr('disabled');
                    var errData = {'code': 500, 'msg': '服务器内部错误'};
                    if(err.status == 422 && typeof(err.responseJSON) !== "undefined"){
                        errData = $.extend(true, {'code': 422, 'msg': '输入参数有误', 'errData': err.responseJSON });
                    }else if(err.status == 403) {
                        errData = {'code': 403, 'msg': '禁止访问！'};
                    }
                    else if(typeof(err.responseJSON) !== "undefined"){
                        errData = err.responseJSON;
                    }

                    self.alert('CODE: ' + errData.code + ':' + errData.msg, 'danger');
                }
            });
        },

        ajaxPost: function(url, params, callback){
            var self =this;
            self.syncBtnDom.html('waiting... ');
            self.syncBtnDom.attr('disabled', 'disabled');

            $.ajax({
                type: "POST",
                url: rootUri + url,
                dataType: "json",
                data: params,
                async: (typeof(params.async) == 'undefined') ? true : params.async,
                complete: function(){
                    self.syncBtnDom.html('Sync');
                    self.syncBtnDom.removeAttr('disabled');
                },
                success: function(data, status){
                    if(data.code == 200) {
                        return callback(data);
                    }else{
                        self.alert('CODE: ' + data.code + ':' + data.msg, 'danger');
                        return false;
                    }
                },
                error: function(err){
                    self.syncBtnDom.html('Sync');
                    self.syncBtnDom.removeAttr('disabled');
                    var errData = {'code': 500, 'msg': '服务器内部错误'};
                    if(err.status == 422 && typeof(err.responseJSON) !== "undefined"){
                        errData = $.extend(true, {'code': 422, 'msg': '输入参数有误', 'errData': err.responseJSON });
                    }else if(err.status == 403) {
                        errData = {'code': 403, 'msg': '禁止访问！'};
                    }
                    else if(typeof(err.responseJSON) !== "undefined"){
                        errData = err.responseJSON;
                    }

                    self.alert('CODE: ' + errData.code + ':' + errData.msg, 'danger');
                }
            });
        },

        alert: function(message, type){
            $('#message-container').append('\
            <div class="alert alert-'+type+' alert-dismissible" role="alert">\
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>\
             ' + message + '</div>');
        },
        urlParam: function() {
            var param, url = location.search, theRequest = {};
            if (url.indexOf("?") != -1) {
                var str = url.substr(1);
                strs = str.split("&");
                for(var i = 0, len = strs.length; i < len; i ++) {
                    param = strs[i].split("=");
                    theRequest[param[0]]=decodeURIComponent(param[1]);
                }
            }
            return theRequest;
        }
    };

</script>
</body>
</html>