function show(file) {
    var html = '<div class="container-fluid">' +
               '<div class="row text-center" style="height:75px;padding:20px">' +
               '<audio src="/sounds/' + file + '" style="width:333px" preload="metadata" controls="controls">您的浏览器不支持录音试听</audio>' +
               '</div>' +
               '</div>';

    layer.open({
        type: 1,
        title: '语音试听',
        skin: 'layui-layer-demo',
        closeBtn: 1,
        shift: 0,
        shade: 0,
        area: ['420px', '120px'],
        content: html
    });
}

function changeSound(id) {
        var vm = new Vue({
        el: 'body',
        ready() {
            url = '/api/sound_get/' + id;
            this.$http.options.emulateHTTP = true;
            this.$http.get(url).then((response) => {
                var ret = response.json();
                if (ret.status == 'ok') {
                    layer.open({
                        type: 1,
                        title: '语音编辑',
                        skin: 'layui-layer-demo',
                        closeBtn: 1,
                        shift: 0,
                        shadeClose: true,
                        area: ['390px', '240px'],
                        content: '<form id="app" class="form-horizontal" style="padding: 25px" action="/sound/update" method="post">' +
                                 '<input type="hidden" name="id" value="' + id + '">' +
                                 '<div class="form-group">' +
                                 '<label class="col-sm-3 control-label">语音名称</label>' +
                                 '<div class="col-sm-8">' +
                                 '<input type="text" class="form-control" placeholder="请输入语音名称" name="name" value="' + ret.data.name + '" required>' +
                                 '</div>' +
                                 '</div>' +
                                 '<div class="form-group">' +
                                 '<label class="col-sm-3 control-label">备注信息</label>' +
                                 '<div class="col-sm-8">' +
                                 '<input type="text" class="form-control" placeholder="请输入备注信息" name="remark" value="' + ret.data.remark + '" required>' +
                                 '</div>' +
                                 '</div>' +
                                 '<div class="form-group">' +
                                 '<div class="col-sm-offset-3 col-sm-8">' +
                                 '<button type="submit" class="btn btn-success">保存修改</button>' +
                                 '</div>' +
                                 '</div>' +
                                 '</form>'
                    });
                } else {
                    layer.msg(ret.message, {icon: 2, time: 3000});
                }
            }, (response) => {
                layer.msg('抱歉，与服务器通信发生错误', {icon: 2, time: 3000});
            });
        }
    });
}

