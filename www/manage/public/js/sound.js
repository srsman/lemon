function show(id, file) {
    var html = '<div class="container-fluid">' +
               '<div class="row text-center" style="height:90px;padding:30px">' +
               '<audio src="http://' + window.location.host + ':8088/sounds/' + file + '" preload="metadata" controls="controls">您的浏览器不支持录音试听</audio>' +
               '</div>' +
               '<div class="row text-center">' +
               '<a href="/sound/pass/' + id + '" class="btn btn-success">通 过</a><a href="/sound/reject/' + id + '" class="btn btn-danger" style="margin-left:18px">不通过</a>' +
               '</div>' +
               '</div>';

    layer.open({
        type: 1,
        title: '语音审核',
        skin: 'layui-layer-demo',
        closeBtn: 1,
        shift: 0,
        shadeClose: true,
        area: ['420px', '220px'],
        content: html
    });
}

function deleteSound(id) {
    layer.confirm('亲，确定要删除？', {
            btn: ['是','否']
        }, function(){
            var url = '/sound/delete/' + id;
            $.get(url, function(){
                layer.msg('删除成功!', {icon: 1, time: 1000});
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            });
    }, function(){
        layer.msg('好险么么哒', {icon: 0, time: 1000});
    });
}