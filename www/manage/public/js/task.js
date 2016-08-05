
lock = false;
timer = 0;

function show(id) {
  if (lock) {
    return;
  }

  company_id = id;
  lock = true;

  reqwest({
        url: '/task/getStatus/' + id,
        method: 'get',
        success: function (resp) {
            var obj = JSON.parse(resp);

            var templet = '<div class="container-fluid">' +
                          '<table class="table" style="margin-top:20px">' +
                          '<tbody>' +
                          '<tr><td style="border-color:#ffffff">公司名称: <span id="company">' + obj.company.substr(0, 6) + '</span></td><td style="border-color:#ffffff">当前任务: <span id="task">' + obj.task.substr(0, 5) + '</span></td></tr>' +
                          '<tr><td>当前并发: <span id="concurrent">' + obj.concurrent + '<span></td><td>正听语音: <span id="playback">' + obj.playback + '</span></td></tr>' +
                          '<tr><td>登录座席: <span id="login">' + obj.login + '</span></td><td>正在通话: <span id="talking">' + obj.talking + '</span></td></tr>' +
                          '</tbody>' +
                          '</table>' +
                          '</div>';

            layer.open({
                type: 1,
                title: '状态监控',
                skin: 'layui-layer-demo',
                closeBtn: 1,
                shift: 0,
                shade: 0,
                area: ['420px', '220px'],
                content: templet,
                end: function() {
                  lock = false;
                  clearInterval(timer);
                }
            });

            timer = setInterval(function() {
              reqwest({
                url: '/task/getStatus/' + company_id,
                method: 'get',
                success: function (resp) {
                  var obj = JSON.parse(resp);
                  $('#company').text(obj.company.substr(0, 6));
                  $('#task').text(obj.task.substr(0, 5));
                  $('#concurrent').text(obj.concurrent);
                  $('#playback').text(obj.playback);
                  $('#login').text(obj.login);
                  $('#talking').text(obj.talking);
                }
              });
              
            }, 1000);
        }
    });
}