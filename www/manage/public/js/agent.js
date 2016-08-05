function show(id) {
    location.href = '/agent/list/' + id;
}

function checkpass(obj) {
    if (obj.value.length < 8) {
        layer.alert('亲，密码必须大于 7 位长度', {title: '提示信息', icon: 2});
        obj.value = '';
    }

    var key = ['12345678', '87654321', '00000000', '11111111', '22222222', '33333333', '44444444', '55555555', '66666666', '77777777', '88888888', '99999999'];
    for (i in key) {
        if (key[i] == obj.value) {
            layer.alert('亲，你的密码太简单，请换个密码', {title: '提示信息', icon: 2});
            obj.value = '';
            break;
        }
    }
}

function deleteAgent(uid) {
    layer.confirm('亲，确定要删除？', {
            btn: ['是','否']
        }, function(){
            var url = '/agent/delete/' + uid;
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

function showAgent(uid) {
  reqwest({
        url: '/agent/getAgent/' + uid,
        method: 'get',
        success: function (resp) {
            var obj = JSON.parse(resp);

            var templet = '<div class="container-fluid">' +
                          '<table class="table" style="margin-top:20px">' +
                          '<tbody>' +
                          '<tr><td style="border-color:#ffffff">座席账号: <span id="company">' + obj.uid + '</span></td><td style="border-color:#ffffff">座席姓名: <span id="task">' + obj.name.substr(0, 5) + '</span></td></tr>' +
                          '<tr><td>座席类型: <span id="concurrent">' + obj.type + '<span></td><td>座席状态: <span id="playback">' + obj.status + '</span></td></tr>' +
                          '<tr><td>外显号码: <span id="login">' + obj.callerid + '</span></td><td>绑定手机: <span id="talking">' + obj.phone + '</span></td></tr>' +
                          '<tr><td>允许Web: <span id="login">' + obj.web + '</span></td><td>允许外呼: <span id="talking">' + obj.calls + '</span></td></tr>' +
                          '</tbody>' +
                          '</table>' +
                          '</div>';

            layer.open({
                type: 1,
                title: '座席详细信息',
                skin: 'layui-layer-demo',
                closeBtn: 1,
                shift: 0,
                shade: 0,
                area: ['450px', '235px'],
                content: templet,
                end: function() {
                  lock = false;
                  clearInterval(timer);
                }
            });
        }
    });
}
