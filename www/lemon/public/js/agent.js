function syncStatus() {
    reqwest({
        url: '/agent/getStatus',
        method: 'get',
        success: function (resp) {
            var templet = '';

            if (resp == '2') {
                templet = '座席状态: <span class="glyphicon glyphicon-ok-sign text-success" aria-hidden="true"></span>';
            } else if (resp == '1') {
                templet = '座席状态: <span class="glyphicon glyphicon-minus-sign text-warning" aria-hidden="true"></span>';
            } else {
                templet = '座席状态: <span class="glyphicon glyphicon-remove-sign text-danger" aria-hidden="true"></span>';
            }

            $('#status').html(templet);
        }
    });
}

function loadMore() {
    reqwest({
        url: '/agent/getOrder/' + last,
        method: 'get',
        success: function (resp) {
            var obj = JSON.parse(resp);
            last = obj.last;
            for (var i in obj.data) {
                var str = '<tr>' +
                    '<tr>' +
                    '<td><span class="glyphicon glyphicon-shopping-cart" aria-hidden="true"></span></td>' +
                    '<td>' + obj.data[i].id + '</td>' +
                    '<td>' + obj.data[i].name + '</td>' +
                    '<td>' + obj.data[i].phone + '</td>' +
                    '<td>' + obj.data[i].product + '</td>' +
                    '<td>' + obj.data[i].number + '</td>';

                if (obj.data[i].status == 1) {
                    str = str + '<td><span class="label label-default">待审核</span></td>';
                } else if (obj.data[i].status == 2) {
                    str = str + '<td><span class="label label-success">已通过</span></td>';
                } else if (obj.data[i].status == 3) {
                    str = str + '<td><span class="label label-danger" data-toggle="tooltip" data-placement="top" title="' + obj.data[i].reason + '">不通过</span></td>';
                } else if (obj.data[i].status == 4) {
                    str = str + '<td><span class="label label-info">已发货</span></td>';
                }  else if (obj.data[i].status == 5) {
                    str = str + '<td><span class="label label-warning" data-toggle="tooltip" data-placement="top" title="' + obj.data[i].reason + '">待&nbsp;&nbsp;&nbsp;定</span></td>';
                }
                
                str = str + '<td>' + obj.data[i].quality + '</td>' +
                    '<td>' + obj.data[i].create_time + '</td>' +
                    '</tr>';

                $(str).appendTo("#list");
            }

            if (obj.data.length < 25) {
                $("#loading").css("display","none");
            }
        }
    });
}

function getCalled() {
    reqwest({
        url: '/agent/getCalled',
        method: 'get',
        success: function (resp) {
            if (resp.length > 1) {
                document.getElementById("phone").value = resp;
            }
        }
    });
}

function show() {
    window.open('/agent/add', 'popwindow','height=398,width=680,top=80,left=180,toolbar=no,menubar=no,scrollbars=no,resizable=no,location=no,status=no');   
}
