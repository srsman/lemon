function loadMore(query) {
    var arg = '';
    var append = false;
    var start = $('#start').val();
    var end = $('#end').val();
    var billsec = $('select[name="billsec"]').val();
    var caller = $('#caller').val();
    var called = $('#called').val();

    if (last > 0) {
        arg += 'id=' + last.toString();
        append = true;
    }

    if (append) {
        arg += '&start=' + start;
        append = true;
    } else {
        arg += 'start=' + start;
    }

    if (append) {
        arg += '&end=' + end;
        append = true;
    } else {
        arg += 'end=' + end;
    }

    if (append) {
        arg += '&billsec=' + billsec;
        append = true;
    } else {
        arg += 'billsec=' + billsec;
    }

    if (append) {
        arg += '&caller=' + caller;
        append = true;
    } else {
        arg += 'caller=' + caller;
    }

    if (append) {
        arg += '&called=' + called;
        append = true;
    } else {
        arg += 'called=' + called;
    }

    reqwest({
        url: '/api/cdr_query?' + arg,
        method: 'get',
        success: function (resp) {
            var obj = JSON.parse(resp);
            last = obj.last;
            for (var i in obj.data) {
                var str = '<tr>' +
                    '<td>' + obj.data[i].id + '</td>' +
                    '<td>' + obj.data[i].caller_id_number + '</td>' +
                    '<td>' + obj.data[i].destination_number + '</td>' +
                    '<td><span class="label label-default">' + getForSeconds(obj.data[i].billsec) + '</span></td>' +
                    '<td>' + obj.data[i].start_stamp.substr(0, 19) + '</td>' +
                    '<td><a id="p' + obj.data[i].id + '" href="javascript:;" style="color:#337ab7" onClick="show(' + "'" + obj.data[i].start_stamp.substr(0, 10).replace(/-/g, "/") + "/" + obj.data[i].bleg_uuid + ".wav'" + ')" data-placement="top" data-trigger="focus"><span class="glyphicon glyphicon-headphones"></span> 试 听</a></td>' +
                    '<td><a href="/record/' + obj.data[i].start_stamp.substr(0, 10).replace(/-/g, "/") + '/' + obj.data[i].bleg_uuid + '.wav" style="color:#555555">本地下载</a></td>' +
                    '</tr>';
                $(str).appendTo("#list");
            }
            if (obj.data.length < 45) {
                $("#loading").css("display","none");
            }
        }
    });
}

function show(file) {
    var html = '<div class="container-fluid">' +
               '<div class="row text-center" style="height:75px;padding:20px">' +
               '<audio src="/record/' + file + '" style="width:333px" preload="metadata" autoplay="autoplay" controls="controls">您的浏览器不支持录音试听</audio>' +
               '</div>' +
               '</div>';

    layer.open({
        type: 1,
        title: '录音试听',
        skin: 'layui-layer-demo',
        closeBtn: 1,
        shift: 0,
        shade: 0,
        area: ['420px', '120px'],
        content: html
    });
}

function getForSeconds(totalSeconds) {  
    if (totalSeconds < 86400) {  
        var dt = new Date("01/01/2000 0:00");  
        dt.setSeconds(totalSeconds);  
        return formatForDate(dt);  
    } else {  
        return null;  
    }  
}  

function formatForDate(dt) {  
    var h = dt.getHours(),  
        m = dt.getMinutes(),  
        s = dt.getSeconds(),  
        r = "";  
    if (h > 0) {  
        r += (h > 9 ? h.toString() : "0" + h.toString()) + ":";  
    } else {
        r += "00:";
    }
    r += (m > 9 ? m.toString() : "0" + m.toString()) + ":"  
    r += (s > 9 ? s.toString() : "0" + s.toString());  
    return r;  
}
